<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight Request (Solusyon sa Network Error)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);
$ticket_id = $data['ticket_id'] ?? null;
$status = $data['status'] ?? null;

if ($ticket_id && $status) {
    try {
        $conn->begin_transaction();

        // 1. Kunin ang ticket_no para sa synchronization
        $info_stmt = $conn->prepare("SELECT badge_number, ticket_no FROM dvats_db.apprehensions WHERE id = ?");
        $info_stmt->bind_param("i", $ticket_id);
        $info_stmt->execute();
        $ticket_info = $info_stmt->get_result()->fetch_assoc();
        
        if ($ticket_info) {
            $badge = $ticket_info['badge_number'];
            $tno = $ticket_info['ticket_no'];

            // 2. Update status sa App Table
            $stmt1 = $conn->prepare("UPDATE dvats_db.apprehensions SET status = ? WHERE id = ?");
            $stmt1->bind_param("si", $status, $ticket_id);
            $stmt1->execute();

            // 3. Update status sa Admin Table (Kambal Sync)
            $stmt2 = $conn->prepare("UPDATE lto_system.violations SET status = ?, updated_at = NOW() WHERE ticket_no = ?");
            $stmt2->bind_param("ss", $status, $tno);
            $stmt2->execute();

            // 4. Insert Notification gamit ang 'is_read' column
            $notif_title = "PAYMENT RECEIVED";
            $notif_desc = "Ticket #$tno has been marked as PAID.";
            
            // Gamit na dito ang 'is_read' (may underscore)
            $notif_query = "INSERT INTO dvats_db.notifications (badge_number, type, title, description, is_read, created_at) VALUES (?, 'payment', ?, ?, 0, NOW())";
            $stmt_notif = $conn->prepare($notif_query);
            $stmt_notif->bind_param("sss", $badge, $notif_title, $notif_desc);
            $stmt_notif->execute();

            $conn->commit();
            echo json_encode(["status" => "success", "message" => "Updated and Synchronized!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Ticket ID not found."]);
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Missing ticket_id or status."]);
}

$conn->close();
?>