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
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="grid-overlay"></div>
    </div>

    <div class="overlay"></div>

    <div class="logout-box">
        <div class="icon-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4M16 17l5-5-5-5M19.8 12H9" />
            </svg>
        </div>
        <h2>Confirm Logout</h2>
        <p>You are about to end your session. Any unsaved booking progress may be lost.</p>
        
        <div class="btn-group">
            <a href="logout.php?confirm=yes" class="btn-yes">Logout</a>
            <a href="../user/home_page.php" class="btn-no">Stay Logged In</a>
        </div>
    </div>
</body>
</html>