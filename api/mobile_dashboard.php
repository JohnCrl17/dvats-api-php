<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: ngrok-skip-browser-warning");

$conn = new mysqli("localhost", "root", "", "lto_system");

if ($conn->connect_error) {
    echo json_encode([
        "success" => false,
        "error" => "DB Connection Failed"
    ]);
    exit;
}

// KUNIN ANG MGA PARAMETERS
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$license_no = $_GET['license_no'] ?? '';

if ($client_id === 0 && empty($license_no)) {
    echo json_encode([
        "success" => false,
        "error" => "Missing identification"
    ]);
    exit;
}

$response = [
    "success" => true,
    "driver" => null,
    "violations" => [],
    "appointments" => [],
    "notifications" => []
];

try {

    // =========================================================
    // DRIVER INFO
    // =========================================================

    $stmt_d = $conn->prepare("
        SELECT 
            client_id,
            fullname,
            license_no,
            date_of_birth,
            license_expiry,
            gender,
            email,
            phone_number,
            face_data,
            profile_path,
            qr_image
        FROM clients
        WHERE client_id = ?
        LIMIT 1
    ");

    $stmt_d->bind_param("i", $client_id);
    $stmt_d->execute();

    $res_d = $stmt_d->get_result();

    if ($row_d = $res_d->fetch_assoc()) {

        // FIX BASE64 FACE IMAGE
        $face = $row_d['face_data'] ?? null;

        if ($face && !str_starts_with($face, 'data:image')) {
            $row_d['face_data'] = 'data:image/jpeg;base64,' . $face;
        }

        $response["driver"] = $row_d;
    }

    // =========================================================
    // VIOLATIONS
    // =========================================================

    $query_v = "
        SELECT *
        FROM violations
        WHERE client_id = ?
        OR (license_no = ? AND license_no != '')
        ORDER BY created_at DESC
    ";

    $stmt_v = $conn->prepare($query_v);

    $stmt_v->bind_param("is", $client_id, $license_no);

    $stmt_v->execute();

    $res_v = $stmt_v->get_result();

    while ($row = $res_v->fetch_assoc()) {

        $row['fine_amount'] = $row['fine_amount'] ?? 0;

        $response["violations"][] = $row;
    }

    // =========================================================
    // APPOINTMENTS
    // =========================================================

    $stmt_a = $conn->prepare("
        SELECT *
        FROM appointments
        WHERE client_id = ?
        ORDER BY appointment_date DESC
    ");

    $stmt_a->bind_param("i", $client_id);

    $stmt_a->execute();

    $res_a = $stmt_a->get_result();

    while ($row = $res_a->fetch_assoc()) {

        $response["appointments"][] = $row;
    }

    // =========================================================
    // FINAL RESPONSE
    // =========================================================

    echo json_encode($response);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>