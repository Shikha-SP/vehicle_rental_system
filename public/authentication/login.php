<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

$theme = 'light';
if (isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark') {
    $theme = 'dark';
}

// Redirect logged-in users to the correct homepage based on admin status
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

    $email = strtolower(trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)));
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($password)) $errors[] = "Password is required.";

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id, first_name, password, is_admin FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && mysqli_num_rows($result) === 1) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['first_name'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];
                $_SESSION['csrf_token'] = generateCsrfToken();

                // Redirect based on admin status
                if ($user['is_admin']) {
                    redirect('../admin/home_page.php');
                } else {
                    redirect('../user/home_page.php');
                }

            } else {
                $errors[] = "Incorrect password.";
            }
        } else {
            $errors[] = "No account found with this email.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
</head>
<body>
    <h1>Login</h1>
    <?php if (!empty($errors)): ?>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
        <input type="email" name="email" placeholder="Email" value="<?= e($email ?? '') ?>" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <button type="submit">Login</button>
    </form>
    <a href="signup.php">Don't have an account? Sign up</a>
</body>
</html>