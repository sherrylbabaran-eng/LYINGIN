<?php
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
setSecurityHeaders();

function jsonResponse(int $statusCode, array $payload): void {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (!isset($_SESSION['user_id']) || (($_SESSION['user_type'] ?? '') !== 'admin')) {
    jsonResponse(401, [
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
}

$servername = 'localhost';
$db_username = 'root';
$db_password = '';
$dbname = 'lyingin_db';

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    logSecurityEvent('DB_CONNECTION_ERROR', ['error' => $conn->connect_error]);
    jsonResponse(500, [
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
}

function fetchCount(mysqli $conn, string $query): int {
    $result = $conn->query($query);
    if (!$result) {
        return 0;
    }

    $row = $result->fetch_row();
    return isset($row[0]) ? (int)$row[0] : 0;
}

$totalClinics = fetchCount($conn, 'SELECT COUNT(*) FROM clinics');
$verifiedClinics = fetchCount($conn, 'SELECT COUNT(*) FROM clinics WHERE email_verified = 1');
$totalPatients = fetchCount($conn, 'SELECT COUNT(*) FROM regpatient');
$verifiedPatients = fetchCount($conn, 'SELECT COUNT(*) FROM regpatient WHERE email_verified = 1');
$monthlyAppointments = fetchCount($conn, "SELECT COUNT(*) FROM patient_appointments WHERE YEAR(appointment_date) = YEAR(CURDATE()) AND MONTH(appointment_date) = MONTH(CURDATE())");
$pendingAppointments = fetchCount($conn, "SELECT COUNT(*) FROM patient_appointments WHERE status = 'pending'");

$monthlyLabels = [];
$monthlyPatientsData = [];
for ($offset = 5; $offset >= 0; $offset--) {
    $date = new DateTime("first day of -{$offset} month");
    $monthlyLabels[] = strtoupper($date->format('M'));

    $stmt = $conn->prepare('SELECT COUNT(*) FROM regpatient WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?');
    if ($stmt) {
        $year = (int)$date->format('Y');
        $month = (int)$date->format('m');
        $stmt->bind_param('ii', $year, $month);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $monthlyPatientsData[] = (int)$count;
        $stmt->close();
    } else {
        $monthlyPatientsData[] = 0;
    }
}

$statusLabels = ['pending', 'confirmed', 'completed', 'cancelled'];
$scheduleBreakdown = [];
foreach ($statusLabels as $statusLabel) {
    $stmt = $conn->prepare('SELECT COUNT(*) FROM patient_appointments WHERE status = ?');
    if ($stmt) {
        $stmt->bind_param('s', $statusLabel);
        $stmt->execute();
        $stmt->bind_result($statusCount);
        $stmt->fetch();
        $scheduleBreakdown[] = [
            'label' => ucfirst($statusLabel),
            'value' => (int)$statusCount
        ];
        $stmt->close();
    } else {
        $scheduleBreakdown[] = [
            'label' => ucfirst($statusLabel),
            'value' => 0
        ];
    }
}

$recentClinics = [];
$clinicQuery = "
    SELECT id, clinic_name, email_verified, created_at
    FROM clinics
    ORDER BY created_at DESC
    LIMIT 7
";
$clinicResult = $conn->query($clinicQuery);
if ($clinicResult) {
    while ($row = $clinicResult->fetch_assoc()) {
        $recentClinics[] = [
            'id' => (int)$row['id'],
            'clinic_name' => $row['clinic_name'] ?: 'Unnamed Clinic',
            'status' => ((int)$row['email_verified'] === 1) ? 'Verified' : 'Pending Verification',
            'last_update' => $row['created_at']
        ];
    }
}

$conn->close();

jsonResponse(200, [
    'status' => 'success',
    'data' => [
        'summary' => [
            'total_clinics' => $totalClinics,
            'verified_clinics' => $verifiedClinics,
            'monthly_appointments' => $monthlyAppointments,
            'pending_appointments' => $pendingAppointments,
            'total_patients' => $totalPatients,
            'verified_patients' => $verifiedPatients
        ],
        'charts' => [
            'monthly_registrations' => [
                'labels' => $monthlyLabels,
                'data' => $monthlyPatientsData
            ],
            'schedule_breakdown' => $scheduleBreakdown
        ],
        'recent_clinics' => $recentClinics
    ]
]);
