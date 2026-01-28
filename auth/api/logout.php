<?php
// Logout script
session_start();

// Destroy session
session_destroy();

// Clear cookies
setcookie('patient_id', '', time() - 3600, '/');
setcookie('clinic_id', '', time() - 3600, '/');
setcookie('admin_id', '', time() - 3600, '/');
setcookie('user_type', '', time() - 3600, '/');

// Redirect to home
header('Location: ../../index.html');
exit;
?>
