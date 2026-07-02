<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ✅ FIX: Use db_connection.php instead of hardcoded localhost
include 'db_connection.php';

// ✅ Use $conn from db_connection.php
$result = $conn->query("SELECT supervisor_pin FROM admins WHERE role = 'admin' LIMIT 1");
$row = $result->fetch_assoc();

echo json_encode([
    'status' => 'success',
    'pin' => $row['supervisor_pin'] ?? ''
]);
?>