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

$theme = 'dark'; // Default to dark for premium feel

$success = false;
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Please enter a valid email address.";
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
            // Generate a 6-digit OTP for password reset
            $otp = generateOTP(6);
            
            // Delete any existing unused tokens for this user
            $delete = mysqli_prepare($conn, "DELETE FROM password_resets WHERE user_id = ? AND used = 0");
            mysqli_stmt_bind_param($delete, "i", $user['id']);
            mysqli_stmt_execute($delete);
            
            // Save the OTP in the database and set it to expire in 15 minutes
            $insert = mysqli_prepare($conn, 
                "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))"
            );
            mysqli_stmt_bind_param($insert, "is", $user['id'], $otp);
            mysqli_stmt_execute($insert);
            
            // Prepare and send the password reset email using PHPMailer
            try {
                $mail = createMailer();
                $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
                $mail->Subject = 'Your Password Reset Code - TD Rentals';
                $mail->isHTML(true);
                
                // Build the HTML email template
                $mail->Body = "
                    <html>
                    <body style='font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px;'>
                        <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; border: 1px solid #ddd;'>
                            <h2 style='color: #2c3e50; text-align: center;'>TD RENTALS</h2>
                            <h3 style='color: #333;'>Password Reset Request</h3>
                            <p>Hello {$user['first_name']},</p>
                            <p>We received a request to reset your password. Please use the following code to proceed:</p>
                            <div style='background: #f4f7f6; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #e74c3c; margin: 20px 0; border-radius: 5px;'>
                                {$otp}
                            </div>
                            <p>This code will expire in <strong>15 minutes</strong>.</p>
                            <p>If you didn't request this, please ignore this email.</p>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #777; text-align: center;'>&copy; " . date('Y') . " TD Rentals. All rights reserved.</p>
                        </div>
                    </body>
                    </html>
                ";
                
                $mail->AltBody = "Hello {$user['first_name']}, your password reset code is: {$otp} (expires in 15 minutes)";
                
                $mail->send();
                $_SESSION['reset_email'] = $email;
                redirect('verify_password_otp.php');
                
            } catch (Exception $e) {
                error_log("Password reset email failed: " . $mail->ErrorInfo);
                $errors[] = "Unable to send reset email. Please try again later.";
            }
        } else {
            // Security: Always redirect to verification page to prevent account enumeration
            $_SESSION['reset_email'] = $email;
            redirect('verify_password_otp.php');
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
    <script>
      (function() {
        const theme = localStorage.getItem('td-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
      })();
    </script>
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
        <div style="display: flex; align-items: center; gap: 20px;">
            <button id="themeToggleBtn" class="login-theme-toggle" aria-label="Toggle Theme">
                <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
            <a href="login.php" class="login-nav__signup">Back to Login</a>
        </div>
    </nav>

    <main class="login-main">
        <div class="login-card">
            <div class="login-card__rule"></div>
            
            <div class="login-card__heading">
                <span>Forgot</span>
                <span>Password?</span>
            </div>
            
            <?php if (!empty($errors) && !isset($errors['email'])): ?>
                <div class="toast-container">
                    <?php foreach ($errors as $error): ?>
                        <div class="toast toast--error">
                            <span class="toast__icon">⚠️</span>
                            <span class="toast__msg"><?= e($error) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
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
                        <input class="login-form__input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                               placeholder="driver@velocity.com" value="<?= e($email) ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="field-error">⚠️ <?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button class="login-form__submit" type="submit">
                        Send Reset Instructions
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Auto-dismiss toasts after 5 seconds
document.querySelectorAll('.toast').forEach(toast => {
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        setTimeout(() => toast.remove(), 300);
    }, 5000);
});

// Theme Toggle Logic
const themeBtn = document.getElementById('themeToggleBtn');
if (themeBtn) {
    themeBtn.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'light' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('td-theme', next);
    });
}
</script>
</body>
</html>