<?php
/**
 * public/vehicle/vehicle_detail.php  —  Detailed vehicle view + Booking
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
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

// Check whether the current user has ever had a confirmed booking for this vehicle
$userBooking = false;
$booking_id = null;
if ($user_id) {
    $booking_check_sql = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? AND status = 'confirmed' AND end_date >= CURDATE() LIMIT 1";
    $booking_check_stmt = $conn->prepare($booking_check_sql);
    $booking_check_stmt->bind_param("ii", $user_id, $id);
    $booking_check_stmt->execute();
    $booking_row = $booking_check_stmt->get_result()->fetch_assoc();
    $userBooking = (bool) $booking_row;
    $booking_id = $booking_row['id'] ?? null;
}
// Check if user has ANY booking (confirmed or cancelled) for review purposes
$canReview = false;
$review_booking_id = null;
if ($user_id) {
    $review_check_sql = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
    $review_check_stmt = $conn->prepare($review_check_sql);
    $review_check_stmt->bind_param("ii", $user_id, $id);
    $review_check_stmt->execute();
    $review_row = $review_check_stmt->get_result()->fetch_assoc();
    $canReview = (bool) $review_row;
    $review_booking_id = $review_row['id'] ?? null;
}
// Fetch user's existing rating and review
$user_rating_value = 0;
$effective_booking_id = $booking_id ?? $review_booking_id;
if ($user_id && $effective_booking_id) {
    $rating_check_sql = "SELECT rating, review FROM reviews WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
    $rating_check_stmt = $conn->prepare($rating_check_sql);
    $rating_check_stmt->bind_param("ii", $user_id, $id);
    $rating_check_stmt->execute();
    $rating_result = $rating_check_stmt->get_result()->fetch_assoc();
    if ($rating_result) {
        $user_rating_value = $rating_result['rating'];
    }
}

// Fetch all reviews for this vehicle
$reviews_sql = "SELECT r.rating, r.review, u.first_name, r.created_at 
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.vehicle_id = ? AND r.review IS NOT NULL AND r.review != ''
                ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $id);
$reviews_stmt->execute();
$all_reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Default dates for preview
$def_pickup = date('Y-m-d', strtotime('+1 day'));
$def_dropoff = date('Y-m-d', strtotime('+3 days'));
$def_days = 2;

$pageTitle = $vehicle['model'] . " – TD Rentals";
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/vehicle_detail.css">
<!-- For star icon -->
<script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>
<main class="detail-page">
    <!-- HERO SECTION -->
    <section class="vehicle-hero">
        <div class="hero-bg">
            <img src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/placeholder.png') ?>"
                alt="<?= htmlspecialchars($vehicle['model']) ?>">
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
                    <span class="spec-value"><?= htmlspecialchars($vehicle['top_speed'] ?? '—') ?>
                        <small>KM/H</small></span>
                </div>
                <div class="spec-card">
                    <span class="spec-label">CAPACITY</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['fuel_capacity'] ?? '—') ?>
                        <small>LITERS</small></span>
                </div>
            </div>

            <div class="vehicle-description">
                <h2>Performance Excellence</h2>
                <p>The <?= htmlspecialchars($vehicle['model']) ?> represents a masterclass in automotive engineering,
                    meticulously finished in a striking <?= htmlspecialchars($vehicle['color'] ?? 'custom') ?> exterior.
                    This performance-driven <?= htmlspecialchars($vehicle['fuel_type']) ?> vehicle is equipped with a
                    precise <?= htmlspecialchars($vehicle['transmission']) ?> transmission, ensuring every mile is
                    delivered with absolute control.</p>
                <p>Boasting a top speed of <?= htmlspecialchars($vehicle['top_speed'] ?? 'N/A') ?> KM/H and a
                    substantial <?= htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A') ?>-liter fuel capacity, this
                    <?= htmlspecialchars(strtoupper($vehicle['license_type'])) ?> category vehicle is built for
                    long-distance cruising and exhilarating performance alike.
                </p>

                <div class="color-swatch-box">
                    <strong>EXTERIOR FINISH:</strong>
                    <div class="swatch" style="background: <?= htmlspecialchars($vehicle['color'] ?? '#333') ?>"></div>
                    <span><?= htmlspecialchars($vehicle['color'] ?? 'Factory Standard') ?></span>
                </div>

                <!-- Rate Vehicle Only After Booking -->
                <?php if ($userBooking || $canReview): ?>
                    <div class="rating-box" data-vehicle-id="<?= $id ?>" data-booking-id="<?= $effective_booking_id ?>">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $user_rating_value): ?>
                                    <i class="fa-solid fa-star active"></i>
                                <?php else: ?>
                                    <i class="fa-regular fa-star"></i>
                                <?php endif; ?>

                            <?php endfor; ?>

                        </div>
                        <p class="review-trigger" id="openReviewModal">
                            <?= $user_rating_value ? 'Edit your review' : 'Write a review' ?>
                        </p>
                        <p>Rate this vehicle</p>
                    </div>
                <?php endif; ?>
                <!-- display reviews -->
                <div class="avg-rating-box">
                    <h3>Community Reviews</h3>
                    <?php
                    $avg = round($vehicle['avg_rating'] ?? 0);
                    for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= $avg): ?>
                            <i class="fa-solid fa-star active"></i>
                        <?php else: ?>
                            <i class="fa-regular fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span><?= $vehicle['avg_rating'] ? number_format($vehicle['avg_rating'], 1) : 'No ratings yet' ?></span>
                    <div class="review-box">
                        <?php if (empty($all_reviews)): ?>
                            <p class="no-reviews">No reviews yet. Be the first to review!</p>
                        <?php else: ?>
                            <?php foreach ($all_reviews as $review): ?>
                                <div class="review-card">
                                    <div class="review-card-header">
                                        <div class="reviewer-avatar">
                                            <?= strtoupper(substr($review['first_name'], 0, 1)) ?>
                                        </div>
                                        <div class="reviewer-meta">
                                            <strong>
                                                <?= htmlspecialchars($review['first_name']) ?>
                                            </strong>
                                            <span class="review-date">
                                                <?= date('M d, Y', strtotime($review['created_at'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-<?= $i <= $review['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <p class="review-text">
                                        <?= htmlspecialchars($review['review']) ?>
                                    </p>

                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
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
                            <input type="date" name="pickup_date" min="<?= date('Y-m-d') ?>" value="<?= $def_pickup ?>"
                                required>
                        </div>

                        <div class="form-group">
                            <label>Drop-off Date</label>
                            <input type="date" name="dropoff_date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                value="<?= $def_dropoff ?>" required>
                        </div>

                        <div class="price-summary">
                            <div class="summary-line">
                                <span>Base Rate (<span id="summary-days"><?= $def_days ?></span> days)</span>
                                <span id="summary-total">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                            </div>
                            <div class="summary-line total">
                                <span>Total Estimated</span>
                                <span id="summary-grand">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
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
<!-- Review modal -->
<?php if ($userBooking || $canReview): ?>
    <div class="review-modal-overlay" id="reviewModal" data-rating="<?= $user_rating_value ?>">
        <div class="review-modal">
            <button class="modal-close" id="closeModal">&times;</button>

            <div class="modal-header">
                <img src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/placeholder.png') ?>"
                    alt="<?= htmlspecialchars($vehicle['model']) ?>" class="modal-vehicle-img">
                <div>
                    <h3>
                        <?= htmlspecialchars($vehicle['model']) ?>
                    </h3>
                    <p>Share your experience</p>
                </div>
            </div>

            <!-- Stars inside modal too -->
            <div class="modal-stars stars-modal">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= $user_rating_value): ?>
                        <i class="fa-solid fa-star active"></i>
                    <?php else: ?>
                        <i class="fa-regular fa-star"></i>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <p class="modal-rating-label" id="modalRatingLabel">
                <?= $user_rating_value ? ['', 'Terrible', 'Poor', 'Average', 'Good', 'Excellent'][$user_rating_value] : 'Select a rating' ?>
            </p>

            <textarea id="reviewText" placeholder="Describe your experience with this vehicle..." rows="5"></textarea>

            <button class="btn-submit-review" id="submitReview">Post Review</button>
        </div>
    </div>
<?php endif; ?>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const pIn = document.querySelector('input[name="pickup_date"]');
        const pOff = document.querySelector('input[name="dropoff_date"]');
        const displayDays = document.getElementById('summary-days');
        const displayTotal = document.getElementById('summary-total');
        const displayGrand = document.getElementById('summary-grand');
        const dailyRate = <?= (float) $vehicle['price_per_day'] ?>;

        function updatePrice() {
            const d1 = new Date(pIn.value);
            const d2 = new Date(pOff.value);

            if (d1 && d2 && d2 > d1) {
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
<script src="../../assets/js/ratings.js"></script>

<?php include '../../includes/footer.php'; ?>