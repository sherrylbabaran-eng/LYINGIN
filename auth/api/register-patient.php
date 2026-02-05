<?php
// In production hide display errors to avoid sending HTML error pages to clients
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Convert warnings/notices into exceptions so we can return JSON errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');

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

function validate_id($type, $value, &$errMsg = '') {
    switch ($type) {
        case 'passport':
            if (!preg_match('/^[A-Z0-9]{6,9}$/i', $value)) {
                $errMsg = 'Passport should be 6-9 letters or numbers.';
                return false;
            }
            return true;
        case 'drivers':
            if (!preg_match('/^[A-Z0-9\-]{5,20}$/i', $value)) {
                $errMsg = "Driver's License should be 5-20 characters (letters, numbers, dashes).";
                return false;
            }
            return true;
        case 'national':
            // Accept 10-16 digits to cover various national/legacy formats
            if (!preg_match('/^\d{10,16}$/', $value)) {
                $errMsg = 'National ID should be 10 to 16 digits.';
                return false;
            }
            return true;
        case 'philhealth':
            if (!preg_match('/^\d{12,14}$/', $value)) {
                $errMsg = 'PhilHealth ID should be 12 to 14 digits.';
                return false;
            }
            return true;
        case 'sss':
            if (!preg_match('/^\d{10,12}$/', $value)) {
                $errMsg = 'SSS ID should be 10 to 12 digits.';
                return false;
            }
            return true;
        default:
            if (!preg_match('/^[A-Z0-9\- ]{5,30}$/i', $value)) {
                $errMsg = 'Invalid ID format.';
                return false;
            }
            return true;
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

// Normalize and validate ID server-side
$normalized_id = normalize_id($id_type, $id_number);
$errMsg = '';
if (!validate_id($id_type, $normalized_id, $errMsg)) {
    echo json_encode(["status"=>"error","message"=>"Invalid ID: $errMsg"]);
    exit;
}
// Replace with canonical normalized value to store in DB
$id_number = $normalized_id;

// --- Handle file upload ---
if (!isset($_FILES['idFile']) || $_FILES['idFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(["status"=>"error","message"=>"Please upload a valid ID file."]);
    exit;
}

$allowedTypes = ['image/jpeg','image/png','application/pdf'];
$fileType = mime_content_type($_FILES['idFile']['tmp_name']);

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(["status"=>"error","message"=>"Invalid file type. Only JPG, PNG, PDF allowed."]);
    exit;
}

$targetDir = __DIR__ . "/../../uploads/patients/";
if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

$fileName = uniqid("id_", true) . "_" . basename($_FILES['idFile']['name']);
$id_file_path = $targetDir . $fileName;

if (!move_uploaded_file($_FILES['idFile']['tmp_name'], $id_file_path)) {
    echo json_encode(["status"=>"error","message"=>"Failed to upload ID file."]);
    exit;
}

// --- Insert into DB ---
// Only mark face_verified if client explicitly confirmed face verification; defaults to 0
$face_verified = (isset($_POST['face_verified']) && $_POST['face_verified'] == '1') ? 1 : 0;
$email_verified = 0; // Not verified until email confirmation

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
    // Generate verification token
    $verification_token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $verification_token);
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Update user with verification token
    $update_stmt = $conn->prepare("UPDATE RegPatient SET email_verification_token = ?, email_verification_expires = ? WHERE email = ?");
    if (!$update_stmt) {
        error_log('DB prepare failed (update token): ' . $conn->error);
        // We consider this non-fatal for the user; return success but log for investigation
        echo json_encode(["status"=>"success","message"=>"Registered successfully. Failed to set verification token; contact support."]); exit;
    }
    $update_stmt->bind_param("sss", $token_hash, $expires_at, $email);
    
    if ($update_stmt->execute()) {
        // Send verification email
        $verification_link = "http://yoursite.com/auth/verify-email.html?token=" . $verification_token . "&type=patient";
        
        $subject = "Email Verification - LYINGIN Healthcare";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 8px; }
                .header { background-color: #2c3e50; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { padding: 20px; }
                .button { display: inline-block; background-color: #3498db; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
                .footer { background-color: #ecf0f1; padding: 10px; text-align: center; font-size: 12px; color: #7f8c8d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Email Verification</h2>
                </div>
                <div class='content'>
                    <p>Welcome, $first_name $last_name!</p>
                    <p>Thank you for registering with LYINGIN Healthcare Management System.</p>
                    <p>Please verify your email address by clicking the button below:</p>
                    <a href='" . htmlspecialchars($verification_link) . "' class='button'>Verify Email</a>
                    <p style='margin-top: 20px; color: #7f8c8d; font-size: 14px;'>
                        If you didn't register, please ignore this email.
                    </p>
                    <p style='color: #7f8c8d; font-size: 12px;'>
                        This link expires in 24 hours.
                    </p>
                </div>
                <div class='footer'>
                    <p>LYINGIN - Healthcare Management System</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: tandicoalessandranicole@gmail.com" . "\r\n";
        
        // Try to send verification email (SendGrid preferred, then SMTP, then PHP mail)
        $sendRes = sendVerificationEmail($email, $subject, $message);
        if ($sendRes === 'OK') {
            echo json_encode([
                "status" => "success",
                "message" => "Patient registered successfully! A verification email has been sent to $email. Please verify your email to complete registration.",
                "redirect" => "/auth/verify-pending.html"
            ]);
        } else {
            // Email failed — save a copy to server logs so support can retry/deliver manually
            $savedPath = saveEmailToFile($email, $subject, $message);
            error_log('Email delivery failed: ' . $sendRes . ' — saved to: ' . $savedPath);

            echo json_encode([
                "status" => "success",
                "message" => "Patient registered successfully! However, we couldn't send the verification email. A copy was saved on the server for manual delivery; please check your email or contact support.",
                "redirect" => "/auth/verify-pending.html"
            ]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Registration failed. Please try again."]);
    }
    
    $update_stmt->close();
} else {
    echo json_encode(["status"=>"error","message"=>"DB Error: ".$stmt->error]);
}

$stmt->close();
$conn->close();
} catch (Throwable $e) {
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

    // 2) Try SMTP if credentials provided
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

    // 3) Try PHP mail() if available
    if (function_exists('mail')) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: " . $mailFrom . "\r\n";
        $sent = mail($to_email, $subject, $html_body, $headers);
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
