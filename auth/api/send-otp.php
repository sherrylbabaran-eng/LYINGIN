<?php
/**
 * OTP Verification System
 * Sends 6-digit OTP to email during registration
 */

header('Content-Type: application/json');

function jsonResponse($status, $message, array $extra = []) {
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));
    exit;
}

function loadEnvConfig() {
    $envFile = null;
    $candidates = [
        realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . '.env',
        realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . '.env',
        __DIR__ . DIRECTORY_SEPARATOR . '.env'
    ];

    foreach ($candidates as $cand) {
        if ($cand && file_exists($cand)) {
            $envFile = $cand;
            break;
        }
    }

    if (!$envFile) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim(trim($val), "'\"");
        putenv("$key=$val");
        $_ENV[$key] = $val;
    }
}

loadEnvConfig();

require_once __DIR__ . '/mail-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse('error', 'Invalid request method');
}

// Get email
$email = trim($_POST['email'] ?? '');
$user_type = trim($_POST['user_type'] ?? 'patient');

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse('error', 'Invalid email address');
}

if (!in_array($user_type, ['patient', 'clinic', 'admin'], true)) {
    $user_type = 'patient';
}

session_start();

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$_SESSION['otp_' . md5($email)] = [
    'otp' => $otp,
    'expires' => $otp_expires,
    'email' => $email,
    'user_type' => $user_type
];

// Helper function to read all SMTP response lines
function readSMTPResponse($socket) {
    $response = '';
    while ($line = fgets($socket, 1024)) {
        $response .= $line;
        // Stop reading if line doesn't have hyphen (continuation)
        if (substr($line, 3, 1) !== '-') {
            break;
        }
    }
    return trim($response);
}

function buildOtpEmailHtml($otp) {
    $brandName = getenv('MAIL_FROM_NAME') ?: 'LYINGIN';
    return "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { text-align: center; margin-bottom: 30px; }
                .logo { font-size: 24px; font-weight: bold; color: #007bff; }
                .content { text-align: center; }
                .otp-code { font-size: 36px; font-weight: bold; color: #007bff; letter-spacing: 5px; margin: 20px 0; padding: 20px; background-color: #f0f0f0; border-radius: 5px; }
                .expires { font-size: 12px; color: #999; margin-top: 20px; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #999; border-top: 1px solid #ddd; padding-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>LYINGIN</div>
                    <h2>Email Verification</h2>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>Your One-Time Password (OTP) for email verification is:</p>
                    <div class='otp-code'>" . $otp . "</div>
                    <p>This code will expire in 10 minutes.</p>
                    <p>If you did not request this verification, please ignore this email.</p>
                    <div class='expires'>This OTP is valid for 10 minutes only.</div>
                </div>
                <div class='footer'>
                    <p>&copy; 2024 {$brandName}. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
}

// Send OTP via SMTP (Gmail, Mailtrap, or custom SMTP)
function sendOTPViaSMTP($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $to_email, $otp) {
    try {
        $fromName = getenv('MAIL_FROM_NAME') ?: 'LYINGIN';
        // Determine if SSL or TLS
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
            error_log("SMTP Connection Error: $errstr (Code: $errno) - Host: $smtp_host:$smtp_port");
            return false;
        }
        
        // Read initial response
        readSMTPResponse($socket);
        
        // Send EHLO
        fwrite($socket, "EHLO smtp.example.com\r\n");
        readSMTPResponse($socket);
        
        // Start TLS if needed (for port 587)
        if ($use_tls && $smtp_port == 587) {
            fwrite($socket, "STARTTLS\r\n");
            $response = readSMTPResponse($socket);
            if (strpos($response, '220') === false) {
                error_log("STARTTLS failed: $response");
                fclose($socket);
                return false;
            }
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fwrite($socket, "EHLO smtp.example.com\r\n");
            readSMTPResponse($socket);
        }
        
        // Send AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = readSMTPResponse($socket);
        
        if (strpos($response, '334') === false) {
            error_log("SMTP AUTH prompt error: $response");
            fclose($socket);
            return false;
        }
        
        // Send username (base64 encoded)
        fwrite($socket, base64_encode($smtp_user) . "\r\n");
        $response = readSMTPResponse($socket);

        
        if (strpos($response, '334') === false) {
            error_log("SMTP username prompt error: $response");
            fclose($socket);
            return false;
        }
        
        // Send password (base64 encoded)
        fwrite($socket, base64_encode($smtp_pass) . "\r\n");
        $response = readSMTPResponse($socket);
        
        // Check if auth was successful
        if (strpos($response, '235') === false) {
            error_log("SMTP AUTH Failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send MAIL FROM
        fwrite($socket, "MAIL FROM:<" . $smtp_user . ">\r\n");
        readSMTPResponse($socket);
        
        // Send RCPT TO
        fwrite($socket, "RCPT TO:<" . $to_email . ">\r\n");
        readSMTPResponse($socket);
        
        // Send DATA
        fwrite($socket, "DATA\r\n");
        readSMTPResponse($socket);
        
        // Prepare email message
        $message = "From: " . $fromName . " <" . $smtp_user . ">\r\n";
        $message .= "To: " . $to_email . "\r\n";
        $message .= "Subject: Email Verification - OTP Code\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        $html_body = buildOtpEmailHtml($otp);
        
        $message .= $html_body . "\r\n.\r\n";
        
        // Send message
        fwrite($socket, $message);
        readSMTPResponse($socket);
        
        // Send QUIT
        fwrite($socket, "QUIT\r\n");
        readSMTPResponse($socket);
        
        fclose($socket);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP Exception: " . $e->getMessage());
        return false;
    }
}

// Get SMTP credentials from .env
$smtp_host = getenv('MAIL_SMTP_HOST') ?: 'smtp.gmail.com';
$smtp_port = getenv('MAIL_SMTP_PORT') ?: 587;
$smtp_user = getenv('MAIL_USERNAME') ?: '';
$smtp_pass = getenv('MAIL_PASSWORD') ?: '';

if (!$smtp_user || !$smtp_pass) {
    jsonResponse('error', 'Email service not configured. Please contact support.');
}

// Send OTP
$subject = 'Email Verification - OTP Code';
$html_body = buildOtpEmailHtml($otp);
$phpmailer_error = null;

if (sendMailWithPHPMailer($email, $subject, $html_body, $phpmailer_error)) {
    jsonResponse('success', 'OTP sent to your email. Valid for 10 minutes.', ['email' => $email]);
}

if ($phpmailer_error) {
    error_log('PHPMailer OTP send failed: ' . $phpmailer_error);
}

if (sendOTPViaSMTP($smtp_host, $smtp_port, $smtp_user, $smtp_pass, $email, $otp)) {
    jsonResponse('success', 'OTP sent to your email. Valid for 10 minutes.', ['email' => $email]);
} else {
    jsonResponse('error', 'Failed to send OTP. Please try again.');
}
?>
