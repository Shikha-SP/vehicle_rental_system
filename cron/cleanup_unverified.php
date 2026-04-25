<?php
// Include the database configuration file to establish a connection
require_once __DIR__ . '/../config/db.php';

// Prepare a query to delete users who have not verified their accounts 
// and whose verification token has already expired
$stmt = mysqli_prepare($conn,
    "DELETE FROM users WHERE is_verified = 0 AND token_expires_at < NOW()"
);

// Execute the delete query
mysqli_stmt_execute($stmt);

// Get the number of rows (users) that were deleted
$deleted = mysqli_stmt_affected_rows($stmt);

// Log the result with the current timestamp and the number of deleted accounts
echo date('Y-m-d H:i:s') . " — Deleted {$deleted} expired unverified account(s)\n";
