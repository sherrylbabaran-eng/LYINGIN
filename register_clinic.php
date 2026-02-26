<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');

/* ===================== SECURITY HEADERS ===================== */
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

/* ===================== PRODUCTION SETTINGS ===================== */
ini_set('display_errors', 0);
error_reporting(E_ALL);

/* ===================== RATE LIMITING ===================== */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

$_SESSION['rate_limit'] = $_SESSION['rate_limit'] ?? [];
$_SESSION['rate_limit'][] = time();

$_SESSION['rate_limit'] = array_filter(
    $_SESSION['rate_limit'],
    fn($t) => $t > time() - 60
);

if (count($_SESSION['rate_limit']) > 5) {
    echo json_encode(["status"=>"error","message"=>"Too many attempts. Try again later."]);
    exit;
}

/* ===================== CSRF PROTECTION ===================== */
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    echo json_encode(["status"=>"error","message"=>"Invalid request"]);
    exit;
}

/* ===================== DATABASE ===================== */
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
    echo json_encode(["status"=>"error","message"=>"Server error"]);
    exit;
}

/* ===================== REQUIRED FIELDS ===================== */
$required = [
    'clinic_name','license_number','address','email','contact_number',
    'admin_name','username','password','confirm_password',
    'id_type','id_number','latitude','longitude'
];

foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        echo json_encode(["status"=>"error","message"=>"Invalid form submission"]);
        exit;
    }
}

/* ===================== SANITIZATION ===================== */
$clinic_name    = trim($_POST['clinic_name']);
$license_number = strtoupper(trim($_POST['license_number']));
$address        = trim($_POST['address']);
$email          = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
$contact        = trim($_POST['contact_number']);
$admin_name     = trim($_POST['admin_name']);
$username       = trim($_POST['username']);
$password       = $_POST['password'];
$confirm        = $_POST['confirm_password'];
$latitude       = filter_var($_POST['latitude'], FILTER_VALIDATE_FLOAT);
$longitude      = filter_var($_POST['longitude'], FILTER_VALIDATE_FLOAT);
$id_type        = $_POST['id_type'];
$id_number      = trim($_POST['id_number']);

/* ===================== VALIDATIONS ===================== */

// Email
if (!$email) {
    echo json_encode(["status"=>"error","message"=>"Invalid email"]);
    exit;
}

// Contact number
if (!preg_match('/^[0-9\-\+ ]{7,20}$/', $contact)) {
    echo json_encode(["status"=>"error","message"=>"Invalid contact number"]);
    exit;
}

// License format (alphanumeric + dash allowed)
if (!preg_match('/^[A-Z0-9\-]{5,20}$/', $license_number)) {
    echo json_encode(["status"=>"error","message"=>"Invalid license number"]);
    exit;
}

// Username
if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    echo json_encode(["status"=>"error","message"=>"Invalid username"]);
    exit;
}

// Password
if ($password !== $confirm) {
    echo json_encode(["status"=>"error","message"=>"Passwords do not match"]);
    exit;
}

if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $password)) {
    echo json_encode([
        "status"=>"error",
        "message"=>"Password must be 8+ chars with uppercase, lowercase & number"
    ]);
    exit;
}

// Coordinates validation
if ($latitude === false || $longitude === false) {
    echo json_encode(["status"=>"error","message"=>"Invalid coordinates"]);
    exit;
}

// Cavite bounding box enforcement
if ($latitude < 13.9 || $latitude > 14.7 || $longitude < 120.5 || $longitude > 121.5) {
    echo json_encode(["status"=>"error","message"=>"Location must be within Cavite province"]);
    exit;
}

/* ===================== ID VALIDATION ===================== */
$idRules = [
    'passport'   => '/^[A-Z0-9]{6,9}$/i',
    'drivers'    => '/^[A-Z0-9\-]{8,15}$/i',
    'national'   => '/^[0-9]{12}$/',
    'philhealth' => '/^[0-9]{12}$/',
    'sss'        => '/^[0-9]{10}$/'
];

if (!isset($idRules[$id_type]) || !preg_match($idRules[$id_type], $id_number)) {
    echo json_encode(["status"=>"error","message"=>"Invalid ID format"]);
    exit;
}

/* ===================== DUPLICATE CHECK ===================== */
$stmt = $pdo->prepare("
    SELECT id FROM clinics 
    WHERE email = ? 
       OR username = ? 
       OR license_number = ?
       OR (id_type = ? AND id_number = ?)
");
$stmt->execute([$email, $username, $license_number, $id_type, $id_number]);

if ($stmt->fetch()) {
    echo json_encode(["status"=>"error","message"=>"Account already exists"]);
    exit;
}

/* ===================== FILE UPLOAD ===================== */
if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode(["status"=>"error","message"=>"ID upload failed"]);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['id_file']['tmp_name']);

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf'
];

if (!isset($allowed[$mime])) {
    echo json_encode(["status"=>"error","message"=>"Invalid file type"]);
    exit;
}

if ($_FILES['id_file']['size'] > 5 * 1024 * 1024) {
    echo json_encode(["status"=>"error","message"=>"File too large"]);
    exit;
}

$uploadDir = __DIR__ . "/../../private_uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileName = bin2hex(random_bytes(16)) . "." . $allowed[$mime];

if (!move_uploaded_file($_FILES['id_file']['tmp_name'], $uploadDir . $fileName)) {
    echo json_encode(["status"=>"error","message"=>"Upload failed"]);
    exit;
}

/* ===================== PASSWORD HASH ===================== */
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

/* ===================== EMAIL TOKEN ===================== */
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

/* ===================== TRANSACTION SAFE INSERT ===================== */
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
    INSERT INTO clinics (
        clinic_name, license_number, address, email, contact_number,
        admin_name, username, password,
        id_type, id_number, id_file,
        latitude, longitude,
        email_verification_token, email_verification_expires
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
        $token,
        $expires
    ]);

    $pdo->commit();

    echo json_encode([
        "status"=>"success",
        "message"=>"Clinic registered successfully. Please verify your email."
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(["status"=>"error","message"=>"Registration failed"]);
}