<?php
// ─── CORS HEADERS ───
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning, Accept");
header("Access-Control-Allow-Credentials: false");
header("Cross-Origin-Resource-Policy: cross-origin");
header("Access-Control-Max-Age: 86400");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// ─── NGROK BYPASS ───
// Check if this is an ngrok browser warning request
if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_GET['ngrok_skip'])) {
    // Allow the request to proceed
}

$image_path = $_GET['path'] ?? '';

if (empty($image_path)) {
    header("Content-Type: image/svg+xml");
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="#f1f5f9" width="200" height="200"/><text x="100" y="105" text-anchor="middle" fill="#94a3b8" font-size="16">No image</text></svg>';
    exit();
}

// Decode the URL-encoded path
$image_path = urldecode($image_path);

// Extract the filename and determine the folder
$filename = basename($image_path);

if (strpos($image_path, 'violation') !== false) {
    $file_path = __DIR__ . '/uploads/violation/' . $filename;
} elseif (strpos($image_path, 'proof') !== false) {
    $file_path = __DIR__ . '/uploads/proof/' . $filename;
} else {
    // Try common folders
    if (file_exists(__DIR__ . '/uploads/violation/' . $filename)) {
        $file_path = __DIR__ . '/uploads/violation/' . $filename;
    } elseif (file_exists(__DIR__ . '/uploads/proof/' . $filename)) {
        $file_path = __DIR__ . '/uploads/proof/' . $filename;
    } else {
        $file_path = __DIR__ . '/uploads/' . $filename;
    }
}

// Check if file exists
if (!file_exists($file_path)) {
    header("Content-Type: image/svg+xml");
    header("Cache-Control: no-cache");
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="#fef2f2" width="200" height="200"/><text x="100" y="95" text-anchor="middle" fill="#ef4444" font-size="14">File not found</text><text x="100" y="115" text-anchor="middle" fill="#94a3b8" font-size="10">' . htmlspecialchars($filename) . '</text></svg>';
    exit();
}

// Serve the image with proper headers
$mime_type = mime_content_type($file_path);
$file_size = filesize($file_path);

header("Content-Type: " . $mime_type);
header("Content-Length: " . $file_size);
header("Cache-Control: public, max-age=3600");
header("X-Content-Type-Options: nosniff");
header("Timing-Allow-Origin: *");

// Output the file
readfile($file_path);
exit();
?>