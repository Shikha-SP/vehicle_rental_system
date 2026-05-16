<?php
/**
 * public/vehicle/vehicle_detail.php  —  Detailed vehicle view + Booking
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$id = (int) ($_GET['id'] ?? 0);
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

// Check whether logged-in user is the vehicle owner
$isOwner = ($user_id && $user_id == $vehicle['user_id']);

// Check whether the current user already has an active confirmed booking for this vehicle
$userBooking = false;
$booking_id = null;
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
    $inWishlist = (bool) $wish_stmt->get_result()->fetch_assoc();
}

// Check if user has ANY booking (confirmed or cancelled) for review purposes
$canReview = false;
$booking_id = null;
$review_booking_id = null;
if ($user_id) {
    $review_check_sql = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
    $review_check_stmt = $conn->prepare($review_check_sql);
    $review_check_stmt->bind_param("ii", $user_id, $id);
    $review_check_stmt->execute();
    $review_row = $review_check_stmt->get_result()->fetch_assoc();
    $canReview = (bool) $review_row;
    $review_booking_id = $review_row['id'] ?? null;

    // Also get active booking id if exists
    $booking_check_sql = "SELECT id FROM bookings WHERE user_id = ? AND vehicle_id = ? AND status = 'confirmed' AND end_date >= CURDATE() LIMIT 1";
    $booking_check_stmt = $conn->prepare($booking_check_sql);
    $booking_check_stmt->bind_param("ii", $user_id, $id);
    $booking_check_stmt->execute();
    $booking_row = $booking_check_stmt->get_result()->fetch_assoc();
    $booking_id = $booking_row['id'] ?? null;
}

// Fetch user's existing rating and review
$user_rating_value = 0;
$user_review_text = '';
$effective_booking_id = $booking_id ?? $review_booking_id;
if ($user_id && $effective_booking_id) {
    $rating_check_sql = "SELECT rating, review FROM reviews WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
    $rating_check_stmt = $conn->prepare($rating_check_sql);
    $rating_check_stmt->bind_param("ii", $user_id, $id);
    $rating_check_stmt->execute();
    $rating_result = $rating_check_stmt->get_result()->fetch_assoc();
    if ($rating_result) {
        $user_rating_value = $rating_result['rating'];
        $user_review_text = $rating_result['review'] ?? '';
    }
}

// Fetch all reviews for this vehicle
$reviews_sql = "SELECT r.id, r.rating, r.review, r.created_at AS review_created, 
                       rr.reply_text, rr.created_at AS reply_created, 
                       u.first_name AS reviewer_name, 
                       ou.first_name AS owner_name
                FROM reviews r 
                JOIN users u ON r.user_id = u.id 
                LEFT JOIN review_replies rr ON r.id = rr.review_id
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                LEFT JOIN users ou ON rr.owner_id = ou.id
                WHERE r.vehicle_id = ? AND r.review IS NOT NULL AND r.review != ''
                ORDER BY r.created_at DESC";
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param("i", $id);
$reviews_stmt->execute();
$all_reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch average rating
$avg_sql = "SELECT AVG(rating) as avg_rating FROM reviews WHERE vehicle_id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("i", $id);
$avg_stmt->execute();
$avg_result = $avg_stmt->get_result()->fetch_assoc();
$vehicle['avg_rating'] = $avg_result['avg_rating'];

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
            <img src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/placeholder.png') ?>"
                alt="<?= htmlspecialchars($vehicle['model']) ?>">
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
                    <span class="spec-value"><?= htmlspecialchars($vehicle['top_speed'] ?? '—') ?>
                        <small>KM/H</small></span>
                </div>
                <div class="spec-card">
                    <span class="spec-label">CAPACITY</span>
                    <span class="spec-value"><?= htmlspecialchars($vehicle['fuel_capacity'] ?? '—') ?>
                        <small>LITERS</small></span>
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
                                                    <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                        </div>
                                        <div class="reviewer-meta">
                                            <strong><?= htmlspecialchars($review['reviewer_name']) ?></strong>
                                            <span class="review-date"><?= date('M d, Y', strtotime($review['review_created'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="review-stars">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fa-<?= $i <= $review['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                                                <?php endfor; ?>
                                    </div>
                                    <p class="review-text"><?= htmlspecialchars($review['review']) ?></p>
                        
                                            <?php if (!empty($review['reply_text'])): ?>
                                        <div class="owner-reply">
                                            <div class="owner-reply-header">
                                                <div class="reviewer-avatar">
                                                    <?= strtoupper(substr($review['owner_name'], 0, 1)) ?>
                                                </div>
                                                <div class="owner-info">
                                                    <strong><?= htmlspecialchars($review['owner_name']) ?></strong>
                                                    <span class="review-date"><?= date('M d, Y', strtotime($review['reply_created'])) ?></span>
                                                </div>
                                            </div>
                                            <p><?= htmlspecialchars($review['reply_text']) ?></p>
                                        </div>
                                            <?php endif; ?>
                        
                                            <?php if ($isOwner): ?>
                                            <div class="reply-form" id="reply-form-<?= $review['id'] ?>">
                                                <?php if (!empty($review['reply_text'])): ?>
                                                <button class="btn-toggle-reply" data-review-id="<?= $review['id'] ?>">
                                                    Edit Reply
                                                </button>
                                                        <?php else: ?>
                                                <button class="btn-toggle-reply" data-review-id="<?= $review['id'] ?>">
                                                    Reply to Review
                                                </button>
                                                        <?php endif; ?>
                                            <div class="reply-input-box" id="reply-input-<?= $review['id'] ?>" style="display:none;">
                                                <textarea id="reply-text-<?= $review['id'] ?>" rows="3"
                                                    placeholder="Write your response..."><?= htmlspecialchars($review['reply_text'] ?? '') ?></textarea>
                                                <button class="btn-submit-reply" data-review-id="<?= $review['id'] ?>">
                                                    <?= empty($review['reply_text']) ? 'Post Reply' : 'Update Reply' ?>
                                                </button>
                                                <span class="reply-msg" id="reply-msg-<?= $review['id'] ?>"></span>
                                            </div>
                                        </div>
                                            <?php endif; ?>
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

                <?php
                if ($userBooking): ?>
                    <div class="alert alert-success">You already have an active confirmed booking for this vehicle.</div>
                    <button type="button" class="btn-book btn-booked" disabled>Booked</button>

                    <?php
                    $ext_sql = "SELECT id, end_date FROM bookings WHERE user_id = ? AND vehicle_id = ? AND status = 'confirmed' AND end_date >= CURDATE() LIMIT 1";
                    $ext_stmt = $conn->prepare($ext_sql);
                    $ext_stmt->bind_param("ii", $user_id, $id);
                    $ext_stmt->execute();
                    $ext_row = $ext_stmt->get_result()->fetch_assoc();
                    $current_end = $ext_row['end_date'] ?? date('Y-m-d');
                    $active_booking_id = $ext_row['id'] ?? null;
                    $min_extend = date('Y-m-d', strtotime($current_end . ' +1 day'));
                    ?>

                    <!-- Toggle button -->
                    <button type="button" class="btn-extend-toggle" id="btn-extend-toggle">
                        Extend Booking
                    </button>

                    <!-- Hidden panel, shown on click -->
                    <div class="extension-box" id="extension-box" style="display:none;">
                        <p class="ext-current">Current drop-off: <strong
                                id="ext-current-display"><?= date('M d, Y', strtotime($current_end)) ?></strong></p>
                        <div class="form-group">
                            <label style="color: #555555;">New Drop-off Date</label>
                            <input type="date" id="extend-date" min="<?= $min_extend ?>" value="<?= $min_extend ?>">
                        </div>
                        <div class="ext-price-preview" id="ext-price-preview" style="display:none;">
                            <span>Extra days: <strong id="ext-days">0</strong></span>
                            <span>Additional cost: <strong id="ext-cost">NPR 0</strong></span>
                        </div>
                        <button class="btn-book btn-extend" id="btn-extend" data-booking-id="<?= $active_booking_id ?>"
                            data-current-end="<?= $current_end ?>" data-rate="<?= (float) $vehicle['price_per_day'] ?>">
                            Confirm Extension
                        </button>
                        <p id="ext-msg" class="ext-msg"></p>
                    </div>

                    <p class="secure-note">View your booking history for full trip details.</p>
                <?php else: ?>
                    <form method="POST" action="../payment/paymentdetail.php" class="booking-form">
                        <input type="hidden" name="action" value="init_payment">
                        <input type="hidden" name="vehicle_id" value="<?= $id ?>">
                        <input type="hidden" name="days" id="booking-days" value="<?= $def_days ?>">
                        <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">

                        <div class="form-group">
                            <label>Pick-up Date</label>
                            <input type="date" name="pickup_date" id="pickup-date" min="<?= date('Y-m-d') ?>" value="<?= $def_pickup ?>"
                                required>
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
                            <input type="date" name="dropoff_date" id="dropoff-date" min="<?= date('Y-m-d') ?>"
                               
                                value="<?= $def_dropoff ?>" required>
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
                                <span id="summary-total">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                                <span id="summary-total">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                            </div>
                            <div class="summary-line total">
                                <span>Total Estimated</span>
                                <span id="summary-grand">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                                <span id="summary-grand">NPR
                                    <?= number_format($vehicle['price_per_day'] * $def_days, 0) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn-book">Book Now</button>
                        <p class="secure-note">🛡️ Secure Payment & Insurance Covered</p>
                    </form>
                <?php endif; ?>

                <div class="sidebar-wishlist">
                    <button id="wishlist-btn" class="btn-wishlist-sidebar <?= $inWishlist ? 'active' : '' ?>"
                        data-id="<?= $id ?>">
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

<!-- Review modal -->
<?php if ($userBooking || $canReview): ?>
    <div class="review-modal-overlay" id="reviewModal" data-rating="<?= $user_rating_value ?>"
        data-review="<?= htmlspecialchars($user_review_text) ?>">
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

            <textarea id="reviewText" placeholder="Describe your experience with this vehicle..."
                rows="5"><?= htmlspecialchars($user_review_text) ?></textarea>

            <button class="btn-submit-review"
                id="submitReview"><?= $user_review_text ? 'Update Review' : 'Post Review' ?></button>
        </div>
    </div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const pIn   = document.getElementById('pickup-date');
        const pOff  = document.getElementById('dropoff-date');
    const tIn   = document.getElementById('pickup-time');
    const tOff  = document.getElementById('return-time');
        const displayDays  = document.getElementById('summary-days');
        const displayTotal = document.getElementById('summary-total');
        const displayGrand = document.getElementById('summary-grand');
        const dailyRate    = <?= (float) $vehicle['price_per_day'] ?>;

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
        // Booking Extension
        const extendDate = document.getElementById('extend-date');
        const btnExtend = document.getElementById('btn-extend');
        const toggleBtn = document.getElementById('btn-extend-toggle');
        const extensionBox = document.getElementById('extension-box');

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

        if (pIn && pOff) {
            pIn.addEventListener('change', updatePrice);
            pOff.addEventListener('change', updatePrice);
    tIn.addEventListener('change', updatePrice);
    tOff.addEventListener('change', updatePrice);

    // Run immediately on page load to filter times if today is already selected
    filterPickupTimes();
    updatePrice();
        }

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
            wishlistBtn.addEventListener('click', function () {
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

        if (toggleBtn && extensionBox) {
            toggleBtn.addEventListener('click', () => {
                const isOpen = extensionBox.style.display !== 'none';
                extensionBox.style.display = isOpen ? 'none' : 'block';
                toggleBtn.textContent = isOpen ? 'Extend Booking' : '✖ Cancel';
            });
        }

        if (extendDate && btnExtend) {
            const dailyRateExt = parseFloat(btnExtend.dataset.rate);
            const currentEnd = new Date(btnExtend.dataset.currentEnd);
            const pricePreview = document.getElementById('ext-price-preview');
            const extDaysEl = document.getElementById('ext-days');
            const extCostEl = document.getElementById('ext-cost');
            const extMsg = document.getElementById('ext-msg');

            extendDate.addEventListener('change', () => {
                const newEnd = new Date(extendDate.value);
                if (newEnd > currentEnd) {
                    const diffDays = Math.ceil((newEnd - currentEnd) / (1000 * 60 * 60 * 24));
                    extDaysEl.textContent = diffDays;
                    extCostEl.textContent = 'NPR ' + (diffDays * dailyRateExt).toLocaleString();
                    pricePreview.style.display = 'flex';
                } else {
                    pricePreview.style.display = 'none';
                }
            });

            btnExtend.addEventListener('click', () => {
                const newEnd = extendDate.value;
                const bookingId = btnExtend.dataset.bookingId;

                if (!newEnd || new Date(newEnd) <= currentEnd) {
                    extMsg.textContent = 'Please pick a date after your current drop-off.';
                    extMsg.className = 'ext-msg error';
                    return;
                }

                btnExtend.disabled = true;
                btnExtend.textContent = 'Processing…';

                const fd = new FormData();
                fd.append('booking_id', bookingId);
                fd.append('new_end_date', newEnd);
                fd.append('csrf_token', '<?= generateCsrfToken() ?>');

                fetch('../api/extend_booking.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            
                            extMsg.textContent = '✅ Booking extended to ' + data.new_end_date;
                            showToast('Booking Extended!');
                            extMsg.className = 'ext-msg success';
                            // Update the displayed current drop-off
                            document.querySelector('.ext-current strong').textContent = data.new_end_date;
                            pricePreview.style.display = 'none';
                            // Update min date for next extension
                            const next = new Date(data.raw_end_date);
                            next.setDate(next.getDate() + 1);
                            extendDate.min = next.toISOString().split('T')[0];
                            extendDate.value = next.toISOString().split('T')[0];
                            btnExtend.dataset.currentEnd = data.raw_end_date;

                            setTimeout(() => {
                                extensionBox.style.display = 'none';
                                toggleBtn.textContent = 'Extend Booking';
                            }, 2000);
                        } else {
                            extMsg.textContent = '❌ ' + data.message;
                            extMsg.className = 'ext-msg error';
                        }
                    })
                    .catch(() => {
                        extMsg.textContent = '❌ Network error. Please try again.';
                        extMsg.className = 'ext-msg error';
                    });
            });
        }
    });
</script>

<script src="../../assets/js/ratings.js"></script>

<?php include '../../includes/footer.php'; ?>