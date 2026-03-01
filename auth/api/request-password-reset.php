<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mail-helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Validate CSRF token
validateCSRF();

// Load .env configuration
$envFile = null;
$candidates = [
    realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',
    realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',
    __DIR__ . DIRECTORY_SEPARATOR . '.env'
];
foreach ($candidates as $cand) {
    if ($cand && file_exists($cand)) { $envFile = $cand; break; }
}
if ($envFile) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        $val = trim($val, "'\"");
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}

function sendResetEmailWithHelper($to_email, $reset_link, &$sendError = null) {
    $sendError = null;
    $subject = 'Password Reset - LYINGIN';
    $html_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 30px; border-radius: 8px; }
            .button { display: inline-block; background-color: #0d6efd; color: white; padding: 12px 18px; text-decoration: none; border-radius: 4px; }
            .muted { color: #777; font-size: 12px; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Password Reset</h2>
            <p>We received a request to reset your password.</p>
            <p><a class='button' href='" . htmlspecialchars($reset_link) . "'>Reset Password</a></p>
            <p class='muted'>This link expires in 30 minutes. If you did not request this, you can ignore this email.</p>
        </div>
    </body>
    </html>
    ";

    if (sendMailWithPHPMailer($to_email, $subject, $html_body, $sendError)) {
        return true;
    }

    $mailSent = @mail(
        $to_email,
        $subject,
        "Reset your password using this link: {$reset_link}\nThis link expires in 30 minutes.",
        'From: no-reply@localhost'
    );

    if (!$mailSent && !$sendError) {
        $sendError = 'mail_function_failed';
    }

    return $mailSent;
}

// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$email = sanitizeEmail($_POST['email'] ?? '');

if (!validateEmail($email)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    $conn->close();
    exit;
}

// Check if account exists (supports patient and clinic)
$accountType = null;
$accountQueries = [
    'clinic' => "SELECT id FROM clinics WHERE email = ? LIMIT 1",
    'patient' => "SELECT id FROM RegPatient WHERE email = ? LIMIT 1"
];

foreach ($accountQueries as $type => $query) {
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        $accountType = $type;
        break;
    }
}

if (!$accountType) {
    // Do not leak account existence
    echo json_encode([
        "status" => "success",
        "message" => "If your account exists, a reset link has been sent to your email."
    ]);
    $conn->close();
    exit;
}

// Create reset token
$token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
// Use database time for expiry to avoid PHP/MySQL timezone mismatch
$expires_at = null;

// Store account identity in password_resets.email as "type|email"
$identity = $accountType . '|' . $email;

// Clear existing tokens for this email (legacy and typed identity)
$cleanup = $conn->prepare("DELETE FROM password_resets WHERE email = ? OR email = ? OR email = ?");
if ($cleanup) {
    $legacyClinicIdentity = 'clinic|' . $email;
    $legacyPatientIdentity = 'patient|' . $email;
    $cleanup->bind_param("sss", $email, $legacyClinicIdentity, $legacyPatientIdentity);
    $cleanup->execute();
    $cleanup->close();
}

$stmt = $conn->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$stmt->bind_param("ss", $identity, $token_hash);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to create reset token"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Build reset link
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$reset_link = $scheme . '://' . $host . $basePath . '/reset-password.html?token=' . urlencode($token);

$sendError = null;
if (sendResetEmailWithHelper($email, $reset_link, $sendError)) {
    echo json_encode([
        "status" => "success",
        "message" => "If your account exists, a reset link has been sent to your email."
    ]);
} else {
    $detail = $sendError ? " (" . $sendError . ")" : "";
    error_log('request-password-reset.php: reset email send failed for ' . $email . $detail);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to send reset email. Please try again later." . $detail
    ]);
}

$conn->close();
?>