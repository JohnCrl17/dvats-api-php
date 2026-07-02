<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

// IMPORTANTE: Ito ang solusyon sa Network Error (Preflight Request)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

$data = json_decode(file_get_contents("php://input"));

// Chine-check natin kung 'ticket_id' ang pinasa ng App (base sa luma mong code)
if(!empty($data->ticket_id)){

    $id = $data->ticket_id;

    try {
        $conn->begin_transaction();

        // 1. Kunin ang ticket_no para madamay ang LTO Admin table
        $stmt_get = $conn->prepare("SELECT ticket_no FROM dvats_db.apprehensions WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res = $stmt_get->get_result();
        $row = $res->fetch_assoc();

        if (!$row) throw new Exception("Record not found.");
        $ticket_no = $row['ticket_no'];

        // 2. Delete sa App Database (dvats_db)
        $stmt1 = $conn->prepare("DELETE FROM dvats_db.apprehensions WHERE id = ?");
        $stmt1->bind_param("i", $id);
        $stmt1->execute();

        // 3. Delete sa Admin Database (lto_system)
        $stmt2 = $conn->prepare("DELETE FROM lto_system.violations WHERE ticket_no = ?");
        $stmt2->bind_param("s", $ticket_no);
        $stmt2->execute();

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Ticket deleted in both systems."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }

} else {
    echo json_encode(["status" => "error", "message" => "Missing ticket_id"]);
}

$conn->close();
?>