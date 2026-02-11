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

$stmt = $conn->prepare("SELECT id, record_type, title, record_date, record_time, status, remarks, file_path FROM patient_records WHERE patient_id = ? ORDER BY record_date DESC, record_time DESC");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(["status" => "success", "items" => $items]);
?>