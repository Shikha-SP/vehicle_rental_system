<?php
session_start();

// Ensure the user is logged in and is NOT admin
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
?>

<?php require_once '../../includes/header.php'; ?>
<main>

<ul>
    <li><a href="../vehicle/add.php">View Your Bookings</a></li>
    
</ul>
</main>
<?php require_once '../../includes/footer.php'; ?>