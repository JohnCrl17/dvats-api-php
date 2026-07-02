<?php
header('Content-Type: application/json');

include "db_connection.php";

require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $license = $data['license_no'] ?? '';
    $email = $data['email'] ?? '';

    if (!$license || !$email) {
        echo json_encode(["success" => false, "message" => "Missing fields"]);
        exit;
    }

    // 🔍 CHECK USER
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE license_no=? AND email=?");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "SQL ERROR",
            "debug" => $conn->error
        ]);
        exit;
    }

    $stmt->bind_param("ss", $license, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    $user = $result->fetch_assoc();
    $client_id = $user['client_id'];

    // 🔥 GENERATE OTP
    $otp = rand(100000, 999999);

    // OPTIONAL: save OTP in DB (recommended for security)
    $stmt = $conn->prepare("UPDATE clients SET otp_code=?, otp_expiry=DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE client_id=?");

    if (!$stmt) {
        echo json_encode([
            "success" => false,
            "message" => "SQL PREPARE FAILED",
            "debug" => $conn->error
        ]);
        exit;
    }
    $stmt->bind_param("is", $otp, $client_id);
    $stmt->execute();

    // 📧 SEND EMAIL
    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'dvats.official@gmail.com';
    $mail->Password = 'cdenlebapktzhmbx';// IMPORTANT
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('dvats.official@gmail.com', 'DVATS System');
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = "DVATS Password Reset OTP";

    $mail->Body = "
        <div style='font-family:Arial'>
            <h2>DVATS OTP Code</h2>
            <p>Your OTP is:</p>
            <h1 style='color:#2563eb'>$otp</h1>
            <p>This code will expire in 5 minutes.</p>
        </div>
    ";

    $mail->send();

    echo json_encode([
        "success" => true,
        "message" => "OTP sent to email"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}