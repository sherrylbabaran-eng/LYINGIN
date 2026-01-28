<?php
/**
 * OTP Verification API
 * Validates the OTP code entered by user
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

session_start();

$email = trim($_POST['email'] ?? '');
$otp_code = trim($_POST['otp'] ?? '');

// Validate input
if (empty($email) || empty($otp_code)) {
    echo json_encode(["status" => "error", "message" => "Email and OTP required"]);
    exit;
}

// Check if OTP exists in session
$otp_key = 'otp_' . md5($email);

if (!isset($_SESSION[$otp_key])) {
    echo json_encode(["status" => "error", "message" => "OTP not found. Please request a new one."]);
    exit;
}

$stored_data = $_SESSION[$otp_key];

// Check if OTP is expired
if (strtotime($stored_data['expires']) < time()) {
    unset($_SESSION[$otp_key]);
    echo json_encode(["status" => "error", "message" => "OTP has expired. Request a new one."]);
    exit;
}

// Check if OTP matches
if ($stored_data['otp'] !== $otp_code) {
    echo json_encode(["status" => "error", "message" => "Invalid OTP code"]);
    exit;
}

// OTP verified - mark email as verified in session
$_SESSION['verified_email'] = $email;
unset($_SESSION[$otp_key]); // Clear the OTP after successful verification

echo json_encode([
    "status" => "success",
    "message" => "Email verified successfully!",
    "email" => $email
]);
?>
