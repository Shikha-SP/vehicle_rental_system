<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Creates and configures a new PHPMailer instance.
 *
 * @return PHPMailer Returns a configured PHPMailer object ready for sending emails.
 */
function createMailer(): PHPMailer {
    // Create a new PHPMailer instance, true enables exceptions
    $mail = new PHPMailer(true);
    
    // Set mailer to use SMTP
    $mail->isSMTP();
    
    // Specify main and backup SMTP servers (using Gmail in this case)
    $mail->Host       = 'smtp.gmail.com';
    
    // Enable SMTP authentication
    $mail->SMTPAuth   = true;
    
    // SMTP username
    $mail->Username   = 'pandeyshikha567@gmail.com';        
    
    // SMTP password or App Password
    $mail->Password   = 'odtuojutiyxfvuko';     
    
    // Enable TLS encryption; PHPMailer::ENCRYPTION_SMTPS also accepted
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    
    // TCP port to connect to (587 for STARTTLS, 465 for SMTPS)
    $mail->Port       = 587;
    
    // Set the sender's email address and name
    $mail->setFrom('pandeyshikha567@gmail.com', 'TDRentals');  
    
    return $mail;
}