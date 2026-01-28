<?php
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

// Generate CSRF token
$token = generateCSRFToken();

echo json_encode(['token' => $token]);
?>
