<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("ngrok-skip-browser-warning: true");

include 'db_connection.php';

$badge = isset($_GET['badge_number']) ? $conn->real_escape_string($_GET['badge_number']) : '';

if (empty($badge)) {
    echo json_encode(["status" => "error", "message" => "Badge number is required"]);
    exit;
}

try {
    // TAMA: 'dvats_db.apprehensions' at 'badge_number' column
   $sql = "SELECT 
                COUNT(*) as captured, 
                SUM(fine_amount) as fines,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_tickets
            FROM dvats_db.apprehensions 
            WHERE badge_number = '$badge'";
            
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "data" => [
            "captured"      => (int)($row['captured'] ?? 0),
            "fines"         => (float)($row['fines'] ?? 0),
            "today_tickets" => (int)($row['today_tickets'] ?? 0),
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
$conn->close();
?>