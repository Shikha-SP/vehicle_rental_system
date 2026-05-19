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
$extraStyles = '
<link rel="stylesheet" href="../assets/css/index.css">
<link rel="stylesheet" href="../assets/css/scroll-reveal.css">

<!-- Preload hero transition videos so they are buffered before user interacts -->
<link rel="preload" as="video" href="../assets/images/whitetodarkmode.webm" type="video/webm">
<link rel="preload" as="video" href="../assets/images/darktowhitemode.webm" type="video/webm">
';
include('../config/db.php');
include('../includes/header.php');
require_once('../includes/functions.php');
?>

<main class="dashboard-content">
    <!-- Hero Section -->
    <header class="hero" id="hero">
        <!-- Two hero videos for theme transitions -->
        <video id="hero-video-todark" muted playsinline preload="auto" class="hero-video">
            <source src="../assets/images/whitetodarkmode.webm" type="video/webm">
            <source src="../assets/images/whitetodarkmode.mp4" type="video/mp4">
        </video>

        <video id="hero-video-tolight" muted playsinline preload="auto" class="hero-video hero-video-hidden">
            <source src="../assets/images/darktowhitemode.webm" type="video/webm">
            <source src="../assets/images/darktowhitemode.mp4" type="video/mp4">
        </video>

        <!-- Overlay keeps text legible over any video frame -->
        <div class="hero-overlay"></div>

        <div class="hero-content">
            <h4 class="hero-subtitle">ENGINEERED FOR ADRENALINE</h4>
            <h1 class="hero-title">THE <span class="text-red">KINETIC</span><br>GALLERY.</h1>
            <p class="hero-desc">Beyond Transportation We Provide the key automotive excellence. Curated performance for
                those who demand the pinnacle of engineering.</p>
            <div class="hero-buttons">
                <a href="authentication/login.php" class="btn btn-primary">SECURE THE FLEET</a>
                <a href="authentication/login.php" class="btn btn-secondary">EXPLORE SPECS</a>
            </div>
        </div>
    </header>

    <!-- Curated Collections Section -->
    <section class="collections" style="overflow-x: hidden;">
        <div style="text-align: center; margin-bottom: 40px;">
            <h2 class="section-heading text-center">CURATED COLLECTIONS</h2>
        </div>

        <div class="slider-wrapper">
            <!-- Glassmorphic buttons -->
            <button class="slider-btn left"><i class="fa-solid fa-chevron-left"></i></button>
            <button class="slider-btn right"><i class="fa-solid fa-chevron-right"></i></button>

            <div class="slider-container" id="landingSlider">
                <!-- Original set of cards -->
                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD1.png" alt="Supercars image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: PERFORMANCE</span>
                        <h3 class="card-title">SUPERCARS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD2.png" alt="Classics image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: HERITAGE</span>
                        <h3 class="card-title">CLASSICS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD3.png" alt="Luxury SUVs image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: POWER</span>
                        <h3 class="card-title">LUXURY SUVS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD4.png" alt="Custom Fleet image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: BESPOKE</span>
                        <h3 class="card-title">CUSTOM SELECTION</h3>
                    </div>
                </div>

                <!-- Duplicated set of cards for seamless infinite sliding loop -->
                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD1.png" alt="Supercars image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: PERFORMANCE</span>
                        <h3 class="card-title">SUPERCARS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD2.png" alt="Classics image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: HERITAGE</span>
                        <h3 class="card-title">CLASSICS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD3.png" alt="Luxury SUVs image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: POWER</span>
                        <h3 class="card-title">LUXURY SUVS</h3>
                    </div>
                </div>

                <div class="collection-card element-class">
                    <img src="../assets/images/LandingPageD4.png" alt="Custom Fleet image">
                    <div class="card-content">
                        <span class="card-category">CATEGORY: BESPOKE</span>
                        <h3 class="card-title">CUSTOM SELECTION</h3>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Detailed Stats & Features Section -->
    <section class="features">
        <!-- Feature 1 -->
        <div class="feature-card border-accent element-class">
            <div class="feature-icon"><i class="fa-solid fa-gauge-high"></i></div>
            <h3 class="feature-title">24/7 CONCIERGE</h3>
            <p class="feature-desc">Personal liaison for logistics, route planning, and dedicated support across all
                time zones.</p>
            <span class="feature-number">01</span>
        </div>

        <!-- Feature 2 -->
        <div class="feature-card border-accent element-class">
            <div class="feature-icon"><i class="fa-solid fa-earth-americas"></i></div>
            <h3 class="feature-title">GLOBAL NETWORK</h3>
            <p class="feature-desc">Inter-connected hubs in major capitals ensuring your preferred machine is always
                waiting.</p>
            <span class="feature-number">02</span>
        </div>

        <!-- Feature 3 -->
        <div class="feature-card border-accent element-class">
            <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
            <h3 class="feature-title">Track Ready</h3>
            <p class="feature-desc">Every vehicle is meticulously maintained by master technicians to factory-fresh
                performance standards.</p>
            <span class="feature-number">03</span>
        </div>
    </section>
    <!-- Call to Action Banner -->
    <section class="cta">
        <div class="cta-content element-class">
            <h2 class="cta-title">READY FOR THE <br>THROTTLE?</h2>
            <p class="cta-desc">Join the inner circle of the world's most exclusive driving club. Experience the
                pinnacle of velocity.</p>
        </div>
        <div class="cta-action-area element-class">
            <a href="authentication/login.php" class="btn btn-white">DISCOVER CARS</a>
        </div>
    </section>
    <!-- Global Footprint Section -->
    <section class="global-footprint">
        <h2 class="section-heading text-center">OUR GLOBAL FOOTPRINT</h2>

        <div class="map-container element-class">
            <img src="../assets/images/LandingPageMap.png" alt="World Map Silhouette">
            <div class="map-dots">
                <!-- Example geographic pins (using percentage positioning) -->
                <div class="dot" style="top: 35%; left: 22%;"></div> <!-- North America -->
                <div class="dot" style="top: 55%; left: 51%;"></div> <!-- Africa -->
                <div class="dot" style="top: 48.5%; left: 70.5%;"></div> <!-- Asia -->
            </div>
        </div>

        <!-- Statistics Counter -->
        <div class="stats-counter">
            <div class="stat-item element-class">
                <h3 class="stat-num" data-target="15" data-suffix="+">0+</h3>
                <p class="stat-label">LOCATIONS</p>
            </div>
            <div class="stat-item element-class">
                <h3 class="stat-num" data-target="500" data-suffix="+">0+</h3>
                <p class="stat-label">VEHICLES</p>
            </div>
            <div class="stat-item element-class">
                <h3 class="stat-num" data-target="10" data-suffix="K">0K</h3>
                <p class="stat-label">CLIENTS</p>
            </div>
        </div>
    </section>

</main>

<script>
    // --- Curated Collections Glider Slider logic ---
    (function () {
        const container = document.getElementById('landingSlider');
        const wrapper = container ? container.closest('.slider-wrapper') : null;
        if (!container || !wrapper) return;

        let isHovered = false;
        let scrollSpeed = 0.8; // px per frame
        let scrollPos = 0;

        // We duplicated the elements in HTML. Let's calculate the real content width (half of total scrollWidth).
        // This allows seamless infinite looping.
        function getHalfLimit() {
            return container.scrollWidth / 2;
        }

        function step() {
            if (!isHovered) {
                scrollPos += scrollSpeed;
                const limit = getHalfLimit();
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
        // Delay slightly to allow layout calculations
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
                container.scrollBy({ left: -300, behavior: 'smooth' });
                // Sync position after smooth scroll
                setTimeout(() => { scrollPos = container.scrollLeft; }, 400);
            });
        }

        if (rightBtn) {
            rightBtn.addEventListener('click', () => {
                container.scrollBy({ left: 300, behavior: 'smooth' });
                // Sync position after smooth scroll
                setTimeout(() => { scrollPos = container.scrollLeft; }, 400);
            });
        }
    })();

    // --- Scroll-Activated Statistics Counting ---
    (function () {
        const counters = document.querySelectorAll('.stat-num');
        const countUp = (counter) => {
            const target = parseInt(counter.getAttribute('data-target'));
            const suffix = counter.getAttribute('data-suffix') || '';
            const duration = 1500; // ms
            let startTime = null;

            const animate = (timestamp) => {
                if (!startTime) startTime = timestamp;
                const progress = timestamp - startTime;
                const percentage = Math.min(progress / duration, 1);

                // Ease out quad
                const easePercentage = percentage * (2 - percentage);
                const currentVal = Math.floor(easePercentage * target);

                counter.textContent = currentVal + suffix;

                if (percentage < 1) {
                    requestAnimationFrame(animate);
                } else {
                    counter.textContent = target + suffix;
                }
            };
            requestAnimationFrame(animate);
        };

        const statsSection = document.querySelector('.stats-counter');
        if (statsSection) {
            const observer = new IntersectionObserver((entries, obs) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        counters.forEach(countUp);
                        obs.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });
            observer.observe(statsSection);
        }
    })();

    // --- Hero Transition Videos & Preloading logic ---
    (function () {
        const toDarkVid = document.getElementById('hero-video-todark');   // white → dark
        const toLightVid = document.getElementById('hero-video-tolight');  // dark  → white
        if (!toDarkVid || !toLightVid) return;

        const SPEED = 2.5;

        // Explicitly set the speed immediately
        toDarkVid.playbackRate = SPEED;
        toLightVid.playbackRate = SPEED;

        function isDark() {
            return document.documentElement.getAttribute('data-theme') !== 'light';
        }

        // --- Show/hide helpers ---
        function showVideo(active, hidden) {
            active.style.opacity = '1';
            active.style.zIndex = '1';
            hidden.style.opacity = '0';
            hidden.style.zIndex = '0';
        }

        // --- Set the correct still frame silently (no animation) ---
        function initStillFrame() {
            toDarkVid.playbackRate = SPEED;
            toLightVid.playbackRate = SPEED;

            if (isDark()) {
                const setEnd = () => {
                    toDarkVid.currentTime = toDarkVid.duration;
                    toDarkVid.pause();
                };
                if (toDarkVid.readyState >= 1) { setEnd(); }
                else { toDarkVid.addEventListener('loadedmetadata', setEnd, { once: true }); }
                showVideo(toDarkVid, toLightVid);
            } else {
                toDarkVid.currentTime = 0;
                toDarkVid.pause();
                showVideo(toDarkVid, toLightVid);
            }
        }

        // --- Force buffering ---
        window.addEventListener('load', () => {
            toDarkVid.load();
            toLightVid.load();

            toDarkVid.playbackRate = SPEED;
            toLightVid.playbackRate = SPEED;

            // JS backup preload for Firefox (ignores <link rel=preload as=video>)
            const preload = (src) => {
                const v = document.createElement('video');
                v.src = src; v.muted = true; v.preload = 'auto';
                v.style.display = 'none';
                v.load();
            };
            preload('../assets/images/whitetodarkmode.webm');
            preload('../assets/images/darktowhitemode.webm');

            initStillFrame();
        });

        // Also init immediately for faster first paint
        initStillFrame();

        // --- Theme toggle handler ---
        // Track the theme at observer-start time so we can detect REAL changes
        // and ignore any spurious fires during page init.
        let lastTheme = document.documentElement.getAttribute('data-theme') ?? 'dark';

        function onThemeChange() {
            const currentTheme = document.documentElement.getAttribute('data-theme') ?? 'dark';
            // Ignore if the theme attribute didn't actually change value
            if (currentTheme === lastTheme) return;
            lastTheme = currentTheme;

            if (currentTheme !== 'light') {
                // Going TO dark → play whitetodarkmode forward
                toDarkVid.currentTime = 0;
                toDarkVid.playbackRate = SPEED;
                showVideo(toDarkVid, toLightVid);
                toDarkVid.play().catch(() => initStillFrame());
            } else {
                // Going TO light → play darktowhitemode forward
                toLightVid.currentTime = 0;
                toLightVid.playbackRate = SPEED;
                showVideo(toLightVid, toDarkVid);
                toLightVid.play().catch(() => initStillFrame());
            }
        }

        const observer = new MutationObserver(onThemeChange);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    })();
</script>

<?php include('../includes/footer.php'); ?>