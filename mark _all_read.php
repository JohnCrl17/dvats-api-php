<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

ini_set('display_errors', 0);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "dvats_db");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Connection failed"
    ]);
    exit();
}

$badge_number = isset($_GET['badge_number']) ? $_GET['badge_number'] : null;

if ($badge_number) {

    $badge_number = $conn->real_escape_string($badge_number);

    $sql = "UPDATE notifications 
            SET is_read = 1 
            WHERE badge_number='$badge_number' 
            AND is_read = 0";

    if ($conn->query($sql)) {
        echo json_encode([
            "status" => "success",
            "message" => "Notifications marked as read"
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $conn->error
        ]);
    }

} else {
    echo json_encode([
        "status" => "error",
        "message" => "No badge number provided"
    ]);
}

$conn->close();
?>