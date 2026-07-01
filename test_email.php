<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'dvats.official@gmail.com'; 
    $mail->Password   = 'cdenlebapktzhmbx'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('dvats.official@gmail.com', 'DVATS System Test');
    $mail->addAddress('balbagsjc@gmail.com'); // DITO YUNG EMAIL MO

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'DVATS Test Email';
    $mail->Body    = '<h1>Gumagana ang Email System!</h1><p>Congratulations, John Carlo! Naka-setup na ang iyong system email.</p>';

    $mail->send();
    echo "Message has been sent successfully to balbagsjc@gmail.com!";
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
?>