<?php
require 'config/db.php';
$res = $conn->query('SELECT booking_id, COUNT(*) as count FROM reviews GROUP BY booking_id HAVING count > 1');
if ($res) {
    while($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
?>
