<?php
// cron/apply_reminder_schema.php
// This is a one-time migration script for the Booking Reminder System.

require_once __DIR__ . '/../config/db.php';

echo "Starting DB Migration...\n";

// 1. Add pickup_time to bookings
$sql1 = "ALTER TABLE bookings ADD COLUMN IF NOT EXISTS pickup_time TIME DEFAULT '09:00:00'";
if ($conn->query($sql1) === TRUE) {
    echo "[SUCCESS] Added 'pickup_time' column to 'bookings' table.\n";
} else {
    echo "[ERROR] Failed to add 'pickup_time' column: " . $conn->error . "\n";
}

// 2. Create the reminder log table
$sql2 = "CREATE TABLE IF NOT EXISTS reminder_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    booking_id      INT          NOT NULL,
    reminder_type   ENUM('email','sms','banner') NOT NULL,
    sent_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_booking_type (booking_id, reminder_type),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
)";
if ($conn->query($sql2) === TRUE) {
    echo "[SUCCESS] Created 'reminder_log' table.\n";
} else {
    echo "[ERROR] Failed to create 'reminder_log' table: " . $conn->error . "\n";
}

echo "Migration complete.\n";
?>
