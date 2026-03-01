<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isSuperAdmin = isset($_SESSION['user_id'])
    && ((($_SESSION['user_type'] ?? '') === 'superadmin') || (($_SESSION['role'] ?? '') === 'superadmin'));

if (!$isSuperAdmin) {
    header('Location: ../index.html');
    exit;
}

require __DIR__ . '/admins.html';
