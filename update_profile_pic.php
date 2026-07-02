<?php
ini_set('post_max_size', '20M');
ini_set('upload_max_filesize', '20M');
ini_set('memory_limit', '256M');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

include 'db_connection.php';

// ✅ Use lto_system database (where enforcers table is)
$conn->select_db('lto_system');

$rawData = file_get_contents("php://input");

if (!$rawData) {
    echo json_encode(["status" => "error", "message" => "No input received"]);
    exit;
}

$data = json_decode($rawData, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

$badge = $data['badge_number'] ?? '';
// ✅ Accept both profile_pic and face_token from mobile app
$face  = $data['profile_pic'] ?? $data['face_token'] ?? '';

if (empty($badge) || empty($face)) {
    echo json_encode([
        "status"      => "error",
        "message"     => "Missing data: badge_number and profile_pic/face_token are required",
        "badge"       => $badge,
        "face_length" => strlen($face)
    ]);
    exit;
}

// Clean base64
$face = preg_replace('#^data:image/\w+;base64,#i', '', $face);
$face = trim($face);

// ✅ Update face_token in lto_system.enforcers (this is the correct table!)
$stmt = $conn->prepare("UPDATE enforcers SET face_token = ? WHERE badge_number = ?");

if (!$stmt) {
    echo json_encode([
        "status"  => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("ss", $face, $badge);

if ($stmt->execute()) {
    echo json_encode([
        "status"       => "success",
        "message"      => "Profile photo and face token updated successfully!",
        "badge_number" => $badge
    ]);
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "DB update failed: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>