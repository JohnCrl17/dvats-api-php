<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$data      = json_decode(file_get_contents("php://input"), true);
$base64    = $data['image']    ?? '';
$type      = $data['type']     ?? 'misc'; // 'violation' or 'proof'
$ticket_no = $data['ticket_no'] ?? uniqid();

if (empty($base64)) {
    echo json_encode(["status" => "error", "message" => "No image"]);
    exit;
}

// I-decode ang base64
$base64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64);
$binary = base64_decode($base64);

if (!$binary) {
    echo json_encode(["status" => "error", "message" => "Invalid base64 data"]);
    exit;
}

// ✅ Folder - use absolute path for Render
$folder = __DIR__ . "/uploads/{$type}/";
if (!is_dir($folder)) {
    if (!mkdir($folder, 0755, true)) {
        echo json_encode(["status" => "error", "message" => "Failed to create upload directory"]);
        exit;
    }
}

// ✅ Check if writable
if (!is_writable($folder)) {
    echo json_encode(["status" => "error", "message" => "Upload directory not writable"]);
    exit;
}

// Filename
$filename = $type . '_' . $ticket_no . '_' . time() . '.jpg';
$filepath = $folder . $filename;

// I-save
if (file_put_contents($filepath, $binary) === false) {
    echo json_encode(["status" => "error", "message" => "Failed to save image"]);
    exit;
}

// ✅ FIXED: Use Render URL instead of Ngrok
$url = "https://dvats-api-php.onrender.com/" . "uploads/{$type}/" . $filename;

echo json_encode([
    "status" => "success",
    "url"    => $url,
    "type"   => $type
]);
?>