<?php
// Include required database and mailer configurations
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/mailer.php';

// Helper function to safely output user data and prevent XSS (Cross-Site Scripting)
if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }
}

session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

$success = false;
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    
    if (empty($errors)) {
        // Verify if a user with the submitted email address exists in the database
        $stmt = mysqli_prepare($conn, 
            "SELECT id, first_name, last_name, email FROM users WHERE email = ? LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($user = mysqli_fetch_assoc($result)) {
            // Generate a secure, random token for the password reset link
            $reset_token = bin2hex(random_bytes(32));
            
            // Delete any existing unused tokens for this user so only the newest link works
            $delete = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ? AND used = 0");
            mysqli_stmt_bind_param($delete, "i", $user['id']);
            mysqli_stmt_execute($delete);
            
            // Save the new token in the database and set it to expire in 1 hour
            $insert = mysqli_prepare($conn, 
                "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );
            mysqli_stmt_bind_param($insert, "is", $user['id'], $reset_token);
            mysqli_stmt_execute($insert);
            
            // Build the full reset URL to send in the email
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $reset_link = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $reset_token;
            
            // Prepare and send the password reset email using PHPMailer
            try {
                $mail = createMailer();
                $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
                $mail->Subject = 'Reset Your Password - TD Rentals';
                $mail->isHTML(true);
                
                // Build the HTML email template
                $mail->Body = "
                    <html>
                    <head>
                        <style>
                            body { font-family: Arial, sans-serif; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                            .content { padding: 30px; background: #f9f9f9; }
                            .button { 
                                display: inline-block; 
                                padding: 12px 24px; 
                                background: #3498db; 
                                color: white; 
                                text-decoration: none; 
                                border-radius: 4px;
                                margin: 20px 0;
                            }
                            .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <h2>TD Rentals</h2>
                            </div>
                            <div class='content'>
                                <h3>Password Reset Request</h3>
                                <p>Hello {$user['first_name']} {$user['last_name']},</p>
                                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                                <p style='text-align: center;'>
                                    <a href='{$reset_link}' class='button'>Reset Password</a>
                                </p>
                                <p>Or copy this link to your browser:</p>
                                <p><a href='{$reset_link}'>{$reset_link}</a></p>
                                <p>This link will expire in 1 hour.</p>
                                <p>If you didn't request this, please ignore this email.</p>
                            </div>
                            <div class='footer'>
                                <p>&copy; " . date('Y') . " TD Rentals. All rights reserved.</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                // Plain text version for email clients that don't support HTML
                $mail->AltBody = "Password Reset Request\n\n" .
                                 "Hello {$user['first_name']} {$user['last_name']},\n\n" .
                                 "We received a request to reset your password.\n\n" .
                                 "Click this link to reset your password:\n{$reset_link}\n\n" .
                                 "This link will expire in 1 hour.\n\n" .
                                 "If you didn't request this, please ignore this email.\n\n" .
                                 "TD Rentals";
                
                $mail->send();
                $success = true;
                
            } catch (Exception $e) {
                // Log the exact error for debugging, but don't show technical details to the user
                error_log("Password reset email failed: " . $mail->ErrorInfo);
                $errors[] = "Unable to send reset email. Please try again later.";
            }
        } else {
            // Security: Always show success regardless of whether the email exists.
            // This prevents attackers from guessing which emails are registered.
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .login-success { 
            background: #d4edda; 
            color: #155724; 
            padding: 1.5rem; 
            border-radius: 8px; 
            border: 1px solid #c3e6cb; 
            line-height: 1.6; 
        }
        .login-errors { 
            background: #f8d7da; 
            color: #721c24; 
            padding: 1rem; 
            border-radius: 8px; 
            margin-bottom: 1rem;
        }
        .login-errors li { 
            margin-left: 1.5rem; 
        }
    </style>
</head>
<body>

<div class="login-page">
    <nav class="login-nav">
        <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
        <a href="login.php" class="login-nav__signup">Back to Login</a>
    </nav>

    <main class="login-main">
        <div class="login-card">
            <div class="login-card__rule"></div>
            
            <div class="login-card__heading">
                <span>Forgot</span>
                <span>Password?</span>
            </div>
            
            <?php if (!empty($errors)): ?>
                <ul class="login-errors">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="login-success">
                    <p><strong>Reset Instructions Sent</strong></p>
                    <p>If an account exists for <strong><?= e($email) ?></strong>, check your inbox for reset instructions.</p>
                    <p style="margin-top: 10px; font-size: 14px;">Don't see the email? Check your spam folder.</p>
                </div>
            <?php else: ?>
                <form class="login-form" method="POST" novalidate>
                    <div class="login-form__group">
                        <label class="login-form__label" for="email">Email Address</label>
                        <input class="login-form__input" type="email" id="email" name="email"
                               placeholder="driver@velocity.com" value="<?= e($email) ?>" required>
                    </div>
                    
                    <button class="login-form__submit" type="submit">
                        Send Reset Instructions
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>