<?php
require 'config/db.php';

// 1. Add unique constraint to booking_id
$sql = "ALTER TABLE reviews ADD UNIQUE (booking_id)";
if ($conn->query($sql)) {
    echo "Successfully added UNIQUE constraint to booking_id in reviews table.\n";
} else {
    echo "Error adding constraint: " . $conn->error . "\n";
}
?>
