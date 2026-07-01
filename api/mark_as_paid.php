<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

// 1. SOLUSYON SA "SERVER UNREACHABLE": Handle Preflight Request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

$data = json_decode(file_get_contents("php://input"));

// Pwedeng ticket_id o ticket_no ang ipasa ng app
$id = isset($data->ticket_id) ? $conn->real_escape_string($data->ticket_id) : null;
$ticket_no = isset($data->ticket_no) ? $conn->real_escape_string($data->ticket_no) : null;

if($id || $ticket_no) {
    // 2. HANAPIN ANG RECORD: Gamit ang dvats_db.apprehensions
    $whereClause = $id ? "id = '$id'" : "ticket_no = '$ticket_no'";
    $getInfo = $conn->query("SELECT id, ticket_no, badge_number, driver_name FROM dvats_db.apprehensions WHERE $whereClause");
    
    if($getInfo && $getInfo->num_rows > 0) {
        $row = $getInfo->fetch_assoc();
        $actual_ticket = $row['ticket_no'];
        $badge = $row['badge_number'];
        $driver = $row['driver_name'];

        try {
            $conn->begin_transaction();

            // 3. UPDATE APP DATABASE (dvats_db)
            $conn->query("UPDATE dvats_db.apprehensions SET status = 'PAID' WHERE ticket_no = '$actual_ticket'");

            // 4. UPDATE ADMIN DATABASE (lto_system) - KAMBAL SYNC
            $conn->query("UPDATE lto_system.violations SET status = 'PAID', updated_at = NOW() WHERE ticket_no = '$actual_ticket'");

            // 5. INSERT NOTIFICATION PARA SA ENFORCER
            $title = "PAYMENT RECEIVED";
            $description = "Ticket #$actual_ticket for $driver has been settled.";
            $type = "payment";

            $notifSql = "INSERT INTO dvats_db.notifications (badge_number, type, title, description, isRead, created_at) 
                         VALUES ('$badge', '$type', '$title', '$description', 0, NOW())";
            
            $conn->query($notifSql);

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Paid! Both systems updated and Enforcer notified."]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Record not found in database."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing ticket_id or ticket_no."]);
}

$conn->close();
?>