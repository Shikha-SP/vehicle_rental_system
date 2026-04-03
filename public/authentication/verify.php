<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

$token  = trim($_GET['token'] ?? '');
$status = 'invalid';

if (!empty($token)) {
    $stmt = mysqli_prepare($conn,
        "SELECT id, first_name, is_verified, token_expires_at FROM users WHERE verification_token = ?"
    );
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    mysqli_stmt_bind_result($stmt, $user_id, $first_name, $is_verified, $expires_at);
    mysqli_stmt_fetch($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        $status = 'invalid';
    } elseif ($is_verified) {
        $status = 'already_verified';
    } elseif (strtotime($expires_at) < time()) {
        mysqli_stmt_close($stmt);
        $del = mysqli_prepare($conn, "DELETE FROM users WHERE verification_token = ?");
        mysqli_stmt_bind_param($del, "s", $token);
        mysqli_stmt_execute($del);
        $status = 'expired';
    } else {
        mysqli_stmt_close($stmt);
        $upd = mysqli_prepare($conn,
            "UPDATE users SET is_verified = 1, verification_token = NULL, token_expires_at = NULL WHERE id = ?"
        );
        mysqli_stmt_bind_param($upd, "i", $user_id);
        if (mysqli_stmt_execute($upd)) {
            $status = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification</title>

    <!-- ✅ CSS LINK -->
    <link rel="stylesheet" href="../../assets/css/verification.css">
</head>
<body>

<div class="container 
    <?= $status === 'success' ? 'success' : 
        ($status === 'expired' ? 'warning' : 'error') ?>">

    <?php if ($status === 'success'): ?>
        <h1>Email Verified!</h1>
        <p>Hi <?= e($first_name) ?>, your account is now active.</p>
        <a href="login.php">Log in now</a>

    <?php elseif ($status === 'already_verified'): ?>
        <h1>Already Verified</h1>
        <p>Your email is already verified. <a href="login.php">Log in</a></p>

    <?php elseif ($status === 'expired'): ?>
        <h1>Link Expired!</h1>
        <p>
            This link expired after 24 hours and your account has been removed.
            Please <a href="signup.php">sign up again</a>.
        </p>

    <?php else: ?>
        <h1>Invalid Link</h1>
        <p>This verification link is invalid. <a href="signup.php">Sign up</a></p>
    <?php endif; ?>

</div>

</body>
</html>