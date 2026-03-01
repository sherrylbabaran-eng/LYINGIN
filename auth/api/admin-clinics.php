<?php
/**
 * Admin Clinics Management API
 * 
 * Handles clinic listing and management operations for super admin
 * Supports filtering, sorting, and pagination
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

// Get request parameters with sanitization
$action = sanitizeInput($_GET['action'] ?? 'list');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, intval($_GET['limit'] ?? 10));
$offset = ($page - 1) * $limit;

try {
    switch ($action) {
        case 'list':
            // Fetch clinics with pagination using prepared statement
            $query = "SELECT 
                        id,
                        clinic_name,
                        license_number,
                        address,
                        email,
                        contact_number,
                        email_verified,
                        created_at
                    FROM clinics
                    ORDER BY created_at DESC
                    LIMIT ? OFFSET ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $limit, $offset);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $clinics = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Get total count for pagination
            $countResult = $conn->query("SELECT COUNT(*) as total FROM clinics");
            $countRow = $countResult->fetch_assoc();
            $totalClinics = $countRow['total'] ?? 0;

            // Format response with human-readable status
            $clinicList = array_map(function($clinic) {
                return [
                    'id' => (int)$clinic['id'],
                    'clinic_name' => htmlspecialchars($clinic['clinic_name']),
                    'license_number' => htmlspecialchars($clinic['license_number']),
                    'address' => htmlspecialchars($clinic['address']),
                    'email' => htmlspecialchars($clinic['email']),
                    'contact_number' => htmlspecialchars($clinic['contact_number']),
                    'status' => $clinic['email_verified'] ? 'verified' : 'pending',
                    'created_at' => date('M d, Y', strtotime($clinic['created_at']))
                ];
            }, $clinics);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'clinics' => $clinicList,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total_records' => (int)$totalClinics,
                        'total_pages' => ceil($totalClinics / $limit)
                    ]
                ]
            ]);
            break;

        case 'get':
            // Get single clinic details
            $clinicId = intval($_GET['id'] ?? 0);
            if ($clinicId <= 0) {
                throw new Exception('Invalid clinic ID');
            }

            $query = "SELECT * FROM clinics WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $clinicId);
            $stmt->execute();
            
            $result = $stmt->get_result();
            $clinic = $result->fetch_assoc();
            $stmt->close();

            if (!$clinic) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Clinic not found'
                ]);
                exit;
            }

            // Count patients associated with this clinic
            $patientQuery = "SELECT COUNT(*) as total FROM patient_appointments 
                            WHERE clinic_id = ?";
            $patientStmt = $conn->prepare($patientQuery);
            $patientStmt->bind_param("i", $clinicId);
            $patientStmt->execute();
            $patientResult = $patientStmt->get_result();
            $patientRow = $patientResult->fetch_assoc();
            $patientCount = $patientRow['total'] ?? 0;
            $patientStmt->close();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => (int)$clinic['id'],
                    'clinic_name' => htmlspecialchars($clinic['clinic_name']),
                    'license_number' => htmlspecialchars($clinic['license_number']),
                    'address' => htmlspecialchars($clinic['address']),
                    'email' => htmlspecialchars($clinic['email']),
                    'contact_number' => htmlspecialchars($clinic['contact_number']),
                    'admin_name' => htmlspecialchars($clinic['admin_name']),
                    'status' => $clinic['email_verified'] ? 'verified' : 'pending',
                    'patient_count' => (int)$patientCount,
                    'created_at' => $clinic['created_at']
                ]
            ]);
            break;

        case 'stats':
            // Get clinic statistics
            $verifiedResult = $conn->query("SELECT COUNT(*) as total FROM clinics WHERE email_verified = 1");
            $verifiedRow = $verifiedResult->fetch_assoc();
            $verifiedCount = $verifiedRow['total'] ?? 0;

            $pendingResult = $conn->query("SELECT COUNT(*) as total FROM clinics WHERE email_verified = 0");
            $pendingRow = $pendingResult->fetch_assoc();
            $pendingCount = $pendingRow['total'] ?? 0;

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'verified_clinics' => (int)$verifiedCount,
                    'pending_verification' => (int)$pendingCount,
                    'total_clinics' => (int)($verifiedCount + $pendingCount)
                ]
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
    }

} catch (Exception $e) {
    error_log('Admin Clinics API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to process request'
    ]);
}

$conn->close();
?>
