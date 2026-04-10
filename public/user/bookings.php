<?php
/**
 * User Bookings Display Page
 * 
 * This page allows logged-in users (non-admins) to view their vehicle bookings.
 * It ensures the user is authenticated before displaying the content.
 */
session_start();

// Ensure the user is logged in and is NOT an admin.
// If the user is an admin or not logged in, redirect them to the landing page.
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

// Retrieve the username from the session, default to 'User' if not set
$username = $_SESSION['username'] ?? 'User';
?>

<?php require_once '../../includes/header.php'; ?>
<main>

<ul>
    <!-- Link for the user to view their bookings -->
    <li><a href="../vehicle/add.php">View Your Bookings</a></li>
    
</ul>
</main>
<?php require_once '../../includes/footer.php'; ?>