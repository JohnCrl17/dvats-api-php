<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

// 1. Kunin ang input mula sa Mobile App
$license_no = isset($_GET['license_no']) ? trim(strtoupper($_GET['license_no'])) : '';

if (empty($license_no)) {
    echo json_encode(["status" => "error", "message" => "License number is required"]);
    exit;
}

try {
    // 2. LINISIN ANG INPUT (D06-16-000189 -> D0616000189)
    // Kasama na ang pagpapalit ng Letter 'O' sa Number '0'
    $clean_input = str_replace([' ', '-', 'O'], ['', '', '0'], $license_no);
    
    // 3. FUZZY LOGIC (Last character wildcard)
    $fuzzy_clean = substr($clean_input, 0, -1) . '_';

    $escaped_clean = $conn->real_escape_string($clean_input);
    $escaped_fuzzy = $conn->real_escape_string($fuzzy_clean);

    // 4. SQL QUERY (Flexible Search)
    // Nililinis din ang record sa Database habang naghahanap
    $sql = "SELECT client_id, fullname, phone_number, license_no 
            FROM lto_system.clients 
            WHERE REPLACE(REPLACE(REPLACE(license_no, ' ', ''), '-', ''), 'O', '0') = '$escaped_clean' 
            OR REPLACE(REPLACE(REPLACE(license_no, ' ', ''), '-', ''), 'O', '0') LIKE '$escaped_fuzzy' 
            LIMIT 1";
            
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $driver = $result->fetch_assoc();
        
        // Return structured success response
        echo json_encode([
            "status" => "success",
            "data" => [
                "full_name" => $driver['fullname'], // Siguraduhin na ang key sa kaliwa ay 'full_name' 
                "verified_license" => $driver['license_no'], 
                "client_id" => $driver['client_id'], 
                "contact_no" => $driver['phone_number']
            ]
        ]);
    } else {
        // Return detailed error for debugging
        echo json_encode([
            "status" => "error", 
            "message" => "No driver record found for $license_no",
            "debug" => [
                "searched_clean" => $clean_input,
                "fuzzy_used" => $fuzzy_clean
            ]
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}

$conn->close();
?>