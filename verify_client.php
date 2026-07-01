<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "dvats_db");
$license = $_GET['license'];

// Hanapin ang client base sa license number
$sql = "SELECT * FROM clients WHERE license_number = '$license' LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "exists" => true,
        "clientData" => [
            "full_name" => $row['full_name'],
            "address" => $row['address'],
            "license_no" => $row['license_number']
        ]
    ]);
} else {
    echo json_encode(["exists" => false]);
}
$conn->close();
?>