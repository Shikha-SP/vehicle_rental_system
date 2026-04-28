<?php
require_once 'config/db.php';
$res = $conn->query("DESCRIBE users");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\n";
}
?>
