<?php
/**
 * Reset Password Handler
 * 
 * This file allows a user to set a new password after clicking the link 
 * in their "Forgot Password" email. It verifies the token before allowing 
 * a password reset to ensure the request is legitimate.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// XSS Mitigation Fallback: Ensure the character escaping routine exists in the global scope 
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

$token = $_GET['token'] ?? '';
$valid_token = false;
$user_id = null;
$errors = [];
$success = false;

// Step 1: Token Validation
// Verify that the reset token provided in the URL is valid, hasn't been used yet, 
// and hasn't expired (tokens generally expire to limit exposure windows).
if (empty($token)) {
    $errors[] = "Invalid password reset link.";
} else {
    $stmt = mysqli_prepare($conn, 
        "SELECT pr.user_id, pr.token, u.email, u.first_name 
         FROM password_resets pr
         JOIN users u ON pr.user_id = u.id
         WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW() 
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($reset = mysqli_fetch_assoc($result)) {
        $valid_token = true;
        $user_id = $reset['user_id'];
    } else {
        $errors[] = "This password reset link is invalid or has expired.";
    }
}

// Step 2: Form Processing
// If the token is valid and the user submits the new password form, process the update.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter.";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update the user's password in the database securely using a prepared statement
        $update = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($update, "si", $hashed_password, $user_id);
        
        if (mysqli_stmt_execute($update)) {
            // Security measure: Mark the token as used so it cannot be reused (prevents replay attacks)
            $mark_used = mysqli_prepare($conn, "UPDATE password_resets SET used = 1 WHERE token = ?");
            mysqli_stmt_bind_param($mark_used, "s", $token);
            mysqli_stmt_execute($mark_used);
            
            $success = true;
        } else {
            $errors[] = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — TD Rentals</title>
    <link rel="stylesheet" href="../../assets/css/login.css">
    <style>
        .login-success {
            background: #d4edda;
            color: #155724;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
        .login-success a {
            color: #155724;
            font-weight: bold;
            text-decoration: underline;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 8px;
            line-height: 1.4;
        }
    </style>
</head>
<body>

<div class="login-page">
    <nav class="login-nav">
        <a href="../../public/landing_page.php" class="login-nav__logo">TD Rentals</a>
        <a href="signup.php" class="login-nav__signup">Create Account</a>
    </nav>

    <main class="login-main">
        <div class="login-card">
            <div class="login-card__rule"></div>
            
            <div class="login-card__heading">
                <span>Create New</span>
                <span>Password</span>
            </div>
            <p class="login-card__sub">Enter your new password below.</p>
            
            <?php if ($success): ?>
                <div class="login-success">
                    <p><strong>Success!</strong></p>
                    <p>Your password has been updated.</p>
                    <p style="margin-top: 1rem;">
                        <a href="login.php">Log in with your new password →</a>
                    </p>
                </div>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <ul class="login-errors">
                        <?php foreach ($errors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if ($valid_token): ?>
                    <form class="login-form" method="POST" novalidate>
                        <div class="login-form__group">
                            <label class="login-form__label" for="password">New Password</label>
                            <input class="login-form__input" type="password" id="password" name="password"
                                   placeholder="••••••••" required autocomplete="new-password">
                            <div class="password-requirements">
                                • At least 8 characters<br>
                                • At least 1 uppercase letter<br>
                                • At least 1 number
                            </div>
                        </div>
                        
                        <div class="login-form__group">
                            <label class="login-form__label" for="confirm_password">Confirm Password</label>
                            <input class="login-form__input" type="password" id="confirm_password" name="confirm_password"
                                   placeholder="••••••••" required autocomplete="new-password">
                        </div>
                        
                        <button class="login-form__submit" type="submit">
                            Reset Password
                        </button>
                    </form>
                <?php else: ?>
                    <p style="text-align: center; margin-top: 1rem;">
                        <a href="forgot_password.php">Request a new reset link →</a>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div>

</body>
</html>