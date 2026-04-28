<?php
session_start();
echo "--- SESSION DATA ---\n";
print_r($_SESSION);
echo "\n--- DATABASE CHECK ---\n";
require_once 'config/db.php';
if (isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $res = $conn->query("SELECT * FROM users WHERE id = $uid");
    $user = $res->fetch_assoc();
    print_r($user);
} else {
    echo "No user_id in session.\n";
}
?>
