<?php
// 1. Headers para sa CORS at JSON
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// 2. I-include ang database connection mo
// Palitan ang 'db_connection.php' ng file name ng DB connection mo
include_once 'db_connection.php'; 

// 3. Basahin ang JSON data mula sa request
$data = json_decode(file_get_contents("php://input"), true);

// 4. Validate input
if (isset($data['violation_id']) && isset($data['comment'])) {
    $violation_id = $data['violation_id'];
    $comment = $data['comment'];

    // 5. Gamitin ang prepared statement para sa security
    // Siguraduhin na ang column name 'driver_remarks' ay nage-exist sa table 'violations'
    $stmt = $conn->prepare("UPDATE violations SET driver_remarks = ? WHERE id = ?");
    
    // "s" = string (comment), "i" = integer (id)
    $stmt->bind_param("si", $comment, $violation_id);

    if ($stmt->execute()) {
        echo json_encode([
            "success" => true, 
            "message" => "Concern submitted successfully!"
        ]);
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Database error: " . $conn->error
        ]);
    }

    $stmt->close();
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Invalid input data: violation_id and comment are required."
    ]);
}

$conn->close();
?>