<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';
session_start();
file_put_contents('../../session_debug.txt', print_r($_SESSION, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../info/contact.php');
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!empty($name) && !empty($email) && !empty($message)) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Please provide a valid email address.'); window.history.back();</script>";
        exit;
    }

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    $stmt = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $name, $email, $message);
    
    if ($stmt->execute()) {
        echo "<script>alert('Thank you for contacting us! We will get back to you soon.'); window.location.href = '../info/contact.php';</script>";
    } else {
        echo "<script>alert('Something went wrong. Please try again later.'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('Please fill out all fields.'); window.history.back();</script>";
}
exit;
?>
