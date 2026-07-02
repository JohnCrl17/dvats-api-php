<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");
include 'db_connection.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$driver_name = !empty($data['driver_name']) ? trim($conn->real_escape_string($data['driver_name'])) : "UNKNOWN DRIVER";
$license_no  = !empty($data['license_no']) ? trim($conn->real_escape_string($data['license_no'])) : "NOT DETECTED";
$violation   = $conn->real_escape_string($data['violation_types']);
$amount      = (float)$data['penalty_amount'];
$badge       = $conn->real_escape_string($data['badge_number']);
$offense_level = $conn->real_escape_string($data['offense_level'] ?? 'first');

// ── PHOTOS ──
$violation_photo = isset($data['violation_photo']) && !empty($data['violation_photo']) 
    ? "'" . $conn->real_escape_string($data['violation_photo']) . "'" 
    : "NULL";
$enforcer_proof  = isset($data['enforcer_proof']) && !empty($data['enforcer_proof']) 
    ? "'" . $conn->real_escape_string($data['enforcer_proof']) . "'" 
    : "NULL";
$proof_type      = isset($data['proof_type']) && !empty($data['proof_type']) 
    ? "'" . $conn->real_escape_string($data['proof_type']) . "'" 
    : "NULL";

/* 2. GET CLIENT_ID FROM LTO_SYSTEM */
$client_id = "NULL"; 
$is_registered = 0;

$check = $conn->query("SELECT client_id, fullname FROM lto_system.clients WHERE license_no='$license_no' LIMIT 1");

if ($check && $check->num_rows > 0) {
    $user = $check->fetch_assoc();
    $client_id = "'" . $user['client_id'] . "'"; 
    $driver_name = $user['fullname'];
    $is_registered = 1;
}

$ticket_no = "DVATS-" . strtoupper(substr(md5(uniqid()), 0, 6));

/* 3. INSERT TO APPREHENSIONS (dvats_db) */
$apprehensionQuery = "
INSERT INTO dvats_db.apprehensions
(ticket_no, license_no, badge_number, driver_name, violation_name, fine_amount, penalty_amount, status, is_registered, client_id, violation_photo, enforcer_proof, proof_type)
VALUES
('$ticket_no','$license_no','$badge','$driver_name','$violation','$amount','$amount','PENDING',$is_registered, $client_id, $violation_photo, $enforcer_proof, $proof_type)
";

if (!$conn->query($apprehensionQuery)) {
    echo json_encode([
        "status" => "error",
        "message" => "Apprehension INSERT failed: " . $conn->error,
        "query" => $apprehensionQuery
    ]);
    exit();
}

/* 4. ✅ SYNC TO VIOLATIONS TABLE (lto_system) - gamit si $conn lang! */
$conn->query("
INSERT INTO lto_system.violations
(ticket_no, license_no, badge_number, driver_name, violation_name, penalty_amount, fine_amount, status, is_registered, client_id, violation_photo, enforcer_proof, proof_type)
VALUES
('$ticket_no','$license_no','$badge','$driver_name','$violation','$amount','$amount','PENDING',$is_registered, $client_id, $violation_photo, $enforcer_proof, $proof_type)
");

echo json_encode([
    "status" => "success",
    "ticket_no" => $ticket_no,
    "is_registered" => $is_registered,
    "driver_name" => $driver_name
]);
?>