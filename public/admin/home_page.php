<?php
session_start();

// Ensure the user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($username) ?> (Admin)</h1>
    <p><a href="../authentication/logout.php">Logout</a></p>
    <ul>
        <li><a href="../vehicle/add.php">Add Vehicle</a></li>
        <li><a href="../vehicle/edit.php">Edit Vehicles</a></li>
        <li><a href="../vehicle/delete.php">Delete Vehicles</a></li>
    </ul>
</body>
</html>