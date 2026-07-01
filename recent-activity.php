<?php
// ==========================
// 🔥 CORS FIX
// ==========================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==========================
// 📦 JSON RESPONSE
// ==========================
header("Content-Type: application/json");

// ==========================
// 🛠 DEBUG (REMOVE IN PRODUCTION)
// ==========================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==========================
// 🔌 DATABASE CONNECTION
// ==========================
$conn = new mysqli("localhost", "root", "", "lto_system");
if ($conn->connect_error) {
    echo json_encode([
        "error" => "Database connection failed",
        "details" => $conn->connect_error
    ]);
    exit;
}

// ==========================
// 📄 QUERY - RECENT VIOLATIONS
// ==========================
// Ensure table exists
$sql = "
    SELECT 
        'Violations' AS type, 
        CONCAT(violation_name, ' - ', driver_name) AS description, 
        status, 
        created_at AS activity_date 
    FROM violations 
    WHERE is_registered = 1 
    OR (is_registered = 0 AND ticket_no NOT IN (
        SELECT ticket_no FROM violations WHERE is_registered = 1
    ))
    ORDER BY created_at DESC 
    LIMIT 10
";

$result = $conn->query($sql);
if (!$result) {
    echo json_encode([
        "error" => "Query failed",
        "details" => $conn->error
    ]);
    exit;
}

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// ==========================
// 📤 OUTPUT
// ==========================
echo json_encode($data);

$conn->close();