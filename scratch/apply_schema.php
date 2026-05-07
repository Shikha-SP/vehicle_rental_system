<?php
require_once '../config/db.php';
$conn->query("ALTER TABLE users ADD COLUMN status ENUM('active', 'banned', 'timeout') DEFAULT 'active'");
$conn->query("ALTER TABLE users ADD COLUMN ban_expires_at DATETIME DEFAULT NULL");
echo "Done";
?>
