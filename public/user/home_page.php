<?php
session_start();

// Ensure the user is logged in and is NOT admin
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($username) ?></h1>
    <p><a href="../authentication/logout.php">Logout</a></p>
    <ul>
        <li><a href="../vehicle/add.php">View Vehicles</a></li>
        <li><a href="../vehicle/edit.php">My Rentals / Bookings</a></li>
    </ul>
</body>
</html>