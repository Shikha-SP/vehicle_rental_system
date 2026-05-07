<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../authentication/login.php');
}

if (!isset($_SESSION['restricted_user_id'])) {
    redirect('../authentication/login.php');
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    die("Security error: Invalid CSRF token.");
}

$user_id = $_SESSION['restricted_user_id'];
$message = trim($_POST['message'] ?? '');

if (!empty($message)) {
    $stmt = $conn->prepare("INSERT INTO user_inquiries (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();
    
    // Store success message in session or just show simple page
    echo "<script>
        alert('Your appeal has been sent to the administration. Please wait for a response.');
        window.location.href = '../authentication/restricted.php';
    </script>";
    exit;
} else {
    echo "<script>
        alert('Please enter a message.');
        window.history.back();
    </script>";
    exit;
}
?>
