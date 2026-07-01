<?php
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

// Kunin ang ID mula sa URL (?id=17)
$id = $_GET['id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $id) {
    $conn->begin_transaction();

    try {
        // 1. Kunin muna ang pangalan ng client bago burahin para mabura rin ang violations
        $res = $conn->query("SELECT fullname FROM lto_system.clients WHERE client_id = '$id'");
        $client = $res->fetch_assoc();
        
        if ($client) {
            $name = $client['fullname'];
            // Burahin ang violations base sa pangalan
            $conn->query("DELETE FROM lto_system.violations WHERE driver_name = '$name'");
        }

        // 2. Burahin ang mismong client
        $stmt = $conn->prepare("DELETE FROM lto_system.clients WHERE client_id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Deleted successfully."]);
        } else {
            throw new Exception("Delete failed: " . $conn->error);
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}

$conn->close();
?>