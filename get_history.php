<?php
// Pigilan ang anumang error na lumabas bilang text sa Expo
error_reporting(0);
ini_set('display_errors', 0);

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
    // TAMA: Ginamit na natin ang 'violation_name' base sa feedback mo
    $sql = "SELECT 
                id, 
                ticket_no, 
                driver_name, 
                violation_name, 
                fine_amount, 
                status, 
                created_at 
            FROM dvats_db.apprehensions 
            WHERE badge_number = '$badge' 
            ORDER BY created_at DESC";

    $result = $conn->query($sql);
    
    $list = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            // Safety defaults para hindi mag-crash ang React Native UI
            $row['status'] = $row['status'] ? $row['status'] : 'PENDING';
            $row['fine_amount'] = (string)$row['fine_amount'];
            $list[] = $row;
        }
        echo json_encode(["status" => "success", "data" => $list]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>