<?php
header('Content-Type: application/json');
ini_set('display_errors', '0');
error_reporting(E_ALL);

function loadEnvConfig() {
    $candidates = [
        __DIR__ . '/.env',
        __DIR__ . '/auth/.env',
        __DIR__ . '/auth/api/.env'
    ];

    foreach ($candidates as $envFile) {
        if (!file_exists($envFile)) {
            continue;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "'\"");

            if ($key !== '') {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }

        return;
    }
}

function sendClinicOtpEmail($email, $otp, &$sendError = null) {
    $sendError = null;
    $subject = 'Your Clinic Registration OTP';
    $message = "
        <div style='font-family: Arial, sans-serif; line-height: 1.5;'>
            <h2>Clinic Registration OTP</h2>
            <p>Your OTP is: <strong>{$otp}</strong></p>
            <p>This OTP expires in 10 minutes.</p>
        </div>
    ";

    $mailHelper = __DIR__ . '/auth/api/mail-helper.php';
    if (file_exists($mailHelper)) {
        require_once $mailHelper;
        if (function_exists('sendMailWithPHPMailer')) {
            if (sendMailWithPHPMailer($email, $subject, $message, $sendError)) {
                return true;
            }
        }
    }

    $mailSent = @mail(
        $email,
        $subject,
        "Your OTP is: {$otp}\nIt expires in 10 minutes.",
        'From: no-reply@localhost'
    );

    if (!$mailSent && !$sendError) {
        $sendError = 'mail_function_failed';
    }

    return $mailSent;
}

loadEnvConfig();

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$email) {
    echo json_encode(["status" => "error", "message" => "Valid email is required"]);
    exit;
}

function logOtpFallback($email, $otp, $otpExpires, $reason = 'mail_failed') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $entry = sprintf(
        "[%s] reason=%s email=%s otp=%s expires=%s%s",
        date('Y-m-d H:i:s'),
        $reason,
        $email,
        $otp,
        $otpExpires,
        PHP_EOL
    );

    @file_put_contents($logDir . '/clinic-otp-fallback.log', $entry, FILE_APPEND);
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=lyingin_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$stmt = $pdo->prepare("SELECT id, email_verified FROM clinics WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$clinic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$clinic) {
    echo json_encode(["status" => "error", "message" => "Clinic account not found"]);
    exit;
}

if ((int)$clinic['email_verified'] === 1) {
    echo json_encode(["status" => "error", "message" => "Account already verified"]);
    exit;
}

$otp = (string) random_int(100000, 999999);
$otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$update = $pdo->prepare("UPDATE clinics SET email_verification_token = ?, email_verification_expires = ? WHERE id = ?");
$update->execute([$otp, $otpExpires, $clinic['id']]);

$sendError = null;
$mailSent = sendClinicOtpEmail($email, $otp, $sendError);

if (!$mailSent) {
    $detail = $sendError ? " ({$sendError})" : '';
    error_log("resend-clinic-otp.php: OTP resend mail failed for {$email}{$detail}. SMTP may be unavailable.");
    logOtpFallback($email, $otp, $otpExpires, 'resend_mail_failed');
}

echo json_encode([
    "status" => "success",
    "message" => $mailSent
        ? "OTP resent successfully."
        : "OTP regenerated, but email delivery is unavailable on this server. Check local fallback log.",
    "mail_sent" => $mailSent,
    "mail_error" => $mailSent ? null : ($sendError ?: 'unknown_mail_error'),
    "requires_otp" => true
]);
exit;
