<?php
require_once 'config/db.php';
$check = $conn->query("SHOW COLUMNS FROM bookings LIKE 'status'");
if ($check->num_rows == 0) {
    $res = $conn->query("ALTER TABLE bookings ADD COLUMN status ENUM('confirmed', 'cancelled', 'completed') DEFAULT 'confirmed' AFTER total_price");
    if ($res) {
        echo "SUCCESS: Status column added to bookings.";
    } else {
        echo "ERROR: " . $conn->error;
    }
} else {
    echo "NOTICE: Status column already exists.";
}
?>
