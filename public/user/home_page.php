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
      AND b.payment_status = 'paid'
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
/* Only 3 source cards — they are duplicated 4× in PHP for the seamless loop */
LIMIT 3
";

$gallery_result = $conn->query($gallery_sql);


// ----------------------------------------------------------
// License-type filter tabs
// ----------------------------------------------------------
$brands_sql = "
SELECT DISTINCT license_type 
FROM vehicles 
WHERE status IN ('approved', 'available')
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
            <img src="../../assets/images/HomePageDisplay.jpg" alt="Featured Vehicle">
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
                        <span class="hero-heading__line-inner">
                            <span class="text-red">PERFORMANCE.</span>
                        </span>
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

            <?php
            $gallery_cars = [];
            if ($gallery_result && $gallery_result->num_rows > 0) {
                while ($row = $gallery_result->fetch_assoc()) {
                    $gallery_cars[] = $row;
                }
            }
            // Duplicate the items 4 times to ensure a smooth, seamless infinite scrolling loop
            $loop_cars = array_merge($gallery_cars, $gallery_cars, $gallery_cars, $gallery_cars);
            ?>

            <div class="slider-wrapper">
                <!-- Glassmorphic Navigation Buttons -->
                <button class="slider-btn left"><i class="fa-solid fa-chevron-left"></i></button>
                <button class="slider-btn right"><i class="fa-solid fa-chevron-right"></i></button>

                <div class="slider-container car-grid" id="homeSlider">

                    <?php if (count($loop_cars) > 0): ?>

                        <?php foreach ($loop_cars as $car): ?>

                            <?php
                            $price = number_format((float) $car['price_per_day'], 0);
                            $fuel = $car['fuel_capacity'] ? $car['fuel_capacity'] . ' L' : 'N/A';
                            ?>

                            <div class="car-card gallery-card element-class">

                                <!-- Full-bleed hero image -->
                                <div class="gallery-card__img">
                                    <?php
                                    $imgSrc = getImageUrl($car['image_path']);
                                    if ($imgSrc): ?>
                                        <img src="<?php echo e($imgSrc); ?>" alt="<?php echo e($car['model']); ?>">
                                    <?php else: ?>
                                        <div class="gallery-card__no-img">NO IMAGE</div>
                                    <?php endif; ?>
                                    <!-- Gradient overlay -->
                                    <div class="gallery-card__overlay"></div>
                                    <!-- Type badge -->
                                    <span class="gallery-card__badge"><?php echo e(strtoupper($car['license_type'] ?? 'CAR')); ?></span>
                                </div>

                                <!-- Info panel at the bottom -->
                                <div class="gallery-card__info">
                                    <div class="gallery-card__meta">
                                        <h3 class="gallery-card__name"><?php echo e($car['model']); ?></h3>
                                        <span class="gallery-card__fuel">⛽ <?php echo e($fuel); ?></span>
                                    </div>
                                    <div class="gallery-card__footer">
                                        <span class="gallery-card__price">NPR <?php echo e($price); ?><small>/day</small></span>
                                        <a href="../vehicle/vehicle_detail.php?id=<?php echo (int) $car['id']; ?>" class="gallery-card__cta">
                                            RENT NOW <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="no-vehicle-message element-class">
                            <p>No vehicles available at the moment.</p>
                        </div>

                    <?php endif; ?>

                </div>
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

<script>
// --- Performance Gallery Glider Slider logic ---
(function() {
    const container = document.getElementById('homeSlider');
    const wrapper = container ? container.closest('.slider-wrapper') : null;
    if (!container || !wrapper) return;

    let isHovered = false;
    let scrollSpeed = 0.8; // px per frame
    let scrollPos = 0;

    // Calculate the real content width of the original items.
    // Since we merged 4 arrays, the base length is 1/4 of total scrollWidth.
    function getQuarterLimit() {
        return container.scrollWidth / 4;
    }

    function step() {
        if (!isHovered) {
            scrollPos += scrollSpeed;
            const limit = getQuarterLimit();
            // Reset to 0 when we cross the quarter limit to keep looping seamless
            if (scrollPos >= limit) {
                scrollPos = 0;
            }
            container.scrollLeft = scrollPos;
        } else {
            // Keep scrollPos synchronized with manual scrolls/drags
            scrollPos = container.scrollLeft;
        }
        requestAnimationFrame(step);
    }

    // Auto-scroll start
    setTimeout(() => {
        scrollPos = container.scrollLeft;
        requestAnimationFrame(step);
    }, 100);

    // Hover state to pause sliding and show navigation buttons
    wrapper.addEventListener('mouseenter', () => { isHovered = true; });
    wrapper.addEventListener('mouseleave', () => { isHovered = false; });

    // Left/Right glassmorphic manual controls
    const leftBtn = wrapper.querySelector('.slider-btn.left');
    const rightBtn = wrapper.querySelector('.slider-btn.right');

    if (leftBtn) {
        leftBtn.addEventListener('click', () => {
            container.scrollBy({ left: -350, behavior: 'smooth' });
            setTimeout(() => { scrollPos = container.scrollLeft; }, 400);
        });
    }

    if (rightBtn) {
        rightBtn.addEventListener('click', () => {
            container.scrollBy({ left: 350, behavior: 'smooth' });
            setTimeout(() => { scrollPos = container.scrollLeft; }, 400);
        });
    }
})();
</script>

<?php include('../../includes/footer.php'); ?>