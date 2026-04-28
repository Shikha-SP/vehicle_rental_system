<?php
require_once 'config/db.php';
$res = $conn->query("SELECT first_name FROM users");
while($row = $res->fetch_assoc()) {
    echo "[" . $row['first_name'] . "]\n";
}
?>
