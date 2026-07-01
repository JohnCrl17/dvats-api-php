<?php
date_default_timezone_set('Asia/Manila');
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
include 'PHPMailer/Exception.php';
include 'PHPMailer/PHPMailer.php';
include 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$data = json_decode(file_get_contents("php://input"), true);
$email = $data['email'] ?? '';
$license_no = $data['license_no'] ?? '';

if (empty($email)) {
    echo json_encode(["status" => "error", "message" => "Email is required"]);
    exit;
}

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'dvats.official@gmail.com';
    $mail->Password = 'cdenlebapktzhmbx'; // Siguraduhin na tama ito
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('dvats.official@gmail.com', 'DVATS LTO Dasmariñas');
    $mail->addAddress($email);
    $mail->isHTML(true);
    $registrationDate = date("F j, Y, g:i a"); // May kasama ng time
    $ticketReference = "DVATS-REF-" . strtoupper(substr(md5(uniqid()), 0, 8)); // Random ref
    $mail->Subject = 'DVATS - Complete Your Driver Registration';
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #e2e8f0; padding: 25px; border-radius: 10px;'>

        <div style='text-align: center;'>
            <h2 style='color: #1e293b;'>LTO DASMARIÑAS</h2>
            <p style='color: #64748b; font-size: 12px;'>Dasmariñas Violations Alert and Tracking System (DVATS)</p>
        </div>

        <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>

        <p><strong>Date & Time:</strong> $registrationDate</p>
        <p><strong>Ticket Reference No:</strong> $ticketReference</p>

        <p>Dear Driver,</p>

        <p>
            This is to formally inform you that a traffic violation has been recorded under your license number:
            <strong>$license_no</strong>.
        </p>

        <p>
            You may review the details of this citation and complete your registration through the official DVATS portal using the link below:
        </p>

        <table width='100%' cellspacing='0' cellpadding='0'>
            <tr>
                <td align='center'>
                    <a href='https://unadroitly-nonthinking-lora.ngrok-free.dev/dvats_api/mobile-register/mobile_register.html' 
                    style='background-color: #2563eb; color: #ffffff; padding: 12px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                    VIEW CITATION / COMPLETE REGISTRATION
                    </a>
                </td>
            </tr>
        </table>

        <div style='background: #f8fafc; padding: 15px; border-radius: 5px; font-size: 13px; color: #475569; margin-top: 25px;'>
            <p>
                <strong>Important:</strong> Failure to comply with this notice may result in further administrative actions 
                in accordance with Land Transportation Office (LTO) regulations.
            </p>
        </div>

        <br>

        <p>
            Sincerely,<br>
            <strong>Administrative Division</strong><br>
            LTO Dasmariñas (DVATS)
        </p>

    </div>
    ";

    $mail->send();
    echo json_encode(["status" => "success", "message" => "Registration link sent!"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $mail->ErrorInfo]);
}
?>