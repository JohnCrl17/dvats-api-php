<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$conn = new mysqli("localhost", "root", "", "lto_system");

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed']);
    exit;
}

$result = $conn->query("SELECT supervisor_pin FROM admins WHERE role = 'admin' LIMIT 1");
$row = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'pin' => $row['supervisor_pin'] ?? ''
]);