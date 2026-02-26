<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
    exit;
}

$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "lyingin_db";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$clinic_name = trim($_POST['clinic_name'] ?? '');
$license_number = strtoupper(trim($_POST['license_number'] ?? ''));
$address = trim($_POST['address'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$admin_name = trim($_POST['admin_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$plain_password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$id_type = trim($_POST['id_type'] ?? '');
$id_number = trim($_POST['id_number'] ?? '');

if (
    $clinic_name === '' || $license_number === '' || $address === '' || $email === '' ||
    $contact_number === '' || $admin_name === '' || $username === '' ||
    $plain_password === '' || $confirm_password === '' || $id_type === '' || $id_number === ''
) {
    echo json_encode(["status" => "error", "message" => "All fields are required."]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["status" => "error", "message" => "Invalid email"]);
    exit;
}

if (!preg_match('/^[0-9\-\+ ]{7,20}$/', $contact_number)) {
    echo json_encode(["status" => "error", "message" => "Invalid contact number"]);
    exit;
}

if (!preg_match('/^[A-Z0-9\-]{5,20}$/', $license_number)) {
    echo json_encode(["status" => "error", "message" => "Invalid license number"]);
    exit;
}

if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
    echo json_encode(["status" => "error", "message" => "Invalid username"]);
    exit;
}

if ($plain_password !== $confirm_password) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $plain_password)) {
    echo json_encode(["status" => "error", "message" => "Password must be 8+ chars with uppercase, lowercase & number"]);
    exit;
}

if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    echo json_encode(["status" => "error", "message" => "Invalid clinic map coordinates."]);
    exit;
}

if ($latitude < 13.9 || $latitude > 14.7 || $longitude < 120.5 || $longitude > 121.5) {
    echo json_encode(["status" => "error", "message" => "Location must be within Cavite province"]);
    exit;
}

$idRules = [
    'passport' => '/^[A-Z0-9]{6,9}$/i',
    'drivers' => '/^[A-Z0-9\-]{8,15}$/i',
    'national' => '/^[0-9]{12}$/',
    'philhealth' => '/^[0-9]{12}$/',
    'sss' => '/^[0-9]{10}$/'
];

$idTypeMap = [
    'passport' => 'Passport',
    'drivers' => "Driver's License",
    'national' => 'National ID',
    'philhealth' => 'PhilHealth ID',
    'sss' => 'SSS ID'
];

if (!isset($idRules[$id_type]) || !preg_match($idRules[$id_type], $id_number)) {
    echo json_encode(["status" => "error", "message" => "Invalid ID format"]);
    exit;
}

$db_id_type = $idTypeMap[$id_type];

$dup = $conn->prepare("SELECT id FROM clinics WHERE email = ? OR username = ? OR license_number = ? OR (id_type = ? AND id_number = ?)");
if (!$dup) {
    echo json_encode(["status" => "error", "message" => "Server error"]);
    exit;
}
$dup->bind_param("sssss", $email, $username, $license_number, $db_id_type, $id_number);
$dup->execute();
$dupResult = $dup->get_result();
if ($dupResult && $dupResult->num_rows > 0) {
    $dup->close();
    echo json_encode(["status" => "error", "message" => "Account already exists"]);
    exit;
}
$dup->close();

if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode(["status" => "error", "message" => "ID upload failed"]);
    exit;
}

$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'application/pdf' => 'pdf'
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($_FILES['id_file']['tmp_name']);
if (!isset($allowed[$mime])) {
    echo json_encode(["status" => "error", "message" => "Invalid file type"]);
    exit;
}

if ($_FILES['id_file']['size'] > 5 * 1024 * 1024) {
    echo json_encode(["status" => "error", "message" => "File too large"]);
    exit;
}

$targetDir = __DIR__ . "/../../uploads/clinics/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$storedName = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
$absolutePath = $targetDir . $storedName;
$id_file_path = "uploads/clinics/" . $storedName;

if (!move_uploaded_file($_FILES['id_file']['tmp_name'], $absolutePath)) {
    echo json_encode(["status" => "error", "message" => "Failed to upload ID file."]);
    exit;
}

$password = password_hash($plain_password, PASSWORD_DEFAULT);
$email_verified = 1;

$stmt = $conn->prepare("INSERT INTO clinics (clinic_name, license_number, address, email, contact_number, latitude, longitude, admin_name, username, password, id_type, id_number, id_file, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Server error"]);
    exit;
}

$stmt->bind_param("sssssddssssssi", $clinic_name, $license_number, $address, $email, $contact_number, $latitude, $longitude, $admin_name, $username, $password, $db_id_type, $id_number, $id_file_path, $email_verified);

if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Clinic registered successfully! You can now login.",
        "redirect" => "/auth/login.html"
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Registration failed"]);
}

$stmt->close();
$conn->close();

