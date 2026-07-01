<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning");
include 'db_connection.php'; 

$badge = $_GET['badge_number'] ?? null;

if($badge) {
    // Ginamit ko ang 'apprehensions' dahil iyon ang table name sa status code mo kanina
    // Siguraduhin na pareho ang table name na ginagamit sa INSERT at FETCH
    $stmt = $conn->prepare("SELECT ticket_no, driver_name, violation_name, penalty_amount, status, created_at 
                        FROM violations  -- <--- DAPAT 'violations' DITO
                        WHERE badge_number = ? 
                        ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("s", $badge);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rows = [];
    while($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode(["status" => "success", "data" => $rows]);
} else {
    echo json_encode(["status" => "error", "message" => "Missing badge number"]);
}
$conn->close();
?>