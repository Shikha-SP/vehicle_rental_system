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
        // Check for existing account with this email
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
                // Unverified old account — delete it so they can re-register
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
                // Email failed — delete the user so they can try again
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
    <title>Sign Up</title>
</head>
<body>
    <h1>Sign Up</h1>

    <?php if ($success): ?>
        <p>✅ Account created! Check your email (<strong><?= e($email) ?></strong>) for a verification link. It expires in 24 hours.</p>
        <a href="login.php">Back to Login</a>

    <?php else: ?>
        <?php if (!empty($errors)): ?>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden"   name="csrf_token"       value="<?= e($_SESSION['csrf_token']) ?>">
            <input type="text"     name="first_name"       placeholder="First Name"       value="<?= e($first_name ?? '') ?>"   required><br>
            <input type="text"     name="last_name"        placeholder="Last Name"        value="<?= e($last_name ?? '') ?>"    required><br>
            <input type="email"    name="email"            placeholder="Email"            value="<?= e($email ?? '') ?>"        required><br>
            <input type="text"     name="address"          placeholder="Address"          value="<?= e($address ?? '') ?>"      required><br>
            <input type="text"     name="phone"            placeholder="Phone"            value="<?= e($phone ?? '') ?>"        required><br>
            <input type="password" name="password"         placeholder="Password"                                               required><br>
            <input type="password" name="confirm_password" placeholder="Confirm Password"                                       required><br>
            <input type="text"     name="license_no"       placeholder="License Number"   value="<?= e($license_no ?? '') ?>"   required><br>
            <select name="license_type" required>
                <option value="">Select License Type</option>
                <option value="A" <?= (isset($license_type) && $license_type=="A") ? "selected" : "" ?>>A - Motorcycles, Scooters, Mopeds</option>
                <option value="B" <?= (isset($license_type) && $license_type=="B") ? "selected" : "" ?>>B - Cars, Jeeps, Vans</option>
                <option value="C" <?= (isset($license_type) && $license_type=="C") ? "selected" : "" ?>>C - Commercial heavy vehicles</option>
                <option value="D" <?= (isset($license_type) && $license_type=="D") ? "selected" : "" ?>>D - Public service vehicles</option>
                <option value="E" <?= (isset($license_type) && $license_type=="E") ? "selected" : "" ?>>E - Heavy vehicles with trailers</option>
            </select><br>
            <button type="submit">Sign Up</button>
        </form>
        <a href="login.php">Already have an account? Login</a>
    <?php endif; ?>
</body>
</html>