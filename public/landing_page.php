<?php
// landing_page.php - public landing page
session_start();

// If user is already logged in, redirect based on role
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        header("Location: admin/home_page.php");
        exit;
    } else {
        header("Location: user/home_page.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to Vehicle Rental System</title>
</head>
<body>
    <h1>Welcome to Vehicle Rental System</h1>
    <p>Please <a href="authentication/login.php">Login</a> or <a href="authentication/signup.php">Sign Up</a></p>
</body>
</html>