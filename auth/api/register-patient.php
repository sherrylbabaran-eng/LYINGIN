<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

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

// --- Validate required fields ---
if (
    !$first_name || !$last_name || !$birthdate || !$gender || !$email ||
    !$contact_no || !$address || !$username || !$rawPass || !$id_type || !$id_number
) {
    echo json_encode(["status"=>"error","message"=>"All fields are required."]);
    exit;
}

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
$face_verified = 1;
$email_verified = 0; // Not verified until email confirmation

$stmt = $conn->prepare("
    INSERT INTO RegPatient
    (first_name, last_name, birthdate, gender, email, contact_no, address,
     username, password, id_type, id_number, id_file, face_verified, email_verified)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

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
        
        if (sendEmailViaGmail('tandicoalessandranicole@gmail.com', 'zbjs naxg scid wzzi', $email, $subject, $message)) {
            echo json_encode([
                "status" => "success",
                "message" => "Patient registered successfully! A verification email has been sent to $email. Please verify your email to complete registration.",
                "redirect" => "/auth/verify-pending.html"
            ]);
        } else {
            // Registration successful but email failed
            echo json_encode([
                "status" => "success",
                "message" => "Patient registered successfully! However, we couldn't send the verification email. Please check your email or contact support.",
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

/**
 * Send Email via Gmail SMTP (Real Gmail)
 */
function sendEmailViaGmail($gmail_sender, $gmail_password, $to_email, $subject, $html_body) {
    try {
        $context = stream_context_create([
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);

        $socket = @stream_socket_client('ssl://smtp.gmail.com:465', $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) return false;

        stream_set_timeout($socket, 5);
        fgets($socket);
        fputs($socket, "EHLO localhost\r\n");
        fgets($socket);
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket);
        fputs($socket, base64_encode($gmail_sender) . "\r\n");
        fgets($socket);
        fputs($socket, base64_encode($gmail_password) . "\r\n");
        $response = fgets($socket);

        if (strpos($response, '235') === false) {
            fclose($socket);
            return false;
        }

        fputs($socket, "MAIL FROM:<$gmail_sender>\r\n");
        fgets($socket);
        fputs($socket, "RCPT TO:<$to_email>\r\n");
        fgets($socket);
        fputs($socket, "DATA\r\n");
        fgets($socket);

        $headers = "From: LYINGIN <$gmail_sender>\r\n";
        $headers .= "To: $to_email\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";

        fputs($socket, $headers . $html_body . "\r\n.\r\n");
        fgets($socket);
        fputs($socket, "QUIT\r\n");
        fclose($socket);

        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
