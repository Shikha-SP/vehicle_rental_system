<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

$email = $_SESSION['verify_email'] ?? '';

if (empty($email)) {
    redirect('signup.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');

    if (empty($otp)) {
        $errors[] = "Please enter the verification code.";
    } else {
        $stmt = mysqli_prepare($conn, 
            "SELECT id FROM users WHERE email = ? AND verification_token = ? AND token_expires_at > NOW() AND is_verified = 0"
        );
        mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $user_id);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            // Fetch additional user details for the session
            $user_stmt = mysqli_prepare($conn, "SELECT first_name, is_admin FROM users WHERE id = ?");
            mysqli_stmt_bind_param($user_stmt, "i", $user_id);
            mysqli_stmt_execute($user_stmt);
            $user_res = mysqli_stmt_get_result($user_stmt);
            $user = mysqli_fetch_assoc($user_res);

            $update = mysqli_prepare($conn, "UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ?");
            mysqli_stmt_bind_param($update, "i", $user_id);
            if (mysqli_stmt_execute($update)) {
                // Auto-login logic
                session_regenerate_id(true);
                $_SESSION['user_id']   = $user_id;
                $_SESSION['username']  = $user['first_name'];
                $_SESSION['is_admin']  = (bool) $user['is_admin'];
                $_SESSION['csrf_token'] = generateCsrfToken();

                unset($_SESSION['verify_email']);
                
                // Redirect based on role
                if ($user['is_admin']) {
                    redirect('../admin/dashboard.php');
                } else {
                    redirect('../user/home_page.php');
                }
            } else {
                $errors[] = "Something went wrong. Please try again.";
            }
        } else {
            $errors[] = "Invalid or expired verification code.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/signup.css">
    <link rel="stylesheet" href="../../assets/css/loading.css?v=<?= time() ?>">
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
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.2);
        }
        .resend-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .resend-link a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .resend-link a:hover {
            text-decoration: underline;
        }
        .hidden-otp {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>

<!-- Loading Overlay UI -->
<div id="td-progress-bar"></div>
<div id="td-overlay">
  <div class="loader-logo">TD <span>RENTALS</span></div>
  <div class="loader-bar-track"><div class="loader-bar-fill"></div></div>
  <div id="td-overlay-msg">Loading…</div>
</div>

<div class="signup-page">
    <nav class="signup-nav">
        <a href="../../public/landing_page.php" class="signup-nav__logo">TD Rentals</a>
        <a href="login.php" class="signup-nav__login">Login</a>
    </nav>

    <main class="signup-main">
        <section class="signup-form-panel">
            <?php if ($success): ?>
                <div class="signup-success">
                    <div class="signup-success__icon">✅</div>
                    <h1 class="signup-success__title">Email Verified!</h1>
                    <p class="signup-success__msg">
                        Your account has been successfully verified. You can now log in and start your journey.
                    </p>
                    <a href="login.php" class="signup-success__back">
                        Go to Login
                    </a>
                </div>
            <?php else: ?>
                <div class="signup-heading">
                    <span class="signup-heading__line1">Verify Your</span>
                    <span class="signup-heading__line2">Email Address</span>
                </div>
                <p class="signup-subtext">
                    We've sent a 6-digit verification code to <br>
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

                <form class="signup-form" method="POST" id="otpForm">
                    <div class="otp-input-container">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d*" inputmode="numeric">
                    </div>
                    <input type="hidden" name="otp" id="finalOtp">

                    <button class="signup-form__submit" type="submit">
                        Verify Code
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </button>

                    <div class="resend-link">
                        Didn't receive the code? 
                        <form action="resend_otp.php" method="POST" style="display:inline;" data-no-loading="false">
                            <button type="submit" style="background:none; border:none; color:#3498db; font-weight:bold; cursor:pointer; padding:0; font-family:inherit; font-size:inherit;">Resend Code</button>
                        </form>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <aside class="signup-hero" aria-hidden="true">
            <img class="signup-hero__img" src="../../assets/images/hero-car4.png" alt="car image">
            <div class="signup-hero__overlay"></div>
        </aside>
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

        // Handle paste
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

<script src="../../assets/js/loading.js?v=<?= time() ?>"></script>
</body>
</html>
