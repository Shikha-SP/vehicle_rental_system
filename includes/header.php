<?php
/**
 * includes/header.php
 * Shared HTML <head> + navbar partial.
 *
 * Expected variables provided by the including page:
 *   $pageTitle  (string)  — value for <title>
 *   $activeNav  (string)  — 'vehicles' | 'brands' | 'about' | 'contact' | 'bookings'
 *
 * How to include (from any public/subfolder page):
 *   $pageTitle = 'TD RENTALS — Home';
 *   $activeNav = 'vehicles';
 *   require_once ROOT_PATH . '/includes/header.php';
 */

// ROOT_PATH must already be defined via config/db.php
$user = currentUser();

// Depth-aware asset path: pages sit at public/<subfolder>/, so assets are ../../assets/
// We expose a helper variable so every page can use it.
// Pages set $assetBase before including header if they need a custom depth.
$assetBase = $assetBase ?? '../../assets';
$siteBase  = $siteBase  ?? '../..';    // path back to project root
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'TD RENTALS') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= $assetBase ?>/css/style.css" />
  <?php if (!empty($extraHead)) echo $extraHead; ?>
</head>
<body>

<nav class="navbar" id="navbar">
  <a href="<?= $siteBase ?>/public/user/index.php" class="brand">TD RENTALS</a>

  <div class="nav-links" id="navLinks">
    <a href="<?= $siteBase ?>/public/user/index.php"
       class="nav-link <?= ($activeNav ?? '') === 'vehicles' ? 'active' : '' ?>">VEHICLES</a>
    <a href="<?= $siteBase ?>/public/vehicle/fleet.php"
       class="nav-link <?= ($activeNav ?? '') === 'brands'   ? 'active' : '' ?>">BRANDS</a>
    <a href="<?= $siteBase ?>/public/user/index.php#about"
       class="nav-link <?= ($activeNav ?? '') === 'about'    ? 'active' : '' ?>">ABOUT</a>
    <a href="<?= $siteBase ?>/public/user/index.php#contact"
       class="nav-link <?= ($activeNav ?? '') === 'contact'  ? 'active' : '' ?>">CONTACT</a>
  </div>

  <div class="nav-actions">
    <?php if ($user): ?>
      <span class="nav-user">Hello, <?= htmlspecialchars($user['name']) ?></span>
      <a href="<?= $siteBase ?>/public/booking/my-bookings.php" class="btn btn-ghost btn-sm">MY BOOKINGS</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="<?= $siteBase ?>/public/admin/admin.php" class="btn btn-ghost btn-sm">ADMIN</a>
      <?php endif; ?>
      <a href="<?= $siteBase ?>/public/authentication/logout.php" class="btn btn-ghost btn-sm">LOG OUT</a>
    <?php else: ?>
      <a href="<?= $siteBase ?>/public/authentication/login.php"    class="btn btn-ghost btn-sm">LOG IN</a>
      <a href="<?= $siteBase ?>/public/authentication/register.php" class="btn btn-primary btn-sm">REGISTER</a>
    <?php endif; ?>
    <button class="hamburger" id="hamburger" aria-label="Menu">&#9776;</button>
  </div>
</nav>
