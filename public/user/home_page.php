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
include('../../includes/header.php');
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
?>

<link rel="stylesheet" href="../../assets/css/Homepage.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

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
                    ENGINEERED FOR<br>
                    <span class="text-red">PERFORMANCE.</span><br>
                    DRIVEN BY YOU.
                </h1>

                <div class="hero-btns">
                    <a href="../vehicle/vehicles.php" class="btn btn-red">BOOK NOW</a>
                    <a href="../vehicle/vehicles.php" class="btn btn-ghost">VIEW FLEET</a>
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

                                <div class="car-card">

                                    <div class="card-type-badge">
                                        <?php echo e(strtoupper($car['license_type'] ?? 'CAR')); ?>
                                    </div>


                                    <div class="card-img-wrapper">

                                        <?php if (!empty($car['image_path'])): 
                                            $imgPath = $car['image_path'];
                                            $imgSrc = (strpos($imgPath, 'http') === 0) ? $imgPath : '../../' . $imgPath;
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

                                            <a href="booking.php?id=<?php echo (int) $car['id']; ?>" class="btn-rent">
                                                RENT NOW
                                            </a>

                                        </div>

                                    </div>

                                </div>

                        <?php endwhile; ?>

                <?php else: ?>

                        <div class="no-vehicle-message">
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

                                <a href="../vehicle/vehicles.php?license_type=<?php echo urlencode($b['license_type']); ?>" class="brand-tab">

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

            <div class="banner-text">
                <h3>Rent your vehicle</h3>
                <p>Rent your vehicle and earn some money</p>
            </div>

            <a href="../renter/list_car.php" class="btn-white-outline">
                Rent and earn
            </a>

        </div>
    </div>



    <!-- SHOWROOM SECTION -->
    <section class="showroom-section">
        <div class="container grid-two">

            <div class="showroom-info">

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