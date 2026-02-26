<?php
// In production hide display errors to avoid sending HTML error pages to clients
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Convert errors into exceptions (but skip warnings from external functions like mail)
// Only throw exceptions for user-defined errors and fatal errors, not warnings
set_error_handler(function($severity, $message, $file, $line) {
    // Don't throw for warnings or notices from external functions (e.g., mail())
    // Only throw for user errors (E_USER_ERROR, E_USER_WARNING, E_USER_NOTICE)
    if ($severity & (E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE | E_CORE_ERROR | E_COMPILE_ERROR)) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false; // Let default handler process other errors
});

header('Content-Type: application/json');
session_start();

try {

// Load .env into environment if present (search common parent locations so Apache/PHP uses project .env)
$candidates = [
    realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',      // auth/.env
    realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',   // project root /.env
    __DIR__ . DIRECTORY_SEPARATOR . '.env'                         // auth/api/.env
];
$envFile = null;
foreach ($candidates as $cand) {
    if ($cand && file_exists($cand)) { $envFile = $cand; break; }
}
if ($envFile) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($key, $val) = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // strip surrounding quotes
        $val = trim($val, "'\"");
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
} else {
    error_log('No .env file found in expected locations; environment mail settings may be missing.');
}

require_once __DIR__ . '/mail-helper.php';

// DB config
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status"=>"error","message"=>"DB Connection failed: ".$conn->connect_error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status"=>"error","message"=>"Invalid request method."]);
    exit;
}

// --- Step 1: Personal info ---
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$birthdate  = trim($_POST['birthdate'] ?? '');
$gender     = trim($_POST['gender'] ?? '');
$email      = trim($_POST['email'] ?? '');
$contact_no = trim($_POST['contact_no'] ?? '');
$address    = trim($_POST['address'] ?? '');

// --- Step 2: Account & ID ---
$username   = trim($_POST['username'] ?? '');
$rawPass    = $_POST['password'] ?? '';
$password   = password_hash($rawPass, PASSWORD_DEFAULT);
$id_type    = trim($_POST['id_type'] ?? '');
$id_number  = trim($_POST['id_number'] ?? '');

// Normalize and validate ID helper functions
function normalize_id($type, $value) {
    $v = trim($value);
    // For numeric IDs, strip non-digits
    if (in_array($type, ['national', 'philhealth', 'sss'])) {
        return preg_replace('/\D/', '', $v);
    }
    // For others, collapse whitespace
    return preg_replace('/\s+/', '', $v);
}

function parse_face_descriptor($raw) {
    if (!$raw) return null;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return null;
    if (count($decoded) < 64) return null;
    $out = [];
    foreach ($decoded as $v) {
        if (!is_numeric($v)) return null;
        $out[] = (float)$v;
    }
    return $out;
}

function face_distance($a, $b) {
    if (!$a || !$b) return null;
    $len = min(count($a), count($b));
    if ($len < 64) return null;
    $sum = 0.0;
    for ($i = 0; $i < $len; $i++) {
        $d = $a[$i] - $b[$i];
        $sum += $d * $d;
    }
    return sqrt($sum);
}

function cleanupUploadedFiles(array $paths) {
    foreach ($paths as $path) {
        if ($path && is_string($path) && file_exists($path)) {
            @unlink($path);
        }
    }
}

// If username is not provided, derive one from email local-part or generate a fallback
if (empty($username)) {
    $local = strstr($email, '@', true);
    $candidate = $local ? preg_replace('/[^A-Za-z0-9._-]/', '', $local) : 'user_' . uniqid();
    // Ensure uniqueness
    $check = $conn->prepare("SELECT COUNT(*) as cnt FROM RegPatient WHERE username = ?");
    if (!$check) {
        error_log('DB prepare failed (username check): ' . $conn->error);
        echo json_encode(["status"=>"error","message"=>"Server error. Please try again later."]); exit;
    }
    $check->bind_param('s', $candidate);
    $check->execute();
    $res = $check->get_result()->fetch_assoc();
    if ($res && $res['cnt'] > 0) {
        $candidate .= '_' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
    $username = $candidate;
}

// --- Validate required fields ---
if (
    !$first_name || !$last_name || !$birthdate || !$gender || !$email ||
    !$contact_no || !$address || !$rawPass || !$id_type || !$id_number
) {
    echo json_encode(["status"=>"error","message"=>"All fields are required."]);
    exit;
}

// Normalize ID server-side (no strict OCR/format enforcement)
$normalized_id = normalize_id($id_type, $id_number);
if (!$normalized_id) {
    echo json_encode(["status"=>"error","message"=>"Please provide an ID number."]);
    exit;
}
// Replace with canonical normalized value to store in DB
$id_number = $normalized_id;

// --- Handle file upload ---
if (!isset($_FILES['idFile']) || $_FILES['idFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status"=>"error","message"=>"Please upload a valid front ID file."]);
    exit;
}

if (!isset($_FILES['idFileBack']) || $_FILES['idFileBack']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status"=>"error","message"=>"Please upload a valid back ID file."]);
    exit;
}

$allowedTypes = ['image/jpeg','image/png','application/pdf'];
$fileType = mime_content_type($_FILES['idFile']['tmp_name']);
$fileTypeBack = mime_content_type($_FILES['idFileBack']['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(["status"=>"error","message"=>"Invalid front ID file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

if (!in_array($fileTypeBack, $allowedTypes)) {
    echo json_encode(["status"=>"error","message"=>"Invalid back ID file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

$maxUploadSize = 5 * 1024 * 1024;
if (($_FILES['idFile']['size'] ?? 0) > $maxUploadSize || ($_FILES['idFileBack']['size'] ?? 0) > $maxUploadSize) {
    echo json_encode(["status"=>"error","message"=>"ID files must be 5MB or less."]);
    exit;
}

// Pre-validate verification gates before persisting uploaded files.
$face_verified = (isset($_POST['face_verified']) && $_POST['face_verified'] == '1') ? 1 : 0;
if (!$face_verified) {
    echo json_encode(["status" => "error", "message" => "Please complete face verification before registering."]);
    exit;
}

$face_live_1_raw = $_POST['face_live_1'] ?? '';
$face_live_2_raw = $_POST['face_live_2'] ?? '';
$face_id_raw = $_POST['face_id'] ?? '';

$face_live_1 = parse_face_descriptor($face_live_1_raw);
$face_live_2 = parse_face_descriptor($face_live_2_raw);
$face_id = parse_face_descriptor($face_id_raw);

if (!$face_live_1 || !$face_live_2 || !$face_id) {
    echo json_encode(["status" => "error", "message" => "Face verification data is missing or invalid. Please retry."]);
    exit;
}

$d1 = face_distance($face_live_1, $face_id);
$d2 = face_distance($face_live_2, $face_id);
$dlive = face_distance($face_live_1, $face_live_2);

if ($d1 === null || $d2 === null || $dlive === null) {
    echo json_encode(["status" => "error", "message" => "Face verification failed. Please retry."]);
    exit;
}

$threshold = (float)(getenv('FACE_MATCH_THRESHOLD') ?: 0.58);
$self_threshold = (float)(getenv('FACE_SELF_THRESHOLD') ?: 0.50);
if (!($d1 <= $threshold && $d2 <= $threshold && $dlive <= $self_threshold)) {
    echo json_encode(["status" => "error", "message" => "Face verification did not meet security thresholds. Please try again."]);
    exit;
}

$session_email = $_SESSION['verified_email'] ?? '';
$email_verified = ($session_email && strcasecmp($session_email, $email) === 0) ? 1 : 0;
if (!$email_verified) {
    echo json_encode(["status" => "error", "message" => "Please verify your email with OTP before registering."]);
    exit;
}

// ID-number OCR matching intentionally disabled; face verification is the strict identity gate.

$targetDir = __DIR__ . "/../../uploads/patients/";
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$fileNameFront = uniqid("id_front_", true) . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['idFile']['name']));
$id_file_front_path = $targetDir . $fileNameFront;

$fileNameBack = uniqid("id_back_", true) . "_" . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['idFileBack']['name']));
$id_file_back_path = $targetDir . $fileNameBack;

if (!move_uploaded_file($_FILES['idFile']['tmp_name'], $id_file_front_path)) {
    echo json_encode(["status"=>"error","message"=>"Failed to upload front ID file."]);
    exit;
}

if (!move_uploaded_file($_FILES['idFileBack']['tmp_name'], $id_file_back_path)) {
    cleanupUploadedFiles([$id_file_front_path]);
    echo json_encode(["status"=>"error","message"=>"Failed to upload back ID file."]);
    exit;
}

$id_file_path = json_encode([
    'front' => $id_file_front_path,
    'back' => $id_file_back_path
]);

// --- Insert into DB ---
// First, check if an unapproved/failed registration exists for this email and clean it up
$cleanup_stmt = $conn->prepare("DELETE FROM RegPatient WHERE email = ? AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
if ($cleanup_stmt) {
    $cleanup_stmt->bind_param("s", $email);
    $cleanup_stmt->execute();
    $cleanup_stmt->close();
}

$stmt = $conn->prepare("
    INSERT INTO RegPatient
    (first_name, last_name, birthdate, gender, email, contact_no, address,
     username, password, id_type, id_number, id_file, face_verified, email_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    error_log('DB prepare failed (insert): ' . $conn->error);
    echo json_encode(["status"=>"error","message"=>"Server error. Please try again later."]); exit;
}

$stmt->bind_param("ssssssssssssii",
    $first_name,
    $last_name,
    $birthdate,
    $gender,
    $email,
    $contact_no,
    $address,
    $username,
    $password,
    $id_type,
    $id_number,
    $id_file_path,
    $face_verified,
    $email_verified
);

if ($stmt->execute()) {
    unset($_SESSION['verified_email']);
    echo json_encode([
        "status" => "success",
        "message" => "Patient registered successfully!",
        "redirect" => "/auth/login.html"
    ]);
} else {
    // Handle INSERT errors, especially duplicate token issues
    $error = $stmt->error;
    error_log('Registration INSERT failed: ' . $error . ' for email: ' . $email);
    
    // Check if it's a duplicate token error (shouldn't happen, but cleanup doesn't always catch it)
    if (strpos($error, 'email_verification_token') !== false && strpos($error, 'Duplicate') !== false) {
        // Force cleanup: delete any existing records with same email and token
        $force_cleanup = $conn->prepare("DELETE FROM RegPatient WHERE email = ?");
        if ($force_cleanup) {
            $force_cleanup->bind_param("s", $email);
            $force_cleanup->execute();
            $force_cleanup->close();
        }
        cleanupUploadedFiles([$id_file_front_path, $id_file_back_path]);
        echo json_encode(["status"=>"error","message"=>"A previous registration attempt exists for this email. Please try registering again."]);
        exit;
    }
    
    // Check for duplicate email registration
    if (strpos($error, 'email') !== false && strpos($error, 'Duplicate') !== false) {
        cleanupUploadedFiles([$id_file_front_path, $id_file_back_path]);
        echo json_encode(["status"=>"error","message"=>"This email is already registered. Please use a different email or log in."]);
        exit;
    }
    
    cleanupUploadedFiles([$id_file_front_path, $id_file_back_path]);
    echo json_encode(["status"=>"error","message"=>"DB Error: ".$error]);
}

$stmt->close();
$conn->close();
} catch (Throwable $e) {
    if (isset($id_file_front_path) || isset($id_file_back_path)) {
        cleanupUploadedFiles([
            isset($id_file_front_path) ? $id_file_front_path : null,
            isset($id_file_back_path) ? $id_file_back_path : null
        ]);
    }
    error_log('Register-patient error: ' . $e->getMessage() . " in " . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(["status"=>"error","message"=>"Server error: " . $e->getMessage()]);
    exit;
}

/**
 * Send Email via Gmail SMTP (Real Gmail)
 */
function sendVerificationEmail($to_email, $subject, $html_body) {
    // 1) Try SendGrid API if configured
    $sendgridKey = getenv('SENDGRID_API_KEY');
    if ($sendgridKey) {
        $payload = [
            'personalizations' => [[
                'to' => [[ 'email' => $to_email ]]
            ]],
            'from' => ['email' => getenv('MAIL_FROM') ?: 'noreply@lyinginclinic.com'],
            'subject' => $subject,
            'content' => [[ 'type' => 'text/html', 'value' => $html_body ]]
        ];

        $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $sendgridKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($code === 202) return 'OK';
        return 'sendgrid_failed: ' . ($curlErr ?: $resp ?: 'unexpected_code_' . $code);
    }

    // 2) Try PHPMailer SMTP if available
    $phpmailer_error = null;
    if (sendMailWithPHPMailer($to_email, $subject, $html_body, $phpmailer_error)) {
        return 'OK';
    }
    if ($phpmailer_error) {
        error_log('PHPMailer verification send failed: ' . $phpmailer_error);
    }

    // 3) Try SMTP if credentials provided
    $smtpHost = getenv('MAIL_SMTP_HOST');
    $smtpUser = getenv('MAIL_USERNAME');
    $smtpPass = getenv('MAIL_PASSWORD');
    $smtpPort = getenv('MAIL_SMTP_PORT') ?: 587;
    $smtpSecure = getenv('MAIL_SMTP_SECURE') ?: 'tls';
    $mailFrom = getenv('MAIL_FROM') ?: 'noreply@lyinginclinic.com';

    if ($smtpHost && $smtpUser && $smtpPass) {
        $transport = ($smtpSecure === 'ssl') ? 'ssl://' : '';
        $ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $socket = @stream_socket_client($transport . $smtpHost . ':' . $smtpPort, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) return "socket_error: $errstr ($errno)";

        stream_set_timeout($socket, 5);
        $banner = fgets($socket);
        if ($banner === false) { fclose($socket); return 'smtp_no_banner'; }

        fputs($socket, "EHLO localhost\r\n");
        // Read server responses (skip detailed parsing)
        while (($line = fgets($socket)) !== false) {
            if (substr($line, 3, 1) !== '-') break;
            if (substr($line, 0, 3) == '250') break;
        }

        if ($smtpSecure === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $startResp = fgets($socket);
            if (strpos($startResp, '220') === false) { fclose($socket); return 'starttls_failed: ' . trim($startResp); }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($socket, "EHLO localhost\r\n");
            fgets($socket);
        }

        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket);
        fputs($socket, base64_encode($smtpUser) . "\r\n");
        fgets($socket);
        fputs($socket, base64_encode($smtpPass) . "\r\n");
        $authResp = fgets($socket);
        if (strpos($authResp, '235') === false) { fclose($socket); return 'auth_failed: ' . trim($authResp); }

        fputs($socket, "MAIL FROM:<$mailFrom>\r\n"); fgets($socket);
        fputs($socket, "RCPT TO:<$to_email>\r\n"); $rcptResp = fgets($socket);
        if (strpos($rcptResp, '250') === false && strpos($rcptResp, '251') === false) { fclose($socket); return 'rcpt_failed: ' . trim($rcptResp); }
        fputs($socket, "DATA\r\n"); fgets($socket);

        $headers = "From: LYINGIN <$mailFrom>\r\n";
        $headers .= "To: $to_email\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        fputs($socket, $headers . $html_body . "\r\n.\r\n");
        $dataResp = fgets($socket);
        if (strpos($dataResp, '250') === false) { fclose($socket); return 'data_failed: ' . trim($dataResp); }
        fputs($socket, "QUIT\r\n"); fclose($socket);
        return 'OK';
    }

    // 4) Try PHP mail() if available
    if (function_exists('mail')) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . $mailFrom . "\r\n";
        // Suppress warnings from mail() - it will still return false if it fails
        $sent = @mail($to_email, $subject, $html_body, $headers);
        return $sent ? 'OK' : 'mail_failed';
    }

    return 'no_mail_transport_configured';
}

/**
 * Save the email to server logs (useful for development when SMTP is blocked)
 */
function saveEmailToFile($to_email, $subject, $html_body) {
    $dir = __DIR__ . "/../logs/emails/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $file = $dir . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $to_email) . '.html';
    $content = "<!-- To: $to_email | Subject: $subject | Generated: " . date('c') . " -->\n" . $html_body;
    file_put_contents($file, $content);
    return $file;
}
?>
