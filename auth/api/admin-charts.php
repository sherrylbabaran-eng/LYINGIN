<?php
/**
 * Admin Charts Data API
 * Returns chart data for dashboard visualizations
 */

header('Content-Type: application/json');
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

// Only allow superadmin and admin access
$allowedTypes = ['superadmin', 'admin'];
if (!in_array($_SESSION['user_type'], $allowedTypes)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

// Create connection - Use error handler to catch connection errors
$conn = @new mysqli($servername, $db_username, $db_password, $dbname);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

try {
    // Initialize response data
    $monthlyData = [];
    $months = [];
    
    // Get monthly statistics for the last 8 months
    for ($i = 7; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $monthLabel = strtoupper(date('M', strtotime("-$i months")));
        $months[] = $monthLabel;
        
        // Get count of patients registered in this month
        $query = "SELECT COUNT(*) as count FROM regpatient WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Patient query prepare failed: " . $conn->error);
        }
        $stmt->bind_param('s', $month);
        if (!$stmt->execute()) {
            throw new Exception("Patient query execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $patientCount = $row['count'] ?? 0;
        $stmt->close();
        
        // Get count of clinics created in this month
        $query = "SELECT COUNT(*) as count FROM clinics WHERE DATE_FORMAT(created_at, '%Y-%m') = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Clinic query prepare failed: " . $conn->error);
        }
        $stmt->bind_param('s', $month);
        if (!$stmt->execute()) {
            throw new Exception("Clinic query execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $clinicCount = $row['count'] ?? 0;
        $stmt->close();
        
        // Get count of appointments scheduled in this month
        $query = "SELECT COUNT(*) as count FROM patient_appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Appointment query prepare failed: " . $conn->error);
        }
        $stmt->bind_param('s', $month);
        if (!$stmt->execute()) {
            throw new Exception("Appointment query execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $appointmentCount = $row['count'] ?? 0;
        $stmt->close();
        
        $monthlyData[] = [
            'month' => $monthLabel,
            'patients' => (int)$patientCount,
            'clinics' => (int)$clinicCount,
            'appointments' => (int)$appointmentCount
        ];
    }
    
    // Get appointment status breakdown for pie/doughnut chart
    $query = "SELECT 
                COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
                COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END), 0) as confirmed,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) as completed
             FROM patient_appointments";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Status query prepare failed: " . $conn->error);
    }
    if (!$stmt->execute()) {
        throw new Exception("Status query execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Use only the statuses we care about for the chart (pending, completed, cancelled)
    $appointmentStats = [
        'pending' => (int)($row['pending'] ?? 0),
        'completed' => (int)($row['completed'] ?? 0),
        'cancelled' => (int)($row['cancelled'] ?? 0)
    ];
    $stmt->close();
    
    // Calculate percentages
    $total = $appointmentStats['pending'] + $appointmentStats['completed'] + $appointmentStats['cancelled'];
    
    if ($total > 0) {
        $appointmentStats['pending_percent'] = round(($appointmentStats['pending'] / $total) * 100);
        $appointmentStats['completed_percent'] = round(($appointmentStats['completed'] / $total) * 100);
        $appointmentStats['cancelled_percent'] = round(($appointmentStats['cancelled'] / $total) * 100);
    } else {
        $appointmentStats['pending_percent'] = 0;
        $appointmentStats['completed_percent'] = 0;
        $appointmentStats['cancelled_percent'] = 0;
    }
    
    // Return successful response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'monthly_data' => $monthlyData,
        'months' => $months,
        'appointment_stats' => $appointmentStats
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    error_log("Admin Charts API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch chart data',
        'debug' => $e->getMessage()
    ]);
}
?>
