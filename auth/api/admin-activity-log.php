<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isSuperAdmin = (($_SESSION['user_type'] ?? '') === 'superadmin') || (($_SESSION['role'] ?? '') === 'superadmin');
if (!isset($_SESSION['user_id']) || !$isSuperAdmin) {
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

$limit = max(1, min(100, intval($_GET['limit'] ?? 20)));

$query = "SELECT actor_role, action, target_patient_id, created_at
          FROM audit_logs
          ORDER BY created_at DESC
          LIMIT ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to prepare query'
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param('i', $limit);
$stmt->execute();
$result = $stmt->get_result();
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

$stmt->close();
$conn->close();
?>
