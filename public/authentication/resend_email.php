<?php
require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

$success = false;
$errors  = [];

$email = $_GET['email'] ?? '';
$email = strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email address.";
} else {
    // Check if user exists
    $stmt = mysqli_prepare($conn, "SELECT id, first_name, last_name, is_verified FROM users WHERE email=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && mysqli_num_rows($result) === 1) {
        $user = mysqli_fetch_assoc($result);

        if ($user['is_verified']) {
            $errors[] = "Your account is already verified. You can login.";
        } else {
            // Generate new token
            $verification_token = bin2hex(random_bytes(32));
            $token_expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Update user record
            $update = mysqli_prepare($conn, 
                "UPDATE users SET verification_token=?, token_expires_at=? WHERE id=?"
            );
            mysqli_stmt_bind_param($update, "ssi", $verification_token, $token_expires_at, $user['id']);
            mysqli_stmt_execute($update);

            // Send verification email
            $verify_url = "http://localhost/vehicle_rental_collab_project/public/authentication/verify.php?token=" . $verification_token;

            try {
                $mail = createMailer();
                $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
                $mail->Subject = 'Verify your email address';
                $mail->isHTML(true);
                $mail->Body = "
                    <p>Hi {$user['first_name']},</p>
                    <p>Please verify your email by clicking the link below. This link expires in <strong>24 hours</strong>.</p>
                    <p><a href='{$verify_url}'>{$verify_url}</a></p>
                    <p>If you didn't create an account, ignore this email.</p>
                ";
                $mail->AltBody = "Hi {$user['first_name']}, verify your email here: {$verify_url} (expires in 24 hours)";
                $mail->send();

                $success = true;
            } catch (Exception $e) {
                $errors[] = "Could not send verification email. Please try again later.";
            }
        }

    } else {
        $errors[] = "No account found with this email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <title>Resend Verification Email</title>

    <!-- ✅ CSS LINK -->
    <link rel="stylesheet" href="../../assets/css/resend_verification_email.css">
</head>
<body>

<div class="container">

    <h1>Resend Verification Email</h1>

    <?php if ($success): ?>
        <p class="success">
            ✅ A new verification email has been sent to 
            <strong><?= e($email) ?></strong>. Check your inbox!
        </p>
        <a href="login.php">Back to Login</a>

    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a href="login.php">Back to Login</a>

    <?php endif; ?>

</div>

</body>
</html>