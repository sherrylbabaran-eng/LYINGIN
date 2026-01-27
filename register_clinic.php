<?php
// Database configuration
$servername = "localhost";
$db_username = "root"; // replace with your DB username
$db_password = "";     // replace with your DB password
$dbname = "lyingin_db";

// Create connection
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $clinic_name    = trim($_POST['clinic_name']);
    $license_number = trim($_POST['license_number']);
    $address        = trim($_POST['address']);
    $email          = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $admin_name     = trim($_POST['admin_name']);
    $username       = trim($_POST['username']);
    $password       = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $id_type        = trim($_POST['id_type']);
    $id_number      = trim($_POST['id_number']);

    // Basic validation
    if(empty($clinic_name) || empty($license_number) || empty($address) || empty($email) || empty($contact_number) || empty($admin_name) || empty($username) || empty($password) || empty($id_type) || empty($id_number)) {
        echo json_encode(["status" => "error", "message" => "All fields are required."]);
        exit;
    }

    // Handle uploaded ID file
    $id_file_path = null;
    if(isset($_FILES['id_file']) && $_FILES['id_file']['error'] === 0){
        $targetDir = "uploads/";
        if(!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $fileName = basename($_FILES['id_file']['name']);
        $id_file_path = $targetDir . time() . "_" . $fileName;
        if(!move_uploaded_file($_FILES['id_file']['tmp_name'], $id_file_path)){
            echo json_encode(["status" => "error", "message" => "Failed to upload ID file."]);
            exit;
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO clinics (clinic_name, license_number, address, email, contact_number, admin_name, username, password, id_type, id_number, id_file) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssss", $clinic_name, $license_number, $address, $email, $contact_number, $admin_name, $username, $password, $id_type, $id_number, $id_file_path);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Clinic registered successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
