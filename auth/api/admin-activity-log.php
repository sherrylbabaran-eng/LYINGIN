<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userType = $_SESSION['user_type'] ?? '';
$userRole = $_SESSION['role'] ?? '';
$isSuperAdmin = $userType === 'superadmin' || $userRole === 'superadmin';
$isAdmin = $userType === 'admin' || $userRole === 'admin';

if (!isset($_SESSION['user_id']) || (!$isSuperAdmin && !$isAdmin)) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

$servername = 'localhost';
$db_username = 'root';
$db_password = '';
$dbname = 'lyingin_db';

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

$limitParam = isset($_GET['limit']) ? intval($_GET['limit']) : 0;

$query = "SELECT actor_role, action, target_patient_id, created_at
          FROM audit_logs
          ORDER BY created_at DESC";

if ($limitParam > 0) {
    $safeLimit = min(500, max(1, $limitParam));
    $query .= " LIMIT " . $safeLimit;
}

$result = $conn->query($query);
if (!$result) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch activity logs'
    ]);
    $conn->close();
    exit;
}

$rows = $result->fetch_all(MYSQLI_ASSOC);

$logs = array_map(function ($row) {
    return [
        'actor_role' => htmlspecialchars((string)($row['actor_role'] ?? 'unknown')),
        'action' => htmlspecialchars((string)($row['action'] ?? '')),
        'target_patient_id' => $row['target_patient_id'] !== null ? (int)$row['target_patient_id'] : null,
        'created_at' => htmlspecialchars((string)($row['created_at'] ?? ''))
    ];
}, $rows);

echo json_encode([
    'status' => 'success',
    'data' => [
        'logs' => $logs
    ]
]);

$conn->close();
?>
