<?php
require_once 'config/db.php';

echo "Starting Database Migration...\n";

// 1. Fill existing NULLs with empty strings to allow NOT NULL conversion
$queries = [
    "UPDATE users SET address = '' WHERE address IS NULL",
    "UPDATE users SET phone_number = '' WHERE phone_number IS NULL",
    "UPDATE users SET license_number = '' WHERE license_number IS NULL",
    "UPDATE users SET license_type = '' WHERE license_type IS NULL",
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Successfully updated existing records for query: $q\n";
    } else {
        echo "Error updating records: " . $conn->error . "\n";
    }
}

// 2. Alter table to NOT NULL
$alter_queries = [
    "ALTER TABLE users MODIFY COLUMN address VARCHAR(255) NOT NULL",
    "ALTER TABLE users MODIFY COLUMN phone_number VARCHAR(15) NOT NULL",
    "ALTER TABLE users MODIFY COLUMN license_number VARCHAR(50) NOT NULL",
    "ALTER TABLE users MODIFY COLUMN license_type VARCHAR(10) NOT NULL",
];

foreach ($alter_queries as $q) {
    if ($conn->query($q)) {
        echo "Successfully altered table for query: $q\n";
    } else {
        echo "Error altering table: " . $conn->error . "\n";
    }
}

echo "Migration Complete.\n";
?>
