<?php
// logout.php
require_once '../../includes/functions.php';
session_start();

// Check if user confirmed logout
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Destroy all session data
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();

    // Redirect to landing page after logout
    redirect('../landing_page.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation</title>

    <!-- CSS -->
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/logout.css">
</head>
<body>

    <!-- Overlay -->
    <div class="overlay"></div>

    <!-- Logout Box -->
    <div class="logout-box">
        <h2>Do you really want to log out?</h2>
        <a href="logout.php?confirm=yes" class="btn-yes">Yes, Log Out</a>
        <a href="../user/home_page.php" class="btn-no">Cancel</a>
    </div>

</body>
</html>