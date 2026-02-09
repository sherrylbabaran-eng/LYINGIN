<?php
require_once __DIR__ . '/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

// Validate CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

// Rate limiting - 5 attempts per 15 minutes per IP
$limiter = new RateLimiter($_SERVER['REMOTE_ADDR'] . '_login');
if (!$limiter->isAllowed()) {
    logSecurityEvent('BRUTE_FORCE_ATTEMPT', ['ip' => $_SERVER['REMOTE_ADDR']]);
    http_response_code(429);
    echo json_encode(["status" => "error", "message" => "Too many login attempts. Please try again later."]);
    exit;
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
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Get and validate form data
$email = sanitizeEmail($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$user_type = sanitizeInput($_POST['user_type'] ?? 'patient');
$remember = isset($_POST['remember']);

// Validate email
if (!validateEmail($email)) {
    logSecurityEvent('INVALID_EMAIL_ATTEMPT', ['email' => $email]);
    echo json_encode(["status" => "error", "message" => "Invalid email format"]);
    exit;
}

// Validate password not empty
if (empty($password) || strlen($password) < 6) {
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

// Whitelist user types
$allowed_types = ['patient', 'clinic', 'admin'];
if (!in_array($user_type, $allowed_types)) {
    echo json_encode(["status" => "error", "message" => "Invalid user type"]);
    exit;
}

$login_success = false;
$redirect = '';

// Authentication logic based on user type
if ($user_type === 'patient') {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, email_verified FROM RegPatient WHERE email = ? LIMIT 1");
    if (!$stmt) {
        logSecurityEvent('DB_PREPARE_ERROR', ['error' => $conn->error]);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if email is verified
        if (!$user['email_verified']) {
            logSecurityEvent('UNVERIFIED_LOGIN_ATTEMPT', ['user_type' => 'patient', 'email' => $email]);
            echo json_encode(["status" => "error", "message" => "Please verify your email before logging in. Check your inbox for the verification link."]);
            $stmt->close();
            exit;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $login_success = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'patient';
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in_at'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            
            // Set remember me cookie (30 days, secure)
            if ($remember) {
                setcookie('patient_id', $user['id'], time() + (30 * 24 * 60 * 60), '/', '', true, true);
                setcookie('user_type', 'patient', time() + (30 * 24 * 60 * 60), '/', '', true, true);
            }
            
            $redirect = 'patient/user.html';
            logSecurityEvent('LOGIN_SUCCESS', ['user_type' => 'patient', 'user_id' => $user['id']]);
        }
    }
    $stmt->close();
    
} elseif ($user_type === 'clinic') {
    $stmt = $conn->prepare("SELECT id, clinic_name, email, password, email_verified FROM clinics WHERE email = ? LIMIT 1");
    if (!$stmt) {
        logSecurityEvent('DB_PREPARE_ERROR', ['error' => $conn->error]);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if email is verified
        if (!$user['email_verified']) {
            logSecurityEvent('UNVERIFIED_LOGIN_ATTEMPT', ['user_type' => 'clinic', 'email' => $email]);
            echo json_encode(["status" => "error", "message" => "Please verify your email before logging in. Check your inbox for the verification link."]);
            $stmt->close();
            exit;
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $login_success = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'clinic';
            $_SESSION['user_name'] = $user['clinic_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in_at'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            
            // Set remember me cookie
            if ($remember) {
                setcookie('clinic_id', $user['id'], time() + (30 * 24 * 60 * 60), '/', '', true, true);
                setcookie('user_type', 'clinic', time() + (30 * 24 * 60 * 60), '/', '', true, true);
            }
            
            $redirect = 'clinic/clinika.html';
            logSecurityEvent('LOGIN_SUCCESS', ['user_type' => 'clinic', 'user_id' => $user['id']]);
        }
    }
    $stmt->close();
    
} elseif ($user_type === 'admin') {
    $stmt = $conn->prepare("SELECT id, username, email, password FROM admins WHERE email = ? LIMIT 1");
    if (!$stmt) {
        logSecurityEvent('DB_PREPARE_ERROR', ['error' => $conn->error]);
        echo json_encode(["status" => "error", "message" => "Database error"]);
        exit;
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            $login_success = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'admin';
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['logged_in_at'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            
            // Set remember me cookie
            if ($remember) {
                setcookie('admin_id', $user['id'], time() + (30 * 24 * 60 * 60), '/', '', true, true);
                setcookie('user_type', 'admin', time() + (30 * 24 * 60 * 60), '/', '', true, true);
            }
            
            $redirect = 'admin/doh.html';
            logSecurityEvent('LOGIN_SUCCESS', ['user_type' => 'admin', 'user_id' => $user['id']]);
        }
    }
    $stmt->close();
}

if ($login_success) {
    echo json_encode([
        "status" => "success",
        "message" => "Login successful",
        "redirect" => $redirect
    ]);
} else {
    logSecurityEvent('LOGIN_FAILED', ['email' => $email, 'user_type' => $user_type]);
    echo json_encode(["status" => "error", "message" => "Invalid email or password"]);
}

$conn->close();
?>

