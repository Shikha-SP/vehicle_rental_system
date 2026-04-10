<?php
/**
 * ==========================================================
 * Landing Page (formerly landing-page.php)
 * File: public/landing_page.php
 * ==========================================================
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (!empty($_SESSION['is_admin'])) {
        header("Location: admin/home_page.php");
        exit;
    } else {
        header("Location: user/home_page.php");
        exit;
    }
}

include('../config/db.php');           // From public/ to config/
include('../includes/header.php');      // From public/ to includes/
require_once('../includes/functions.php'); // From public/ to includes/
?>
<!-- ======================================================
     External Stylesheets
====================================================== -->
<link rel="stylesheet" href="../assets/css/index.css">

<main class="dashboard-content">
    <!-- Hero Section -->
    <header class="hero">
        <!-- Glowing red line effect -->
        <div class="red-glow-line"></div>

        <!-- Hero Background Image (Uploaded car) -->
        <div class="hero-bg-wrapper">
            <img src="../uploads/hero-car.png" alt="Supercar hero background" class="hero-bg">
        </div>

        <div class="hero-content">
            <h4 class="hero-subtitle">ENGINEERED FOR ADRENALINE</h4>
            <h1 class="hero-title">THE <span class="text-red">KINETIC</span><br>GALLERY.</h1>
            <p class="hero-desc">Beyond Transportation We Provide the key automotive excellence. Curated performance for those who demand the pinnacle of engineering.</p>
            <div class="hero-buttons">
                <a href="authentication/login.php" class="btn btn-primary">SECURE THE FLEET</a>
                <a href="authentication/login.php" class="btn btn-secondary">EXPLORE SPECS</a>
            </div>
        </div>
    </header>

    <!-- Curated Collections Section -->
    <section class="collections">
        <h2 class="section-heading">CURATED COLLECTIONS</h2>
        <div class="collections-grid">
            <!-- Item 1: Supercars -->
            <div class="collection-card card-tl">
                <img src="../uploads/car1.png" alt="Supercars image">
                <div class="card-content">
                    <span class="card-category">CATEGORY: PERFORMANCE</span>
                    <h3 class="card-title">SUPERCARS</h3>
                </div>
            </div>

            <!-- Item 2: Classics -->
            <div class="collection-card card-tr">
                <img src="../uploads/car2.png" alt="Classics image">
                <div class="card-content">
                    <span class="card-category">CATEGORY: HERITAGE</span>
                    <h3 class="card-title">CLASSICS</h3>
                </div>
            </div>

            <!-- Item 3: Luxury SUVs -->
            <div class="collection-card card-bl">
                <img src="../uploads/car3.png" alt="Luxury SUVs image">
                <div class="card-content">
                    <span class="card-category">CATEGORY: POWER</span>
                    <h3 class="card-title">LUXURY SUVS</h3>
                </div>
            </div>

            <!-- Item 4: Custom Fleet -->
            <div class="collection-card card-br">
                <img src="../uploads/car4.png" alt="Custom Fleet image">
                <div class="card-content">
                    <span class="card-category">CATEGORY: BESPOKE</span>
                    <h3 class="card-title">CUSTOM FLEET</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Detailed Stats & Features Section -->
    <section class="features">
        <!-- Feature 1 -->
        <div class="feature-card border-accent">
            <div class="feature-icon"><i class="fa-solid fa-gauge-high"></i></div>
            <h3 class="feature-title">24/7 CONCIERGE</h3>
            <p class="feature-desc">Personal liaison for logistics, route planning, and dedicated support across all time zones.</p>
            <span class="feature-number">01</span>
        </div>

        <!-- Feature 2 -->
        <div class="feature-card border-accent">
            <div class="feature-icon"><i class="fa-solid fa-earth-americas"></i></div>
            <h3 class="feature-title">GLOBAL FLEET</h3>
            <p class="feature-desc">Inter-connected hubs in major capitals ensuring your preferred machine is always waiting.</p>
            <span class="feature-number">02</span>
        </div>

        <!-- Feature 3 -->
        <div class="feature-card border-accent">
            <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
            <h3 class="feature-title">Track Ready</h3>
            <p class="feature-desc">Every vehicle is meticulously maintained by master technicians to factory-fresh performance standards.</p>
            <span class="feature-number">03</span>
        </div>
    </section>

    <!-- Global Footprint Section -->
    <section class="global-footprint">
        <h2 class="section-heading text-center">OUR GLOBAL FOOTPRINT</h2>

        <div class="map-container">
            <img src="../uploads/map-bg.png" alt="World Map Silhouette">
            <div class="map-dots">
                <!-- Example geographic pins (using percentage positioning) -->
                <div class="dot" style="top: 35%; left: 22%;"></div> <!-- North America -->
                <div class="dot" style="top: 55%; left: 51%;"></div> <!-- Africa -->
                <div class="dot" style="top: 48.5%; left: 68.3%;"></div> <!-- Asia -->
            </div>
        </div>

        <!-- Statistics Counter -->
        <div class="stats-counter">
            <div class="stat-item">
                <h3 class="stat-num">15+</h3>
                <p class="stat-label">LOCATIONS</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-num">500+</h3>
                <p class="stat-label">VEHICLES</p>
            </div>
            <div class="stat-item">
                <h3 class="stat-num">10K</h3>
                <p class="stat-label">CLIENTS</p>
            </div>
        </div>
    </section>

    <!-- Call to Action Banner -->
    <section class="cta">
        <div class="cta-content">
            <h2 class="cta-title">READY FOR THE <br>THROTTLE?</h2>
            <p class="cta-desc">Join the inner circle of the world's most exclusive driving club. Experience the pinnacle of velocity.</p>
        </div>
        <div class="cta-action-area">
            <a href="authentication/login.php" class="btn btn-white">DISCOVER CARS</a>
        </div>
        <!-- Decorative background element for CTA -->
        <img src="../uploads/cal.png" alt="Brake Caliper Decor" class="cta-bg-image">
    </section>
</main>

<?php include('../includes/footer.php'); ?>