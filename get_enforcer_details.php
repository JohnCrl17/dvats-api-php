<?php
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

$id = $_GET['id'] ?? '';

if (!$id) {
    echo json_encode(["error" => "No ID provided"]);
    exit;
}

try {
    // ✅ lto_system — dito talaga naka-save ang enforcers
    $conn = new mysqli("localhost", "root", "", "lto_system");

    if ($conn->connect_error) {
        throw new Exception("DB connection failed");
    }

    $stmt = $conn->prepare("
        SELECT 
            id,
            full_name,
            badge_number,
            email,
            unit,
            gender,
            dob,
            phone_number,
            status,
            face_token    AS face_data,    -- ✅ i-alias para hindi na kailangan baguhin ang JS
            qr_code_token AS qr_image      -- ✅ i-alias para hindi na kailangan baguhin ang JS
        FROM enforcers 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(["error" => "Enforcer not found"]);
        exit;
    }

    echo json_encode($result->fetch_assoc());
    $conn->close();

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>