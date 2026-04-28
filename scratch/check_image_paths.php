<?php
require_once 'config/db.php';
$res = $conn->query("SELECT model, image_path FROM vehicles LIMIT 5");
echo "Model | Image Path\n";
echo "--- | ---\n";
while($row = $res->fetch_assoc()) {
    echo $row['model'] . " | " . $row['image_path'] . "\n";
}
?>
