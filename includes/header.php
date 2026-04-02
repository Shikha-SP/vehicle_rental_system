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

    <!-- ✅ Always use absolute paths -->
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/footer.css">
</head>
<body>

<header>
  <nav>
    <!-- ✅ Brand -->
    <a href="/vehicle_rental_collab_project/public/landing_page.php" class="brand">
      TD <span>RENTALS</span>
    </a>

    <!-- ✅ Nav Links -->
    <ul class="nav-links" id="navLinks">
      <li><a href="#">Vehicles</a></li>
      <li><a href="#">Brands</a></li>
      <li><a href="#">About</a></li>
      <li><a href="#">Contact</a></li>
    </ul>

    <!-- ✅ Auth Section -->
    <div class="nav-auth" id="navAuth">

      <?php if (isset($_SESSION['user_id'])): ?>

        <span class="welcome-msg">
          Hi, <?= htmlspecialchars($_SESSION['username']) ?>
        </span>

        <?php if (!empty($_SESSION['is_admin'])): ?>
          <a href="/vehicle_rental_collab_project/public/admin/home_page.php" class="btn-admin">
            Admin
          </a>
        <?php else: ?>
          <a href="/vehicle_rental_collab_project/public/user/home_page.php" class="btn-ghost">
            Dashboard
          </a>
        <?php endif; ?>

        <a href="/vehicle_rental_collab_project/public/authentication/logout.php" class="btn-ghost">
          Logout
        </a>

      <?php else: ?>

        <a href="/vehicle_rental_collab_project/public/authentication/login.php" class="btn-ghost">
          Log In
        </a>

        <a href="/vehicle_rental_collab_project/public/authentication/signup.php" class="btn-primary">
          Register
        </a>

      <?php endif; ?>

    </div>

    <!-- ✅ Mobile Toggle -->
    <button class="nav-toggle"
      onclick="document.getElementById('navLinks').classList.toggle('open'); 
               document.getElementById('navAuth').classList.toggle('open')">
      <span></span><span></span><span></span>
    </button>

  </nav>
</header>