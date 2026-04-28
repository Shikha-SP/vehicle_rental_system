<?php
require_once 'config/db.php';
$res = $conn->query("DESCRIBE users");
echo "Column | Nullable\n";
echo "--- | ---\n";
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " | " . ($row['Null'] == 'YES' ? 'YES' : 'NO') . "\n";
}
?>
