<?php
require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
session_start();

$email = $_SESSION['verify_email'] ?? '';

if (empty($email)) {
    redirect('signup.php');
}

$stmt = mysqli_prepare($conn, "SELECT first_name, last_name FROM users WHERE email = ? AND is_verified = 0");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    $otp = generateOTP(6);
    $update = mysqli_prepare($conn, "UPDATE users SET verification_token = ?, token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE email = ?");
    mysqli_stmt_bind_param($update, "ss", $otp, $email);
    
    if (mysqli_stmt_execute($update)) {
        try {
            $mail = createMailer();
            $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
            $mail->Subject = 'Your New Verification Code - TD Rentals';
            $mail->isHTML(true);
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #2c3e50; text-align: center;'>TD RENTALS</h2>
                    <p>Hi {$user['first_name']},</p>
                    <p>Here is your new verification code:</p>
                    <div style='background: #f4f7f6; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #3498db; margin: 20px 0; border-radius: 5px;'>
                        {$otp}
                    </div>
                    <p>This code expires in <strong>30 minutes</strong>.</p>
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 12px; color: #777; text-align: center;'>&copy; 2026 TD Rentals. All rights reserved.</p>
                </div>
            ";
            $mail->send();
            $_SESSION['success_msg'] = "A new code has been sent to your email.";
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Failed to send email. Please try again later.";
        }
    }
}

redirect('verify_otp.php');
?>
