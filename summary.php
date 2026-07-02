<?php
// 1. HEADERS (Dapat laging nasa pinakataas)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, ngrok-skip-browser-warning");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit; }

// 2. I-ON ANG ERROR REPORTING (Para makita natin kung bakit white screen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // 3. DATABASE CONNECTION (LTO_SYSTEM)
    $conn = new mysqli("localhost", "root", "", "lto_system");

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // 4. SQL QUERY (Itinugma sa 'enforcers', 'violation', at 'clients' tables mo)
    $sql = "SELECT 
    (SELECT COUNT(*) FROM violations 
     WHERE is_registered = 1 
        OR (is_registered = 0 AND ticket_no NOT IN (
            SELECT ticket_no FROM violations WHERE is_registered = 1
        ))
        ) as totalViolations,
        (SELECT COUNT(*) FROM appointments) as totalAppointments,
        (SELECT COUNT(*) FROM clients) as totalClients,
        (SELECT COUNT(*) FROM lto_system.enforcers) as totalEnforcers";
            

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    $data = $result->fetch_assoc();

    // 5. OUTPUT THE JSON (Ito yung dapat lumabas sa white screen)
    echo json_encode($data);

} catch (Exception $e) {
    // Kung may error, ito ang lalabas sa screen sa halip na white page
    echo json_encode([
        "error" => "PHP Error Occurred",
        "message" => $e->getMessage()
    ]);
}

$conn->close();
?>