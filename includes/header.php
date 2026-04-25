<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TD Rentals</title>

    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/footer.css">

    <!-- Theme Initialization Script (Prevents FOUC) -->
    <script>
      (function() {
        const theme = localStorage.getItem('td-theme') || 'dark';
        document.documentElement.setAttribute('data-theme', theme);
      })();
    </script>
</head>
<body>

<header>
  <nav>

    <!-- BRAND -->
    <a href="/vehicle_rental_collab_project/public/landing_page.php" class="brand">
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
          <!-- PROFILE DROPDOWN -->
          <div class="profile-menu">
            <button class="profile-btn" onclick="toggleDropdown()">
              <?= isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 1)) : 'U' ?>
            </button>

            <div class="dropdown" id="dropdownMenu">
              <a href="/vehicle_rental_collab_project/public/user/settings.php">Settings</a>
              <a href="/vehicle_rental_collab_project/public/authentication/logout.php?return=<?= urlencode($_SERVER['REQUEST_URI']) ?>">Logout</a>
            </div>
          </div>

          <!-- MAIN CTA - Only show for regular users -->
          <?php if (empty($_SESSION['is_admin'])): ?>
            <a href="/vehicle_rental_collab_project/public/renter/list_car.php" class="btn-primary">
              List Your Vehicle
            </a>
          <?php endif; ?>
        </div>
        
    <?php else: ?>
        <!-- ONLY LOGIN & REGISTER FOR GUESTS -->
        <div class="nav-auth" id="navAuth">
            <a href="/vehicle_rental_collab_project/public/authentication/login.php" class="btn-ghost">
              Log In
            </a>

            <a href="/vehicle_rental_collab_project/public/authentication/signup.php" class="btn-primary">
              Register
            </a>
        </div>
    <?php endif; ?>

    <!-- THEME TOGGLE -->
    <button id="themeToggleBtn" class="theme-toggle" aria-label="Toggle Theme" title="Toggle Light/Dark Mode">
        <svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        <svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
    </button>

    <!-- MOBILE TOGGLE -->
    <button class="nav-toggle"
      onclick="document.getElementById('navLinks').classList.toggle('open'); 
               document.getElementById('navAuth').classList.toggle('open')">
      <span></span><span></span><span></span>
    </button>

  </nav>
</header>

<!-- DROPDOWN SCRIPT -->
<script>
function toggleDropdown() {
  document.getElementById("dropdownMenu").classList.toggle("show");
}

// close dropdown when clicking outside
window.onclick = function(e) {
  if (!e.target.matches('.profile-btn')) {
    const dropdown = document.getElementById("dropdownMenu");
    if (dropdown && dropdown.classList.contains('show')) {
      dropdown.classList.remove('show');
    }
  }
}

// Theme Toggle Logic
document.addEventListener('DOMContentLoaded', () => {
  const themeBtn = document.getElementById('themeToggleBtn');

  themeBtn.addEventListener('click', () => {
    const current = document.documentElement.getAttribute('data-theme');
    const next = current === 'light' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('td-theme', next);
  });
});
</script>