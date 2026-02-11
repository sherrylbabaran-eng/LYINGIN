<?php
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Validate CSRF token
validateCSRF();

$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if (!$token) {
    echo json_encode(["status" => "error", "message" => "Missing reset token"]);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

$pwCheck = validatePassword($password);
if (!$pwCheck['valid']) {
    echo json_encode(["status" => "error", "message" => implode(', ', $pwCheck['errors'])]);
    exit;
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

$token_hash = hash('sha256', $token);

$stmt = $conn->prepare("SELECT email FROM password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$stmt->bind_param("s", $token_hash);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Invalid or expired reset link"]);
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
$email = $row['email'];

$hashed = password_hash($password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE RegPatient SET password = ? WHERE email = ?");
if (!$update) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$update->bind_param("ss", $hashed, $email);

if (!$update->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to update password"]);
    $update->close();
    $conn->close();
    exit;
}
$update->close();

$cleanup = $conn->prepare("DELETE FROM password_resets WHERE token_hash = ?");
if ($cleanup) {
    $cleanup->bind_param("s", $token_hash);
    $cleanup->execute();
    $cleanup->close();
}

$conn->close();

echo json_encode([
    "status" => "success",
    "message" => "Password updated successfully. You can now log in."
]);
?>