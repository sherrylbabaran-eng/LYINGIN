<?php
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Validate CSRF token
validateCSRF();

// Load .env configuration
$envFile = null;
$candidates = [
    realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',
    realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',
    __DIR__ . DIRECTORY_SEPARATOR . '.env'
];
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
        $val = trim($val, "'\"");
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}

// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$email = sanitizeEmail($_POST['email'] ?? '');

if (!validateEmail($email)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    $conn->close();
    exit;
}

// Check if patient account exists
$stmt = $conn->prepare("SELECT id FROM RegPatient WHERE email = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows === 0) {
    // Do not leak account existence
    echo json_encode([
        "status" => "success",
        "message" => "If your account exists, a reset link has been sent to your email."
    ]);
    $conn->close();
    exit;
}

// Create reset token
$token = bin2hex(random_bytes(32));
$token_hash = hash('sha256', $token);
// Use database time for expiry to avoid PHP/MySQL timezone mismatch
$expires_at = null;

// Clear existing tokens for this email
$cleanup = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
if ($cleanup) {
    $cleanup->bind_param("s", $email);
    $cleanup->execute();
    $cleanup->close();
}

$stmt = $conn->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error"]);
    $conn->close();
    exit;
}
$stmt->bind_param("ss", $email, $token_hash);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Failed to create reset token"]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// Build reset link
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$reset_link = $scheme . '://' . $host . $basePath . '/reset-password.html?token=' . urlencode($token);

// SMTP helper
function readSMTPResponse($socket) {
    $response = '';
    while ($line = fgets($socket, 1024)) {
        $response .= $line;
        if (substr($line, 3, 1) !== '-') {
            break;
        }
    }
    return trim($response);
}

function sendResetEmail($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $to_email, $reset_link) {
    try {
        $fromName = getenv('MAIL_FROM_NAME') ?: 'LYINGIN';
        $secure = getenv('MAIL_SMTP_SECURE') ?: 'tls';
        $protocol = ($secure === 'ssl') ? 'ssl://' : 'tcp://';
        $use_tls = ($secure === 'tls');

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);

        $socket = @stream_socket_client($protocol . $smtp_host . ':' . $smtp_port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr (Code: $errno)");
            return false;
        }

        readSMTPResponse($socket);
        fwrite($socket, "EHLO smtp.example.com\r\n");
        readSMTPResponse($socket);

        if ($use_tls && (int)$smtp_port === 587) {
            fwrite($socket, "STARTTLS\r\n");
            $response = readSMTPResponse($socket);
            if (strpos($response, '220') === false) {
                fclose($socket);
                return false;
            }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO smtp.example.com\r\n");
            readSMTPResponse($socket);
        }

        fwrite($socket, "AUTH LOGIN\r\n");
        $response = readSMTPResponse($socket);
        if (strpos($response, '334') === false) {
            fclose($socket);
            return false;
        }

        fwrite($socket, base64_encode($smtp_user) . "\r\n");
        $response = readSMTPResponse($socket);
        if (strpos($response, '334') === false) {
            fclose($socket);
            return false;
        }

        fwrite($socket, base64_encode($smtp_pass) . "\r\n");
        $response = readSMTPResponse($socket);
        if (strpos($response, '235') === false) {
            fclose($socket);
            return false;
        }

        fwrite($socket, "MAIL FROM:<" . $smtp_user . ">\r\n");
        readSMTPResponse($socket);

        fwrite($socket, "RCPT TO:<" . $to_email . ">\r\n");
        readSMTPResponse($socket);

        fwrite($socket, "DATA\r\n");
        readSMTPResponse($socket);

        $message = "From: " . $fromName . " <" . $smtp_user . ">\r\n";
        $message .= "To: " . $to_email . "\r\n";
        $message .= "Subject: Password Reset - LYINGIN\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        $html_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 30px; border-radius: 8px; }
                .button { display: inline-block; background-color: #0d6efd; color: white; padding: 12px 18px; text-decoration: none; border-radius: 4px; }
                .muted { color: #777; font-size: 12px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>Password Reset</h2>
                <p>We received a request to reset your password.</p>
                <p><a class='button' href='" . htmlspecialchars($reset_link) . "'>Reset Password</a></p>
                <p class='muted'>This link expires in 30 minutes. If you did not request this, you can ignore this email.</p>
            </div>
        </body>
        </html>
        ";

        $message .= $html_body . "\r\n.\r\n";
        fwrite($socket, $message);
        readSMTPResponse($socket);

        fwrite($socket, "QUIT\r\n");
        readSMTPResponse($socket);
        fclose($socket);
        return true;

    } catch (Exception $e) {
        error_log('SMTP Exception: ' . $e->getMessage());
        return false;
    }
}

$smtp_host = getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com';
$smtp_port = getenv('MAIL_SMTP_PORT') ?: 587;
$smtp_user = getenv('MAIL_USERNAME') ?: '';
$smtp_pass = getenv('MAIL_PASSWORD') ?: '';

if (!$smtp_user || !$smtp_pass) {
    echo json_encode([
        "status" => "error",
        "message" => "Email service not configured. Please contact support."
    ]);
    $conn->close();
    exit;
}

if (sendResetEmail($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $email, $reset_link)) {
    echo json_encode([
        "status" => "success",
        "message" => "If your account exists, a reset link has been sent to your email."
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to send reset email. Please try again later."
    ]);
}

$conn->close();
?>