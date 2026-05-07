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
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
              <?php if (empty($_SESSION['is_admin'])): ?>
                <a href="/vehicle_rental_collab_project/public/user/wishlist.php">My Wishlist</a>
              <?php else: ?>
                <a href="/vehicle_rental_collab_project/public/admin/inquiries.php">Inbox</a>
              <?php endif; ?>
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
</script>

<!-- AI CHATBOT WIDGET -->
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
            <!-- Messages injected by JS -->
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