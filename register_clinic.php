<?php
session_start();
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
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
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

/* ================= REQUIRED ================= */

$required = [
    'clinic_name','license_number','address','email','contact_number',
    'admin_name','username','password','confirm_password',
    'id_type','id_number','latitude','longitude'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["status"=>"error","message"=>"All fields are required"]);
        exit;
    }
}

/* ================= SANITIZE ================= */

$clinic_name    = trim($_POST['clinic_name']);
$license_number = strtoupper(trim($_POST['license_number']));
$address        = trim($_POST['address']);
$email          = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$contact        = trim($_POST['contact_number']);
$admin_name     = trim($_POST['admin_name']);
$username       = trim($_POST['username']);
$password       = $_POST['password'];
$confirm        = $_POST['confirm_password'];
$latitude       = floatval($_POST['latitude']);
$longitude      = floatval($_POST['longitude']);
$id_type        = $_POST['id_type'];
$id_number      = trim($_POST['id_number']);

if (!$email) {
    echo json_encode(["status"=>"error","message"=>"Invalid email"]);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(["status"=>"error","message"=>"Passwords do not match"]);
    exit;
}

/* ================= DUPLICATE CHECK ================= */

$stmt = $pdo->prepare("SELECT id FROM clinics WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    echo json_encode(["status"=>"error","message"=>"Account already exists"]);
    exit;
}

/* ================= FILE UPLOAD ================= */

if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode(["status"=>"error","message"=>"ID upload failed"]);
    exit;
}

$uploadDir = __DIR__ . "/private_uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION);
$fileName = bin2hex(random_bytes(16)) . "." . $ext;

move_uploaded_file($_FILES['id_file']['tmp_name'], $uploadDir . $fileName);

/* ================= PASSWORD HASH ================= */

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

/* ================= OTP ================= */

$otp = rand(100000, 999999);
$otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

/* ================= INSERT ================= */

$stmt = $pdo->prepare("
INSERT INTO clinics (
    clinic_name, license_number, address, email, contact_number,
    admin_name, username, password,
    id_type, id_number, id_file,
    latitude, longitude,
    email_verification_token, email_verification_expires, email_verified
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)
");

$stmt->execute([
    $clinic_name,
    $license_number,
    $address,
    $email,
    $contact,
    $admin_name,
    $username,
    $hashedPassword,
    $id_type,
    $id_number,
    $fileName,
    $latitude,
    $longitude,
    $otp,
    $otpExpires
]);

/* ================= SEND OTP ================= */

$sendError = null;
$mailSent = sendClinicOtpEmail($email, $otp, $sendError);

if (!$mailSent) {
    $detail = $sendError ? " ({$sendError})" : '';
    error_log("register_clinic.php: OTP mail failed for {$email}{$detail}. SMTP may be unavailable.");
    logOtpFallback($email, $otp, $otpExpires, 'register_mail_failed');
}

echo json_encode([
    "status"=>"success",
    "message"=>$mailSent
        ? "Registration submitted. OTP sent to your email. Verify OTP to activate your account."
        : "Registration submitted. OTP generated but email delivery is unavailable on this server. Use resend after SMTP setup or check local fallback log.",
    "requires_otp"=>true,
    "mail_sent"=>$mailSent,
    "mail_error"=>$mailSent ? null : ($sendError ?: 'unknown_mail_error'),
    "pending_verification"=>true
]);
exit;