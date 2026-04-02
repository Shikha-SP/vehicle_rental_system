<?php
require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

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

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Security error: Invalid CSRF token.");
    }

    $first_name   = trim(filter_input(INPUT_POST, 'first_name',   FILTER_SANITIZE_SPECIAL_CHARS));
    $last_name    = trim(filter_input(INPUT_POST, 'last_name',    FILTER_SANITIZE_SPECIAL_CHARS));
    $email        = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $address      = trim(filter_input(INPUT_POST, 'address',      FILTER_SANITIZE_SPECIAL_CHARS));
    $phone        = trim(filter_input(INPUT_POST, 'phone',        FILTER_SANITIZE_NUMBER_INT));
    $password     = trim($_POST['password'] ?? '');
    $confirm      = trim($_POST['confirm_password'] ?? '');
    $license_no   = trim(filter_input(INPUT_POST, 'license_no',   FILTER_SANITIZE_SPECIAL_CHARS));
    $license_type = trim(filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_SPECIAL_CHARS));

    if (empty($first_name))   $errors[] = "First name is required.";
    if (empty($last_name))    $errors[] = "Last name is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password))     $errors[] = "Password is required.";
    if ($password !== $confirm) $errors[] = "Passwords do not match.";
    if (empty($license_no))   $errors[] = "License number is required.";
    if (empty($license_type)) $errors[] = "License type is required.";

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id, is_verified FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        mysqli_stmt_bind_result($stmt, $existing_id, $existing_verified);
        mysqli_stmt_fetch($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            if ($existing_verified) {
                $errors[] = "Email is already registered.";
            } else {
                mysqli_stmt_close($stmt);
                $del = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
                mysqli_stmt_bind_param($del, "i", $existing_id);
                mysqli_stmt_execute($del);
            }
        } else {
            mysqli_stmt_close($stmt);
        }
    }

    if (empty($errors)) {
        $password_hash      = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        $token_expires_at   = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = mysqli_prepare($conn,
            "INSERT INTO users
                (first_name, last_name, email, address, phone_number, password,
                 license_number, license_type, is_admin, is_verified, verification_token, token_expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, "ssssssssss",
            $first_name, $last_name, $email, $address, $phone,
            $password_hash, $license_no, $license_type,
            $verification_token, $token_expires_at
        );

        if (mysqli_stmt_execute($stmt)) {
            $verify_url = "http://localhost/vehicle_rental_collab_project/public/authentication/verify.php?token=" . $verification_token;

            try {
                $mail = createMailer();
                $mail->addAddress($email, $first_name . ' ' . $last_name);
                $mail->Subject = 'Verify your email address';
                $mail->isHTML(true);
                $mail->Body    = "
                    <p>Hi {$first_name},</p>
                    <p>Thanks for signing up! Please verify your email by clicking the link below.
                       This link expires in <strong>24 hours</strong>.</p>
                    <p><a href='{$verify_url}'>{$verify_url}</a></p>
                    <p>If you didn't create an account, you can ignore this email.</p>
                ";
                $mail->AltBody = "Hi {$first_name}, verify your email here: {$verify_url} (expires in 24 hours)";
                $mail->send();
                $success = true;
            } catch (Exception $e) {
                $del = mysqli_prepare($conn, "DELETE FROM users WHERE verification_token = ?");
                mysqli_stmt_bind_param($del, "s", $verification_token);
                mysqli_stmt_execute($del);
                $errors[] = "Could not send verification email. Please try again later.";
            }
        } else {
            $errors[] = "Failed to create account. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/signup.css">
</head>
<body>

<div class="signup-page">

    <!-- ── Nav ── -->
    <nav class="signup-nav">
        <a href="../../public/landing_page.php" class="signup-nav__logo">TD Rentals</a>
        <?php if (!$success): ?>
            <span class="signup-nav__step">Step 01 / 02</span>
        <?php endif; ?>
        <a href="login.php" class="signup-nav__login">Login</a>
    </nav>

    <!-- ── Main ── -->
    <main class="signup-main">

        <!-- Left: Form -->
        <section class="signup-form-panel">

            <?php if ($success): ?>
            <!-- ── Success State ── -->
            <div class="signup-success">
                <div class="signup-success__icon">✅</div>
                <h1 class="signup-success__title">You're almost in.</h1>
                <p class="signup-success__msg">
                    We sent a verification link to
                    <span class="signup-success__email"><?= e($email) ?></span>.<br>
                    Check your inbox — the link expires in 24 hours.
                </p>
                <a href="login.php" class="signup-success__back">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Login
                </a>
            </div>

            <?php else: ?>
            <!-- ── Step Bars ── -->
            <div class="signup-steps">
                <div class="signup-steps__bar active"></div>
                <div class="signup-steps__bar"></div>
            </div>

            <!-- ── Heading ── -->
            <div class="signup-heading">
                <span class="signup-heading__line1">Create Your</span>
                <span class="signup-heading__line2">Driver Profile</span>
            </div>
            <p class="signup-subtext">Enter your details to access the world's most exclusive fleet.</p>

            <!-- ── Errors ── -->
            <?php if (!empty($errors)): ?>
                <ul class="signup-errors">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <!-- ── Form ── -->
            <form class="signup-form" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <!-- Name row -->
                <div class="signup-form__row">
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="first_name">First Name</label>
                        <input class="signup-form__input" type="text" id="first_name" name="first_name"
                               placeholder="John" value="<?= e($first_name ?? '') ?>" required>
                    </div>
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="last_name">Last Name</label>
                        <input class="signup-form__input" type="text" id="last_name" name="last_name"
                               placeholder="Doe" value="<?= e($last_name ?? '') ?>" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="signup-form__group">
                    <label class="signup-form__label" for="email">Email Address</label>
                    <input class="signup-form__input" type="email" id="email" name="email"
                           placeholder="driver@velocity.com" value="<?= e($email ?? '') ?>" required>
                </div>

                <!-- Phone + Address row -->
                <div class="signup-form__row">
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="phone">Phone Number</label>
                        <input class="signup-form__input" type="text" id="phone" name="phone"
                               placeholder="+1 (555) 000-0000" value="<?= e($phone ?? '') ?>" required>
                    </div>
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="address">Address</label>
                        <input class="signup-form__input" type="text" id="address" name="address"
                               placeholder="123 Main St" value="<?= e($address ?? '') ?>" required>
                    </div>
                </div>

                <!-- Password -->
                <div class="signup-form__group">
                    <label class="signup-form__label" for="password">Security Password</label>
                    <div class="signup-form__pw-wrap">
                        <input class="signup-form__input" type="password" id="password" name="password"
                               placeholder="••••••••••••" required>
                        <button type="button" class="signup-form__pw-toggle" aria-label="Toggle password"
                                onclick="togglePw('password', this)">
                            <!-- Eye icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                    <span class="signup-form__pw-hint">Minimum 8 characters</span>
                </div>

                <!-- Confirm Password -->
                <div class="signup-form__group">
                    <label class="signup-form__label" for="confirm_password">Confirm Password</label>
                    <div class="signup-form__pw-wrap">
                        <input class="signup-form__input" type="password" id="confirm_password"
                               name="confirm_password" placeholder="••••••••••••" required>
                        <button type="button" class="signup-form__pw-toggle" aria-label="Toggle confirm password"
                                onclick="togglePw('confirm_password', this)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- License row -->
                <div class="signup-form__row">
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="license_no">License Number</label>
                        <input class="signup-form__input" type="text" id="license_no" name="license_no"
                               placeholder="DL-000000" value="<?= e($license_no ?? '') ?>" required>
                    </div>
                    <div class="signup-form__group">
                        <label class="signup-form__label" for="license_type">License Type</label>
                        <div class="signup-form__select-wrap">
                            <select class="signup-form__select" id="license_type" name="license_type" required>
                                <option value="">Select Type</option>
                                <option value="A" <?= (isset($license_type) && $license_type=="A") ? "selected" : "" ?>>A — Motorcycles &amp; Scooters</option>
                                <option value="B" <?= (isset($license_type) && $license_type=="B") ? "selected" : "" ?>>B — Cars, Jeeps, Vans</option>
                                <option value="C" <?= (isset($license_type) && $license_type=="C") ? "selected" : "" ?>>C — Commercial Heavy</option>
                                <option value="D" <?= (isset($license_type) && $license_type=="D") ? "selected" : "" ?>>D — Public Service</option>
                                <option value="E" <?= (isset($license_type) && $license_type=="E") ? "selected" : "" ?>>E — Heavy with Trailers</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div class="signup-form__terms">
                    <input class="signup-form__checkbox" type="checkbox" id="terms" name="terms" required>
                    <label class="signup-form__terms-text" for="terms">
                        I agree to the <a href="#">Rental Agreement</a> and <a href="#">Privacy Policy</a>.
                    </label>
                </div>

                <!-- Submit -->
                <button class="signup-form__submit" type="submit">
                    Continue to Verification
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </button>

                <!-- Trust badges -->
                <div class="signup-trust">
                    <span class="signup-trust__item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Secure Handshake
                    </span>
                    <span class="signup-trust__item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        256-Bit Encrypted
                    </span>
                    <span class="signup-trust__item">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        Instant Approval
                    </span>
                </div>

            </form>
            <?php endif; ?>

        </section>

        <!-- Right: Hero Image -->
        <aside class="signup-hero" aria-hidden="true">
            <!-- //Drop a car photo here, e.g. assets/images/hero-car.jpg -->
            <img class="signup-hero__img" src="../../assets/images/hero-car.jpg" alt="car image">
            <div class="signup-hero__fallback"></div>
            <div class="signup-hero__overlay"></div>
        </aside>

    </main>

    <!-- ── Footer ── -->
    <footer class="signup-footer">
        <p class="signup-footer__brand">TD Rentals</p>
        <nav class="signup-footer__links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Contact</a>
        </nav>
        <p class="signup-footer__copy">© 2024 TD Rentals — Engineered for Performance.</p>
    </footer>

</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.style.opacity = isText ? '1' : '0.5';
}
</script>

</body>
</html>