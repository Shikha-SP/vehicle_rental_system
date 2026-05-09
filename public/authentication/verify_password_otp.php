<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

$email = $_SESSION['reset_email'] ?? '';

if (empty($email)) {
    redirect('forgot_password.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp)) {
        $errors[] = "Please enter the reset code.";
    } else {
        // Verify OTP
        $stmt = mysqli_prepare($conn, 
            "SELECT pr.token 
             FROM password_resets pr
             JOIN users u ON pr.user_id = u.id
             WHERE u.email = ? AND pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW() 
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($reset = mysqli_fetch_assoc($result)) {
            // Success: Proceed to actual reset page with the token
            // We use the OTP itself as the token for the next step
            redirect("reset_password.php?token=" . $otp);
        } else {
            $errors[] = "Invalid or expired reset code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Reset Code — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .otp-input-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-input {
            width: 45px;
            height: 55px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid var(--border-color, #eee);
            border-radius: 8px;
            background: var(--bg-secondary, #f9f9f9);
            color: var(--text-primary, #2c3e50);
            transition: all 0.3s ease;
        }
        .otp-input:focus {
            border-color: #e74c3c;
            outline: none;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.2);
        }
        .login-card__sub {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
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
            <div class="login-card__rule" style="background: #e74c3c;"></div>
            
            <div class="login-card__heading">
                <span>Verify</span>
                <span>Reset Code</span>
            </div>
            <p class="login-card__sub">
                Enter the 6-digit code sent to <br>
                <strong><?= e($email) ?></strong>
            </p>

            <?php if (!empty($errors)): ?>
                <div class="toast-container">
                    <?php foreach ($errors as $error): ?>
                        <div class="toast toast--error">
                            <span class="toast__icon">⚠️</span>
                            <span class="toast__msg"><?= e($error) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form class="login-form" method="POST" id="otpForm">
                <div class="otp-input-container">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                </div>
                <input type="hidden" name="otp" id="finalOtp">

                <button class="login-form__submit" type="submit" style="background: #e74c3c;">
                    Verify & Continue
                </button>
            </form>

            <div style="text-align: center; margin-top: 20px; font-size: 14px;">
                Didn't receive the code? <a href="forgot_password.php" style="color: #e74c3c; text-decoration: none; font-weight: bold;">Resend Request</a>
            </div>
        </div>
    </main>
</div>

<script>
    const inputs = document.querySelectorAll('.otp-input');
    const finalOtp = document.getElementById('finalOtp');
    const form = document.getElementById('otpForm');

    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length > 1) {
                e.target.value = e.target.value.slice(0, 1);
            }
            if (e.target.value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            updateFinalOtp();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').slice(0, 6).split('');
            pasteData.forEach((char, i) => {
                if (inputs[i]) inputs[i].value = char;
            });
            updateFinalOtp();
            if (pasteData.length === 6) form.submit();
        });
    });

    function updateFinalOtp() {
        let otp = '';
        inputs.forEach(input => otp += input.value);
        finalOtp.value = otp;
    }

    // Auto-dismiss toasts after 5 seconds
    document.querySelectorAll('.toast').forEach(toast => {
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    });
</script>
</body>
</html>
