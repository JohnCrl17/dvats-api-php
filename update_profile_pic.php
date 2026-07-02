<?php
ini_set('post_max_size', '20M');
ini_set('upload_max_filesize', '20M');
ini_set('memory_limit', '256M');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

include 'db_connection.php';

// ✅ DEBUG: Check if data received
$rawData = file_get_contents("php://input");

if (!$rawData || empty($rawData)) {
    echo json_encode([
        "status"  => "error",
        "message" => "No input received",
        "debug"   => "Raw data is empty"
    ]);
    exit;
}

$data = json_decode($rawData, true);

if (!$data) {
    echo json_encode([
        "status"  => "error",
        "message" => "Invalid JSON",
        "debug"   => "JSON decode failed",
        "raw"     => substr($rawData, 0, 100)
    ]);
    exit;
}

$badge = $data['badge_number'] ?? '';
$face  = $data['face_token'] ?? $data['profile_pic'] ?? '';

if (empty($badge) || empty($face)) {
    echo json_encode([
        "status"      => "error",
        "message"     => "Missing data: badge_number and face_token are required",
        "badge"       => $badge,
        "face_length" => strlen($face),
        "keys"        => array_keys($data)
    ]);
    exit;
}

// Clean base64
$face = preg_replace('#^data:image/\w+;base64,#i', '', $face);
$face = trim($face);

// Update face_token in enforcers table
$conn->select_db('lto_system');
$stmt = $conn->prepare("UPDATE enforcers SET face_token = ? WHERE badge_number = ?");

if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $face, $badge);

if ($stmt->execute()) {
    echo json_encode([
        "status"       => "success",
        "message"      => "Profile photo updated!",
        "badge_number" => $badge
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "DB update failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>