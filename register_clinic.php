<?php
session_start();
header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=lyingin_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    echo json_encode(["status"=>"error","message"=>"Database connection failed"]);
    exit;
}

/* ================= REQUIRED ================= */

$required = [
    'clinic_name','license_number','address','email','contact_number',
    'admin_name','username','password','confirm_password',
    'id_type','id_number','latitude','longitude'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["status"=>"error","message"=>"All fields are required"]);
        exit;
    }
}

/* ================= SANITIZE ================= */

$clinic_name    = trim($_POST['clinic_name']);
$license_number = strtoupper(trim($_POST['license_number']));
$address        = trim($_POST['address']);
$email          = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$contact        = trim($_POST['contact_number']);
$admin_name     = trim($_POST['admin_name']);
$username       = trim($_POST['username']);
$password       = $_POST['password'];
$confirm        = $_POST['confirm_password'];
$latitude       = floatval($_POST['latitude']);
$longitude      = floatval($_POST['longitude']);
$id_type        = $_POST['id_type'];
$id_number      = trim($_POST['id_number']);

if (!$email) {
    echo json_encode(["status"=>"error","message"=>"Invalid email"]);
    exit;
}

if ($password !== $confirm) {
    echo json_encode(["status"=>"error","message"=>"Passwords do not match"]);
    exit;
}

/* ================= DUPLICATE CHECK ================= */

$stmt = $pdo->prepare("SELECT id FROM clinics WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
if ($stmt->fetch()) {
    echo json_encode(["status"=>"error","message"=>"Account already exists"]);
    exit;
}

/* ================= FILE UPLOAD ================= */

if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode(["status"=>"error","message"=>"ID upload failed"]);
    exit;
}

$uploadDir = __DIR__ . "/private_uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION);
$fileName = bin2hex(random_bytes(16)) . "." . $ext;

move_uploaded_file($_FILES['id_file']['tmp_name'], $uploadDir . $fileName);

/* ================= PASSWORD HASH ================= */

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

/* ================= OTP ================= */

$otp = rand(100000, 999999);
$otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

/* ================= INSERT ================= */

$stmt = $pdo->prepare("
INSERT INTO clinics (
    clinic_name, license_number, address, email, contact_number,
    admin_name, username, password,
    id_type, id_number, id_file,
    latitude, longitude,
    email_verification_token, email_verification_expires, email_verified
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)
");

$stmt->execute([
    $clinic_name,
    $license_number,
    $address,
    $email,
    $contact,
    $admin_name,
    $username,
    $hashedPassword,
    $id_type,
    $id_number,
    $fileName,
    $latitude,
    $longitude,
    $otp,
    $otpExpires
]);

/* ================= SEND OTP ================= */

mail(
    $email,
    "Your Clinic Registration OTP",
    "Your OTP is: $otp\nIt expires in 10 minutes.",
    "From: no-reply@localhost"
);

echo json_encode([
    "status"=>"success",
    "message"=>"Registration successful. OTP sent.",
    "requires_otp"=>true
]);
exit;