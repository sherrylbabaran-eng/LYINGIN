<?php
/**
 * Mark Messages As Read API
 * Marks all messages as read for the logged-in user
 */

header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db-config.php';

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
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]);
    exit;
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

$userId = $_SESSION['user_id'];
$userType = $_SESSION['user_type'];

try {
    // Mark all messages as read for this user
    $stmt = $conn->prepare(
        "UPDATE system_messages 
         SET is_read = 1 
         WHERE recipient_id = ? AND recipient_type = ? AND is_read = 0"
    );
    
    $stmt->bind_param('is', $userId, $userType);
    $stmt->execute();
    
    $affected = $stmt->affected_rows;
    
    echo json_encode([
        'status' => 'success',
        'message' => 'All messages marked as read',
        'marked_count' => $affected
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error marking messages as read: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to mark messages as read']);
}

mysqli_close($conn);
?>
