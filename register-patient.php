<?php
// Debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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
    $password   = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
    $id_type    = trim($_POST['id_type'] ?? '');
    $id_number  = trim($_POST['id_number'] ?? '');

    // --- Validate required fields ---
    if (!$first_name || !$last_name || !$birthdate || !$gender || !$email || !$contact_no || !$address || !$username || !$password || !$id_type || !$id_number) {
        echo json_encode(["status"=>"error","message"=>"All fields are required."]);
        exit;
    }

    // --- Handle file upload ---
    if (!isset($_FILES['idFile']) || $_FILES['idFile']['error'] !== 0) {
        echo json_encode(["status"=>"error","message"=>"Please upload a valid ID file."]);
        exit;
    }

    $allowedTypes = ['image/jpeg','image/png','application/pdf'];
    $fileType = $_FILES['idFile']['type'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(["status"=>"error","message"=>"Invalid file type. Only JPG, PNG, PDF allowed."]);
        exit;
    }

    $targetDir = "uploads/patients/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);

    $fileName = time() . "_" . basename($_FILES['idFile']['name']);
    $id_file_path = $targetDir . $fileName;

    if (!move_uploaded_file($_FILES['idFile']['tmp_name'], $id_file_path)) {
        echo json_encode(["status"=>"error","message"=>"Failed to upload ID file. Check folder permissions."]);
        exit;
    }

    // --- Insert into DB ---
    $stmt = $conn->prepare("INSERT INTO RegPatient (first_name, last_name, birthdate, gender, email, contact_no, address, username, password, id_type, id_number, id_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $first_name, $last_name, $birthdate, $gender, $email, $contact_no, $address, $username, $password, $id_type, $id_number, $id_file_path);

    if ($stmt->execute()) {
        echo json_encode(["status"=>"success","message"=>"Patient registered successfully!"]);
    } else {
        echo json_encode(["status"=>"error","message"=>"DB Error: ".$stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
