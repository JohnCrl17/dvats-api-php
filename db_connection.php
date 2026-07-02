<?php
// Railway Credentials
$host = "thomas.proxy.rlwy.net";
$user = "root";
$pass = "ILGYnNyjWRamzJVQyIKAFERnAyEAdueb"; 
$dbname = "railway"; // Ito ang pangalan ng database sa Railway
$port = 59970; // Ito ang port na binigay sa dashboard mo

mysqli_report(MYSQLI_REPORT_OFF);

// Connection gamit ang Port
$conn = new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    http_response_code(500);

    echo json_encode([
        "status" => "error",
        "message" => "Database Connection Failed",
        "debug" => $conn->connect_error
    ]);
    exit;
}

$conn->set_charset("utf8mb4");
?>