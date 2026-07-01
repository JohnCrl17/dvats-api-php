<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // ✅ dvats_db — dito naka-save ang enforcers mo
    $conn = new mysqli("localhost", "root", "", "lto_system");

    if ($conn->connect_error) {
        throw new Exception("Database Connection Failed");
    }

    // ✅ Handle POST — update status
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id     = $_POST['id']     ?? '';
        $status = $_POST['status'] ?? '';

        if ($action === 'update_status' && $id && $status) {
            $stmt = $conn->prepare("UPDATE enforcers SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            echo $stmt->execute()
                ? json_encode(["success" => true])
                : json_encode(["success" => false, "message" => $conn->error]);
        } else {
            echo json_encode(["success" => false, "message" => "Invalid action"]);
        }
        exit;
    }

    // ✅ GET — ibalik lahat ng enforcers
    // Field names ay exact — walang alias para mag-match sa enforcers.js
    $sql = "SELECT id, full_name, badge_number, email, unit, status 
            FROM enforcers 
            ORDER BY id DESC";

    $result    = $conn->query($sql);
    $enforcers = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $enforcers[] = $row;
        }
    }

    echo json_encode($enforcers);

} catch (Exception $e) {
    echo json_encode([]);
}

if (isset($conn)) $conn->close();
?>