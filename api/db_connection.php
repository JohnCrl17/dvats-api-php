<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "lto_system";

mysqli_report(MYSQLI_REPORT_OFF); // IMPORTANT: disable strict crash

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Database Connection Failed",
        "debug" => $conn->connect_error
    ]);
    exit; // 🔥 CRITICAL
}

$conn->set_charset("utf8mb4");
?>