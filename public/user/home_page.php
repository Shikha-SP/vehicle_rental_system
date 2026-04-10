<?php
/**
 * User Home Page (Dashboard)
 * 
 * This is the main dashboard for regular users after they log in. 
 * It provides navigation links to view available vehicles and manage their own rentals/bookings.
 * Access is restricted to non-admin logged-in users.
 */
session_start();

// Ensure the user is logged in and is NOT an admin.
// Redirects unauthorized users to the landing page to prevent unauthorized access.
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

// Retrieve the user's username for personalized greeting in the UI
$username = $_SESSION['username'] ?? 'User';
?>

<?php require_once '../../includes/header.php'; ?>
<main>
<!-- Personalized welcome message for the authenticated user -->
<h1>Welcome, <?= htmlspecialchars($username) ?></h1>

<ul>
    <!-- Navigation links for main user actions -->
    <li><a href="../vehicle/add.php">View Vehicles</a></li>
    <li><a href="../vehicle/edit.php">My Rentals / Bookings</a></li>
</ul>
</main>
<?php require_once '../../includes/footer.php'; ?>