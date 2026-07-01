<?php
// I-off ang display ng errors para hindi sumama sa JSON output
error_reporting(0); 
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: ngrok-skip-browser-warning");

include 'db_connection.php'; // Siguraduhin na tama ang path nito

$id = $_GET['id'] ?? null;

if(!$id) {
    echo json_encode(["error" => "No ID provided"]);
    exit;
}

try {
    // Mas maganda kung lto_system.clients ang gagamitin kung hindi default ang DB
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();

    if ($client) {
        // --- THE FIX PARA SA COLUMN MISMATCH ---
        // Kung face_date ang nasa DB, ilipat natin sa face_data para sa JS
        if (isset($client['face_date'])) {
            $client['face_data'] = $client['face_date'];
        } 
        // Siguraduhin na laging may face_data key para hindi mag-error ang JS
        if (!isset($client['face_data'])) {
            $client['face_data'] = null;
        }

        

        echo json_encode($client);
    } else {
        echo json_encode(["error" => "Client not found"]);
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}

$conn->close();
?>