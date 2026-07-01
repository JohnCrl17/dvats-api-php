<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

// ✅ FIX — Gamitin ang MAX(id) para makuha ang pinakabago sa bawat ticket_no
// Yung mga NULL ticket_no ay lalabas pa rin individually (hindi nako-collapse)
$sql = "SELECT * FROM lto_system.violations 
        WHERE id IN (
            SELECT MAX(id) 
            FROM lto_system.violations 
            GROUP BY COALESCE(ticket_no, id)
        )
        ORDER BY created_at DESC";

try {
    $result = $conn->query($sql);
    $violations = [];

    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['fine_amount'] = isset($row['fine_amount']) ? (float)$row['fine_amount'] : 0;
            $violations[] = $row;
        }
        echo json_encode($violations, JSON_NUMERIC_CHECK);
    } else {
        echo json_encode([]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Database Error: " . $e->getMessage()
    ]);
}

$conn->close();
?>