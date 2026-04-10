<?php
/**
 * ==========================================================
 * User Dashboard / Homepage (formerly Index.php)
 * File: public/user/Home.php
 * ==========================================================
 */
session_start();

// AUTH CHECK
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';

// DB + INCLUDES
include('../../config/db.php');
include('../../includes/header.php');
require_once('../../includes/functions.php');

// ----------------------------------------------------------
// Gallery vehicles (MATCHES YOUR DB - NO 'brand' column)
// ----------------------------------------------------------
$gallery_sql = "
SELECT 
    id,
    model,
    license_type,
    image_path,
    price_per_day,
    fuel_capacity
FROM vehicles
WHERE status='available'
LIMIT 3
";
$gallery_result = $conn->query($gallery_sql);

// ----------------------------------------------------------
// Brand filter tabs → uses license_type (NOT brand)
// ----------------------------------------------------------
$brands_sql = "SELECT DISTINCT license_type FROM vehicles ORDER BY license_type";
$brands_result = $conn->query($brands_sql);
?>

<!-- ======================================================
     External Stylesheets
====================================================== -->
<link rel="stylesheet" href="../../assets/css/Homepage.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    /* Ensure map container has proper dimensions */
    #showroom-map {
        height: 400px;
        width: 100%;
        border-radius: 12px;
        z-index: 1;
    }
    
    .map-container {
        position: relative;
        min-height: 400px;
    }
    
    /* Fix for Leaflet tiles loading */
    .leaflet-container {
        z-index: 1;
    }
    
    /* Contact details styling */
    .contact-details {
        list-style: none;
        padding: 0;
        margin-top: 20px;
    }
    
    .contact-details li {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 15px;
    }
    
    .contact-icon {
        font-size: 20px;
        min-width: 30px;
    }
    
    .contact-details div {
        display: flex;
        flex-direction: column;
    }
    
    .contact-details strong {
        font-size: 14px;
        color: #666;
        margin-bottom: 4px;
    }
    
    .grid-two {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        align-items: start;
    }
    
    /* Show in map button styling */
    .show-map-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #c0392b;
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        margin-top: 15px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }
    
    .show-map-btn:hover {
        background: #a93226;
        transform: translateY(-2px);
    }
    
    .map-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.9);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    
    .map-modal.active {
        display: flex;
    }
    
    .map-modal-content {
        position: relative;
        width: 90%;
        max-width: 1200px;
        height: 80%;
        background: white;
        border-radius: 12px;
        overflow: hidden;
    }
    
    .map-modal-header {
        padding: 15px 20px;
        background: #1a1a1a;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .map-modal-header h3 {
        margin: 0;
    }
    
    .close-map {
        background: none;
        border: none;
        color: white;
        font-size: 28px;
        cursor: pointer;
        padding: 0 10px;
    }
    
    .close-map:hover {
        color: #c0392b;
    }
    
    #fullscreen-map {
        height: calc(100% - 60px);
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .grid-two {
            grid-template-columns: 1fr;
            gap: 30px;
        }
    }
</style>

<main class="dashboard-content">

    <!-- ====================================================
         HERO SECTION
    ==================================================== -->
    <section class="hero-section">

        <div class="hero-gradient"></div>

        <div class="hero-car-img">
            <img src="../../uploads/car.png" alt="Featured Vehicle">
        </div>

        <div class="hero-overlay">
            <div class="hero-text">

                <p class="hero-label">WELCOME BACK, <?php echo e(strtoupper($username)); ?></p>
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

        <!-- SEARCH BAR -->
        <form action="search.php" method="GET" class="hero-search-bar">

            <div class="sb-field">
                <span class="sb-icon">📍</span>
                <div class="sb-field-inner">
                    <label>Pick Up Location</label>
                    <input type="text" name="pickup_location" placeholder="Kathmandu">
                </div>
            </div>

            <div class="sb-divider"></div>

            <div class="sb-field">
                <span class="sb-icon">🚗</span>
                <div class="sb-field-inner">
                    <label>Vehicle Type</label>
                    <select name="type">
                        <option value="">All</option>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                        <option value="E">E</option>
                    </select>
                </div>
            </div>

            <div class="sb-divider"></div>

            <div class="sb-field">
                <span class="sb-icon">📅</span>
                <div class="sb-field-inner">
                    <label>Duration</label>
                    <div class="sb-date-range">
                        <input type="date" name="pickup_date">
                        <span>—</span>
                        <input type="date" name="return_date">
                    </div>
                </div>
            </div>

            <button type="submit" class="sb-search-btn">SEARCH</button>

        </form>

    </section>

    <!-- ====================================================
         QUICK DASHBOARD LINKS
    ==================================================== -->
    <section class="container" style="margin-top: 20px;">
        <h2>Quick Access</h2>
        <ul>
            <li><a href="../vehicle/add.php">View Vehicles</a></li>
            <li><a href="../vehicle/edit.php">My Rentals / Bookings</a></li>
        </ul>
    </section>

    <!-- ====================================================
         PERFORMANCE GALLERY (FIXED)
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

                        $price = number_format((float)$car['price_per_day'], 0);
                        $fuel = $car['fuel_capacity'] ? $car['fuel_capacity'] . ' L' : 'N/A';
                        $type_label = strtoupper($car['license_type'] ?? 'CAR');
                        $model = $car['model'];
                        $image = $car['image_path'];
                ?>

                <div class="car-card">

                    <div class="card-type-badge">
                        <?php echo e($type_label); ?>
                    </div>

                    <div class="card-img-wrapper">
                        <?php if (!empty($image)): ?>
                            <img src="../../uploads/<?php echo e($image); ?>" 
                                 alt="<?php echo e($model); ?>">
                        <?php else: ?>
                            <div class="no-img">NO IMAGE</div>
                        <?php endif; ?>
                    </div>

                    <div class="card-info">

                        <h3>
                            <?php echo e($model); ?>
                            <span class="badge">NEW</span>
                        </h3>

                        <div class="card-specs-row">
                            <span class="spec-item">⛽ <?php echo e($fuel); ?></span>
                        </div>

                        <div class="specs">
                            <span class="price">NPR <?php echo $price; ?>/day</span>
                            <a href="booking.php?id=<?php echo (int)$car['id']; ?>" class="btn-rent">
                                RENT NOW
                            </a>
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

            <!-- FIXED BRAND FILTER (license_type) -->
            <div class="brand-filters">
                <?php
                if ($brands_result && $brands_result->num_rows > 0):
                    while ($b = $brands_result->fetch_assoc()):
                ?>
                    <a href="search.php?license_type=<?php echo urlencode($b['license_type']); ?>" class="brand-tab">
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
                <h3>JOIN TD ELITE CLUB</h3>
                <p>Get exclusive access to premium vehicles.</p>
            </div>
            <a href="signup.php" class="btn-white-outline">Apply For Membership</a>
        </div>
    </div>

    <!-- SHOWROOM -->
    <section class="showroom-section">
        <div class="container grid-two">

            <div class="showroom-info">
                <h2>OUR SHOWROOMS</h2>
                <p>Experience engineering excellence in person.</p>
                <ul class="contact-details">
                    <li>
                        <span class="contact-icon">📍</span>
                        <div>
                            <strong>Location</strong>
                            <span>Naxal Bhagawati Marga, Kathmandu</span>
                        </div>
                    </li>
                    <li>
                        <span class="contact-icon">📞</span>
                        <div>
                            <strong>Phone</strong>
                            <span>+977 98XXXXXXXX</span>
                        </div>
                    </li>
                    <li>
                        <span class="contact-icon">🕘</span>
                        <div>
                            <strong>Hours</strong>
                            <span>Mon – Sat &nbsp;9AM – 6PM</span>
                        </div>
                    </li>
                </ul>
                <!-- SHOW IN MAP BUTTON -->
                <button class="show-map-btn" id="showMapBtn">
                    🗺️ Show in Map
                </button>
            </div>

            <div class="map-container">
                <div id="showroom-map"></div>
            </div>

        </div>
    </section>

</main>

<!-- FULLSCREEN MAP MODAL -->
<div id="mapModal" class="map-modal">
    <div class="map-modal-content">
        <div class="map-modal-header">
            <h3>📍 TD Elite Showroom - Naxal, Kathmandu</h3>
            <button class="close-map" id="closeMapBtn">&times;</button>
        </div>
        <div id="fullscreen-map"></div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // Initialize both maps
    document.addEventListener('DOMContentLoaded', function() {
        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('Leaflet not loaded');
            return;
        }
        
        // Naxal, Kathmandu coordinates
        var naxalCoordinates = [27.7172, 85.3240];
        
        // 1. Initialize the small map in the showroom section
        var smallMapContainer = document.getElementById('showroom-map');
        if (smallMapContainer) {
            var smallMap = L.map('showroom-map').setView(naxalCoordinates, 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(smallMap);
            
            var marker = L.marker(naxalCoordinates).addTo(smallMap);
            marker.bindPopup("<b>TD Elite Showroom</b><br>Naxal Bhagawati Marga, Kathmandu").openPopup();
            
            L.circle(naxalCoordinates, {
                color: '#c0392b',
                fillColor: '#e74c3c',
                fillOpacity: 0.2,
                radius: 200
            }).addTo(smallMap);
        }
        
        // 2. Modal functionality
        var modal = document.getElementById('mapModal');
        var showMapBtn = document.getElementById('showMapBtn');
        var closeMapBtn = document.getElementById('closeMapBtn');
        var fullscreenMap = null;
        
        // Function to initialize fullscreen map
        function initFullscreenMap() {
            if (fullscreenMap) {
                fullscreenMap.invalidateSize();
                return;
            }
            
            var fullscreenContainer = document.getElementById('fullscreen-map');
            if (fullscreenContainer) {
                fullscreenMap = L.map('fullscreen-map').setView(naxalCoordinates, 17);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 19
                }).addTo(fullscreenMap);
                
                var fullMarker = L.marker(naxalCoordinates).addTo(fullscreenMap);
                fullMarker.bindPopup("<b>TD Elite Showroom</b><br>Naxal Bhagawati Marga, Kathmandu<br><br>📍 Open: Mon-Sat 9AM-6PM").openPopup();
                
                L.circle(naxalCoordinates, {
                    color: '#c0392b',
                    fillColor: '#e74c3c',
                    fillOpacity: 0.3,
                    radius: 300
                }).addTo(fullscreenMap);
            }
        }
        
        // Show modal with fullscreen map
        if (showMapBtn) {
            showMapBtn.addEventListener('click', function() {
                modal.classList.add('active');
                setTimeout(function() {
                    initFullscreenMap();
                }, 100);
            });
        }
        
        // Close modal
        if (closeMapBtn) {
            closeMapBtn.addEventListener('click', function() {
                modal.classList.remove('active');
            });
        }
        
        // Close modal when clicking outside
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
        
        console.log('Maps initialized successfully');
    });
</script>

<?php include('../../includes/footer.php'); ?>