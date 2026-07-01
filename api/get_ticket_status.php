<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning");
include 'db_connection.php';

$id = $_GET['id'] ?? null;

if($id) {
    $stmt = $conn->prepare("SELECT status FROM apprehensions WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $res = $result->fetch_assoc();

    if ($res) {
        echo json_encode(["status" => "success", "data" => ["status" => $res['status']]]);
    } else {
        // Default to UNPAID if record is missing but request is valid
        echo json_encode(["status" => "success", "data" => ["status" => "UNPAID"]]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing ticket id"]);
}

$conn->close();
?>