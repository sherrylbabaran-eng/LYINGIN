<?php
/**
 * Email Verification System
 * Sends verification emails to newly registered users
 */

require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

// Load .env configuration
$envFile = null;
$candidates = [
    realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',      // auth/.env
    realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',   // project root /.env
    __DIR__ . DIRECTORY_SEPARATOR . '.env'                         // auth/api/.env
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

require_once __DIR__ . '/mail-helper.php';

// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Get email and user type
$email = sanitizeEmail($_POST['email'] ?? '');
$user_type = sanitizeInput($_POST['user_type'] ?? 'patient');

if (!validateEmail($email)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}

if (!in_array($user_type, ['patient', 'clinic'])) {
    echo json_encode(["status" => "error", "message" => "Invalid user type"]);
    exit;
}

// Generate verification token
$verification_token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $verification_token);
$expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Determine table
$table = ($user_type === 'patient') ? 'RegPatient' : 'clinics';

// Check if email exists
$stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Email not found"]);
    exit;
}

$stmt->close();

// Update user with verification token
$stmt = $conn->prepare("UPDATE $table SET email_verification_token = ?, email_verification_expires = ? WHERE email = ?");
$stmt->bind_param("sss", $token_hash, $expires_at, $email);

if (!$stmt->execute()) {
    logSecurityEvent('VERIFICATION_TOKEN_ERROR', ['email' => $email, 'error' => $stmt->error]);
    echo json_encode(["status" => "error", "message" => "Failed to generate verification token"]);
    $stmt->close();
    exit;
}

$stmt->close();

// Send verification email
$verification_link = "http://yoursite.com/auth/verify-email.html?token=" . $verification_token . "&type=" . $user_type;

$subject = "Email Verification - LYINGIN";
$message = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; }
        .header { background-color: #2c3e50; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .content { padding: 20px; }
        .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .footer { background-color: #ecf0f1; padding: 10px; text-align: center; font-size: 12px; color: #7f8c8d; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>Email Verification</h2>
        </div>
        <div class='content'>
            <p>Thank you for registering with LYINGIN!</p>
            <p>Please verify your email address by clicking the button below:</p>
            <a href='" . htmlspecialchars($verification_link) . "' class='button'>Verify Email</a>
            <p style='margin-top: 20px; color: #7f8c8d; font-size: 14px;'>
                If you didn't register, please ignore this email.
            </p>
            <p style='color: #7f8c8d; font-size: 12px;'>
                This link expires in 24 hours.
            </p>
        </div>
        <div class='footer'>
            <p>LYINGIN - Healthcare Management System</p>
        </div>
    </div>
</body>
</html>
";

// Send email using PHPMailer
$phpmailer_error = null;
if (sendMailWithPHPMailer($email, $subject, $message, $phpmailer_error)) {
    logSecurityEvent('VERIFICATION_EMAIL_SENT', ['email' => $email, 'type' => $user_type]);
    echo json_encode([
        "status" => "success",
        "message" => "Verification email sent to $email. Please check your email to verify your account.",
        "redirect" => "/auth/verify-pending.html"
    ]);
} else {
    logSecurityEvent('EMAIL_SEND_FAILED', ['email' => $email]);
    $detail = $phpmailer_error ? (': ' . $phpmailer_error) : '';
    echo json_encode(["status" => "error", "message" => "Failed to send verification email" . $detail . "."]);
}

$conn->close();
?>
