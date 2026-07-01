<?php

// =============================
// 🔥 CORS FIX (IMPORTANT)
// =============================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, ngrok-skip-browser-warning");
header("Content-Type: application/json; charset=UTF-8");

// 🔥 HANDLE PREFLIGHT REQUEST (CRITICAL FIX)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// =============================
// PHPMailer
// =============================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// =============================
// READ INPUT (JSON + POST fallback)
// =============================
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// fallback kung walang JSON
if (empty($data)) {
    $data = $_POST;
}

// =============================
// VALIDATION
// =============================
if (!isset($data['id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid Request: No ID provided",
        "debug" => [
            "raw_input" => $input,
            "post_data" => $_POST
        ]
    ]);
    exit;
}

// =============================
// DB CONNECTION
// =============================
$conn = new mysqli("localhost", "root", "", "dvats_db");

if ($conn->connect_error) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection failed"
    ]);
    exit;
}

$id = intval($data['id']);

// =============================
// GET USER
// =============================
$stmt = $conn->prepare("SELECT email, full_name FROM enforcers WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    echo json_encode([
        "status" => "error",
        "message" => "Enforcer not found"
    ]);
    exit;
}

// =============================
// UPDATE STATUS
// =============================
$update = $conn->prepare("UPDATE enforcers SET status = 'active' WHERE id = ?");
$update->bind_param("i", $id);

if ($update->execute()) {

    $approvalDate = date("F j, Y");
    $refNumber = "LTO-DASMA-" . str_pad($id, 5, "0", STR_PAD_LEFT) . "-" . date("Y");

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dvats.official@gmail.com';
        $mail->Password = 'cdenlebapktzhmbx';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('dvats.official@gmail.com', 'DVATS Admin');
        $mail->addAddress($user['email']);

        $mail->isHTML(true);
        $mail->Subject = 'Official Notification: Account Approval';

        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; border: 1px solid #ccc; padding: 20px; border-radius: 10px;'>
            <h2 style='color: #00A36C;'>OFFICIAL APPROVAL NOTICE</h2>
            <p><strong>Date:</strong> $approvalDate</p>
            <p><strong>Reference Number:</strong> $refNumber</p>
            <hr>
            <p>Dear <strong>" . htmlspecialchars($user['full_name']) . "</strong>,</p>
            
            <p>We are pleased to formally confirm that your registration for the <strong>DVATS Mobile Application</strong> has been <strong>APPROVED</strong>.</p>
            
            <p>Your account is now fully integrated with our district monitoring system. You may now log in to the application to begin your duties.</p>
            
            <div style='background: #f4f4f4; padding: 10px; border-radius: 5px;'>
                <p><strong>Security Reminder:</strong> Keep your credentials secure. This system is monitored by the LTO Dasmariñas Violations Alert and Tracking System.</p>
            </div>
            
            <br>
            <p>Sincerely,</p>
            <p><strong>Administrative Division</strong><br>
            LTO Dasmariñas Violations Alert and Tracking System (DVATS)</p>
        </div>
        ";

        $mail->send();

        echo json_encode([
            "status" => "success",
            "message" => "Approved + email sent"
        ]);

    } catch (Exception $e) {
        echo json_encode([
            "status" => "success",
            "message" => "Approved but email failed",
            "error" => $mail->ErrorInfo
        ]);
    }

} else {
    echo json_encode([
        "status" => "error",
        "message" => "Database update failed"
    ]);
}
?>