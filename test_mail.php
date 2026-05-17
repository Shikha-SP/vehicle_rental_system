<?php
require 'config/db.php';
require 'config/mailer.php';
require 'includes/functions.php';

$mail = createMailer();
$mail->addAddress('test@example.com', 'Test User');
$mail->Subject = 'Test';
$mail->Body = 'Test Body';
try {
    $mail->send();
    echo 'Mail sent successfully';
} catch (Exception $e) {
    echo 'Mail failed: ' . $e->getMessage();
}
