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

function computeTrackerData($lmp_date, $edd_date) {
    if (!$lmp_date) return null;
    $lmp_dt = new DateTime($lmp_date);
    $now = new DateTime();
    $diff_days = (int)$lmp_dt->diff($now)->format('%a');
    $weeks = (int)floor($diff_days / 7) + 1;
    if ($weeks < 1) { $weeks = 1; }
    if ($weeks > 40) { $weeks = 40; }
    $trimester = ($weeks <= 13) ? '1st Trimester' : (($weeks <= 27) ? '2nd Trimester' : '3rd Trimester');
    return [
        'lmp_date' => $lmp_date,
        'edd_date' => $edd_date,
        'week' => $weeks,
        'trimester' => $trimester
    ];
}

function fetchWeeklyTips($conn, $week) {
    if (!$week) return null;
    $stmt = $conn->prepare("SELECT baby_development, mother_condition, reminders FROM pregnancy_weekly_tips WHERE week_number = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param("i", $week);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function fetchCheckups($conn, $patient_id) {
    $items = [];
    $stmt = $conn->prepare("SELECT checkup_date, gestation_week, weight_kg, blood_pressure, remarks FROM prenatal_checkups WHERE patient_id = ? ORDER BY checkup_date DESC LIMIT 10");
    if (!$stmt) return $items;
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
    return $items;
}

if ($method === 'GET') {
    $stmt = $conn->prepare("SELECT lmp_date, edd_date FROM patient_pregnancy_tracker WHERE patient_id = ? LIMIT 1");
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

    if (!$row) {
        $checkups = fetchCheckups($conn, $patient_id);
        $conn->close();
        echo json_encode(["status" => "success", "data" => null, "tips" => null, "checkups" => $checkups]);
        exit;
    }

    $data = computeTrackerData($row['lmp_date'], $row['edd_date']);
    $tips = fetchWeeklyTips($conn, $data ? $data['week'] : null);
    $checkups = fetchCheckups($conn, $patient_id);
    $conn->close();
    echo json_encode(["status" => "success", "data" => $data, "tips" => $tips, "checkups" => $checkups]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

if ($method === 'POST' || $method === 'PUT') {
    $lmp_date = trim($data['lmp_date'] ?? '');
    if (!$lmp_date) {
        echo json_encode(["status" => "error", "message" => "Missing last menstrual period date"]);
        $conn->close();
        exit;
    }

    $lmp_dt = new DateTime($lmp_date);
    $edd_dt = clone $lmp_dt;
    $edd_dt->modify('+280 days');
    $edd_date = $edd_dt->format('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO patient_pregnancy_tracker (patient_id, lmp_date, edd_date) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE lmp_date = VALUES(lmp_date), edd_date = VALUES(edd_date)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database error"]);
        $conn->close();
        exit;
    }
    $stmt->bind_param("iss", $patient_id, $lmp_date, $edd_date);

    if ($stmt->execute()) {
        $payload = computeTrackerData($lmp_date, $edd_date);
        $tips = fetchWeeklyTips($conn, $payload ? $payload['week'] : null);
        $checkups = fetchCheckups($conn, $patient_id);
        echo json_encode(["status" => "success", "data" => $payload, "tips" => $tips, "checkups" => $checkups]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save tracker data"]);
    }

    $stmt->close();
    $conn->close();
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
$conn->close();
?>