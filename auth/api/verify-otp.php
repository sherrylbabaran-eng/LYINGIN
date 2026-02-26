<?php
/**
 * OTP Verification API
 * Validates the OTP code entered by user
 */

header('Content-Type: application/json');

function jsonResponse($status, $message, array $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Invalid request method');
}

session_start();

$email = trim($_POST['email'] ?? '');
$otp_code = trim($_POST['otp'] ?? '');

// Validate input
if (empty($email) || empty($otp_code)) {
    jsonResponse('error', 'Email and OTP required');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse('error', 'Invalid email address');
}

if (!preg_match('/^\d{6}$/', $otp_code)) {
    jsonResponse('error', 'Invalid OTP code');
}

// Check if OTP exists in session
$otp_key = 'otp_' . md5($email);

if (!isset($_SESSION[$otp_key])) {
    jsonResponse('error', 'OTP not found. Please request a new one.');
}

$stored_data = $_SESSION[$otp_key];

// Check if OTP is expired
if (strtotime($stored_data['expires']) < time()) {
    unset($_SESSION[$otp_key]);
    jsonResponse('error', 'OTP has expired. Request a new one.');
}

// Check if OTP matches
if (!hash_equals((string)$stored_data['otp'], (string)$otp_code)) {
    jsonResponse('error', 'Invalid OTP code');
}

// OTP verified - mark email as verified in session
$_SESSION['verified_email'] = $email;
unset($_SESSION[$otp_key]); // Clear the OTP after successful verification

jsonResponse('success', 'Email verified successfully!', ['email' => $email]);
?>
