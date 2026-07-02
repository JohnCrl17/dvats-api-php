<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("ngrok-skip-browser-warning: true");

// Palitan ito base sa db_connection file mo o i-copy ang connection logic mo
$conn = new mysqli("localhost", "root", "", "dvats_db");

$id = isset($_GET['id']) ? $_GET['id'] : null;

if ($id) {
    $id = intval($id);
    // Siguraduhin na 'notifications' ang table name at 'is_read' ang column
    $sql = "UPDATE notifications SET is_read = 1 WHERE id = $id";

    if ($conn->query($sql)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "No ID provided"]);
}
$conn->close();
?>