<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vehicle Rental System</title>
</head>
<body>

<header>
    <nav>
        <a href="/vehicle_rental_collab_project/public/landing_page.php">Home</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <span>Welcome, <?= htmlspecialchars($_SESSION['username']) ?></span>

            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="/vehicle_rental_collab_project/public/admin/home_page.php">Admin Dashboard</a>
            <?php else: ?>
                <a href="/vehicle_rental_collab_project/public/user/home_page.php">Dashboard</a>
            <?php endif; ?>

            <a href="/vehicle_rental_collab_project/public/authentication/logout.php">Logout</a>

        <?php else: ?>
            <a href="/vehicle_rental_collab_project/public/authentication/login.php">Login</a>
            <a href="/vehicle_rental_collab_project/public/authentication/signup.php">Sign Up</a>
        <?php endif; ?>
    </nav>
</header>