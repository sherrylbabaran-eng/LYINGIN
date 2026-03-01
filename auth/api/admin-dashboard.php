<?php
<<<<<<< HEAD
=======
/**
 * Admin Dashboard Statistics API
 * 
 * Retrieves dashboard metrics for the super admin dashboard including:
 * - Patient count and growth
 * - Clinic count and growth
 * - Appointment metrics
 * - System-wide statistics
 */

header('Content-Type: application/json');
>>>>>>> 7d6577fd5089edccf21ca61fd2313c443b0f6cb7
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

<<<<<<< HEAD
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
=======
// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    logSecurityEvent('DB_CONNECTION_ERROR', ['error' => $conn->connect_error]);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Validate superadmin session
$isSuperAdmin = (($_SESSION['user_type'] ?? '') === 'superadmin') || (($_SESSION['role'] ?? '') === 'superadmin');
if (!isset($_SESSION['user_id']) || !$isSuperAdmin) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

try {
    // Get total patient count
    $patientResult = $conn->query("SELECT COUNT(*) as total FROM patients");
    $patientCount = $patientResult->fetch_assoc()['total'] ?? 0;

    // Get total clinic count
    $clinicResult = $conn->query("SELECT COUNT(*) as total FROM clinics");
    $clinicCount = $clinicResult->fetch_assoc()['total'] ?? 0;

    // Get verified clinics
    $verifiedResult = $conn->query("SELECT COUNT(*) as total FROM clinics WHERE email_verified = 1");
    $verifiedCount = $verifiedResult->fetch_assoc()['total'] ?? 0;

    // Get verified patients
    $verifiedPatientResult = $conn->query("SELECT COUNT(*) as total FROM patients WHERE verification_status = 'verified'");
    $verifiedPatientCount = $verifiedPatientResult->fetch_assoc()['total'] ?? 0;

    // Get pending verification count
    $pendingResult = $conn->query("SELECT COUNT(*) as total FROM patients WHERE verification_status = 'pending'");
    $pendingCount = $pendingResult->fetch_assoc()['total'] ?? 0;

    // Get total appointments
    $appointmentResult = $conn->query("SELECT COUNT(*) as total FROM patient_appointments");
    $appointmentCount = $appointmentResult->fetch_assoc()['total'] ?? 0;

    // Calculate month-over-month growth for patients (simplified)
    $currentMonthResult = $conn->query(
        "SELECT COUNT(*) as total FROM patients WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
    );
    $currentMonthPatients = $currentMonthResult->fetch_assoc()['total'] ?? 0;

    // Calculate growth percentage (compare to previous month)
    $lastMonthResult = $conn->query(
        "SELECT COUNT(*) as total FROM patients WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
    );
    $lastMonthPatients = $lastMonthResult->fetch_assoc()['total'] ?? 1;

    $patientGrowth = $lastMonthPatients > 0 
        ? round((($currentMonthPatients - $lastMonthPatients) / $lastMonthPatients) * 100, 1)
        : 0;

    $response = [
        'status' => 'success',
        'data' => [
            'stats' => [
                'total_patients' => (int)$patientCount,
                'verified_patients' => (int)$verifiedPatientCount,
                'pending_verifications' => (int)$pendingCount,
                'total_clinics' => (int)$clinicCount,
                'verified_clinics' => (int)$verifiedCount,
                'total_appointments' => (int)$appointmentCount,
            ],
            'metrics' => [
                'patient_growth_percentage' => $patientGrowth,
                'current_month_registrations' => (int)$currentMonthPatients,
                'clinic_coverage_rate' => $clinicCount > 0 
                    ? round(($verifiedCount / $clinicCount) * 100, 1)
                    : 0,
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to fetch dashboard data'
    ]);
}

$conn->close();
?>
>>>>>>> 7d6577fd5089edccf21ca61fd2313c443b0f6cb7
