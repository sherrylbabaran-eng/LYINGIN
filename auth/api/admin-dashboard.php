<?php
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
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
