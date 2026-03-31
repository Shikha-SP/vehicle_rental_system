<?php 
/**
 * User Dashboard / Home Page
 * Location: public/user/dashboard.php
 */

// 1. Core Includes
include('../../includes/header.php'); // This should already include functions.php
require_once('../../includes/functions.php'); // Ensure functions.php is loaded so we can use e()
include('../../config/db.php'); 
?>
<!-- Link to Leaflet CSS for the Interactive Map -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />

<!-- Link to global styles if available -->
<link rel="stylesheet" href="../../assets/css/Homepage.css">

<main class="dashboard-content">

    <section class="hero-section">
        <div class="container hero-flex">
            <div class="hero-text">
                <h1>ENGINEERED FOR <br><span class="text-red">PERFORMANCE.</span><br>DRIVEN BY YOU.</h1>
                <div class="hero-btns" style="margin-top:20px;">
                    <!-- btn link -->
                    <button class="btn btn-red">Book Now</button>
                    <!-- btn link -->
                    <button class="btn btn-outline">View Fleet</button>
                </div>

                <!-- Search Bar added below the buttons -->
                <!-- You can update action="search.php" when processing search requests on the backend -->
                <form action="search.php" method="GET" class="search-bar-container">
                    <div class="search-field">
                        <label for="pickup_location">Pick-up Location</label>
                        <input type="text" id="pickup_location" name="pickup_location" placeholder="City or Airport">
                    </div>
                    <div class="search-field">
                        <label for="vehicle_type">Vehicle Type</label>
                        <select id="vehicle_type" name="vehicle_type">
                            <option value="">Any Type</option>
                            <option value="sports">Sports Car</option>
                            <option value="luxury">Luxury</option>
                            <option value="suv">SUV</option>
                            <option value="sedan">Sedan</option>
                        </select>
                    </div>
                    <div class="search-field">
                        <label for="duration">Duration</label>
                        <input type="text" id="duration" name="duration" placeholder="e.g. 3 Days">
                    </div>
                    <button type="submit" class="btn-search">Search</button>
                </form>

            </div>
            <!-- [ADD IMAGE OF THE CAR HERE] - Alternatively to a CSS background, you can place a foreground img tag here -->
        </div>
    </section>

    <section class="gallery-section">
        <div class="container">
            <div class="section-header">
                <h2>THE PERFORMANCE GALLERY</h2>
                <a href="#" class="view-all">VIEW ALL MODELS &rarr;</a>
            </div>

            <div class="car-grid">
                <?php
                // Information access details from backend:
                // We're querying the 'vehicles' table to get the brand, name, image, and fuel_range
                $sql = "SELECT brand, name, image, fuel_range FROM vehicles WHERE status = 'available' LIMIT 3";
                $result = $conn->query($sql);

                if ($result && $result->num_rows > 0):
                    while ($car = $result->fetch_assoc()): ?>
                    <div class="car-card">
                        <div class="card-img-wrapper">
                            <!-- [ADD IMAGE OF THE CAR HERE] -->
                            <!-- The image is fetched from the backend ($car['image']) in PNG format over the black background -->
                            <img src="../../uploads/<?php echo e($car['image']); ?>" alt="<?php echo e($car['brand']); ?> Model">
                        </div>
                        <div class="card-info">
                            <!-- Information access details from backend: Brand -->
                            <h3><?php echo e($car['brand']); ?> <span class="badge">NEW</span></h3>
                            
                            <!-- Information access details from backend: Model Name -->
                            <p class="model-name"><?php echo e($car['name']); ?></p>
                            
                            <div class="specs">
                                <!-- Information access details from backend: Range/Specs -->
                                <span>$1,200/day</span> <!-- Example static price to match design, replace with $car['price_per_day'] if available -->
                                
                                <!-- btn link - redirects to car details or booking page -->
                                <button class="btn-link">RENT NOW</button>
                            </div>
                        </div>
                    </div>
                <?php 
                    endwhile; 
                else: 
                ?>
                    <p>No available vehicles found.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="red-banner">
        <div class="container banner-flex" style="display:flex; justify-content:space-between; width:100%;">
            <div class="banner-text">
                <h3 style="margin:0; font-size:1.8rem;">JOIN THE ELITE CLUB</h3>
                <p style="margin:5px 0 0 0;">Get exclusive access to members-only events, priority booking, and premium tier vehicles.</p>
            </div>
            <!-- btn link -->
            <button class="btn btn-white">CREATE AN ACCOUNT</button>
        </div>
    </div>

    <section class="showroom-section">
        <div class="container grid-two" style="display: flex; justify-content: space-between; gap: 40px; width: 100%;">
            <div class="showroom-info" style="flex: 1;">
                <h2>OUR SHOWROOMS</h2>
                <p style="color:#aaa;">Experience engineering excellence in person at our flagship locations.</p>
                <ul class="contact-details" style="list-style: none; padding: 0; color:#ccc;">
                    <li style="margin-bottom: 10px;"><strong>📍 Location:</strong>Naxal Bhagawati Marga, Kathmandu, Nepal</li>
                    <li><strong>📞 Phone:</strong>98 baunna teha bata uta aaunna</li>
                </ul>
            </div>
            <div class="map-container" style="flex: 1; background-color: #222; padding: 10px;">
                <!-- Interactive Showroom Map Container -->
                <div id="showroom-map" style="width: 100%; height: 100%; min-height: 350px; border-radius: 5px;"></div>
            </div>
        </div>
    </section>

</main>

<!-- Leaflet JS for Interactive Map -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<!-- Custom Map Initialization JS -->
<script src="../../assets/js/map.js"></script>

<?php 
// 3. Close the page with the global footer
include('../../includes/footer.php'); 
?>