<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

header("Content-Type: application/json; charset=UTF-8");

// DITO NA TAYO KUKUHA NG CONNECTION MULA SA db_connection.php
require_once 'db_connection.php'; 

// INPUT
$license  = isset($_POST['license_no']) ? trim($_POST['license_no']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($license) || empty($password)) {
    echo json_encode([
        "success" => false,
        "message" => "Please fill all fields"
    ]);
    exit();
}

// GET USER (Gamit na natin ang $conn variable galing sa db_connection.php)
$stmt = $conn->prepare("SELECT client_id, fullname, license_no, password, profile_path, qr_image, license_expiry, date_of_birth FROM clients WHERE license_no = ? LIMIT 1");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "SQL ERROR",
        "debug" => $conn->error
    ]);
    exit();
}

$stmt->bind_param("s", $license);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "message" => "License number not found"
    ]);
    exit();
}

$driver = $result->fetch_assoc();

$storedPassword = trim($driver['password']);
$loginSuccess = false;

if (!empty($storedPassword) && password_verify($password, $storedPassword)) {
    $loginSuccess = true;
}
elseif ($password === $storedPassword) {
    $loginSuccess = true;
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $update = $conn->prepare("UPDATE clients SET password=? WHERE client_id=?");
    if ($update) {
        $update->bind_param("si", $newHash, $driver['client_id']);
        $update->execute();
        $update->close();
    }
}

// 🚀 SUCCESS RESPONSE
if ($loginSuccess) {
    echo json_encode([
        "success" => true,
        "driver" => [
            "client_id"      => $driver['client_id'],
            "fullname"       => $driver['fullname'],
            "license_no"     => $driver['license_no'],
            "profile_path"   => $driver['profile_path'],
            "qr_image"       => $driver['qr_image'],
            "date_of_birth"  => $driver['date_of_birth'],
            "license_expiry" => $driver['license_expiry']
        ]
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Incorrect password"
    ]);
}

$stmt->close();
$conn->close();
?>