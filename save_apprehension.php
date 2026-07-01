<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// Handle OPTIONS request para sa CORS pre-flight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

include 'db_connection.php'; 

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->badge_number) && !empty($data->driver_name)) {
    // Kunin ang data mula sa JSON
    $badge = $data->badge_number;
    $name = $data->driver_name;
    $violation = $data->violation;
    $fine = $data->fine;
    $status = "UNPAID"; // Default status pagka-issue ng ticket

    // Gamit ang Prepared Statement para sa seguridad
    $stmt = $conn->prepare("INSERT INTO apprehensions (enforcer_badge, driver_name, violation_name, fine_amount, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $badge, $name, $violation, $fine, $status);

    if ($stmt->execute()) {
        // MAHALAGA: Kunin ang ID ng record na kakapasok lang
        $last_id = $conn->insert_id; 

        echo json_encode([
            "status" => "success", 
            "message" => "Apprehension record saved successfully",
            "db_id" => $last_id // Ito ang ipapasa natin sa TicketScreen
        ]);
    } else {
        echo json_encode([
            "status" => "error", 
            "message" => "Database Error: " . $stmt->error
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "Incomplete data: Badge number and Driver name are required"
    ]);
}

$conn->close();
?>