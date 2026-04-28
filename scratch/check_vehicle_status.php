<?php
require_once 'config/db.php';
$res = $conn->query("SELECT status, COUNT(*) as count FROM vehicles GROUP BY status");
echo "Status | Count\n";
echo "--- | ---\n";
while($row = $res->fetch_assoc()) {
    echo $row['status'] . " | " . $row['count'] . "\n";
}
?>
