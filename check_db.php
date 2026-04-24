<?php
require 'config/db.php';
echo "--- VEHICLES ---\n";
$resV = $conn->query("SELECT id, model, status FROM vehicles");
while($v = $resV->fetch_assoc()) {
    echo "ID: {$v['id']} | Model: {$v['model']} | Status: {$v['status']}\n";
}

echo "\n--- BOOKINGS ---\n";
$resB = $conn->query("SELECT * FROM bookings");
while($b = $resB->fetch_assoc()) {
    echo "ID: {$b['id']} | Vehicle ID: {$b['vehicle_id']} | Start: {$b['start_date']} | End: {$b['end_date']}\n";
}

echo "\n--- CURRENT DATE ---\n";
$resD = $conn->query("SELECT CURDATE()");
$d = $resD->fetch_row();
echo "MySQL CURDATE(): " . $d[0] . "\n";
echo "PHP date('Y-m-d'): " . date('Y-m-d') . "\n";
?>
