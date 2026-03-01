<?php
/**
 * Admin Notifications API
 * Returns notifications for the logged-in superadmin/admin
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

// Get optional limit parameter
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$limit = min(max($limit, 1), 50); // Between 1 and 50

try {
    // Fetch notifications for this user
    $stmt = $conn->prepare(
        "SELECT id, title, message, type, icon, is_read, created_at
         FROM notifications
         WHERE user_id = ? AND user_type = ?
         ORDER BY created_at DESC
         LIMIT ?"
    );
    
    $stmt->bind_param('isi', $userId, $userType, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate time ago
        $timestamp = strtotime($row['created_at']);
        $timeAgo = timeAgo($timestamp);
        
        // Map type to Bootstrap color class
        $typeColorMap = [
            'success' => 'bg-success',
            'info' => 'bg-info',
            'warning' => 'bg-warning',
            'danger' => 'bg-danger'
        ];
        $colorClass = $typeColorMap[$row['type']] ?? 'bg-info';
        
        $notifications[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'message' => $row['message'],
            'type' => $row['type'],
            'icon' => $row['icon'] ?? 'mdi-bell',
            'color_class' => $colorClass,
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at'],
            'time_ago' => $timeAgo
        ];
    }
    
    // Get unread count
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) as unread_count
         FROM notifications
         WHERE user_id = ? AND user_type = ? AND is_read = 0"
    );
    $countStmt->bind_param('is', $userId, $userType);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $unreadCount = $countResult->fetch_assoc()['unread_count'];
    
    echo json_encode([
        'status' => 'success',
        'notifications' => $notifications,
        'unread_count' => (int)$unreadCount,
        'total_count' => count($notifications)
    ]);
    
    $stmt->close();
    $countStmt->close();
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch notifications']);
}

/**
 * Convert timestamp to "time ago" format
 */
function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

mysqli_close($conn);
?>
