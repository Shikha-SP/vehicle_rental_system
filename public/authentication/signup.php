<?php
/**
 * Signup Page / Registration Handler
 * 
 * This file handles the registration process for new users.
 * It uses a 2-step registration workflow:
 * Step 1: Basic account information (name, email, password, phone, address).
 * Step 2: Driver-specific details (license number and type).
 *
 * Key features & security implementations:
 * - CSRF protection utilizing session tokens.
 * - Password complexity validation for improved security.
 * - Input sanitization and prepared SQL statements to prevent SQL injections.
 * - Automated email verification to ensure valid accounts.
 */
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

// Track current step in the multi-step form process (defaults to step 1)
$current_step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die("Security error: Invalid CSRF token.");
    }

    // Capture and sanitize ALL input fields immediately. 
    // This allows data persistence so form values aren't lost when moving between Step 1 and Step 2 or if validation fails.
    $first_name   = trim(filter_input(INPUT_POST, 'first_name',   FILTER_SANITIZE_SPECIAL_CHARS));
    $last_name    = trim(filter_input(INPUT_POST, 'last_name',    FILTER_SANITIZE_SPECIAL_CHARS));
    $email        = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $address      = trim(filter_input(INPUT_POST, 'address',      FILTER_SANITIZE_SPECIAL_CHARS));
    $phone        = trim(filter_input(INPUT_POST, 'phone',        FILTER_SANITIZE_NUMBER_INT));
    $password     = trim($_POST['password'] ?? '');
    $confirm      = trim($_POST['confirm_password'] ?? '');
    $license_no   = trim(filter_input(INPUT_POST, 'license_no',   FILTER_SANITIZE_SPECIAL_CHARS));
    $license_type = trim(filter_input(INPUT_POST, 'license_type', FILTER_SANITIZE_SPECIAL_CHARS));

    // Check if the user clicked the "Go Back" button from step 2
    if (isset($_POST['go_back'])) {
        $current_step = 1;
    } 
    // === STEP 1 VALIDATION (Basic Account Details) ===
    else if ($current_step === 1) {
        if (empty($first_name))   $errors[] = "First name is required.";
        if (empty($last_name))    $errors[] = "Last name is required.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (empty($phone))        $errors[] = "Phone number is required.";
        if (empty($address))      $errors[] = "Address is required.";
        
        // Enforce robust password requirements to protect user accounts
        if (empty($password)) {
            $errors[] = "Password is required.";
        } else {
            // Thorough check to ensure password meets all our complexity requirements
            if (strlen($password) < 8) {
                $errors[] = "Password must be at least 8 characters long.";
            }
            if (!preg_match('/[A-Z]/', $password)) {
                $errors[] = "Password must contain at least one uppercase letter.";
            }
            if (!preg_match('/[a-z]/', $password)) {
                $errors[] = "Password must contain at least one lowercase letter.";
            }
            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = "Password must contain at least one number.";
            }
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
                $errors[] = "Password must contain at least one special character (!@#$%^&*(),.?\":{}|<>).";
            }
        }
        
        if ($password !== $confirm) $errors[] = "Passwords do not match.";
        
        // Ensure phone numbers contain a logical amount of digits and no rogue characters
        if (!empty($phone) && !preg_match('/^[0-9]{10,15}$/', $phone)) {
            $errors[] = "Phone number must be 10-15 digits long.";
        }

        // If there are no validation errors in Step 1, advance the user to Step 2
        if (empty($errors)) {
            $current_step = 2; // Move to step 2
        }
    } 
    // === STEP 2 VALIDATION & PROCESSING (Driver Details) ===
    else if ($current_step === 2) {
        // Validate driving credentials, which are mandatory to operate any rental vehicle
        if (empty($license_no)) {
            $errors[] = "License number is required.";
        } else {
            // Validate the format of the license number using regex. This pattern accommodates most regional license numbers.
            if (!preg_match('/^[A-Z0-9-]{5,20}$/', $license_no)) {
                $errors[] = "License number must be 5-20 characters long and can only contain letters, numbers, and hyphens.";
            }
        }
        
        if (empty($license_type)) {
            $errors[] = "License type is required.";
        } else {
            // Restrict license types to predefined values to prevent bad data in the database
            $allowed_license_types = ['A', 'B', 'C', 'D', 'E'];
            if (!in_array($license_type, $allowed_license_types)) {
                $errors[] = "Invalid license type selected.";
            }
        }
        
        if (!isset($_POST['terms'])) $errors[] = "You must agree to the terms.";

        // Verify if the email is already populated in the system before registering a new user
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
                    $current_step = 1; // Send them back to step 1 to fix email
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

        // If all validation rules (Step 1 and Step 2) pass, successfully create the database record.
        if (empty($errors)) {
            // Store a secure hash of the password, never plain text. Also generate an activation token.
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
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/signup.css">
    <style>
        /* Additional front-end styles for evaluating password strength and visualizing license requirements */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }
        
        .password-strength-text {
            font-size: 11px;
            margin-top: 5px;
            color: #666;
            display: block;
        }
        
        .password-requirements {
            margin-top: 8px;
            font-size: 11px;
            color: #666;
        }
        
        .password-requirements ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }
        
        .password-requirements li {
            margin: 2px 0;
        }
        
        .password-requirements li.valid {
            color: #4caf50;
            text-decoration: line-through;
        }
        
        .license-preview {
            margin-top: 8px;
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .license-preview-icon {
            width: 20px;
            height: 20px;
        }
        
        .signup-form__input.error {
            border-color: #f44336;
        }
        
        .error-message {
            color: #f44336;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }
        
        .valid-feedback {
            color: #4caf50;
            font-size: 11px;
            margin-top: 4px;
            display: block;
        }
    </style>
</head>
<body>

<div class="signup-page">

    <nav class="signup-nav">
        <a href="../../public/landing_page.php" class="signup-nav__logo">TD Rentals</a>
        <?php if (!$success): ?>
            <span class="signup-nav__step">Step 0<?= $current_step ?> / 02</span>
        <?php endif; ?>
        <a href="login.php" class="signup-nav__login">Login</a>
    </nav>

    <main class="signup-main">

        <section class="signup-form-panel">

            <?php if ($success): ?>
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
            <div class="signup-steps">
                <div class="signup-steps__bar active"></div>
                <div class="signup-steps__bar <?= $current_step === 2 ? 'active' : '' ?>"></div>
            </div>

            <div class="signup-heading">
                <span class="signup-heading__line1"><?= $current_step === 1 ? 'Create Your' : 'Verify Your' ?></span>
                <span class="signup-heading__line2"><?= $current_step === 1 ? 'Driver Profile' : 'Driving Credentials' ?></span>
            </div>
            <p class="signup-subtext">
                <?= $current_step === 1 ? 'Enter your details to access the world\'s most exclusive vehicle selection.' : 'We need your license details to finalize your registration.' ?>
            </p>

            <?php if (!empty($errors)): ?>
                <ul class="signup-errors">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form class="signup-form" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="step" value="<?= $current_step ?>">

                <?php if ($current_step === 1): ?>
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

                    <div class="signup-form__group">
                        <label class="signup-form__label" for="email">Email Address</label>
                        <input class="signup-form__input" type="email" id="email" name="email"
                               placeholder="driver@velocity.com" value="<?= e($email ?? '') ?>" required>
                    </div>

                    <div class="signup-form__row">
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="phone">Phone Number</label>
                            <input class="signup-form__input" type="tel" id="phone" name="phone"
                                   placeholder="1234567890" value="<?= e($phone ?? '') ?>" required>
                            <small class="valid-feedback" style="display:none;">✓ Valid phone number</small>
                        </div>
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="address">Address</label>
                            <input class="signup-form__input" type="text" id="address" name="address"
                                   placeholder="123 Main St" value="<?= e($address ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="signup-form__group">
                        <label class="signup-form__label" for="password">Security Password</label>
                        <div class="signup-form__pw-wrap">
                            <input class="signup-form__input" type="password" id="password" name="password"
                                   placeholder="••••••••••••" required>
                            <button type="button" class="signup-form__pw-toggle" aria-label="Toggle password"
                                    onclick="togglePw('password', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-requirements" id="passwordRequirements">
                            <small>Password must contain:</small>
                            <ul>
                                <li id="req-length">✓ At least 8 characters</li>
                                <li id="req-upper">✓ At least one uppercase letter</li>
                                <li id="req-lower">✓ At least one lowercase letter</li>
                                <li id="req-number">✓ At least one number</li>
                                <li id="req-special">✓ At least one special character</li>
                            </ul>
                        </div>
                    </div>

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
                        <div id="passwordMatchFeedback" class="error-message" style="display:none;">Passwords do not match</div>
                        <div id="passwordMatchValid" class="valid-feedback" style="display:none;">✓ Passwords match</div>
                    </div>

                    <button class="signup-form__submit" type="submit">
                        Continue to Verification
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </button>

                <?php else: ?>
                    <input type="hidden" name="first_name" value="<?= e($first_name) ?>">
                    <input type="hidden" name="last_name" value="<?= e($last_name) ?>">
                    <input type="hidden" name="email" value="<?= e($email) ?>">
                    <input type="hidden" name="phone" value="<?= e($phone) ?>">
                    <input type="hidden" name="address" value="<?= e($address) ?>">
                    <input type="hidden" name="password" value="<?= e($password) ?>">
                    <input type="hidden" name="confirm_password" value="<?= e($confirm) ?>">

                    <div class="signup-form__row">
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="license_no">License Number</label>
                            <input class="signup-form__input" type="text" id="license_no" name="license_no"
                                   placeholder="DL-000000" value="<?= e($license_no ?? '') ?>" required>
                            <div class="license-preview" id="licensePreview" style="display:none;">
                                <svg class="license-preview-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                <span id="licenseFormatCheck">License number format valid</span>
                            </div>
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
                            <div id="licenseTypeInfo" class="license-preview" style="display:none;">
                                <span id="licenseTypeDescription"></span>
                            </div>
                        </div>
                    </div>

                    <div class="signup-form__terms">
                        <input class="signup-form__checkbox" type="checkbox" id="terms" name="terms" required>
                        <label class="signup-form__terms-text" for="terms">
                            I agree to the <a href="#">Rental Agreement</a> and <a href="#">Privacy Policy</a>.
                        </label>
                    </div>

                    <button class="signup-form__submit" type="submit">
                        Complete Registration
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </button>
                    
                    <button type="submit" name="go_back" value="1" class="signup-form__back-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Profile Details
                    </button>
                <?php endif; ?>

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

        <aside class="signup-hero" aria-hidden="true">
            <img class="signup-hero__img" src="../../assets/images/hero-car4.png" alt="car image">
            <div class="signup-hero__fallback"></div>
            <div class="signup-hero__overlay"></div>
        </aside>

    </main>

    <footer class="signup-footer">
        <p class="signup-footer__brand">TD Rentals</p>
        <nav class="signup-footer__links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Contact</a>
        </nav>
        <p class="signup-footer__copy">© 2026 TD Rentals — Engineered for Performance.</p>
    </footer>

</div>

<script>
// A handy function allowing the user to reveal their password characters to double-check their entry
function togglePw(id, btn) {
    const input = document.getElementById(id);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.style.opacity = isText ? '1' : '0.5';
}

// --- Front-end Real-time Validation Logics ---
// References to document elements for visual strength meters and validation helpers
const passwordInput = document.getElementById('password');
const confirmPasswordInput = document.getElementById('confirm_password');
const strengthBar = document.getElementById('passwordStrengthBar');
const reqLength = document.getElementById('req-length');
const reqUpper = document.getElementById('req-upper');
const reqLower = document.getElementById('req-lower');
const reqNumber = document.getElementById('req-number');
const reqSpecial = document.getElementById('req-special');
const matchFeedback = document.getElementById('passwordMatchFeedback');
const matchValid = document.getElementById('passwordMatchValid');

function checkPasswordStrength(password) {
    let strength = 0;
    let validChecks = {
        length: password.length >= 8,
        upper: /[A-Z]/.test(password),
        lower: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
    };
    
    // Update requirement indicators
    reqLength.style.color = validChecks.length ? '#4caf50' : '#666';
    reqUpper.style.color = validChecks.upper ? '#4caf50' : '#666';
    reqLower.style.color = validChecks.lower ? '#4caf50' : '#666';
    reqNumber.style.color = validChecks.number ? '#4caf50' : '#666';
    reqSpecial.style.color = validChecks.special ? '#4caf50' : '#666';
    
    // Calculate strength
    if (validChecks.length) strength++;
    if (validChecks.upper) strength++;
    if (validChecks.lower) strength++;
    if (validChecks.number) strength++;
    if (validChecks.special) strength++;
    
    // Update strength bar
    let width = (strength / 5) * 100;
    strengthBar.style.width = width + '%';
    
    if (strength <= 2) {
        strengthBar.style.backgroundColor = '#f44336';
    } else if (strength <= 3) {
        strengthBar.style.backgroundColor = '#ff9800';
    } else if (strength <= 4) {
        strengthBar.style.backgroundColor = '#2196f3';
    } else {
        strengthBar.style.backgroundColor = '#4caf50';
    }
    
    return validChecks;
}

function checkPasswordMatch() {
    if (confirmPasswordInput.value.length > 0) {
        if (passwordInput.value === confirmPasswordInput.value) {
            matchFeedback.style.display = 'none';
            matchValid.style.display = 'block';
            return true;
        } else {
            matchFeedback.style.display = 'block';
            matchValid.style.display = 'none';
            return false;
        }
    } else {
        matchFeedback.style.display = 'none';
        matchValid.style.display = 'none';
        return false;
    }
}

if (passwordInput) {
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        if (confirmPasswordInput.value.length > 0) {
            checkPasswordMatch();
        }
    });
}

if (confirmPasswordInput) {
    confirmPasswordInput.addEventListener('input', checkPasswordMatch);
}

// Evaluate formatting requirements dynamically for driver's licenses (during step 2)
const licenseInput = document.getElementById('license_no');
const licensePreview = document.getElementById('licensePreview');
const licenseFormatCheck = document.getElementById('licenseFormatCheck');

if (licenseInput) {
    licenseInput.addEventListener('input', function() {
        const license = this.value;
        const isValid = /^[A-Z0-9-]{5,20}$/.test(license);
        
        if (license.length > 0) {
            licensePreview.style.display = 'flex';
            if (isValid) {
                licenseFormatCheck.style.color = '#4caf50';
                licenseFormatCheck.innerHTML = '✓ License number format valid';
                this.classList.remove('error');
            } else {
                licenseFormatCheck.style.color = '#f44336';
                licenseFormatCheck.innerHTML = '✗ License number must be 5-20 characters (letters, numbers, hyphens)';
                this.classList.add('error');
            }
        } else {
            licensePreview.style.display = 'none';
        }
    });
}

// Contextual tooltips to help users pick the correct license category
const licenseTypeSelect = document.getElementById('license_type');
const licenseTypeInfo = document.getElementById('licenseTypeInfo');
const licenseTypeDescription = document.getElementById('licenseTypeDescription');

const licenseDescriptions = {
    'A': 'Valid for: Motorcycles, scooters, and mopeds',
    'B': 'Valid for: Cars, jeeps, vans, and light vehicles',
    'C': 'Valid for: Trucks, buses, and commercial vehicles',
    'D': 'Valid for: Public service vehicles (taxis, buses)',
    'E': 'Valid for: Heavy vehicles with trailers'
};

if (licenseTypeSelect) {
    licenseTypeSelect.addEventListener('change', function() {
        const type = this.value;
        if (type && licenseDescriptions[type]) {
            licenseTypeInfo.style.display = 'block';
            licenseTypeDescription.innerHTML = licenseDescriptions[type];
        } else {
            licenseTypeInfo.style.display = 'none';
        }
    });
    
    // Trigger on page load if value exists
    if (licenseTypeSelect.value) {
        licenseTypeSelect.dispatchEvent(new Event('change'));
    }
}

// Provide immediate feedback verifying that the phone number has sufficient characters
const phoneInput = document.getElementById('phone');
if (phoneInput) {
    phoneInput.addEventListener('input', function() {
        const phone = this.value;
        const isValid = /^[0-9]{10,15}$/.test(phone);
        const feedback = this.parentElement.querySelector('.valid-feedback');
        
        if (phone.length > 0) {
            if (isValid) {
                feedback.style.display = 'block';
                this.classList.remove('error');
            } else {
                feedback.style.display = 'none';
                this.classList.add('error');
                if (!this.nextElementSibling || !this.nextElementSibling.classList.contains('error-message')) {
                    const errorMsg = document.createElement('div');
                    errorMsg.className = 'error-message';
                    errorMsg.textContent = 'Phone number must be 10-15 digits';
                    this.parentElement.appendChild(errorMsg);
                    setTimeout(() => errorMsg.remove(), 3000);
                }
            }
        } else {
            feedback.style.display = 'none';
        }
    });
}

// Final pre-flight submission check for Step 1
// This guards against submitting forms if JS validation failed but users somehow hit "Continue"
const step1Form = document.querySelector('.signup-form');
if (step1Form && document.getElementById('password')) {
    step1Form.addEventListener('submit', function(e) {
        const passwordValid = checkPasswordStrength(passwordInput.value);
        const allValid = Object.values(passwordValid).every(v => v === true);
        const passwordsMatch = passwordInput.value === confirmPasswordInput.value;
        
        if (!allValid) {
            e.preventDefault();
            alert('Please ensure your password meets all requirements');
            return false;
        }
        
        if (confirmPasswordInput.value.length > 0 && !passwordsMatch) {
            e.preventDefault();
            alert('Passwords do not match');
            return false;
        }
    });
}
</script>

</body>
</html>