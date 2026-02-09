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

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT profile_image FROM RegPatient WHERE id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    $conn->close();

    echo json_encode([
        "status" => "success",
        "profile_image" => $row['profile_image'] ?? null
    ]);
    exit;
}

if ($method === 'POST') {
    validateCSRF();

    if (!isset($_FILES['profile_image'])) {
        echo json_encode(["status" => "error", "message" => "No file uploaded"]);
        $conn->close();
        exit;
    }

    $allowed = ['image/jpeg', 'image/png'];
    $validation = validateFileUpload($_FILES['profile_image'], $allowed, 5 * 1024 * 1024);
    if (!$validation['valid']) {
        echo json_encode(["status" => "error", "message" => $validation['error']]);
        $conn->close();
        exit;
    }

    $destDir = __DIR__ . '/../../uploads/patients/';
    $filename = saveSecureFile($_FILES['profile_image'], $destDir);
    if (!$filename) {
        echo json_encode(["status" => "error", "message" => "Failed to save file"]);
        $conn->close();
        exit;
    }

    $relativePath = 'uploads/patients/' . $filename;
    $stmt = $conn->prepare("UPDATE RegPatient SET profile_image = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("si", $relativePath, $patient_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "profile_image" => $relativePath]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update profile image"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
$conn->close();
?>