<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("ngrok-skip-browser-warning: true");

// I-off ang direct error display para JSON lang ang lumabas sa Expo
error_reporting(0); 

include 'db_connection.php';

$badge = isset($_GET['badge_number']) ? $conn->real_escape_string($_GET['badge_number']) : '';

if (empty($badge)) {
    echo json_encode(["status" => "error", "message" => "Badge number is required"]);
    exit;
}

try {
    // TAMA: 'dvats_db.notifications' base sa screenshot mo
    // TAMA: 'badge_number' at lahat ay lowercase base sa instruction mo
    $sql = "SELECT id, badge_number, type, title, description, is_read, created_at 
            FROM dvats_db.notifications 
            WHERE badge_number = '$badge' 
            ORDER BY created_at DESC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception($conn->error);
    }

    $alerts = [];
    while($row = $result->fetch_assoc()) {
        // Mapping icons/colors para sa UI ng Expo
        $icon = "notifications";
        $color = "#3b82f6";

        if($row['type'] == 'payment') { $icon = "cash-outline"; $color = "#10b981"; }
        if($row['type'] == 'warning') { $icon = "warning-outline"; $color = "#f59e0b"; }
        if($row['type'] == 'urgent') { $icon = "alert-circle-outline"; $color = "#ef4444"; }

        $alerts[] = [
            "id" => $row['id'],
            "badge_number" => $row['badge_number'],
            "type" => $row['type'],
            "title" => $row['title'],
            "description" => $row['description'],
            "is_read" => (int)$row['is_read'], 
            "created_at" => date("M d, h:i A", strtotime($row['created_at'])),
            "icon" => $icon,
            "color" => $color
        ];
    }

    echo json_encode(["status" => "success", "data" => $alerts]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>