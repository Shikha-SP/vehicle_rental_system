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

// Default dates for preview
$def_pickup = date('Y-m-d', strtotime('+1 day'));
$def_dropoff = date('Y-m-d', strtotime('+3 days'));
$def_days = 2;

$pageTitle = $vehicle['model'] . " – TD Rentals";
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/vehicle_detail.css">

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
                            <input type="date" name="pickup_date" min="<?= date('Y-m-d') ?>" value="<?= $def_pickup ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Drop-off Date</label>
                            <input type="date" name="dropoff_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= $def_dropoff ?>" required>
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
            </div>
        </aside>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pIn = document.querySelector('input[name="pickup_date"]');
    const pOff = document.querySelector('input[name="dropoff_date"]');
    const displayDays = document.getElementById('summary-days');
    const displayTotal = document.getElementById('summary-total');
    const displayGrand = document.getElementById('summary-grand');
    const dailyRate = <?= (float)$vehicle['price_per_day'] ?>;

    function updatePrice() {
        const d1 = new Date(pIn.value);
        const d2 = new Date(pOff.value);
        
        if(d1 && d2 && d2 > d1) {
            const diffTime = Math.abs(d2 - d1);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            const total = diffDays * dailyRate;
            
            displayDays.innerText = diffDays;
            displayTotal.innerText = 'NPR ' + total.toLocaleString();
            displayGrand.innerText = 'NPR ' + total.toLocaleString();

            // Send diffDays to paymentdetail.php via hidden field
            const daysHiddenField = document.getElementById('booking-days');
            if (daysHiddenField) {
                daysHiddenField.value = diffDays;
            }
        }
    }

    pIn.addEventListener('change', updatePrice);
    pOff.addEventListener('change', updatePrice);
});
</script>

<?php include '../../includes/footer.php'; ?>