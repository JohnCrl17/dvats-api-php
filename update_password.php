<?php
// Siguraduhin na walang kahit anong space sa labas ng PHP tag
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Suriin kung existing ang db_connection.php
if (!file_exists('db_connection.php')) {
    echo json_encode(["status" => "error", "message" => "db_connection.php file not found"]);
    exit;
}

include 'db_connection.php';

// 2. Kunin ang input
$input = file_get_contents("php://input");
$data = json_decode($input);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    exit;
}

if(!empty($data->badge_number) && !empty($data->new_password)) {
    
    $badge = $data->badge_number;
    $new_pass_raw = $data->new_password;
    $old_pass_raw = $data->old_password ?? '';

    // ── I-verify muna ang old password
    $check = $conn->prepare("SELECT password FROM lto_system.enforcers WHERE badge_number = ?");
    $check->bind_param("s", $badge);
    $check->execute();
    $check->bind_result($hashed);
    $check->fetch();
    $check->close();

    if (!password_verify($old_pass_raw, $hashed)) {
        echo json_encode(["status" => "error", "message" => "Current PIN is incorrect."]);
        exit;
    }
    
    // Hash the password
    $new_pass_hashed = password_hash($new_pass_raw, PASSWORD_DEFAULT);

    // GUMAMIT NG PREPARED STATEMENT (Mas secure at iwas error)
    // Dinagdag natin ang 'dvats_db.' bago ang table name
$stmt = $conn->prepare("UPDATE lto_system.enforcers SET password = ? WHERE badge_number = ?");
    $stmt->bind_param("ss", $new_pass_hashed, $badge);

    if($stmt->execute()) {
        if($stmt->affected_rows > 0) {
            echo json_encode(["status" => "success", "message" => "Password updated"]);
        } else {
            // Pwedeng ang badge ay tama pero ang password ay pareho lang sa dati kaya 0 affected rows
            echo json_encode(["status" => "error", "message" => "No changes made or Badge not found"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $stmt->error]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Badge number or password missing"]);
}

$conn->close();
?>