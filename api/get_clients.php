<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: ngrok-skip-browser-warning");

$host = "localhost";
$db_name = "lto_system";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Kunin lahat ng columns para sigurado
    $stmt = $conn->prepare("SELECT * FROM clients ORDER BY reg_date DESC");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($results);
} catch(PDOException $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
?>