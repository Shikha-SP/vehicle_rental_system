<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
  
<head>
  <?php if (!empty($extraStyles)) echo $extraStyles; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TD Rentals</title>

    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/loading.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/footer.css">
    <!-- From HEAD: Chatbot & Icons -->
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- From Gaurav: Theme Initialization Script (Prevents FOUC) -->
    <script>
      (function() {
        const saved = localStorage.getItem('td-theme');
        const osPrefers = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
        const theme = saved || osPrefers;
        document.documentElement.setAttribute('data-theme', theme);
      })();
    </script>
</head>
<body>

<!-- Top Progress Bar -->
<div id="td-progress-bar"></div>

<!-- Full-page Overlay -->
<div id="td-overlay">
  <div class="loader-logo">TD <span>RENTALS</span></div>
  <div class="loader-bar-track"><div class="loader-bar-fill"></div></div>
  <div id="td-overlay-msg">Loading…</div>
</div>

<header class="td-site-header">
  <nav class="td-site-header__inner" aria-label="Main navigation">

    <!-- BRAND -->
<a href="<?= !empty($_SESSION['is_admin']) 
    ? '/vehicle_rental_collab_project/public/admin/dashboard.php' 
    : '/vehicle_rental_collab_project/public/landing_page.php' 
?>" class="brand">
  TD <span>RENTALS</span>
</a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <!-- NAV LINKS (only for logged-in users) -->
        <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
        <ul class="nav-links" id="navLinks">
          
          <?php if (!empty($_SESSION['is_admin'])): ?>
            <!-- ADMIN NAVIGATION -->
            <li>
              <a href="/vehicle_rental_collab_project/public/admin/dashboard.php" 
                 class="<?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">Dashboard</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/admin/review_rental_requests.php" 
                 class="<?= ($currentPage == 'review_rental_requests.php') ? 'active' : '' ?>">Review Vehicles</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/admin/vehicle_listings.php" 
                 class="<?= ($currentPage == 'vehicle_listings.php') ? 'active' : '' ?>">Add Vehicles</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/admin/customers.php" 
                 class="<?= ($currentPage == 'customers.php') ? 'active' : '' ?>">Customers</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/admin/audit.php" 
                 class="<?= ($currentPage == 'audit.php') ? 'active' : '' ?>">Analytics</a>
            </li>

          <?php else: ?>
            <!-- REGULAR USER NAVIGATION -->
            <li>
              <a href="/vehicle_rental_collab_project/public/user/home_page.php" 
                 class="<?= ($currentPage == 'home_page.php') ? 'active' : '' ?>">Home</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/vehicle/vehicles.php" 
                 class="<?= ($currentPage == 'vehicles.php') ? 'active' : '' ?>">Vehicles</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/user/bookings.php" 
                 class="<?= ($currentPage == 'bookings.php') ? 'active' : '' ?>">My Bookings</a>
            </li>
            <li>
              <a href="/vehicle_rental_collab_project/public/renter/my_vehicles.php" 
                 class="<?= ($currentPage == 'my_vehicles.php') ? 'active' : '' ?>">My Listings</a>
            </li>
          <?php endif; ?>
          
        </ul>

        <!-- RIGHT SIDE -->
        <div class="nav-auth" id="navAuth">
          <div class="profile-menu">
            <button type="button" class="profile-btn" onclick="toggleDropdown()" aria-haspopup="true" aria-expanded="false" id="profileMenuBtn">
              <?= isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U' ?>
            </button>

            <div class="dropdown" id="dropdownMenu" role="menu" aria-labelledby="profileMenuBtn">
              <?php if (empty($_SESSION['is_admin'])): ?>
                <a href="/vehicle_rental_collab_project/public/user/wishlist.php">My Wishlist</a>
              <?php else: ?>
                <a href="/vehicle_rental_collab_project/public/admin/inquiries.php">Messages</a>
              <?php endif; ?>
              <a href="/vehicle_rental_collab_project/public/user/settings.php">Settings</a>
              <a href="/vehicle_rental_collab_project/public/authentication/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Logout</a>
            </div>

            <div class="profile-mobile-links" aria-label="Account">
              <?php if (empty($_SESSION['is_admin'])): ?>
                <a href="/vehicle_rental_collab_project/public/user/wishlist.php">My Wishlist</a>
              <?php else: ?>
                <a href="/vehicle_rental_collab_project/public/admin/inquiries.php">Messages</a>
              <?php endif; ?>
              <a href="/vehicle_rental_collab_project/public/user/settings.php">Settings</a>
              <a href="/vehicle_rental_collab_project/public/authentication/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Logout</a>
            </div>
          </div>

          <?php if (empty($_SESSION['is_admin'])): ?>
            <a href="/vehicle_rental_collab_project/public/renter/list_car.php" class="btn-primary">
              List Your Vehicle
            </a>
          <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- GUESTS -->
        <div class="nav-auth" id="navAuth">
            <a href="/vehicle_rental_collab_project/public/authentication/login.php" class="btn-ghost">Log In</a>
            <a href="/vehicle_rental_collab_project/public/authentication/signup.php" class="btn-primary">Register</a>
        </div>
    <?php endif; ?>

    <!-- THEME TOGGLE (From Gaurav) -->
    <button id="themeToggleBtn" class="theme-toggle" aria-label="Toggle Theme" title="Toggle Light/Dark Mode">
        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>

    <!-- MOBILE TOGGLE -->
    <button type="button"
      class="nav-toggle"
      id="navToggle"
      aria-label="Open menu"
      aria-expanded="false"
      aria-controls="navAuth">
      <span></span><span></span><span></span>
    </button>

  </nav>
</header>

<!-- AI CHATBOT WIDGET (From HEAD) -->
<div class="chatbot-widget">
    <button class="chatbot-toggle" title="Chat with AI">
        <i class="fa-solid fa-comments"></i>
    </button>
    
    <div class="chatbot-window">
        <div class="chat-header">
            <div class="bot-avatar">
                <i class="fa-solid fa-robot"></i>
            </div>
            <div class="chat-header-info">
                <h3>TD RENTALS AI</h3>
                <span>Always Online</span>
            </div>
        </div>
        
        <div class="chat-messages">
            <div class="typing-indicator">
                <span></span><span></span><span></span>
            </div>
        </div>
        
        <div class="chat-input-area">
            <form class="chat-input-form">
                <input type="text" placeholder="Ask about bookings, wishlist..." required autocomplete="off">
                <button type="submit" class="chat-send-btn">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script src="/vehicle_rental_collab_project/assets/js/chatbot.js"></script>

<script>
function toggleDropdown() {
  if (window.innerWidth <= 768) return;
  const dropdown = document.getElementById("dropdownMenu");
  const btn = document.getElementById("profileMenuBtn");
  if (!dropdown || !btn) return;
  const open = dropdown.classList.toggle("show");
  btn.setAttribute("aria-expanded", open ? "true" : "false");
}

window.onclick = function(e) {
  if (!e.target.matches('.profile-btn')) {
    const dropdown = document.getElementById("dropdownMenu");
    const btn = document.getElementById("profileMenuBtn");
    if (dropdown && dropdown.classList.contains('show')) {
      dropdown.classList.remove('show');
      if (btn) btn.setAttribute("aria-expanded", "false");
    }
  }
};

document.addEventListener('DOMContentLoaded', () => {
  const themeBtn = document.getElementById('themeToggleBtn');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme');
      const next = current === 'light' ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('td-theme', next);
    });
  }

  const navToggle = document.getElementById('navToggle');
  const navLinks = document.getElementById('navLinks');
  const navAuth = document.getElementById('navAuth');

  function closeMobileNav() {
    if (!navToggle) return;
    navToggle.classList.remove('is-open');
    navToggle.setAttribute('aria-expanded', 'false');
    navToggle.setAttribute('aria-label', 'Open menu');
    navLinks?.classList.remove('open');
    navAuth?.classList.remove('open');
    document.body.classList.remove('td-nav-open');
    const dropdown = document.getElementById('dropdownMenu');
    const pbtn = document.getElementById('profileMenuBtn');
    if (dropdown) dropdown.classList.remove('show');
    if (pbtn) pbtn.setAttribute('aria-expanded', 'false');
  }

  if (navToggle) {
    navToggle.addEventListener('click', () => {
      const willOpen = !navToggle.classList.contains('is-open');
      navToggle.classList.toggle('is-open', willOpen);
      navToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      navToggle.setAttribute('aria-label', willOpen ? 'Close menu' : 'Open menu');
      navLinks?.classList.toggle('open', willOpen);
      navAuth?.classList.toggle('open', willOpen);
      document.body.classList.toggle('td-nav-open', willOpen);
    });
  }

  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      if (window.innerWidth > 768) closeMobileNav();
    }, 150);
  });

  /* Close drawer when user tries to scroll the page (wheel / touch drag) */
  function menuOpenBlocksPageScroll() {
    return document.body.classList.contains('td-nav-open') && window.innerWidth <= 768;
  }

  window.addEventListener(
    'wheel',
    () => {
      if (menuOpenBlocksPageScroll()) closeMobileNav();
    },
    { passive: true }
  );

  let touchScrollCloseStartY = null;
  window.addEventListener(
    'touchstart',
    (e) => {
      if (!menuOpenBlocksPageScroll()) {
        touchScrollCloseStartY = null;
        return;
      }
      touchScrollCloseStartY = e.touches[0].clientY;
    },
    { passive: true }
  );
  window.addEventListener(
    'touchmove',
    (e) => {
      if (!menuOpenBlocksPageScroll() || touchScrollCloseStartY === null) return;
      const dy = e.touches[0].clientY - touchScrollCloseStartY;
      if (Math.abs(dy) > 10) {
        closeMobileNav();
        touchScrollCloseStartY = null;
      }
    },
    { passive: true }
  );

  document.querySelectorAll('.td-site-header .nav-links a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeMobileNav();
    });
  });

  document.querySelectorAll('.profile-mobile-links a').forEach((link) => {
    link.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeMobileNav();
    });
  });

  document.querySelectorAll('.nav-auth .btn-primary').forEach((btn) => {
    btn.addEventListener('click', () => {
      if (window.innerWidth <= 768) closeMobileNav();
    });
  });
});
</script>

<script src="/vehicle_rental_collab_project/assets/js/loading.js?v=<?= time() ?>"></script>