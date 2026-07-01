<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

include 'db_connection.php';

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['id']) || empty($data['id'])) {
    echo json_encode(["status" => "error", "message" => "ID is missing. Received: " . $input]);
    exit;
}

$id = $conn->real_escape_string($data['id']);

try {
    $conn->begin_transaction();

    // 1. Kunin muna ang ticket_no mula sa lto_system para mahanap ang kapares sa kabilang table
    $query_ticket = "SELECT ticket_no FROM lto_system.violations WHERE id = '$id' LIMIT 1";
    $res = $conn->query($query_ticket);
    $row = $res->fetch_assoc();

    if (!$row) throw new Exception("Record not found.");
    
    $ticket_no = $row['ticket_no'];

    // 2. I-update ang LTO_SYSTEM table
    $sql1 = "UPDATE lto_system.violations 
             SET status = 'PAID', updated_at = NOW() 
             WHERE id = '$id'";
    if (!$conn->query($sql1)) throw new Exception("LTO Update Error: " . $conn->error);

    // 3. I-update ang DVATS_DB table (Kambal Sync)
    $sql2 = "UPDATE dvats_db.apprehensions 
             SET status = 'PAID' 
             WHERE ticket_no = '$ticket_no'";
    if (!$conn->query($sql2)) throw new Exception("DVATS Update Error: " . $conn->error);

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Violation settled in both systems."]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>