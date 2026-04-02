<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

// Redirect logged-in users
if (isLoggedIn()) {
    if ($_SESSION['is_admin']) {
        redirect('../admin/home_page.php');
    } else {
        redirect('../user/home_page.php');
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Security error: Invalid CSRF token.");
    }

    $email    = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn,
            "SELECT id, first_name, password, is_admin, is_verified 
             FROM users WHERE email=? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            if (!$user['is_verified']) {
                $safe_email = urlencode($email);
                $errors[] = "Please verify your email first. 
                    <a href='resend_email.php?email={$safe_email}'>Resend verification email</a>";
            } elseif (password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['username']  = $user['first_name'];
                $_SESSION['is_admin']  = (bool) $user['is_admin'];
                $_SESSION['csrf_token'] = generateCsrfToken();

                if ($user['is_admin']) {
                    redirect('../admin/home_page.php');
                } else {
                    redirect('../user/home_page.php');
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
        } else {
            $errors[] = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/login.css">
</head>
<body>

<div class="login-page">

    <!-- ── Nav ── -->
    <nav class="login-nav">
        <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
        <a href="signup.php" class="login-nav__signup">Create Account</a>
    </nav>

    <!-- ── Main ── -->
    <main class="login-main">
        <div class="login-card">

            <!-- Red rule -->
            <div class="login-card__rule"></div>

            <!-- Heading -->
            <div class="login-card__heading">
                <span>Welcome</span>
                <span>Back.</span>
            </div>
            <p class="login-card__sub">Sign in to access your driver profile and reservations.</p>

            <!-- Errors -->
            <?php if (!empty($errors)): ?>
                <ul class="login-errors">
                    <?php foreach ($errors as $error): ?>
                        <li><?= $error /* allow link HTML */ ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- Form -->
            <form class="login-form" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <!-- Email -->
                <div class="login-form__group">
                    <label class="login-form__label" for="email">Email Address</label>
                    <input class="login-form__input" type="email" id="email" name="email"
                           placeholder="driver@velocity.com"
                           value="<?= e($email ?? '') ?>" required autocomplete="email">
                </div>

                <!-- Password -->
                <div class="login-form__group">
                    <label class="login-form__label" for="password">Password</label>
                    <div class="login-form__pw-wrap">
                        <input class="login-form__input" type="password" id="password" name="password"
                               placeholder="••••••••••••" required autocomplete="current-password">
                        <button type="button" class="login-form__pw-toggle" aria-label="Toggle password"
                                onclick="togglePw()">
                            <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 
                                         9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Forgot link -->
                <a href="#" class="login-form__forgot">Forgot password?</a>

                <!-- Submit -->
                <button class="login-form__submit" type="submit">
                    Access My Account
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </button>

                <!-- Divider -->
                <div class="login-divider">or</div>

                <!-- Sign up prompt -->
                <p class="login-signup-prompt">
                    New to TD Rentals? <a href="signup.php">Create a driver profile</a>
                </p>

            </form>

            <!-- Trust strip -->
            <div class="login-trust">
                <span class="login-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 
                                 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 
                                 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Secure Login
                </span>
                <span class="login-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 
                                 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    256-Bit Encrypted
                </span>
                <span class="login-trust__item">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Instant Access
                </span>
            </div>

        </div>
    </main>

    <!-- ── Footer ── -->
    <footer class="login-footer">
        <span class="login-footer__copy">© 2024 TD Rentals — Engineered for Performance.</span>
        <nav class="login-footer__links">
            <a href="#">Privacy</a>
            <a href="#">Terms</a>
            <a href="#">Contact</a>
        </nav>
    </footer>

</div>

<script>
function togglePw() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>