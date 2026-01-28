<?php
// Session check script - include this in pages that require authentication

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login
    header('Location: ../../auth/login.html');
    exit;
}

// Optional: Define a variable for current user
$current_user = [
    'id' => $_SESSION['user_id'],
    'name' => $_SESSION['user_name'],
    'email' => $_SESSION['user_email'],
    'type' => $_SESSION['user_type']
];
?>
