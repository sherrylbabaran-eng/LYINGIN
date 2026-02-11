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
    $date = $_GET['date'] ?? null;
    $status = $_GET['status'] ?? null;

    $query = "SELECT id, clinic_name, service, appointment_date, appointment_time, status, notes FROM patient_appointments WHERE patient_id = ?";
    $params = [$patient_id];
    $types = "i";

    if ($date) {
        $query .= " AND appointment_date = ?";
        $params[] = $date;
        $types .= "s";
    }
    if ($status) {
        $query .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $query .= " ORDER BY appointment_date DESC, appointment_time DESC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $items = [];
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    $conn->close();

    echo json_encode(["status" => "success", "items" => $items]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

if ($method === 'POST') {
    $clinic_name = trim($data['clinic_name'] ?? '');
    $service = trim($data['service'] ?? '');
    $date = trim($data['appointment_date'] ?? '');
    $time = trim($data['appointment_time'] ?? '');
    $status = trim($data['status'] ?? 'pending');
    $notes = trim($data['notes'] ?? '');

    $allowed_status = ['pending','confirmed','cancelled','completed'];
    if (!in_array($status, $allowed_status)) $status = 'pending';

    if (!$clinic_name || !$service || !$date || !$time) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO patient_appointments (patient_id, clinic_name, service, appointment_date, appointment_time, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("issssss", $patient_id, $clinic_name, $service, $date, $time, $status, $notes);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "id" => $stmt->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create appointment"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'PUT') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Missing appointment id"]);
        $conn->close();
        exit;
    }

    $clinic_name = trim($data['clinic_name'] ?? '');
    $service = trim($data['service'] ?? '');
    $date = trim($data['appointment_date'] ?? '');
    $time = trim($data['appointment_time'] ?? '');
    $status = trim($data['status'] ?? 'pending');
    $notes = trim($data['notes'] ?? '');

    if (!$clinic_name || !$service || !$date || !$time) {
        $lookup = $conn->prepare("SELECT clinic_name, service, appointment_date, appointment_time, notes FROM patient_appointments WHERE id = ? AND patient_id = ? LIMIT 1");
        if ($lookup) {
            $lookup->bind_param("ii", $id, $patient_id);
            $lookup->execute();
            $res = $lookup->get_result();
            if ($row = $res->fetch_assoc()) {
                if (!$clinic_name) $clinic_name = $row['clinic_name'];
                if (!$service) $service = $row['service'];
                if (!$date) $date = $row['appointment_date'];
                if (!$time) $time = $row['appointment_time'];
                if (!$notes) $notes = $row['notes'] ?? '';
            }
            $lookup->close();
        }
    }

    $allowed_status = ['pending','confirmed','cancelled','completed'];
    if (!in_array($status, $allowed_status)) $status = 'pending';

    $stmt = $conn->prepare("UPDATE patient_appointments SET clinic_name = ?, service = ?, appointment_date = ?, appointment_time = ?, status = ?, notes = ?, updated_at = NOW() WHERE id = ? AND patient_id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("ssssssii", $clinic_name, $service, $date, $time, $status, $notes, $id, $patient_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update appointment"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Missing appointment id"]);
        $conn->close();
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM patient_appointments WHERE id = ? AND patient_id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("ii", $id, $patient_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete appointment"]);
    }
    $stmt->close();
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
$conn->close();
?>