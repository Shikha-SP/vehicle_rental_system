<?php
// logout.php
require_once '../../includes/functions.php';
session_start();

// Two-Step Verification: Ensure the user explicitly confirmed their intention to logout via the UI prompt
// This prevents accidental logouts from pre-fetching or maliciously crafted links
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    // Stage 1: Clear the superglobal session array from memory instantly
    $_SESSION = [];
    // Stage 2: Obliterate the underlying session cookie locally on the client's browser
    // Expiring the cookie prevents session stealing or replay attacks after termination
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Stage 3: Erase the session data file physically from the server disk/memcached
    session_destroy();

    // Redirection Phase: Safely route the now unauthenticated client back to the public domain
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
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/loading.css">
</head>
<body>
    <!-- Aesthetic UI Elements: Animated ambient background specifically designed to maintain the application's premium aesthetic during an exit action -->
    <div class="background-container">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="grid-overlay"></div>
    </div>

    <!-- UI darkening layer to focus attention exclusively on the logout modal dialog -->
    <div class="overlay"></div>

    <!-- Interactive Modal: Requesting explicit confirmation limits abrupt UX interruptions -->
    <div class="logout-box">
        <!-- Visual anchor indicating an exit path -->
        <div class="icon-header">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 3H6a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h4M16 17l5-5-5-5M19.8 12H9" />
            </svg>
        </div>
        
        <h2>Confirm Logout</h2>
        
        <!-- Contextual warning emphasizing the risk of data loss on active forms -->
        <p>You are about to end your session. Any unsaved booking progress may be lost.</p>
        
        <?php 
        $return_url = $_GET['return'] ?? '../user/home_page.php';
        ?>
        <div class="btn-group">
            <!-- Appends the required security parameter `?confirm=yes` to trigger server-side destruction -->
            <a href="logout.php?confirm=yes" class="btn-yes">Logout</a>
            <!-- Safe exit path returning user to their original page intact -->
            <a href="<?= htmlspecialchars($return_url) ?>" class="btn-no">Stay Logged In</a>
        </div>
    </div>
<script src="/vehicle_rental_collab_project/assets/js/loading.js"></script>
</body>
</html>