<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch wishlist items
$sql = "SELECT v.*, w.created_at AS added_at 
        FROM wishlist w 
        JOIN vehicles v ON w.vehicle_id = v.id 
        WHERE w.user_id = ? 
        ORDER BY w.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$wishlist = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = "My Wishlist – TD Rentals";
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/style.css">
<link rel="stylesheet" href="../../assets/css/vehicles.css">
<link rel="stylesheet" href="../../assets/css/wishlist.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<main class="vehicles-page">
    <!-- HERO -->
    <section class="page-hero">
        <div class="page-hero-content">
            <h1>MY WISHLIST</h1>
            <p>Your curated collection of premium vehicles saved for later</p>
        </div>
    </section>

    <div class="vehicles-container">
        <!-- STATS -->
        <div class="vehicles-stats">
            <p><?= count($wishlist) ?> vehicle(s) in your wishlist</p>
        </div>

        <div class="vehicles-content">
            <?php if (empty($wishlist)): ?>
                <div class="no-vehicles">
                    <h3>Your wishlist is empty</h3>
                    <p>Start building your dream fleet by exploring our available vehicles.</p>
                    <a href="../vehicle/vehicles.php" class="reset-link">Browse Gallery</a>
                </div>
            <?php else: ?>
                <div class="vehicles-grid">
                    <?php foreach ($wishlist as $item): ?>
                        <div class="vehicle-card" id="wish-card-<?= $item['id'] ?>">
                            <?php
                            $imgPath = $item['image_path'] ?? '';
                            if (!empty($imgPath) && $imgPath !== '0'):
                                if (strpos($imgPath, 'http') === 0) {
                                    $imgSrc = $imgPath;
                                } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, 'assets/images/') === 0) {
                                    $imgSrc = '../../' . $imgPath;
                                } else {
                                    $imgSrc = '../../uploads/vehicles/' . $imgPath;
                                }
                            ?>
                                <div class="vehicle-image-wrapper">
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt="<?= htmlspecialchars($item['model']) ?>" class="vehicle-image">
                                    
                                    <!-- REMOVE BUTTON (WISHLIST SPECIFIC) -->
                                    <button class="wishlist-remove-btn" onclick="removeWish(<?= $item['id'] ?>)" title="Remove from wishlist">
                                        <i class="fa-solid fa-heart"></i>
                                    </button>

                                    <div class="vehicle-badge"><?= htmlspecialchars(strtoupper($item['license_type'])) ?></div>
                                </div>
                            <?php endif; ?>

                            <div class="vehicle-info">
                                <h3 class="vehicle-model"><?= htmlspecialchars($item['model']) ?></h3>
                                
                                <div class="vehicle-specs">
                                    <div class="spec">
                                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($item['color']) ?>;border:1px solid rgba(255,255,255,0.15);flex-shrink:0;"></span>
                                        <span><?= htmlspecialchars($item['color']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                        </svg>
                                        <span><?= htmlspecialchars($item['transmission']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                        <span><?= htmlspecialchars($item['fuel_type']) ?></span>
                                    </div>
                                </div>

                                <div class="vehicle-price">
                                    <span class="price">Rs. <?= number_format($item['price_per_day']) ?></span>
                                    <span class="price-period">/day</span>
                                </div>

                                <div class="vehicle-actions">
                                    <a href="../vehicle/vehicle_detail.php?id=<?= $item['id'] ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function removeWish(vehicleId) {
    if (!confirm('Remove this vehicle from your wishlist?')) return;

    const formData = new FormData();
    formData.append('vehicle_id', vehicleId);
    formData.append('action', 'remove');

    fetch('../api/wishlist_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById(`wish-card-${vehicleId}`);
            card.classList.add('removing');
            setTimeout(() => {
                card.remove();
                if (document.querySelectorAll('.vehicle-card').length === 0) {
                    location.reload();
                }
            }, 400);
        } else {
            alert(data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>

<?php include '../../includes/footer.php'; ?>
