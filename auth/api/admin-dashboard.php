<?php
/**
 * Admin Dashboard Statistics API
 *
 * Returns dashboard metrics for superadmin/admin pages.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setSecurityHeaders();

function jsonResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// Validate admin or superadmin session
$currentUserType = $_SESSION['user_type'] ?? '';
$currentRole = $_SESSION['role'] ?? '';
$isAdmin = $currentUserType === 'admin' || $currentRole === 'admin';
$isSuperAdmin = $currentUserType === 'superadmin' || $currentRole === 'superadmin';

if (!isset($_SESSION['user_id']) || (!$isAdmin && !$isSuperAdmin)) {
    jsonResponse(403, [
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
}

// Validate CSRF token for POST requests
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    validateCSRF();
}

// Database configuration
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

try {
    $hasPatientsTable = false;
    $patientsTableCheck = $conn->query("SHOW TABLES LIKE 'patients'");
    if ($patientsTableCheck) {
        $hasPatientsTable = $patientsTableCheck->num_rows > 0;
    }

    $patientTableName = $hasPatientsTable ? 'patients' : 'regpatient';
    $patientVerifiedCondition = $hasPatientsTable ? "verification_status = 'verified'" : 'email_verified = 1';
    $patientPendingCondition = $hasPatientsTable ? "verification_status = 'pending'" : 'email_verified = 0';

    $patientResult = $conn->query("SELECT COUNT(*) as total FROM {$patientTableName}");
    $patientCount = $patientResult ? (int)($patientResult->fetch_assoc()['total'] ?? 0) : 0;

    $clinicResult = $conn->query("SELECT COUNT(*) as total FROM clinics");
    $clinicCount = $clinicResult ? (int)($clinicResult->fetch_assoc()['total'] ?? 0) : 0;

    $verifiedResult = $conn->query("SELECT COUNT(*) as total FROM clinics WHERE email_verified = 1");
    $verifiedCount = $verifiedResult ? (int)($verifiedResult->fetch_assoc()['total'] ?? 0) : 0;

    $verifiedPatientResult = $conn->query("SELECT COUNT(*) as total FROM {$patientTableName} WHERE {$patientVerifiedCondition}");
    $verifiedPatientCount = $verifiedPatientResult ? (int)($verifiedPatientResult->fetch_assoc()['total'] ?? 0) : 0;

    $pendingResult = $conn->query("SELECT COUNT(*) as total FROM {$patientTableName} WHERE {$patientPendingCondition}");
    $pendingCount = $pendingResult ? (int)($pendingResult->fetch_assoc()['total'] ?? 0) : 0;

    $appointmentResult = $conn->query("SELECT COUNT(*) as total FROM patient_appointments");
    $appointmentCount = $appointmentResult ? (int)($appointmentResult->fetch_assoc()['total'] ?? 0) : 0;

    $pendingAppointmentsResult = $conn->query("SELECT COUNT(*) as total FROM patient_appointments WHERE status = 'pending'");
    $pendingAppointments = $pendingAppointmentsResult ? (int)($pendingAppointmentsResult->fetch_assoc()['total'] ?? 0) : 0;

    $currentMonthResult = $conn->query(
        "SELECT COUNT(*) as total FROM {$patientTableName} WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())"
    );
    $currentMonthPatients = $currentMonthResult ? (int)($currentMonthResult->fetch_assoc()['total'] ?? 0) : 0;

    $lastMonthResult = $conn->query(
        "SELECT COUNT(*) as total FROM {$patientTableName} WHERE MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
    );
    $lastMonthPatients = $lastMonthResult ? (int)($lastMonthResult->fetch_assoc()['total'] ?? 0) : 0;

    $monthlyLabels = [];
    $monthlyPatientsData = [];
    for ($offset = 5; $offset >= 0; $offset--) {
        $date = new DateTime("first day of -{$offset} month");
        $monthlyLabels[] = strtoupper($date->format('M'));

        $stmt = $conn->prepare("SELECT COUNT(*) FROM {$patientTableName} WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?");
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
    $clinicRows = $conn->query($clinicQuery);
    if ($clinicRows) {
        while ($row = $clinicRows->fetch_assoc()) {
            $recentClinics[] = [
                'id' => (int)$row['id'],
                'clinic_name' => $row['clinic_name'] ?: 'Unnamed Clinic',
                'status' => ((int)$row['email_verified'] === 1) ? 'Verified' : 'Pending Verification',
                'last_update' => $row['created_at']
            ];
        }
    }

    $patientGrowth = $lastMonthPatients > 0
        ? round((($currentMonthPatients - $lastMonthPatients) / $lastMonthPatients) * 100, 1)
        : 0;

    jsonResponse(200, [
        'status' => 'success',
        'data' => [
            'summary' => [
                'total_clinics' => $clinicCount,
                'verified_clinics' => $verifiedCount,
                'monthly_appointments' => $appointmentCount,
                'pending_appointments' => $pendingAppointments,
                'total_patients' => $patientCount,
                'verified_patients' => $verifiedPatientCount
            ],
            'charts' => [
                'monthly_registrations' => [
                    'labels' => $monthlyLabels,
                    'data' => $monthlyPatientsData
                ],
                'schedule_breakdown' => $scheduleBreakdown
            ],
            'recent_clinics' => $recentClinics,
            'stats' => [
                'total_patients' => (int) $patientCount,
                'verified_patients' => (int) $verifiedPatientCount,
                'pending_verifications' => (int) $pendingCount,
                'total_clinics' => (int) $clinicCount,
                'verified_clinics' => (int) $verifiedCount,
                'total_appointments' => (int) $appointmentCount,
            ],
            'metrics' => [
                'patient_growth_percentage' => (float) $patientGrowth,
                'current_month_registrations' => (int) $currentMonthPatients,
                'clinic_coverage_rate' => $clinicCount > 0
                    ? round(($verifiedCount / $clinicCount) * 100, 1)
                    : 0,
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
} catch (Throwable $e) {
    error_log('Admin Dashboard Error: ' . $e->getMessage());
    jsonResponse(500, [
        'status' => 'error',
        'message' => 'Unable to fetch dashboard data'
    ]);
} finally {
    $conn->close();
}
