<?php
header('Content-Type: application/json');
include 'db_connection.php';

// gamitin client_id galing URL
$client_id = $_GET['client_id'] ?? 0;

if (!$client_id) {
    echo json_encode([]);
    exit;
}

$sql = "SELECT appointment_date, purpose, status 
        FROM appointment 
        WHERE client_id = ? 
        ORDER BY appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();

$result = $stmt->get_result();

$appointments = [];

while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}

echo json_encode($appointments);
?>