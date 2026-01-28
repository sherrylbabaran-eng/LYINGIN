<?php
/**
 * Verify Email Token
 * Confirms email verification when user clicks the link
 */

require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

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

// Get token and type
$token = sanitizeInput($_POST['token'] ?? '');
$user_type = sanitizeInput($_POST['type'] ?? 'patient');

if (empty($token) || !in_array($user_type, ['patient', 'clinic'])) {
    echo json_encode(["status" => "error", "message" => "Invalid token or user type"]);
    exit;
}

// Hash the token to compare with database
$token_hash = hash('sha256', $token);
$table = ($user_type === 'patient') ? 'RegPatient' : 'clinics';

// Check if token is valid and not expired
$stmt = $conn->prepare("SELECT id, email FROM $table WHERE email_verification_token = ? AND email_verification_expires > NOW()");
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    logSecurityEvent('INVALID_VERIFICATION_TOKEN', ['token' => substr($token, 0, 10), 'type' => $user_type]);
    echo json_encode(["status" => "error", "message" => "Invalid or expired verification token"]);
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Update user as verified
$stmt = $conn->prepare("UPDATE $table SET email_verified = 1, email_verification_token = NULL, email_verification_expires = NULL WHERE id = ?");
$stmt->bind_param("i", $user['id']);

if ($stmt->execute()) {
    logSecurityEvent('EMAIL_VERIFIED', ['email' => $user['email'], 'type' => $user_type]);
    echo json_encode([
        "status" => "success",
        "message" => "Email verified successfully! You can now login.",
        "redirect" => "/auth/login.html"
    ]);
} else {
    logSecurityEvent('VERIFICATION_UPDATE_ERROR', ['email' => $user['email'], 'error' => $stmt->error]);
    echo json_encode(["status" => "error", "message" => "Failed to verify email"]);
}

$stmt->close();
$conn->close();
?>
