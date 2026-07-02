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

// Folder
$folder = "uploads/{$type}/";
if (!is_dir($folder)) mkdir($folder, 0755, true);

// Filename
$filename = $type . '_' . $ticket_no . '_' . time() . '.jpg';
$filepath = $folder . $filename;

// I-save
file_put_contents($filepath, $binary);

// I-return ang URL
$url = "https://unadroitly-nonthinking-lora.ngrok-free.dev/dvats_api/" . $filepath;

echo json_encode([
    "status" => "success",
    "url"    => $url
]);
?>