<?php
/**
 * My Vehicles Page (Renter Dashboard)
 * 
 * Displays all vehicles that the current logged-in user has listed for rent.
 * Provides a UI to view vehicle statuses (pending, approved, rejected) and 
 * actions to manage them (edit, remove).
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is a standard user (not an admin)
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all vehicles owned by the current user to display in the list
$sql = "SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$pageTitle = 'My Fleet – TD Rentals';
require_once '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/my_vehicles.css">

<main class="my-vehicles">
    <h1 style="font-family:'Bebas Neue',sans-serif; font-size: 5rem; margin-bottom: 1rem; letter-spacing: 0.02em;">MY FLEET</h1>
    <p style="color: #666; margin-bottom: 3.5rem; font-weight: 500;">Manage your listed high-performance vehicles</p>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?>">
            <?= $_SESSION['message'] ?>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <div class="vehicles-grid">
            <?php while ($vehicle = $result->fetch_assoc()): 
                $img = $vehicle['image_path'];
                $src = (strpos($img, 'http') === 0) ? $img : '../../' . $img;
            ?>
                <div class="vehicle-card">
                    <div class="card-banner">
                        <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($vehicle['model']) ?>" onerror="this.src='../../assets/images/car-placeholder.png'">
                        <?php if ($vehicle['status'] === 'approved'): ?>
                            <div class="status-badge status-approved">LIVE • APPROVED</div>
                        <?php elseif ($vehicle['status'] === 'pending'): ?>
                            <div class="status-badge status-pending">IN REVIEW</div>
                        <?php elseif ($vehicle['status'] === 'rejected'): ?>
                            <div class="status-badge status-rejected">REJECTED</div>
                        <?php endif; ?>
                        <div class="price-tag">NPR <?= number_format($vehicle['price_per_day'], 0) ?> <span style="font-weight:400; font-size:0.7rem; opacity:0.6;">/ DAY</span></div>
                    </div>
                    
                    <div class="card-content">
                        <h3><?= htmlspecialchars($vehicle['model']) ?></h3>
                        
                        <div class="card-specs">
                            <div class="spec-pill">⚙️ <?= htmlspecialchars($vehicle['transmission'] ?? 'N/A') ?></div>
                            <div class="spec-pill">⛽ <?= htmlspecialchars($vehicle['fuel_type'] ?? 'N/A') ?></div>
                            <div class="spec-pill">⚡ <?= htmlspecialchars($vehicle['top_speed'] ?? 'N/A') ?> KM/H</div>
                        </div>

                        <div class="vehicle-info-block">
                            <div class="info-row">
                                <span>COLOR</span>
                                <span class="info-val">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($vehicle['color'] ?? '#ccc') ?>;border:1px solid rgba(255,255,255,0.15);margin-right:6px;vertical-align:middle;"></span>
                                    <?= htmlspecialchars($vehicle['color'] ?? 'N/A') ?>
                                </span>
                            </div>
                            <div class="info-row">
                                <span>LICENSE TYPE</span>
                                <span class="info-val">CLASS <?= htmlspecialchars($vehicle['license_type'] ?? 'N/A') ?></span>
                            </div>
                            <div class="info-row">
                                <span>LISTED ON</span>
                                <span class="info-val" style="color:var(--red)"><?= date('M d, Y', strtotime($vehicle['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="footer-actions">
                            <?php if ($vehicle['status'] === 'pending'): ?>
                                <a href="edit_renter_vehicle.php?id=<?= $vehicle['id'] ?>" class="btn-action btn-edit">EDIT SPECS</a>
                                <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn-action btn-remove">WITHDRAW</button>
                            <?php elseif ($vehicle['status'] === 'approved'): ?>
                                <a href="../vehicle/vehicle_detail.php?id=<?= $vehicle['id'] ?>" class="btn-action btn-view">VIEW LISTING</a>
                                <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn-action btn-remove">TAKE OFFLINE</button>
                            <?php elseif ($vehicle['status'] === 'rejected'): ?>
                                <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn-action btn-remove">DISCARD ENTRY</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">🏎️</div>
            <h2>NO LISTINGS YET</h2>
            <p>Your fleet is currently empty. Start monetizing your high-performance vehicles today.</p>
            <a href="../renter/list_Car.php" class="btn btn-primary" style="padding: 1rem 3rem; margin-top:20px;">List Your First Vehicle</a>
        </div>
    <?php endif; ?>
</main>

<script>
function confirmDelete(vehicleId) {
    if (confirm('Are you sure you want to remove this vehicle from your listings? This action cannot be undone.')) {
        window.location.href = 'delete_renter_vehicle.php?id=' + vehicleId;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>