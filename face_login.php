<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");

// ✅ FIX: Use db_connection.php instead of hardcoded localhost
include 'db_connection.php';

// =========================
// FACE++ CONFIG
// =========================
$api_key    = "o6oCmbGE5aJ-BoX_JUQzXyExcUTaUgKg";
$api_secret = "-OqrrEAXqdXHZeuQIfi6j0XO8isTvNx2";

// =========================
// ❌ TANGGAL: Hardcoded localhost connection
// =========================
// $host = "localhost";
// $db   = "dvats_db";
// $user = "root";
// $pass = "";
// $conn = new mysqli($host, $user, $pass, $db);
// if ($conn->connect_error) { ... }

// ✅ Instead, use $conn from db_connection.php
// Check if connection exists
if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit();
}

// =========================
// INPUT DATA
// =========================
$data = json_decode(file_get_contents("php://input"), true);

$badge_number = isset($data['badge_number']) ? trim($data['badge_number']) : '';
$selfie_b64   = isset($data['selfie']) ? $data['selfie'] : '';

if (empty($badge_number) || empty($selfie_b64)) {
    echo json_encode([
        "status" => "error",
        "message" => "Badge number and selfie are required"
    ]);
    exit();
}

// =========================
// GET USER FROM DB
// =========================
$stmt = $conn->prepare("SELECT * FROM lto_system.enforcers WHERE badge_number = ? LIMIT 1");
$stmt->bind_param("s", $badge_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Enforcer not found"
    ]);
    exit();
}

$user = $result->fetch_assoc();

// =========================
// CHECK IF FACE_TOKEN EXISTS
// =========================
$stored_face = $user['face_token'];

if (empty($stored_face)) {
    echo json_encode([
        "status" => "error",
        "message" => "No face registered. Please register first."
    ]);
    exit();
}

// =========================
// CLEAN BASE64
// =========================
$selfie_b64 = preg_replace('#^data:image/\w+;base64,#i', '', $selfie_b64);
$stored_face = preg_replace('#^data:image/\w+;base64,#i', '', $stored_face);

$selfie_b64 = trim($selfie_b64);
$stored_face = trim($stored_face);

// =========================
// FACE++ API - COMPARE FACE_TOKEN VS SELFIE
// =========================
$face_api_url = "https://api-us.faceplusplus.com/facepp/v3/compare";

$post_data = [
    'api_key'        => $api_key,
    'api_secret'     => $api_secret,
    'image_base64_1' => $stored_face,
    'image_base64_2' => $selfie_b64,
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $face_api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$curl_error = curl_error($ch);

curl_close($ch);

if ($curl_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Face API connection failed: " . $curl_error
    ]);
    exit();
}

$face_result = json_decode($response, true);

if (isset($face_result['error_message'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Face++ error: " . $face_result['error_message']
    ]);
    exit();
}

$confidence = $face_result['confidence'] ?? 0;
$threshold  = $face_result['thresholds']['1e-5'] ?? 80;

if ($confidence >= $threshold) {
    echo json_encode([
        "status" => "success",
        "message" => "Face recognized",
        "confidence" => round($confidence, 2),
        "user" => [
            "full_name"    => $user['full_name'],
            "badge_number" => $user['badge_number'],
            "unit"         => $user['unit'],
            "email"        => $user['email'],
            "face_token"   => $user['face_token']
        ]
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Face not recognized",
        "confidence" => round($confidence, 2)
    ]);
}

$stmt->close();
$conn->close();
?>