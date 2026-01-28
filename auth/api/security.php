<?php
/**
 * Security Configuration
 * Centralized security settings and helper functions
 */

// Prevent direct access
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    http_response_code(403);
    exit('Access Denied');
}

// ============================================
// 1. SESSION SECURITY
// ============================================

// Secure session configuration
ini_set('session.cookie_httponly', 1);      // Prevent JavaScript access to cookies
ini_set('session.cookie_secure', 1);        // Only send over HTTPS
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection
ini_set('session.gc_maxlifetime', 1800);    // 30 minutes session timeout
ini_set('session.use_strict_mode', 1);      // Prevent session fixation

// ============================================
// 2. SECURITY HEADERS
// ============================================

function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');           // Prevent MIME type sniffing
    header('X-Frame-Options: DENY');                      // Prevent clickjacking
    header('X-XSS-Protection: 1; mode=block');           // XSS protection
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); // HTTPS only
    header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; img-src \'self\' data: https:;');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// ============================================
// 3. ERROR HANDLING
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 0);              // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[ERROR] $errstr in $errfile on line $errline");
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'An error occurred']);
    exit;
});

// ============================================
// 4. INPUT SANITIZATION
// ============================================

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ============================================
// 5. CSRF PROTECTION
// ============================================

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

function validateCSRF() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    
    if (!$token || !verifyCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'CSRF token validation failed']);
        exit;
    }
}

// ============================================
// 6. RATE LIMITING
// ============================================

class RateLimiter {
    private $identifier;
    private $max_attempts;
    private $time_window;
    private $cache_file;
    
    public function __construct($identifier, $max_attempts = 5, $time_window = 900) {
        $this->identifier = md5($identifier);
        $this->max_attempts = $max_attempts;
        $this->time_window = $time_window;
        $this->cache_file = sys_get_temp_dir() . '/rate_limit_' . $this->identifier;
    }
    
    public function isAllowed() {
        if (!file_exists($this->cache_file)) {
            $this->recordAttempt();
            return true;
        }
        
        $data = json_decode(file_get_contents($this->cache_file), true);
        $current_time = time();
        
        // Check if time window has passed
        if ($current_time - $data['first_attempt'] > $this->time_window) {
            // Reset attempts
            file_put_contents($this->cache_file, json_encode([
                'attempts' => 1,
                'first_attempt' => $current_time
            ]));
            return true;
        }
        
        // Check if max attempts exceeded
        if ($data['attempts'] >= $this->max_attempts) {
            return false;
        }
        
        $data['attempts']++;
        file_put_contents($this->cache_file, json_encode($data));
        return true;
    }
    
    private function recordAttempt() {
        file_put_contents($this->cache_file, json_encode([
            'attempts' => 1,
            'first_attempt' => time()
        ]));
    }
}

// ============================================
// 7. FILE UPLOAD SECURITY
// ============================================

function validateFileUpload($file, $allowed_types = [], $max_size = 10485760) {
    // Max size: 10MB by default
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error occurred'];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        return ['valid' => false, 'error' => 'File size exceeds limit'];
    }
    
    // Check MIME type using finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    
    return ['valid' => true, 'mime_type' => $mime_type];
}

function saveSecureFile($file, $destination_dir) {
    // Generate secure filename
    $filename = bin2hex(random_bytes(16)) . '_' . time();
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $secure_filename = $filename . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $file_ext);
    
    $destination = rtrim($destination_dir, '/') . '/' . $secure_filename;
    
    // Ensure directory exists
    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        chmod($destination, 0644);
        return $secure_filename;
    }
    
    return false;
}

// ============================================
// 8. PASSWORD VALIDATION
// ============================================

function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain number';
    }
    if (!preg_match('/[!@#$%^&*]/', $password)) {
        $errors[] = 'Password must contain special character (!@#$%^&*)';
    }
    
    return ['valid' => empty($errors), 'errors' => $errors];
}

// ============================================
// 9. ENCRYPTION (for sensitive data)
// ============================================

class SecureEncryption {
    private $key;
    
    public function __construct($key = null) {
        $this->key = $key ?? getenv('ENCRYPTION_KEY');
        if (!$this->key) {
            $this->key = hash('sha256', 'default-key-change-in-production', true);
        }
    }
    
    public function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, openssl_cipher_iv_length('AES-256-CBC'));
        $encrypted = substr($data, openssl_cipher_iv_length('AES-256-CBC'));
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->key, 0, $iv);
    }
}

// ============================================
// 10. LOGGING
// ============================================

function logSecurityEvent($event, $details = []) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $log_file = $log_dir . '/security.log';
    file_put_contents($log_file, json_encode($log_entry) . PHP_EOL, FILE_APPEND);
}

// ============================================
// 11. INITIALIZE SECURITY
// ============================================

setSecurityHeaders();

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log all requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    logSecurityEvent('REQUEST', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'path' => $_SERVER['REQUEST_URI']
    ]);
}
?>
