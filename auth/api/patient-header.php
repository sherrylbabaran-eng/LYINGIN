<?php
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'patient') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$patient_id = (int)$_SESSION['user_id'];

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$user = [
    'id' => $patient_id,
    'name' => $_SESSION['user_name'] ?? 'Patient',
    'email' => $_SESSION['user_email'] ?? '',
    'profile_image' => null
];

$stmt = $conn->prepare("SELECT profile_image FROM RegPatient WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $user['profile_image'] = $row['profile_image'] ?: null;
    }
    $stmt->close();
}

// Notifications
$notifications = [];
$unread_notifications = 0;
$stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $notifications[] = $row;
        if ((int)$row['is_read'] === 0) {
            $unread_notifications++;
        }
    }
    $stmt->close();
}

// Messages
$messages = [];
$unread_messages = 0;
$stmt = $conn->prepare("SELECT id, sender_name, subject, body, is_read, created_at FROM patient_messages WHERE patient_id = ? ORDER BY created_at DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender_name' => $row['sender_name'],
            'subject' => $row['subject'],
            'body' => $row['body'],
            'created_at' => $row['created_at']
        ];
        if ((int)$row['is_read'] === 0) {
            $unread_messages++;
        }
    }
    $stmt->close();
}

$conn->close();

echo json_encode([
    'status' => 'success',
    'user' => $user,
    'notifications' => $notifications,
    'messages' => $messages,
    'unread_notifications' => $unread_notifications,
    'unread_messages' => $unread_messages
]);
?>