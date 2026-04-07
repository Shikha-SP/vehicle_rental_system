<?php
/**
 * ==========================================================
 * User Dashboard / Homepage (formerly Index.php)
 * File: public/user/Home.php
 * ==========================================================
 */
include('../../config/db.php');
include('../../includes/header.php');
require_once('../../includes/functions.php');
// ----------------------------------------------------------
// Gallery vehicles — 3 available, with full specs
// ----------------------------------------------------------
$gallery_sql = "SELECT id, brand, name, type, image, price_per_day, fuel_range FROM vehicles WHERE status='available' LIMIT 3";
$gallery_result = $conn->query($gallery_sql);

// ----------------------------------------------------------
// Brand filter tabs — distinct brands from DB
// ----------------------------------------------------------
$brands_sql = "SELECT DISTINCT brand FROM vehicles ORDER BY brand";
$brands_result = $conn->query($brands_sql);
?>
<!-- ======================================================
     External Stylesheets
====================================================== -->
<link rel="stylesheet" href="../../assets/css/Homepage.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<main class="dashboard-content">

    <!-- ====================================================
         HERO SECTION
    ==================================================== -->
    <section class="hero-section">

        <!-- Left-to-right dark gradient overlay -->
        <div class="hero-gradient"></div>

        <!-- Car PNG from backend — right side, vertically centered (around green line) -->
        <div class="hero-car-img">
            <img src="../../uploads/car.png" alt="Featured Vehicle">
        </div>

        <!-- Hero Text — bottom-left -->
        <div class="hero-overlay">
            <div class="hero-text">
                <p class="hero-label">ELITE FLEET EXPERIENCE</p>
                <h1 class="hero-heading">
                    ENGINEERED FOR<br>
                    <span class="text-red">PERFORMANCE.</span><br>
                    DRIVEN BY YOU.
                </h1>
                <div class="hero-btns">
                    <a href="vechile.php" class="btn btn-red">BOOK NOW</a>
                    <a href="vechile.php" class="btn btn-ghost">VIEW FLEET</a>
                </div>
            </div>
        </div>

        <!-- ================================================
             SEARCH BAR — inside hero at bottom, full-width flush
        ================================================ -->
        <form action="search.php" method="GET" class="hero-search-bar">

            <!-- Pick Up Location -->
            <div class="sb-field">
                <span class="sb-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z" />
                        <circle cx="12" cy="10" r="3" />
                    </svg>
                </span>
                <div class="sb-field-inner">
                    <label for="pickup_location">Pick Up Location</label>
                    <input type="text" id="pickup_location" name="pickup_location" placeholder="Beverly Hills, CA">
                </div>
            </div>
            <div class="sb-divider"></div>

            <!-- Vehicle Type -->
            <div class="sb-field">
                <span class="sb-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="1" y="3" width="15" height="13" />
                        <polygon points="16 8 20 8 23 11 23 16 16 16 16 8" />
                        <circle cx="5.5" cy="18.5" r="2.5" />
                        <circle cx="18.5" cy="18.5" r="2.5" />
                    </svg>
                </span>
                <div class="sb-field-inner">
                    <label for="vehicle_type">Vehicle Type</label>
                    <select id="vehicle_type" name="type">
                        <option value="">Supercars</option>
                        <option value="sedan">Sedan</option>
                        <option value="suv">SUV</option>
                        <option value="sports">Sports</option>
                        <option value="supercar">Supercar</option>
                        <option value="electric">Electric</option>
                    </select>
                </div>
            </div>
            <div class="sb-divider"></div>

            <!-- Duration -->
            <div class="sb-field">
                <span class="sb-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="#c0392b" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" />
                        <line x1="16" y1="2" x2="16" y2="6" />
                        <line x1="8" y1="2" x2="8" y2="6" />
                        <line x1="3" y1="10" x2="21" y2="10" />
                    </svg>
                </span>
                <div class="sb-field-inner">
                    <label for="pickup_date">Duration</label>
                    <div class="sb-date-range">
                        <input type="date" id="pickup_date" name="pickup_date">
                        <span class="sb-date-sep">—</span>
                        <input type="date" id="return_date" name="return_date">
                    </div>
                </div>
            </div>

            <!-- Search Button -->
            <button type="submit" class="sb-search-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                    stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8" />
                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                </svg>
                SEARCH
            </button>

        </form>

    </section>



    <!-- ====================================================
         PERFORMANCE GALLERY
    ==================================================== -->
    <section class="gallery-section">
        <div class="container">

            <p class="section-label">CURATED SELECTION</p>
            <div class="section-header">
                <h2>THE PERFORMANCE GALLERY</h2>
                <a href="vechile.php" class="view-all">EXPLORE ALL VEHICLES &rarr;</a>
            </div>

            <div class="car-grid">
                <?php
                if ($gallery_result && $gallery_result->num_rows > 0):
                    while ($car = $gallery_result->fetch_assoc()):
                        $price = number_format((float) $car['price_per_day'], 0);
                        $fuel = $car['fuel_range'] ? $car['fuel_range'] . ' km' : 'N/A';
                        $type_label = strtoupper($car['type'] ?? 'CAR');
                        ?>
                        <div class="car-card">

                            <!-- Type badge (from DB field: type) -->
                            <div class="card-type-badge"><?php echo e($type_label); ?></div>

                            <!-- Car image from DB -->
                            <div class="card-img-wrapper">
                                <?php if (!empty($car['image'])): ?>
                                    <img src="../../uploads/<?php echo e($car['image']); ?>"
                                        alt="<?php echo e($car['brand'] . ' ' . $car['name']); ?>">
                                <?php else: ?>
                                    <div class="no-img">NO IMAGE</div>
                                <?php endif; ?>
                            </div>

                            <div class="card-info">

                                <!-- Brand + model from DB -->
                                <div>
                                    <h3><?php echo e($car['brand']); ?> <span class="badge">NEW</span></h3>
                                    <p class="model-name"><?php echo e($car['name']); ?></p>
                                </div>

                                <!-- Specs from DB (fuel_range) -->
                                <div class="card-specs-row">
                                    <span class="spec-item">&#9981; <?php echo e($fuel); ?></span>
                                </div>

                                <!-- Price (from DB: price_per_day) + Rent button -->
                                <div class="specs">
                                    <span class="price">NPR <?php echo $price; ?>/day</span>
                                    <a href="booking.php?id=<?php echo (int) $car['id']; ?>" class="btn-rent">RENT NOW</a>
                                </div>

                            </div>
                        </div>
                        <?php
                    endwhile;
                else:
                    ?>
                    <div class="no-vehicle-message">
                        <p>No vehicles available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Brand Filter Tabs — generated from DB distinct brands -->
            <div class="brand-filters">
                <?php
                if ($brands_result && $brands_result->num_rows > 0):
                    while ($b = $brands_result->fetch_assoc()):
                        ?>
                        <a href="search.php?brand=<?php echo urlencode($b['brand']); ?>" class="brand-tab">
                            <?php echo e(strtoupper($b['brand'])); ?>
                        </a>
                        <?php
                    endwhile;
                endif;
                ?>
            </div>

        </div>
    </section>

    <!-- ====================================================
         PROMOTIONAL BANNER — JOIN TD ELITE CLUB
    ==================================================== -->
    <div class="red-banner">
        <div class="container banner-flex">
            <div class="banner-text">
                <h3>JOIN TD ELITE CLUB</h3>
                <p>Get exclusive access to limited-run hypercars, priority delivery and personalized service.</p>
            </div>
            <a href="signup.php" class="btn-white-outline">Apply For Membership</a>
        </div>
    </div>

    <!-- ====================================================
         SHOWROOM LOCATION + MAP
    ==================================================== -->
    <section class="showroom-section">
        <div class="container grid-two">

            <div class="showroom-info">
                <h2>OUR SHOWROOMS</h2>
                <p>Experience engineering excellence in person.</p>
                <ul class="contact-details">
                    <li>
                        <span class="contact-icon">&#128205;</span>
                        <div>
                            <strong>Location</strong>
                            <span>Naxal Bhagawati Marga, Kathmandu</span>
                        </div>
                    </li>
                    <li>
                        <span class="contact-icon">&#128222;</span>
                        <div>
                            <strong>Phone</strong>
                            <span>+977 98XXXXXXXX</span>
                        </div>
                    </li>
                    <li>
                        <span class="contact-icon">&#128336;</span>
                        <div>
                            <strong>Hours</strong>
                            <span>Mon – Sat &nbsp;9AM – 6PM</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="map-container">
                <div id="showroom-map"></div>
            </div>

        </div>
    </section>

</main>

<!-- Leaflet Map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../../assets/js/map.js"></script>

<?php include('../../includes/footer.php'); ?>