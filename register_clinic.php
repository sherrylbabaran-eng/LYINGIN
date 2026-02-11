<?php
header('Content-Type: application/json');

// ===== DATABASE CONFIG =====
$host = "localhost";
$dbname = "lyingin_db";
$user = "root";
$pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

// ===== REQUIRED FIELDS =====
$required = [
    'clinic_name','license_number','address','email','contact_number',
    'admin_name','username','password','confirm_password',
    'id_type','id_number','latitude','longitude'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit;
    }
}

if ($_POST['password'] !== $_POST['confirm_password']) {
    echo json_encode(["status" => "error", "message" => "Passwords do not match"]);
    exit;
}

// ===== ID TYPE MAP =====
$idTypeMap = [
    'passport'   => 'Passport',
    'drivers'    => "Driver's License",
    'national'   => 'National ID',
    'philhealth' => 'PhilHealth ID',
    'sss'        => 'SSS ID'
];

if (!isset($idTypeMap[$_POST['id_type']])) {
    echo json_encode(["status" => "error", "message" => "Invalid ID type"]);
    exit;
}

$idType = $idTypeMap[$_POST['id_type']];

// ===== DUPLICATE CHECK =====
$check = $pdo->prepare("SELECT id FROM clinics WHERE email = ? OR username = ?");
$check->execute([$_POST['email'], $_POST['username']]);

if ($check->rowCount()) {
    echo json_encode(["status" => "error", "message" => "Email or username already exists"]);
    exit;
}

// ===== FILE UPLOAD =====
$allowed = ['image/jpeg','image/png','application/pdf'];
$maxSize = 5 * 1024 * 1024;

if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode(["status" => "error", "message" => "ID upload failed"]);
    exit;
}

if (!in_array($_FILES['id_file']['type'], $allowed) || $_FILES['id_file']['size'] > $maxSize) {
    echo json_encode(["status" => "error", "message" => "Invalid ID file"]);
    exit;
}

$uploadDir = "../../uploads/ids/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

$fileName = uniqid("id_", true) . "." . pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION);
move_uploaded_file($_FILES['id_file']['tmp_name'], $uploadDir . $fileName);

// ===== HASH PASSWORD =====
$hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

// ===== EMAIL TOKEN =====
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

// ===== INSERT =====
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
    $_POST['clinic_name'],
    $_POST['license_number'],
    $_POST['address'],
    $_POST['email'],
    $_POST['contact_number'],
    $_POST['admin_name'],
    $_POST['username'],
    $hashedPassword,
    $idType,
    $_POST['id_number'],
    $fileName,
    $_POST['latitude'],
    $_POST['longitude'],
    $token,
    $expires
]);

echo json_encode([
    "status" => "success",
    "message" => "Clinic registered successfully. Please verify your email."
]);
