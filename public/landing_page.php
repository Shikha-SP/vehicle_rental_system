<?php
// landing_page.php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_admin'])) {
        header("Location: admin/home_page.php");
        exit;
    } else {
        header("Location: user/home_page.php");
        exit;
    }
}
?>

<?php require_once '../includes/header.php'; ?>

<h1>Welcome to Vehicle Rental System</h1>
<p>
    Please 
    <a href="authentication/login.php">Login</a> 
    or 
    <a href="authentication/signup.php">Sign Up</a>
</p>

<?php require_once '../includes/footer.php'; ?>