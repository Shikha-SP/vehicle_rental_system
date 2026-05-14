<?php
/**
 * ==========================================================
 * User Dashboard / Homepage
 * File: public/user/Home.php
 * ==========================================================
 */
session_start();

// AUTH CHECK
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

// DB + INCLUDES
include('../../config/db.php');
require_once('../../includes/functions.php');

$user_id = $_SESSION['user_id'];

// Fetch user data for the welcome message
$user_query = "SELECT first_name FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$username = $user_data['first_name'] ?? 'Guest';

// ── BOOKING REMINDER BANNER ──────────────────────────────────
$reminder_booking = null;
$banner_sql = "
    SELECT
        b.id,
        b.start_date,
        b.pickup_time,
        v.model AS vehicle_model,
        CONCAT(b.start_date, ' ', IFNULL(b.pickup_time, '09:00:00')) AS pickup_datetime,
        TIMESTAMPDIFF(HOUR, NOW(),
            CONCAT(b.start_date, ' ', IFNULL(b.pickup_time, '09:00:00'))
        ) AS hours_until
    FROM bookings b
    JOIN vehicles v ON v.id = b.vehicle_id
    WHERE b.user_id = ?
      AND b.status  = 'confirmed'
      AND CONCAT(b.start_date, ' ', IFNULL(b.pickup_time, '09:00:00'))
            BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ORDER BY pickup_datetime ASC
    LIMIT 1
";
$banner_stmt = $conn->prepare($banner_sql);
$banner_stmt->bind_param('i', $user_id);
$banner_stmt->execute();
$banner_result = $banner_stmt->get_result();
$reminder_booking = $banner_result->fetch_assoc();
// ─────────────────────────────────────────────────────────────

// Gallery vehicles — booking-safe version
$gallery_sql = "
SELECT 
    id, 
    model, 
    license_type, 
    image_path, 
    price_per_day, 
    fuel_capacity
FROM vehicles
WHERE status = 'approved'
AND id NOT IN (
    SELECT vehicle_id 
    FROM bookings 
    WHERE status != 'cancelled'
    AND end_date >= CURDATE()
)
/* Order by ID descending so the most recently added cars appear first */
ORDER BY id DESC
/* Limit to 3 to keep the UI clean and satisfy the 'minimum of 3' display requirement */
LIMIT 3
";

$gallery_result = $conn->query($gallery_sql);


// ----------------------------------------------------------
// License-type filter tabs
// ----------------------------------------------------------
$brands_sql = "
SELECT DISTINCT license_type 
FROM vehicles 
ORDER BY license_type
";

$brands_result = $conn->query($brands_sql);

// Page CSS goes into <head> via header.php ($extraStyles) so it loads with the rest of the layout, not before <!DOCTYPE>.
$extraStyles = '
<link rel="stylesheet" href="../../assets/css/Homepage.css">
<link rel="stylesheet" href="../../assets/css/scroll-reveal.css">
<link rel="stylesheet" href="../../assets/css/reminder_banner.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
';

include '../../includes/header.php';
?>

<?php if ($reminder_booking): ?>
    <?php
        $hours = (int) $reminder_booking['hours_until'];
        $pickup_fmt = date('M j, Y \a\t g:i A',
            strtotime($reminder_booking['pickup_datetime']));

        if ($hours <= 2) {
            $banner_class   = 'reminder-banner--urgent';
            $banner_heading = 'Pickup in under 2 hours!';
        } elseif ($hours <= 6) {
            $banner_class   = 'reminder-banner--soon';
            $banner_heading = 'Pickup today — get ready!';
        } else {
            $banner_class   = 'reminder-banner--tomorrow';
            $banner_heading = 'Your rental starts tomorrow';
        }
    ?>
    <div class="reminder-banner <?= $banner_class ?>">
        <div class="reminder-banner__inner">

            <div class="reminder-banner__text">
                <strong><?= $banner_heading ?></strong>
                <span>
                    <?= e($reminder_booking['vehicle_model']) ?> —
                    pickup on <?= e($pickup_fmt) ?>
                </span>
            </div>
            <a href="../user/bookings.php" class="reminder-banner__link">
                View Booking →
            </a>
        </div>
    </div>
<?php endif; ?>

<main class="dashboard-content">

    <!-- HERO SECTION -->
    <section class="hero-section">

        <div class="hero-gradient"></div>

        <div class="hero-car-img">
            <img src="../../assets/images/HomePageDiaplayCar.png" alt="Featured Vehicle">
        </div>

        <div class="hero-overlay">
            <div class="hero-text">
                <p class="hero-label">
                    WELCOME BACK, <?php echo e(strtoupper($username)); ?>
                </p>
                <h1 class="hero-heading">
                    <span class="hero-heading__line hero-heading__line--1">
                        <span class="hero-heading__line-inner">ENGINEERED FOR</span>
                    </span>
                    <span class="hero-heading__line hero-heading__line--2">
                        <span class="hero-heading__line-inner"><span class="text-red">PERFORMANCE.</span></span>
                    </span>
                    <span class="hero-heading__line hero-heading__line--3">
                        <span class="hero-heading__line-inner">DRIVEN BY YOU.</span>
                    </span>
                </h1>

                <div class="hero-btns">
                    <a href="../vehicle/vehicles.php" class="btn btn-red">BOOK NOW</a>
                    <a href="../vehicle/vehicles.php" class="btn btn-ghosts">VIEW FLEET</a>
                </div>

            </div>
        </div>


    </section>


    <!-- PERFORMANCE GALLERY -->
    <section class="gallery-section">
        <div class="container">

            <p class="section-label">CURATED SELECTION</p>

            <div class="section-header">
                <h2>THE PERFORMANCE GALLERY</h2>
                <a href="../vehicle/vehicles.php" class="view-all">EXPLORE ALL VEHICLES →</a>
            </div>

            <div class="car-grid">

                <?php if ($gallery_result && $gallery_result->num_rows > 0): ?>

                    <?php while ($car = $gallery_result->fetch_assoc()): ?>

                        <?php
                        $price = number_format((float) $car['price_per_day'], 0);
                        $fuel = $car['fuel_capacity'] ? $car['fuel_capacity'] . ' L' : 'N/A';
                        ?>

                        <div class="car-card element-class">

                            <div class="card-type-badge">
                                <?php echo e(strtoupper($car['license_type'] ?? 'CAR')); ?>
                            </div>


                            <div class="card-img-wrapper">

                                <?php
                                $imgSrc = getImageUrl($car['image_path']);

                                if ($imgSrc):
                                    ?>
                                    <img src="<?php echo e($imgSrc); ?>" alt="<?php echo e($car['model']); ?>">
                                <?php else: ?>
                                    <div class="no-img">NO IMAGE</div>
                                <?php endif; ?>

                            </div>


                            <div class="card-info">

                                <h3>
                                    <?php echo e($car['model']); ?>
                                    <span class="badge">NEW</span>
                                </h3>


                                <div class="card-specs-row">
                                    <span class="spec-item">⛽ <?php echo e($fuel); ?></span>
                                </div>


                                <div class="specs">
                                    <span class="price">NPR <?php echo e($price); ?> /day</span>

                                    <a href="../vehicle/vehicle_detail.php?id=<?php echo (int) $car['id']; ?>" class="btn-rent">
                                        RENT NOW
                                    </a>

                                </div>

                            </div>

                        </div>

                    <?php endwhile; ?>

                <?php else: ?>

                    <div class="no-vehicle-message element-class">
                        <p>No vehicles available at the moment.</p>
                    </div>

                <?php endif; ?>

            </div>


            <!-- LICENSE TYPE FILTER BUTTONS -->
            <div class="brand-filters">

                <?php
                if ($brands_result && $brands_result->num_rows > 0):

                    while ($b = $brands_result->fetch_assoc()):
                        ?>

                        <a href="../vehicle/vehicles.php?license_type=<?php echo urlencode($b['license_type']); ?>"
                            class="brand-tab element-class">

                            <?php echo e(strtoupper($b['license_type'])); ?>

                        </a>

                        <?php
                    endwhile;

                endif;
                ?>

            </div>
        </div>
    </section>



    <!-- PROMO BANNER -->
    <div class="red-banner">
        <div class="container banner-flex">

            <div class="banner-text element-class">
                <h3>Rent your vehicle</h3>
                <p>Rent your vehicle and earn some money</p>
            </div>

            <a href="../renter/list_car.php" class="btn-white-outline element-class">
                Rent and earn
            </a>

        </div>
    </div>



    <!-- SHOWROOM SECTION -->
    <section class="showroom-section">
        <div class="container grid-two">

            <div class="showroom-info element-class">

                <h2>OUR SHOWROOMS</h2>

                <p>Experience engineering excellence in person.</p>

                <ul class="contact-details">
                    <li>📍 Naxal Bhagawati Marga, Kathmandu</li>
                    <li>📞 +977 98XXXXXXXX</li>
                    <li>⏰ Mon – Sat 9AM – 6PM</li>
                </ul>

            </div>


            <div class="map-container">
                <div id="showroom-map"></div>
            </div>

        </div>
    </section>

</main>


<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../assets/js/map.js?v=2"></script>

<?php include('../../includes/footer.php'); ?>