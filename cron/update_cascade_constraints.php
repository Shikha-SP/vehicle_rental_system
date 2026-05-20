<?php
// cron/update_cascade_constraints.php
// This script updates existing foreign key constraints in the database to use ON DELETE CASCADE.

require_once __DIR__ . '/../config/db.php';

echo "Starting DB Migration: Updating foreign key constraints for cascading deletions...\n";

// Helper function to safely drop a constraint if it exists and add a new one
function alterConstraint($conn, $table, $constraintName, $dropSql, $addSql) {
    // Check if constraint exists
    $check = $conn->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
        WHERE TABLE_SCHEMA = 'vehicle_rental_db' 
          AND TABLE_NAME = '$table' 
          AND CONSTRAINT_NAME = '$constraintName'
    ");
    
    if ($check && $check->num_rows > 0) {
        if ($conn->query($dropSql) === TRUE) {
            echo "[SUCCESS] Dropped constraint '$constraintName' from '$table'.\n";
        } else {
            echo "[ERROR] Failed to drop constraint '$constraintName' from '$table': " . $conn->error . "\n";
            return false;
        }
    }
    
    if ($conn->query($addSql) === TRUE) {
        echo "[SUCCESS] Added updated constraint to '$table'.\n";
        return true;
    } else {
        echo "[ERROR] Failed to add updated constraint to '$table': " . $conn->error . "\n";
        return false;
    }
}

// 1. Transactions Constraints
echo "\n--- Updating transactions table constraints ---\n";
alterConstraint(
    $conn,
    'transactions',
    'transactions_ibfk_1',
    "ALTER TABLE transactions DROP FOREIGN KEY transactions_ibfk_1",
    "ALTER TABLE transactions ADD CONSTRAINT transactions_ibfk_1 FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE"
);

alterConstraint(
    $conn,
    'transactions',
    'transactions_ibfk_2',
    "ALTER TABLE transactions DROP FOREIGN KEY transactions_ibfk_2",
    "ALTER TABLE transactions ADD CONSTRAINT transactions_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
);

// 2. Reviews Constraints
echo "\n--- Updating reviews table constraints ---\n";
alterConstraint(
    $conn,
    'reviews',
    'reviews_ibfk_1',
    "ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_1",
    "ALTER TABLE reviews ADD CONSTRAINT reviews_ibfk_1 FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE"
);

alterConstraint(
    $conn,
    'reviews',
    'reviews_ibfk_2',
    "ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_2",
    "ALTER TABLE reviews ADD CONSTRAINT reviews_ibfk_2 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
);

alterConstraint(
    $conn,
    'reviews',
    'reviews_ibfk_3',
    "ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_3",
    "ALTER TABLE reviews ADD CONSTRAINT reviews_ibfk_3 FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE"
);

// 3. Review Replies Constraints
echo "\n--- Updating review_replies table constraints ---\n";
alterConstraint(
    $conn,
    'review_replies',
    'review_replies_ibfk_2',
    "ALTER TABLE review_replies DROP FOREIGN KEY review_replies_ibfk_2",
    "ALTER TABLE review_replies ADD CONSTRAINT review_replies_ibfk_2 FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE"
);

// 4. Notification Preference Constraints
echo "\n--- Updating notification_preference table constraints ---\n";
alterConstraint(
    $conn,
    'notification_preference',
    'notification_preference_ibfk_1',
    "ALTER TABLE notification_preference DROP FOREIGN KEY notification_preference_ibfk_1",
    "ALTER TABLE notification_preference ADD CONSTRAINT notification_preference_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"
);

echo "\nMigration complete!\n";
?>
