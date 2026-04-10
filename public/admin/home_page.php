<?php
// Start a new or resume an existing session to track user state
session_start();

// Authentication and Authorization Check
// Ensure the user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    // If not, redirect them to the landing page and stop further execution
    header("Location: ../landing_page.php");
    exit;
}

// Retrieve the admin's username from the session, default to 'Admin' if not available
$username = $_SESSION['username'] ?? 'Admin';
?>

<!-- Include the site header navigation -->
<?php require_once '../../includes/header.php'; ?>

<!-- Display a personalized welcome message for the admin -->
<h1>Welcome, <?= htmlspecialchars($username) ?> (Admin)</h1>

<!-- Admin Navigation Menu for Vehicle Management Capabilities -->
<ul>
    <li><a href="../vehicle/add.php">Add Vehicle</a></li>
    <li><a href="../vehicle/edit.php">Edit Vehicles</a></li>
    <li><a href="../vehicle/delete.php">Delete Vehicles</a></li>
</ul>

<!-- Include the site footer -->
<?php require_once '../../includes/footer.php'; ?>