<?php
require_once __DIR__ . '/../config/db.php';

$stmt = mysqli_prepare($conn,
    "DELETE FROM users WHERE is_verified = 0 AND token_expires_at < NOW()"
);
mysqli_stmt_execute($stmt);
$deleted = mysqli_stmt_affected_rows($stmt);
echo date('Y-m-d H:i:s') . " — Deleted {$deleted} expired unverified account(s)\n";