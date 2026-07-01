<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['number']) || !isset($data['message'])) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid input"
    ]);
    exit;
}

$api_key = "399585c088a0f0d485a95e24623f068e";

$message = trim($data['message']);

$number = preg_replace('/\s+/', '', $data['number']);
$number = preg_replace('/[^0-9]/', '', $number);

if (str_starts_with($number, '0')) {
    $number = '63' . substr($number, 1);
} elseif (str_starts_with($number, '9')) {
    $number = '63' . $number;
}

if (!preg_match('/^639\d{9}$/', $number)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid mobile number"
    ]);
    exit;
}

$parameters = [
    'apikey'     => $api_key,
    'number'     => $number,
    'message'    => $message,
    'sendername' => 'DVATS',
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.semaphore.co/api/v4/messages',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($parameters),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$response = curl_exec($ch);
$error = curl_error($ch);

curl_close($ch);

if ($error) {

    echo json_encode([
        "success" => false,
        "error" => $error
    ]);

    exit;
}

$decoded = json_decode($response, true);

if (isset($decoded[0]['message_id'])) {

    echo json_encode([
        "success" => true,
        "formatted_number" => $number,
        "response" => $decoded
    ]);

} else {

    echo json_encode([
        "success" => false,
        "formatted_number" => $number,
        "response" => $decoded
    ]);
}
?>