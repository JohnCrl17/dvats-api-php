<?php
header("Access-Control-Allow-Origin: *");

// 2. Payagan ang JSON content at ang makulit na ngrok header
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// 3. Payagan ang POST method
header("Access-Control-Allow-Methods: POST, OPTIONS");

// 4. Importante: Sagutin ang "OPTIONS" request (Preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

// Palitan ang connection details kung iba ang settings mo
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dvats_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed"]));
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->id)) {
    $id = $data->id;
    
    // Prepare statement para iwas SQL Injection
    $stmt = $conn->prepare("DELETE FROM enforcers WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Officer deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete record."]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "No ID provided."]);
}

$conn->close();
?>