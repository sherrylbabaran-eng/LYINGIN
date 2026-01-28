<?php
/**
 * Security Initialization & Setup Script
 * Run this once after deployment to ensure all security settings are correct
 */

echo "=== Lying-In Clinic Security Setup ===\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Check logs directory
echo "[1/8] Checking logs directory...";
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
    $success[] = "Logs directory created";
} else {
    if (!is_writable(__DIR__ . '/logs')) {
        $errors[] = "Logs directory is not writable. Run: chmod 755 logs/";
    } else {
        $success[] = "Logs directory exists and is writable";
    }
}
echo "\n";

// 2. Check uploads directory
echo "[2/8] Checking uploads directory...";
$upload_dirs = ['uploads/', 'uploads/patients/', 'uploads/clinics/'];
foreach ($upload_dirs as $dir) {
    $full_path = __DIR__ . '/../' . $dir;
    if (!is_dir($full_path)) {
        mkdir($full_path, 0755, true);
        $success[] = "Created directory: $dir";
    }
    if (!is_writable($full_path)) {
        $errors[] = "Directory not writable: $dir. Run: chmod 755 $dir";
    }
}
echo "\n";

// 3. Check .env file
echo "[3/8] Checking environment configuration...";
if (!file_exists(__DIR__ . '/../.env')) {
    $warnings[] = "No .env file found. Copy .env.example to .env and configure it.";
} else {
    $success[] = ".env file exists";
}
echo "\n";

// 4. Check PHP version
echo "[4/8] Checking PHP version...";
$php_version = phpversion();
if (version_compare($php_version, '7.2.0', '<')) {
    $errors[] = "PHP 7.2+ required. Current: $php_version";
} else {
    $success[] = "PHP version OK: $php_version";
}
echo "\n";

// 5. Check required PHP extensions
echo "[5/8] Checking PHP extensions...";
$required_extensions = ['mysqli', 'openssl', 'pdo'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Required extension missing: $ext";
    }
}
if (empty($errors)) {
    $success[] = "All required extensions present";
}
echo "\n";

// 6. Check database connection
echo "[6/8] Testing database connection...";
$db_host = getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';
$db_name = getenv('DB_NAME') ?: 'lyingin_db';

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    $warnings[] = "Database connection failed: {$conn->connect_error}. This may be normal if database isn't set up yet.";
} else {
    $success[] = "Database connection successful";
    
    // Check required tables
    $tables = ['RegPatient', 'clinics'];
    $result = $conn->query("SHOW TABLES");
    $existing_tables = [];
    while ($row = $result->fetch_row()) {
        $existing_tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        if (!in_array($table, $existing_tables)) {
            $warnings[] = "Table not found: $table";
        }
    }
    
    $conn->close();
}
echo "\n";

// 7. Check file permissions
echo "[7/8] Checking file permissions...";
$config_file = __DIR__ . '/api/security.php';
if (is_readable($config_file)) {
    $success[] = "Security configuration readable";
} else {
    $errors[] = "Security configuration not readable: $config_file";
}
echo "\n";

// 8. Test CSRF token generation
echo "[8/8] Testing security functions...";
try {
    require_once(__DIR__ . '/api/security.php');
    session_start();
    
    // Test CSRF token generation
    $token = generateCSRFToken();
    if (!empty($token)) {
        $success[] = "CSRF token generation working";
    }
    
    // Test input sanitization
    $test_input = "<script>alert('test')</script>";
    $sanitized = sanitizeInput($test_input);
    if ($sanitized !== $test_input) {
        $success[] = "Input sanitization working";
    }
    
    // Test email validation
    if (validateEmail('test@example.com') && !validateEmail('invalid')) {
        $success[] = "Email validation working";
    }
    
} catch (Exception $e) {
    $errors[] = "Security test failed: " . $e->getMessage();
}
echo "\n";

// Output results
echo "\n=== RESULTS ===\n\n";

if (!empty($success)) {
    echo "✓ SUCCESS (" . count($success) . ")\n";
    foreach ($success as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠ WARNINGS (" . count($warnings) . ")\n";
    foreach ($warnings as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "✗ ERRORS (" . count($errors) . ")\n";
    foreach ($errors as $msg) {
        echo "  - $msg\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "\n=== Security setup verified! ===\n";
    exit(0);
}
?>
