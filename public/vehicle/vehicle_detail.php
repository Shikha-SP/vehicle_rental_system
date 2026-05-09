<?php
/**
 * public/vehicle/vehicle_detail.php  —  Detailed vehicle view + Booking
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: vehicles.php');
    exit;
}

// 1. FETCH VEHICLE
$sql = "SELECT v.*, u.first_name AS owner_name 
        FROM vehicles v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.id = ? AND (v.status = 'available' OR v.status = 'approved') 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    header('Location: vehicles.php');
    exit;
}

// Redirected to paymentdetail.php for final step
$user_id = $_SESSION['user_id'] ?? null;

// Check whether the current user already has an active confirmed booking for this vehicle
$userBooking = false;
if ($user_id) {
    $booking_check_sql = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? AND status = 'confirmed' AND end_date >= CURDATE() LIMIT 1";
    $booking_check_stmt = $conn->prepare($booking_check_sql);
    $booking_check_stmt->bind_param("ii", $user_id, $id);
    $booking_check_stmt->execute();
    $userBooking = (bool) $booking_check_stmt->get_result()->fetch_assoc();
}

// Check wishlist status
$inWishlist = false;
if ($user_id) {
    $wish_sql = "SELECT 1 FROM wishlist WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
    $wish_stmt = $conn->prepare($wish_sql);
    $wish_stmt->bind_param("ii", $user_id, $id);
    $wish_stmt->execute();
    $inWishlist = (bool)$wish_stmt->get_result()->fetch_assoc();
}

// Default dates for preview
$def_pickup = date('Y-m-d', strtotime('+1 day'));
$def_dropoff = date('Y-m-d', strtotime('+3 days'));
$def_days = 2;

$pageTitle = $vehicle['model'] . " – TD Rentals";

include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/vehicle_detail.css">
<link rel="stylesheet" href="../../assets/css/ai_recommendations.css">
<link rel="stylesheet" href="../../assets/css/wishlist.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<main class="detail-page">
    <!-- HERO SECTION -->
    <section class="vehicle-hero">
        <div class="hero-bg">
            <img src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/placeholder.png') ?>" alt="<?= htmlspecialchars($vehicle['model']) ?>">
            <div class="hero-overlay"></div>
        </div>
        
        <div class="container hero-content">
            <div class="hero-text">
                <span class="badge red"><?= htmlspecialchars(strtoupper($vehicle['license_type'])) ?> CATEGORY</span>
                <h1><?= htmlspecialchars($vehicle['model']) ?></h1>
                <p class="owner-pill">By <?= htmlspecialchars($vehicle['owner_name']) ?></p>
            </div>
        </div>
    </section>

    <!-- CONTENT GRID -->
    <div class="container main-grid">
        <!-- SPECS & INFO -->
        <div class="specs-section">
            <div class="specs-grid">
                <div class="spec-card">
                    <span class="spec-label">TRANSMISSION</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['transmission']) ?></span>
                </div>
                <div class="spec-card">
                    <span class="spec-label">FUEL TYPE</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['fuel_type']) ?></span>
                </div>
                <div class="spec-card">
                    <span class="spec-label">TOP SPEED</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['top_speed'] ?? '—') ?> <small>KM/H</small></span>
                </div>
                <div class="spec-card">
                    <span class="spec-label">CAPACITY</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['fuel_capacity'] ?? '—') ?> <small>LITERS</small></span>
                </div>
            </div>

            <div class="vehicle-description">
                <h2>Performance Excellence</h2>
                <p>The <?= htmlspecialchars($vehicle['model']) ?> represents a masterclass in automotive engineering, meticulously finished in a striking <?= htmlspecialchars($vehicle['color'] ?? 'custom') ?> exterior. This performance-driven <?= htmlspecialchars($vehicle['fuel_type']) ?> vehicle is equipped with a precise <?= htmlspecialchars($vehicle['transmission']) ?> transmission, ensuring every mile is delivered with absolute control.</p>
                <p>Boasting a top speed of <?= htmlspecialchars($vehicle['top_speed'] ?? 'N/A') ?> KM/H and a substantial <?= htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A') ?>-liter fuel capacity, this <?= htmlspecialchars(strtoupper($vehicle['license_type'])) ?> category vehicle is built for long-distance cruising and exhilarating performance alike.</p>
                
                <div class="color-swatch-box">
                    <strong>EXTERIOR FINISH:</strong>
                    <div class="swatch" style="background: <?= htmlspecialchars($vehicle['color'] ?? '#333') ?>"></div>
                    <span><?= htmlspecialchars($vehicle['color'] ?? 'Factory Standard') ?></span>
                </div>
            </div>
        </div>

        <!-- BOOKING WIDGET -->
        <aside class="booking-sidebar">
            <div class="booking-card">
                <div class="price-header">
                    <div class="rate">
                        <span class="currency">NPR</span>
                        <span class="amount"><?= number_format($vehicle['price_per_day'], 0) ?></span>
                        <span class="period">/ day</span>
                    </div>
                </div>
                <?php if ($userBooking): ?>
                    <div class="alert alert-success">You already have an active confirmed booking for this vehicle.</div>
                    <button type="button" class="btn-book btn-booked" disabled>Booked</button>
                    <p class="secure-note">View your booking history for full trip details.</p>
                <?php else: ?>
                    <form method="POST" action="../payment/paymentdetail.php" class="booking-form">
                        <input type="hidden" name="action" value="init_payment">
                        <input type="hidden" name="vehicle_id" value="<?= $id ?>">
                        <input type="hidden" name="days" id="booking-days" value="<?= $def_days ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <div class="form-group">
                            <label>Pick-up Date</label>
                            <input type="date" name="pickup_date" id="pickup-date" min="<?= date('Y-m-d') ?>" value="<?= $def_pickup ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Pick-up Time</label>
                            <select name="pickup_time" id="pickup-time" required>
                                <?php
                                $times = [];
                                for ($h = 7; $h <= 22; $h++) {
                                    $val = sprintf('%02d:00:00', $h);
                                    $label = date('g:i A', strtotime($val));
                                    $times[] = ['val' => $val, 'label' => $label];
                                }
                                foreach ($times as $t):
                                ?>
                                    <option value="<?= $t['val'] ?>" <?= ($t['val'] === '09:00:00') ? 'selected' : '' ?>>
                                        <?= $t['label'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Drop-off Date</label>
                            <input type="date" name="dropoff_date" id="dropoff-date" min="<?= date('Y-m-d') ?>" value="<?= $def_dropoff ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Return Time</label>
                            <select name="return_time" id="return-time" required>
                                <?php foreach ($times as $t): ?>
                                    <option value="<?= $t['val'] ?>" <?= ($t['val'] === '18:00:00') ? 'selected' : '' ?>>
                                        <?= $t['label'] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="price-summary">
                            <div class="summary-line">
                                <span>Base Rate (<span id="summary-days"><?= $def_days ?></span> days)</span>
                                <span id="summary-total">NPR <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                            </div>
                            <div class="summary-line total">
                                <span>Total Estimated</span>
                                <span id="summary-grand">NPR <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn-book">Book Now</button>
                        <p class="secure-note">🛡️ Secure Payment & Insurance Covered</p>
                    </form>
                <?php endif; ?>

                <div class="sidebar-wishlist">
                    <button id="wishlist-btn" class="btn-wishlist-sidebar <?= $inWishlist ? 'active' : '' ?>" data-id="<?= $id ?>">
                        <div class="wishlist-icon-box">
                            <i class="<?= $inWishlist ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                        </div>
                        <span class="wishlist-text"><?= $inWishlist ? 'IN COLLECTION' : 'SAVE TO WISHLIST' ?></span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- AI RECOMMENDATIONS SECTION -->
    <section class="ai-recommendations" id="ai-recommendations-container">
        <!-- Skeleton Loading -->
        <div class="ai-loading">
            <div class="skeleton-header">
                <div class="skeleton-title"></div>
                <div class="skeleton-subtitle"></div>
            </div>
            <div class="recommendations-grid">
                <div class="rec-card skeleton-card"></div>
                <div class="rec-card skeleton-card"></div>
                <div class="rec-card skeleton-card"></div>
                <div class="rec-card skeleton-card"></div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pIn   = document.getElementById('pickup-date');
    const pOff  = document.getElementById('dropoff-date');
    const tIn   = document.getElementById('pickup-time');
    const tOff  = document.getElementById('return-time');
    const displayDays  = document.getElementById('summary-days');
    const displayTotal = document.getElementById('summary-total');
    const displayGrand = document.getElementById('summary-grand');
    const dailyRate    = <?= (float)$vehicle['price_per_day'] ?>;

    const today = new Date().toISOString().split('T')[0];
    const currentHour = new Date().getHours();

    function filterPickupTimes() {
        const isToday = pIn.value === today;
        let firstEnabled = null;

        Array.from(tIn.options).forEach(opt => {
            const h = parseInt(opt.value.split(':')[0]);
            if (isToday && h <= currentHour) {
                opt.disabled = true;
                opt.style.color = '#aaa';
            } else {
                opt.disabled = false;
                opt.style.color = '';
                if (firstEnabled === null) firstEnabled = opt;
            }
        });

        // If selected pickup time is now disabled, jump to first available
        const selectedHour = parseInt(tIn.value.split(':')[0]);
        if (isToday && selectedHour <= currentHour && firstEnabled) {
            tIn.value = firstEnabled.value;
        }
    }

    function updatePrice() {
        // Filter pickup times first (disable past times if today is selected)
        filterPickupTimes();

        const d1 = new Date(pIn.value);
        const d2 = new Date(pOff.value);

        // Keep dropoff min = pickup date (allows same day)
        if (pIn.value) {
            pOff.min = pIn.value;
            // If dropoff is now before pickup, reset it to pickup date
            if (pOff.value && pOff.value < pIn.value) {
                pOff.value = pIn.value;
            }
        }

        if (d1 && d2 && d2 >= d1) {
            const diffTime = d2 - d1;
            const diffDays = diffTime === 0 ? 1 : Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const total = diffDays * dailyRate;

            displayDays.innerText = diffDays;
            displayTotal.innerText = 'NPR ' + total.toLocaleString();
            displayGrand.innerText = 'NPR ' + total.toLocaleString();

            document.getElementById('booking-days').value = diffDays;
        }

        // Same-day validation: return time must be after pickup time
        if (pIn.value && pOff.value && pIn.value === pOff.value) {
            const pickupHour  = parseInt(tIn.value.split(':')[0]);
            const returnHour  = parseInt(tOff.value.split(':')[0]);
            if (returnHour <= pickupHour) {
                const newHour = Math.min(pickupHour + 1, 22);
                tOff.value = String(newHour).padStart(2,'0') + ':00:00';
            }
            Array.from(tOff.options).forEach(opt => {
                const h = parseInt(opt.value.split(':')[0]);
                opt.disabled = (h <= pickupHour);
            });
        } else {
            Array.from(tOff.options).forEach(opt => opt.disabled = false);
        }
    }

    pIn.addEventListener('change', updatePrice);
    pOff.addEventListener('change', updatePrice);
    tIn.addEventListener('change', updatePrice);
    tOff.addEventListener('change', updatePrice);

    // Run immediately on page load to filter times if today is already selected
    filterPickupTimes();
    updatePrice();

    // Fetch AI Recommendations
    const aiContainer = document.getElementById('ai-recommendations-container');
    const vehicleId = <?= $id ?>;
    
    fetch(`../api/get_recommendations.php?id=${vehicleId}`)
        .then(response => response.text())
        .then(html => {
            aiContainer.innerHTML = html;
        })
        .catch(error => {
            console.error('Error fetching recommendations:', error);
            aiContainer.innerHTML = '<p class="secure-note" style="text-align:center; padding: 2rem;">Unable to load recommendations.</p>';
        });

    // Wishlist logic
    const wishlistBtn = document.getElementById('wishlist-btn');
    if (wishlistBtn) {
        wishlistBtn.addEventListener('click', function() {
            const vehicleId = this.getAttribute('data-id');
            const isActive = this.classList.contains('active');
            const action = isActive ? 'remove' : 'add';
            
            const formData = new FormData();
            formData.append('vehicle_id', vehicleId);
            formData.append('action', action);

            fetch('../api/wishlist_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.in_wishlist) {
                        this.classList.add('active');
                        this.querySelector('i').classList.replace('fa-regular', 'fa-solid');
                        this.querySelector('span').innerText = 'In Wishlist';
                    } else {
                        this.classList.remove('active');
                        this.querySelector('i').classList.replace('fa-solid', 'fa-regular');
                        this.querySelector('span').innerText = 'Add to Wishlist';
                    }
                } else {
                    alert(data.message);
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>