<?php
// Pigilan ang anumang error na lumabas bilang HTML pero payagan ang exceptions sa code
error_reporting(0);
ini_set('display_errors', 0);

// FIX: Pilitin ang MySQLi na magbato ng Exception para gumana ang try-catch block mo
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'db_connection.php';

$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!empty($data['badge_number']) && !empty($data['password'])) {
    
    $badge = $conn->real_escape_string($data['badge_number']);
    $input_password = $data['password'];

    // SQL Query - Naka-point sa dvats_db.enforcers gaya ng configuration mo
    $sql = "SELECT * FROM lto_system.enforcers WHERE badge_number = '$badge' LIMIT 1";
    
    try {
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Check kung Active ang account ng Enforcer bago papasukin
            if ($user['status'] !== 'active') {
                echo json_encode(["status" => "error", "message" => "Account is pending approval. Please wait for admin."]);
                exit;
            }

            $stored_password = $user['password'];

            // Support sa dalawang klase ng password checking
            if ($input_password === $stored_password || password_verify($input_password, $stored_password)) {
                
                // Tanggalin ang password hash bago ibalik sa React Native para sa security
                unset($user['password']);
                
                echo json_encode([
                    "status" => "success",
                    "message" => "Login successful",
                    "user" => $user
                ]);
            } else {
                echo json_encode(["status" => "error", "message" => "Invalid Security PIN"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Badge Number not found"]);
        }
    } catch (Exception $e) {
        // Dahil sa mysqli_report sa taas, siguradong sasalo na ito kapag mali ang table o database name
        echo json_encode(["status" => "error", "message" => "Query Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Incomplete login details"]);
}

$conn->close();
?>