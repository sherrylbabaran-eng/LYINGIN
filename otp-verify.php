<?php
header('Content-Type: application/json');

$email = $_POST['email'] ?? '';
$otp   = $_POST['otp'] ?? '';

if (!$email || !$otp) {
    echo json_encode(["status"=>"error","message"=>"Missing data"]);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=lyingin_db;charset=utf8mb4",
        "root",
        "",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        SELECT id, email_verification_expires
        FROM clinics
        WHERE email = ? AND email_verification_token = ? AND email_verified = 0
    ");

    $stmt->execute([$email, $otp]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(["status"=>"error","message"=>"Invalid OTP"]);
        exit;
    }

    if (strtotime($user['email_verification_expires']) < time()) {
        echo json_encode(["status"=>"error","message"=>"OTP expired"]);
        exit;
    }

    $update = $pdo->prepare("
        UPDATE clinics
        SET email_verified = 1,
            email_verification_token = NULL,
            email_verification_expires = NULL
        WHERE id = ?
    ");
    $update->execute([$user['id']]);

    echo json_encode(["status"=>"success"]);
    exit;

} catch (Exception $e) {
    echo json_encode(["status"=>"error","message"=>"Server error"]);
}