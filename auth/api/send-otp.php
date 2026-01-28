<?php
/**
 * OTP Verification System
 * Sends 6-digit OTP to email during registration
 */

header('Content-Type: application/json');

// Database configuration
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

// Get email
$email = trim($_POST['email'] ?? '');
$user_type = trim($_POST['user_type'] ?? 'patient');

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}

// Generate 6-digit OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Store OTP in session or temporary table
// Using session for now (simpler)
session_start();
$_SESSION['otp_' . md5($email)] = [
    'otp' => $otp,
    'expires' => $otp_expires,
    'email' => $email
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

// Send OTP via Gmail
function sendOTPViaGmail($gmail_sender, $gmail_password, $to_email, $otp) {
    try {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            error_log("SMTP Connection Error: $errstr (Code: $errno)");
            return false;
        }
        
        // Read initial response
        readSMTPResponse($socket);
        
        // Send EHLO
        fwrite($socket, "EHLO smtp.gmail.com\r\n");
        readSMTPResponse($socket);
        
        // Send AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = readSMTPResponse($socket);
        
        if (strpos($response, '334') === false) {
            error_log("SMTP AUTH prompt error: $response");
            fclose($socket);
            return false;
        }
        
        // Send username (base64 encoded)
        fwrite($socket, base64_encode($gmail_sender) . "\r\n");
        $response = readSMTPResponse($socket);
        
        if (strpos($response, '334') === false) {
            error_log("SMTP username prompt error: $response");
            fclose($socket);
            return false;
        }
        
        // Send password (base64 encoded)
        fwrite($socket, base64_encode($gmail_password) . "\r\n");
        $response = readSMTPResponse($socket);
        
        // Check if auth was successful
        if (strpos($response, '235') === false) {
            error_log("SMTP AUTH Failed: $response");
            fclose($socket);
            return false;
        }
        
        // Send MAIL FROM
        fwrite($socket, "MAIL FROM:<" . $gmail_sender . ">\r\n");
        readSMTPResponse($socket);
        
        // Send RCPT TO
        fwrite($socket, "RCPT TO:<" . $to_email . ">\r\n");
        readSMTPResponse($socket);
        
        // Send DATA
        fwrite($socket, "DATA\r\n");
        readSMTPResponse($socket);
        
        // Prepare email message
        $message = "From: LYINGIN Healthcare <" . $gmail_sender . ">\r\n";
        $message .= "To: " . $to_email . "\r\n";
        $message .= "Subject: Email Verification - OTP Code\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        
        $html_body = "
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
                    <p>&copy; 2024 LYINGIN Healthcare. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
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

// Gmail credentials
$gmail_sender = "tandicoalessandranicole@gmail.com";
$gmail_password = "zbjs naxg scid wzzi";

// Send OTP
if (sendOTPViaGmail($gmail_sender, $gmail_password, $email, $otp)) {
    echo json_encode([
        "status" => "success",
        "message" => "OTP sent to your email. Valid for 10 minutes.",
        "email" => $email
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to send OTP. Please try again."
    ]);
}

$conn->close();
?>
