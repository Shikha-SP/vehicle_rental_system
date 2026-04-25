<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

// If the user is already logged in, redirect them to their respective dashboard
// so they do not see the login form again.
if (isLoggedIn()) {
    if ($_SESSION['is_admin']) {
        redirect('../admin/home_page.php'); // Route admin users to the management portal
    } else {
        redirect('../user/home_page.php');  // Route normal customers to the user dashboard
    }
}

// Initialize CSRF protection: generate a token to prevent Cross-Site Request Forgery
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verify the CSRF token submitted from the form against the one in their active session.
    // If it doesn't match or doesn't exist, we halt the script.
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Security error: Invalid CSRF token.");
    }

    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = trim($_POST['password'] ?? '');

    // Perform basic validation to ensure the fields are formatted properly
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If basic validation passes, lookup the user in the database
    if (empty($errors)) {
        // Use prepared statements here to completely prevent SQL injection attacks
        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, first_name, password, is_admin, is_verified 
             FROM users WHERE email=? LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);

            // Ensure the user actually verified their email address before letting them in
            if (!$user['is_verified']) {
                $safe_email = urlencode($email);
                $errors[] = "Please verify your email first. 
                    <a href='resend_email.php?email={$safe_email}'>Resend verification email</a>";
                // Compare the submitted password with the completely secure bcrypt hash we have stored
            } elseif (password_verify($password, $user['password'])) {
                // Generate a new session ID to protect against session fixation vulnerabilities
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['first_name'];
                $_SESSION['is_admin'] = (bool) $user['is_admin'];
                $_SESSION['csrf_token'] = generateCsrfToken();

                // Check the user's role and route them to the correct dashboard section
                if ($user['is_admin']) {
                    redirect('../admin/dashboard.php'); // Send admin users to management portal
                } else {
                    redirect('../user/home_page.php');  // Send normal drivers to standard portal
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/login.css">
    <!-- Theme Initialization (Prevents FOUC) -->
    <script>
      (function() {
        const t = localStorage.getItem('td-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
</head>

<body>

    <div class="login-page">

        <!-- ── Nav ── -->
        <nav class="login-nav">
            <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
            <div style="display:flex;align-items:center;gap:1rem;">
                <button id="themeToggleBtn" class="login-theme-toggle" aria-label="Toggle theme" title="Toggle Light/Dark Mode">
                    <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>
                <a href="signup.php" class="login-nav__signup">Create Account</a>
            </div>
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

                <!-- Conditionally render the error notification block if exceptions were raised during form processing -->
                <!-- Note: We don't automatically escape these error elements so that we can embed HTML links (like the "Resend Verification" link) directly in the error message -->
                <?php if (!empty($errors)): ?>
                    <ul class="login-errors">
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
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
                            placeholder="driver@velocity.com" value="<?= e($email ?? '') ?>" required
                            autocomplete="email">
                    </div>

                    <!-- Password -->
                    <div class="login-form__group">
                        <label class="login-form__label" for="password">Password</label>
                        <div class="login-form__pw-wrap">
                            <input class="login-form__input" type="password" id="password" name="password"
                                placeholder="••••••••••••" required autocomplete="current-password">
                            <button type="button" class="login-form__pw-toggle" aria-label="Toggle password"
                                onclick="togglePw()">
                                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 
                                         9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Password Recovery entry point -->
                    <a href="forgot_password.php" class="login-form__forgot">Forgot password?</a>


                    <!-- Submit -->
                    <button class="login-form__submit" type="submit">
                        Access My Account
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
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
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 
                                 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 
                                 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        Secure Login
                    </span>
                    <span class="login-trust__item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 
                                 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        256-Bit Encrypted
                    </span>
                    <span class="login-trust__item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Instant Access
                    </span>
                </div>

            </div>
        </main>

        <!-- ── Footer ── -->
        <footer class="login-footer">
            <span class="login-footer__copy">© 2026 TD Rentals — Engineered for Performance.</span>
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

        // Theme Toggle
        document.addEventListener('DOMContentLoaded', () => {
            const btn = document.getElementById('themeToggleBtn');
            btn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const next = current === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                localStorage.setItem('td-theme', next);
            });
        });
    </script>

</body>

</html>