<?php
session_start();

// Ensure the user is logged in and is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
?>

<?php require_once '../../includes/header.php'; ?>

<h1>Welcome, <?= htmlspecialchars($username) ?> (Admin)</h1>

<ul>
    <li><a href="../vehicle/add.php">Add Vehicle</a></li>
    <li><a href="../vehicle/edit.php">Edit Vehicles</a></li>
    <li><a href="../vehicle/delete.php">Delete Vehicles</a></li>
</ul>

<?php require_once '../../includes/footer.php'; ?>