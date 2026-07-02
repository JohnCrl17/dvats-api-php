<?php
error_reporting(0);
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

$id = $_GET['id'] ?? 0;
$history = [];

if ($id) {
    // ✅ DIRECT FILTER USING client_id (NO NEED fullname)
    $stmt = $conn->prepare("
        SELECT 
        violation_name, 
        created_at, 
        status
        FROM violations 
        WHERE client_id = ?
        ORDER BY created_at DESC
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
}

echo json_encode($history);
$conn->close();
?>