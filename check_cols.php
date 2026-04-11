<?php
require_once 'config/db.php';
$res = $conn->query("DESCRIBE vehicles");
$cols = [];
while($row = $res->fetch_assoc()) {
    $cols[] = $row['Field'];
}
echo "VEHICLES_COLUMNS:" . implode(',', $cols);
?>
