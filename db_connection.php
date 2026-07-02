<?php
// Kunin ang credentials mula sa Render Environment Variables (na-save na natin kanina)
$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$dbname = getenv('DB_NAME');
$port = getenv('DB_PORT');

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli($host, $user, $pass, $dbname, (int)$port);

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