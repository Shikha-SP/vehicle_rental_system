<?php
require 'config/db.php';
$res = $conn->query("SELECT id, vehicle_id, start_date, end_date, status FROM bookings");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
$res->close();
$conn->close();
?>
