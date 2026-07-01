<?php
// I-off ang error reporting para malinis ang JSON output
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("ngrok-skip-browser-warning: true");

include 'db_connection.php';

// Basahin ang input mula sa Expo/Mobile App
$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "No data received from App"]);
    exit;
}

// 1. I-sanitize ang lahat ng inputs (Protection vs SQL Injection)
$badge     = $conn->real_escape_string($data['badge_number']);
$driver    = $conn->real_escape_string($data['driver_name']);
$violation = $conn->real_escape_string($data['violation_name']);
$amount    = $conn->real_escape_string($data['fine_amount']);
// Generate unique Ticket Number
$ticket_no = "DVATS-" . strtoupper(substr(md5(time()), 0, 6)); 

try {
    // Simulan ang transaction para siguradong "sabay" silang papasok
    $conn->begin_transaction();

    // 2. INSERT sa DVATS_DB (Para kay Enforcer)
    $sql1 = "INSERT INTO dvats_db.apprehensions 
             (ticket_no, badge_number, driver_name, violation_name, fine_amount, status, created_at) 
             VALUES ('$ticket_no', '$badge', '$driver', '$violation', '$amount', 'PENDING', NOW())";
    
    if (!$conn->query($sql1)) {
        throw new Exception("Error saving to Apprehensions: " . $conn->error);
    }

    // 3. INSERT sa LTO_SYSTEM (Para kay Admin Web - KAMBAL VERSION)
    $sql2 = "INSERT INTO lto_system.violations 
             (ticket_no, badge_number, driver_name, violation_name, fine_amount, status, created_at) 
             VALUES ('$ticket_no', '$badge', '$driver', '$violation', '$amount', 'PENDING', NOW())";

    if (!$conn->query($sql2)) {
        throw new Exception("Error saving to LTO Violations: " . $conn->error);
    }

    // 4. INSERT sa NOTIFICATIONS (Update sa App ni Enforcer)
    $notif_desc = "$violation ticket issued for $driver";
    $sql3 = "INSERT INTO dvats_db.notifications 
             (badge_number, type, title, description, is_read, created_at) 
             VALUES ('$badge', 'warning', 'Violation Issued', '$notif_desc', 0, NOW())";
             
    if (!$conn->query($sql3)) {
        throw new Exception("Error saving Notification: " . $conn->error);
    }

    // Kung walang error, i-save na lahat permanently
    $conn->commit();
    
    echo json_encode([
        "status" => "success", 
        "message" => "Sync Successful: Data recorded in both systems", 
        "ticket_no" => $ticket_no
    ]);

} catch (Exception $e) {
    // Kapag may kahit isang error, bawiin lahat ng in-insert (Rollback)
    $conn->rollback();
    echo json_encode([
        "status" => "error", 
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>