<?php
header('Content-Type: application/json');
include "db_connection.php";

try {

    $data = json_decode(file_get_contents("php://input"), true);

    $license = $data['license_no'] ?? '';
    $otp = $data['otp'] ?? '';
    $newpass = $data['new_password'] ?? '';

    if (!$license || !$otp || !$newpass) {
        echo json_encode(["success"=>false,"message"=>"Missing fields"]);
        exit;
    }

    // GET USER
    $stmt = $conn->prepare("SELECT otp_code, otp_expiry FROM clients WHERE license_no=?");
    $stmt->bind_param("s", $license);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["success"=>false,"message"=>"User not found"]);
        exit;
    }

    $user = $result->fetch_assoc();

    // VERIFY OTP
    if ($user['otp_code'] != $otp) {
        echo json_encode(["success"=>false,"message"=>"Invalid OTP"]);
        exit;
    }

    // UPDATE PASSWORD
    $hashed = password_hash($newpass, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("UPDATE clients SET password=?, otp_code=NULL WHERE license_no=?");
    $stmt->bind_param("ss", $hashed, $license);
    $stmt->execute();

    echo json_encode([
        "success" => true,
        "message" => "Password updated successfully"
    ]);

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}