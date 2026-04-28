<?php
require_once '../../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    exit;
}

// Fetch current vehicle's license type as fallback
$sql = "SELECT license_type FROM vehicles WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    exit;
}

$recommended_vehicles = [];
$ai_enabled = false;

// Call the Python Recommender
$python_path = "python"; // Adjust if necessary
$script_path = realpath(__DIR__ . '/../../AI/get_recommendations.py');
$command = escapeshellcmd("$python_path \"$script_path\" \"$id\"");
$output = shell_exec($command);

if ($output) {
    $recommended_ids = json_decode($output, true);
    if ($recommended_ids && is_array($recommended_ids) && !isset($recommended_ids['error'])) {
        $ai_enabled = true;
        if (!empty($recommended_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($recommended_ids), '?'));
            $rec_sql = "SELECT * FROM vehicles WHERE id IN ($ids_placeholder) AND status IN ('available', 'approved')";
            $rec_stmt = $conn->prepare($rec_sql);
            $rec_stmt->bind_param(str_repeat('i', count($recommended_ids)), ...$recommended_ids);
            $rec_stmt->execute();
            $recommended_vehicles = $rec_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

// Fallback: If AI fails or no recommendations, get same license category vehicles
if (empty($recommended_vehicles)) {
    $fallback_sql = "SELECT * FROM vehicles WHERE license_type = ? AND id != ? AND status IN ('available', 'approved') LIMIT 4";
    $fb_stmt = $conn->prepare($fallback_sql);
    $fb_stmt->bind_param("si", $vehicle['license_type'], $id);
    $fb_stmt->execute();
    $recommended_vehicles = $fb_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $ai_enabled = false;
}
?>

<div class="ai-header">
    <div>
        <?php if ($ai_enabled): ?>
            <div class="ai-tag"><i class="fas fa-robot"></i> AI Powered</div>
        <?php endif; ?>
        <h2>Recommended For You</h2>
        <p>Based on performance specs and category matching</p>
    </div>
    <a href="vehicles.php" class="owner-pill">View All Vehicles →</a>
</div>

<div class="recommendations-grid">
    <?php foreach ($recommended_vehicles as $rec): ?>
        <a href="vehicle_detail.php?id=<?= $rec['id'] ?>" class="rec-card">
            <div class="rec-image">
                <img src="../../<?= htmlspecialchars($rec['image_path'] ?? 'assets/images/placeholder.png') ?>" alt="<?= htmlspecialchars($rec['model']) ?>">
                <div class="rec-badge"><?= htmlspecialchars(strtoupper($rec['license_type'])) ?></div>
            </div>
            <div class="rec-info">
                <h3><?= htmlspecialchars($rec['model']) ?></h3>
                <div class="rec-meta">
                    <span><i class="fas fa-cog"></i> <?= htmlspecialchars($rec['transmission']) ?></span>
                    <span><i class="fas fa-gas-pump"></i> <?= htmlspecialchars($rec['fuel_type']) ?></span>
                </div>
                <div class="rec-price">
                    <div>
                        <span class="unit">NPR</span>
                        <span class="amount"><?= number_format($rec['price_per_day'], 0) ?></span>
                        <span class="unit">/ day</span>
                    </div>
                </div>
            </div>
        </a>
    <?php endforeach; ?>

    <?php if (empty($recommended_vehicles)): ?>
        <p class="secure-note">No similar vehicles found at the moment.</p>
    <?php endif; ?>
</div>
