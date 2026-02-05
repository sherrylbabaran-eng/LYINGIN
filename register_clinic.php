<?php
header('Content-Type: application/json');

// ===== DATABASE CONFIG =====
$host = "localhost";
$dbname = "lyingin_db";
$user = "root";
$pass = "";

try {
    $pdo = new PDO(
        "mysql:host=$host;lyingin_db=$lyingin_db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

// ===== BASIC VALIDATION =====
$required = [
    'clinic_name','license_number','address','email','contact_number',
    'admin_name','username','password','confirm_password',
    'id_type','id_number'
];

foreach ($required as $field) {
    if (empty($_POST[$field])) {
        echo json_encode([
            "status" => "error",
            "message" => "Missing required field: $field"
        ]);
        exit;
    }
}

if ($_POST['password'] !== $_POST['confirm_password']) {
    echo json_encode([
        "status" => "error",
        "message" => "Passwords do not match"
    ]);
    exit;
}

// ===== MAP ID TYPE TO ENUM VALUES =====
$idTypeMap = [
    'passport'   => 'Passport',
    'drivers'    => "Driver's License",
    'national'   => 'National ID',
    'philhealth' => 'PhilHealth ID',
    'sss'        => 'SSS ID'
];

if (!isset($idTypeMap[$_POST['id_type']])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid ID type"
    ]);
    exit;
}

$idType = $idTypeMap[$_POST['id_type']];

// ===== CHECK DUPLICATES =====
$check = $pdo->prepare("
    SELECT id FROM clinics 
    WHERE email = :email OR username = :username
");
$check->execute([
    ':email' => $_POST['email'],
    ':username' => $_POST['username']
]);

if ($check->rowCount() > 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Email or username already registered"
    ]);
    exit;
}

// ===== FILE UPLOAD =====
if (!isset($_FILES['id_file']) || $_FILES['id_file']['error'] !== 0) {
    echo json_encode([
        "status" => "error",
        "message" => "ID file upload failed"
    ]);
    exit;
}

$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$maxSize = 5 * 1024 * 1024;

if (
    !in_array($_FILES['id_file']['type'], $allowedTypes) ||
    $_FILES['id_file']['size'] > $maxSize
) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid ID file type or size"
    ]);
    exit;
}

$uploadDir = "../../uploads/ids/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$ext = pathinfo($_FILES['id_file']['name'], PATHINFO_EXTENSION);
$fileName = uniqid("id_", true) . "." . $ext;
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($_FILES['id_file']['tmp_name'], $filePath)) {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save uploaded ID"
    ]);
    exit;
}

// ===== PASSWORD HASH =====
$hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

// ===== EMAIL VERIFICATION TOKEN =====
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

// ===== INSERT INTO DATABASE =====
$sql = "
INSERT INTO clinics (
    clinic_name,
    license_number,
    address,
    email,
    contact_number,
    admin_name,
    username,
    password,
    id_type,
    id_number,
    id_file,
    email_verification_token,
    email_verification_expires
) VALUES (
    :clinic_name,
    :license_number,
    :address,
    :email,
    :contact_number,
    :admin_name,
    :username,
    :password,
    :id_type,
    :id_number,
    :id_file,
    :token,
    :expires
)
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':clinic_name'    => $_POST['clinic_name'],
    ':license_number'=> $_POST['license_number'],
    ':address'        => $_POST['address'],
    ':email'          => $_POST['email'],
    ':contact_number' => $_POST['contact_number'],
    ':admin_name'     => $_POST['admin_name'],
    ':username'       => $_POST['username'],
    ':password'       => $hashedPassword,
    ':id_type'        => $idType,
    ':id_number'      => $_POST['id_number'],
    ':id_file'        => $fileName,
    ':token'          => $token,
    ':expires'        => $expires
]);

echo json_encode([
    "status" => "success",
    "message" => "Clinic registered successfully. Please verify your email."
]);
