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

$theme = 'dark'; // Default to dark for premium feel

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
        if (empty($first_name))   $errors['first_name'] = "First name is required.";
        if (empty($last_name))    $errors['last_name'] = "Last name is required.";
        
        if (empty($email)) {
            $errors['email'] = "Email is required.";
        } else if (!is_email_genuine($email)) {
            $errors['email'] = "Please enter a valid, genuine email address.";
        }

        if (empty($phone)) {
            $errors['phone'] = "Phone number is required.";
        } else if (!preg_match('/^[0-9]{10,15}$/', $phone)) {
            $errors['phone'] = "Phone number must be 10-15 digits.";
        }

        if (empty($address)) $errors['address'] = "Address is required.";
        
        if (empty($password)) {
            $errors['password'] = "Password is required.";
        } else {
            if (strlen($password) < 8) {
                $errors['password'] = "Password must be at least 8 characters.";
            } else if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $errors['password'] = "Password must contain uppercase and numbers.";
            }
        }
        
        if ($password !== $confirm) $errors['confirm_password'] = "Passwords do not match.";
        
        // Check if email already exists
        if (empty($errors)) {
            $stmt = mysqli_prepare($conn, "SELECT id, is_verified FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            mysqli_stmt_bind_result($stmt, $existing_id, $existing_verified);
            mysqli_stmt_fetch($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                if ($existing_verified) {
                    $errors['email'] = "This email is already in use.";
                }
            }
            mysqli_stmt_close($stmt);
        }

        if (empty($errors)) {
            $current_step = 2;
        }
    } 
    // === STEP 2 VALIDATION & PROCESSING (Driver Details) ===
    else if ($current_step === 2) {
        if (empty($license_no)) {
            $errors['license_no'] = "License number is required.";
        } else if (!preg_match('/^[A-Z0-9-]{5,20}$/', $license_no)) {
            $errors['license_no'] = "Invalid license format (5-20 characters).";
        }
        
        if (empty($license_type)) {
            $errors['license_type'] = "License type is required.";
        }
        
        if (!isset($_POST['terms'])) $errors['terms'] = "You must agree to the terms.";

        if (empty($errors)) {
            // Check for unverified duplicate again just to be safe before deletion
            $stmt = mysqli_prepare($conn, "SELECT id, is_verified FROM users WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            mysqli_stmt_bind_result($stmt, $existing_id, $existing_verified);
            mysqli_stmt_fetch($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                if ($existing_verified) {
                    $errors['email'] = "Email is already registered.";
                    $current_step = 1;
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
            $otp                = generateOTP(6);
            $token_expires_at   = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmt = mysqli_prepare($conn,
                "INSERT INTO users
                    (first_name, last_name, email, address, phone_number, password,
                     license_number, license_type, is_admin, is_verified, verification_token, token_expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, "ssssssssss",
                $first_name, $last_name, $email, $address, $phone,
                $password_hash, $license_no, $license_type,
                $otp, $token_expires_at
            );

            if (mysqli_stmt_execute($stmt)) {
                $verify_url = "http://localhost/vehicle_rental_collab_project/public/authentication/verify.php?token=" . $verification_token;

                try {
                    $mail = createMailer();
                    $mail->addAddress($email, $first_name . ' ' . $last_name);
                    $mail->Subject = 'Your Verification Code - TD Rentals';
                    $mail->isHTML(true);
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                            <h2 style='color: #2c3e50; text-align: center;'>TD RENTALS</h2>
                            <p>Hi {$first_name},</p>
                            <p>Thanks for signing up! Please use the following One-Time Password (OTP) to verify your email address:</p>
                            <div style='background: #f4f7f6; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; letter-spacing: 5px; color: #3498db; margin: 20px 0; border-radius: 5px;'>
                                {$otp}
                            </div>
                            <p>This code expires in <strong>30 minutes</strong>.</p>
                            <p>If you didn't create an account, you can ignore this email.</p>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 12px; color: #777; text-align: center;'>&copy; 2026 TD Rentals. All rights reserved.</p>
                        </div>
                    ";
                    $mail->AltBody = "Hi {$first_name}, your verification code is: {$otp} (expires in 30 minutes)";
                    $mail->send();
                    
                    $_SESSION['verify_email'] = $email;
                    redirect('verify_otp.php');
                } catch (Exception $e) {
                    $del = mysqli_prepare($conn, "DELETE FROM users WHERE verification_token = ?");
                    mysqli_stmt_bind_param($del, "s", $otp);
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
    <link rel="stylesheet" href="../../assets/css/loading.css?v=<?= time() ?>">
    <script>
      (function() {
        const theme = localStorage.getItem('td-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
      })();
    </script>
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
        <div style="display: flex; align-items: center; gap: 24px;">
            <?php if (!$success): ?>
                <span class="signup-nav__step">Step 0<?= $current_step ?> / 02</span>
            <?php endif; ?>
            <button id="themeToggleBtn" class="login-theme-toggle" aria-label="Toggle Theme">
                <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
            <a href="login.php" class="signup-nav__login">Login</a>
        </div>
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

            <?php
            if (!empty($errors) && !isset($errors['first_name']) && !isset($errors['last_name']) && !isset($errors['email']) && !isset($errors['phone']) && !isset($errors['address']) && !isset($errors['password']) && !isset($errors['confirm_password']) && !isset($errors['license_no']) && !isset($errors['license_type']) && !isset($errors['terms'])) {
                // This handles general errors as a toast
                echo '<div class="toast-container">';
                foreach ($errors as $error) {
                    echo '<div class="toast toast--error"><span class="toast__icon">⚠️</span><span class="toast__msg">' . e($error) . '</span></div>';
                }
                echo '</div>';
            }
            ?>

            <form class="signup-form" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="step" value="<?= $current_step ?>">

                <?php if ($current_step === 1): ?>
                    <div class="signup-form__row">
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="first_name">First Name</label>
                            <input class="signup-form__input <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" type="text" id="first_name" name="first_name"
                                   placeholder="John" value="<?= e($first_name ?? '') ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['first_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="last_name">Last Name</label>
                            <input class="signup-form__input <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" type="text" id="last_name" name="last_name"
                                   placeholder="Doe" value="<?= e($last_name ?? '') ?>" required>
                            <?php if (isset($errors['last_name'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['last_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="signup-form__group">
                        <label class="signup-form__label" for="email">Email Address</label>
                        <input class="signup-form__input <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                               placeholder="driver@velocity.com" value="<?= e($email ?? '') ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <span class="field-error">⚠️ <?= e($errors['email']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="signup-form__row">
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="phone">Phone Number</label>
                            <input class="signup-form__input <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" type="tel" id="phone" name="phone"
                                   placeholder="1234567890" value="<?= e($phone ?? '') ?>" required>
                            <?php if (isset($errors['phone'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['phone']) ?></span>
                            <?php else: ?>
                                <small class="valid-feedback" style="display:none;">✓ Valid phone number</small>
                            <?php endif; ?>
                        </div>
                        <div class="signup-form__group">
                            <label class="signup-form__label" for="address">Address</label>
                            <input class="signup-form__input <?= isset($errors['address']) ? 'is-invalid' : '' ?>" type="text" id="address" name="address"
                                   placeholder="123 Main St" value="<?= e($address ?? '') ?>" required>
                            <?php if (isset($errors['address'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['address']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="signup-form__group">
                        <label class="signup-form__label" for="password">Security Password</label>
                        <div class="signup-form__pw-wrap">
                            <input class="signup-form__input <?= isset($errors['password']) ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
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
                        <?php if (isset($errors['password'])): ?>
                            <span class="field-error">⚠️ <?= e($errors['password']) ?></span>
                        <?php endif; ?>
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
                            <input class="signup-form__input <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" type="password" id="confirm_password"
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
                        <?php if (isset($errors['confirm_password'])): ?>
                            <span class="field-error">⚠️ <?= e($errors['confirm_password']) ?></span>
                        <?php endif; ?>
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
                            <input class="signup-form__input <?= isset($errors['license_no']) ? 'is-invalid' : '' ?>" type="text" id="license_no" name="license_no"
                                   placeholder="DL-000000" value="<?= e($license_no ?? '') ?>" required>
                            <?php if (isset($errors['license_no'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['license_no']) ?></span>
                            <?php endif; ?>
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
                                <select class="signup-form__select <?= isset($errors['license_type']) ? 'is-invalid' : '' ?>" id="license_type" name="license_type" required>
                                    <option value="">Select Type</option>
                                    <option value="A" <?= (isset($license_type) && $license_type=="A") ? "selected" : "" ?>>A — Motorcycles &amp; Scooters</option>
                                    <option value="B" <?= (isset($license_type) && $license_type=="B") ? "selected" : "" ?>>B — Cars, Jeeps, Vans</option>
                                    <option value="C" <?= (isset($license_type) && $license_type=="C") ? "selected" : "" ?>>C — Commercial Heavy</option>
                                    <option value="D" <?= (isset($license_type) && $license_type=="D") ? "selected" : "" ?>>D — Public Service</option>
                                    <option value="E" <?= (isset($license_type) && $license_type=="E") ? "selected" : "" ?>>E — Heavy with Trailers</option>
                                </select>
                            </div>
                            <?php if (isset($errors['license_type'])): ?>
                                <span class="field-error">⚠️ <?= e($errors['license_type']) ?></span>
                            <?php endif; ?>
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
                        <?php if (isset($errors['terms'])): ?>
                            <span class="field-error" style="display:block; width:100%; margin-top:4px;">⚠️ <?= e($errors['terms']) ?></span>
                        <?php endif; ?>
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
        
        if (passwordInput.value === '') {
            return; // Let HTML5 validation handle required fields naturally
        }
        
        if (!allValid) {
            e.preventDefault();
            e.stopImmediatePropagation();
            let banner = document.getElementById('signup-error-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'signup-error-banner';
                banner.className = 'error-message';
                banner.style.padding = '10px';
                banner.style.background = 'rgba(244, 67, 54, 0.1)';
                banner.style.borderRadius = '6px';
                banner.style.marginBottom = '15px';
                banner.style.fontSize = '14px';
                const heading = document.querySelector('.signup-heading');
                heading.parentNode.insertBefore(banner, heading.nextSibling);
            }
            banner.textContent = 'Please ensure your password meets all requirements.';
            return false;
        }
        
        if (confirmPasswordInput.value.length > 0 && !passwordsMatch) {
            e.preventDefault();
            e.stopImmediatePropagation();
            let banner = document.getElementById('signup-error-banner');
            if (!banner) {
                banner = document.createElement('div');
                banner.id = 'signup-error-banner';
                banner.className = 'error-message';
                banner.style.padding = '10px';
                banner.style.background = 'rgba(244, 67, 54, 0.1)';
                banner.style.borderRadius = '6px';
                banner.style.marginBottom = '15px';
                banner.style.fontSize = '14px';
                const heading = document.querySelector('.signup-heading');
                heading.parentNode.insertBefore(banner, heading.nextSibling);
            }
            banner.textContent = 'Passwords do not match.';
            return false;
        }
    });
}

// Global submit handler to prevent double-clicks on all steps
const signupForm = document.querySelector('.signup-form');
if (signupForm) {
    signupForm.addEventListener('submit', function(e) {
        // Use timeout to allow native and custom validations to run first
        setTimeout(() => {
            if (!e.defaultPrevented) {
                const submitBtn = this.querySelector('.signup-form__submit');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.7';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.innerHTML = 'Processing...';
                }
            }
        }, 10);
    });
}

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

<script src="../../assets/js/loading.js?v=<?= time() ?>"></script>
</body>
</html>