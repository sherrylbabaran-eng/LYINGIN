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

// Next appointment
$next = null;
$stmt = $conn->prepare("SELECT id, clinic_name, service, appointment_date, appointment_time, status FROM patient_appointments WHERE patient_id = ? AND status IN ('pending','confirmed') AND appointment_date >= CURDATE() ORDER BY appointment_date ASC, appointment_time ASC LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $next = $row;
    }
    $stmt->close();
}

// Records count
$records_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM patient_records WHERE patient_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $records_count = (int)$row['cnt'];
    }
    $stmt->close();
}

// Pregnancy tracker
$tracker = null;
$stmt = $conn->prepare("SELECT lmp_date, edd_date FROM patient_pregnancy_tracker WHERE patient_id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $lmp = $row['lmp_date'];
        $edd = $row['edd_date'];
        if ($lmp) {
            $lmp_dt = new DateTime($lmp);
            $now = new DateTime();
            $diff_days = (int)$lmp_dt->diff($now)->format('%a');
            $weeks = (int)floor($diff_days / 7) + 1;
            if ($weeks < 1) { $weeks = 1; }
            if ($weeks > 40) { $weeks = 40; }
            $trimester = ($weeks <= 13) ? '1st Trimester' : (($weeks <= 27) ? '2nd Trimester' : '3rd Trimester');
            $tracker = [
                'lmp_date' => $lmp,
                'edd_date' => $edd,
                'week' => $weeks,
                'trimester' => $trimester
            ];
        }
    }
    $stmt->close();
}

// Recent appointments
$appointments = [];
$stmt = $conn->prepare("SELECT id, clinic_name, service, appointment_date, appointment_time, status FROM patient_appointments WHERE patient_id = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 5");
if ($stmt) {
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();
}

$conn->close();

echo json_encode([
    "status" => "success",
    "data" => [
        "next_appointment" => $next,
        "records_count" => $records_count,
        "pregnancy" => $tracker,
        "recent_appointments" => $appointments
    ]
]);
?>