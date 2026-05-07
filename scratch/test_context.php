<?php
require_once __DIR__ . '/../config/db.php';
$userId = 2; // Assuming user 2
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_price),0) as total_spent,
           COUNT(*) as total_bookings
    FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'completed', 'cancelled')
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$spendingRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$totalSpent    = number_format($spendingRow['total_spent'], 2);
$totalBookings = $spendingRow['total_bookings'];
echo "ACCOUNT SUMMARY: {$totalBookings} total booking(s), NPR {$totalSpent} total spent.\n";
