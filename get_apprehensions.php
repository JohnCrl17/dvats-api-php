<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

include 'db_connection.php';

$badge = $_GET['badge_number'] ?? '';

if (!$badge) {
    echo json_encode(["status" => "error", "message" => "Badge required"]);
    exit;
}

$sql = "SELECT * FROM dvats_db.apprehensions 
        WHERE badge_number = '$badge'
        ORDER BY created_at DESC LIMIT 5";

$result = $conn->query($sql);

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(["status" => "success", "data" => $data]);