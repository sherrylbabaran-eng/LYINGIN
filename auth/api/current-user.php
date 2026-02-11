<?php
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "user" => [
        "id" => $_SESSION['user_id'],
        "name" => $_SESSION['user_name'] ?? '',
        "email" => $_SESSION['user_email'] ?? '',
        "type" => $_SESSION['user_type'] ?? ''
    ]
]);
?>