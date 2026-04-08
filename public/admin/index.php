<?php
require_once __DIR__ . '/config.php';

$car = db()->query("SELECT * FROM cars WHERE is_featured = 1 LIMIT 1")->fetch();

$features = db()->prepare("SELECT * FROM car_features WHERE car_id = ? ORDER BY sort_order");
$features->execute([$car['id']]);
$features = $features->fetchAll();

$testimonials = db()->query("SELECT * FROM testimonials WHERE active = 1 ORDER BY id")->fetchAll();
$fleet = db()->query("SELECT * FROM cars WHERE is_featured = 0 AND available = 1 LIMIT 3")->fetchAll();

$user = currentUser();

$bError = $bSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book') {
    if (!$user) {
        header('Location: ' . SITE_URL . '/login.php?redirect=index.php&msg=login_to_book');
        exit;
    }
    verifyCsrf();
    $pickup   = $_POST['pickup_date']  ?? '';
    $dropoff  = $_POST['dropoff_date'] ?? '';
    $location = trim($_POST['location'] ?? 'TD Garage, Los Angeles');

    if (!$pickup || !$dropoff) {
        $bError = 'Please select pickup and drop-off dates.';
    } elseif (strtotime($dropoff) <= strtotime($pickup)) {
        $bError = 'Drop-off must be after pickup date.';
    } else {
        $days        = (int)((strtotime($dropoff) - strtotime($pickup)) / 86400);
        $rentalTotal = $days * $car['price_per_day'];
        $insurance   = $days * 150;
        $grandTotal  = $rentalTotal + $insurance;

        try {
            db()->beginTransaction();
            $conflict = db()->prepare("
                SELECT id FROM bookings
                WHERE car_id = ? AND status NOT IN ('cancelled')
                  AND pickup_date < ? AND dropoff_date > ?
                LIMIT 1 FOR UPDATE
            ");
            $conflict->execute([$car['id'], $dropoff, $pickup]);

            if ($conflict->fetch()) {
                db()->rollBack();
                $bError = 'Vehicle not available for the selected dates. Please choose different dates.';
            } else {
                // Auto-cancel previous pending bookings by this user for the same car/overlap
                db()->prepare("
                    UPDATE bookings SET status='cancelled'
                    WHERE car_id=? AND user_id=? AND status='pending'
                      AND NOT (pickup_date >= ? OR dropoff_date <= ?)
                ")->execute([$car['id'], $user['id'], $dropoff, $pickup]);

                $stmt = db()->prepare("INSERT INTO bookings
                    (user_id,car_id,guest_name,guest_email,pickup_date,dropoff_date,location,rental_total,insurance_fee,grand_total,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,'confirmed')");
                $stmt->execute([
                    $user['id'], $car['id'], $user['name'], $user['email'],
                    $pickup, $dropoff, $location ?: 'TD Garage, Los Angeles',
                    $rentalTotal, $insurance, $grandTotal
                ]);
                $bookingId = db()->lastInsertId();
                db()->commit();
                $bSuccess = "Booking #{$bookingId} confirmed! Total: $" . number_format($grandTotal, 0);
            }
        } catch (Exception $e) {
            if (db()->inTransaction()) db()->rollBack();
            $bError = 'A booking error occurred. Please try again.';
        }
    }
}

$redirectMsg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'login_to_book') {
    $redirectMsg = 'Please log in or register to confirm your booking.';
}

$today = date('Y-m-d');
$defaultPickup  = date('Y-m-d', strtotime('+1 day'));
$defaultDropoff = date('Y-m-d', strtotime('+4 days'));
$defaultDays    = 3;
$defaultTotal   = $defaultDays * $car['price_per_day'];
$defaultIns     = $defaultDays * 150;
$defaultGrand   = $defaultTotal + $defaultIns;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>TD RENTALS — Performance Luxury Car Rental</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/vehicle_rental_system/assets/css/style.css" />
</head>
<body>

<nav class="navbar" id="navbar">
  <a href="index.php" class="brand">TD RENTALS</a>
  <div class="nav-links" id="navLinks">
    <a href="#" class="nav-link active">VEHICLES</a>
    <a href="#fleet" class="nav-link">BRANDS</a>
    <a href="#about" class="nav-link">ABOUT</a>
    <a href="#contact" class="nav-link">CONTACT</a>
  </div>
  <div class="nav-actions">
    <?php if ($user): ?>
      <span class="nav-user">Hello, <?= htmlspecialchars($user['name']) ?></span>
      <a href="my-bookings.php" class="btn btn-ghost btn-sm">MY BOOKINGS</a>
      <?php if ($user['role'] === 'admin'): ?><a href="admin.php" class="btn btn-ghost btn-sm">ADMIN</a><?php endif; ?>
      <a href="logout.php" class="btn btn-ghost btn-sm">LOG OUT</a>
    <?php else: ?>
      <a href="login.php" class="btn btn-ghost btn-sm">LOG IN</a>
      <a href="register.php" class="btn btn-primary btn-sm">REGISTER</a>
    <?php endif; ?>
    <button class="hamburger" id="hamburger" aria-label="Menu">&#9776;</button>
  </div>
</nav>

<section class="hero">
  <div class="hero-bg">
    <img src="../../images/<?= htmlspecialchars($car['image_file']) ?>" alt="<?= htmlspecialchars($car['name']) ?>" />
    <div class="hero-overlay"></div>
  </div>
  <div class="hero-content container">
    <span class="badge">Performance King</span>
    <h1 class="hero-title">PORSCHE 911<br /><span class="text-primary">GT3 RS</span></h1>
  </div>
</section>

<section class="specs-section">
  <div class="container">
    <div class="specs-grid">
      <div class="specs-left">
        <div class="specs-chips">
          <div class="spec-chip"><p class="spec-label">TOP SPEED</p><p class="spec-value"><?= htmlspecialchars($car['top_speed']) ?> <span class="spec-unit">KM/H</span></p></div>
          <div class="spec-chip"><p class="spec-label">0-100 KM/H</p><p class="spec-value"><?= htmlspecialchars($car['acceleration']) ?> <span class="spec-unit">SEC</span></p></div>
          <div class="spec-chip"><p class="spec-label">MAX POWER</p><p class="spec-value"><?= htmlspecialchars($car['max_power']) ?> <span class="spec-unit">PS</span></p></div>
          <div class="spec-chip"><p class="spec-label">ENGINE</p><p class="spec-value"><?= htmlspecialchars($car['engine']) ?></p></div>
        </div>
        <h2 class="section-title">ENGINEERED FOR THE LIMIT</h2>
        <div class="specs-desc">
          <p><?= htmlspecialchars($car['description1']) ?></p>
          <p><?= htmlspecialchars($car['description2']) ?></p>
        </div>
        <div class="feature-grid">
          <?php foreach ($features as $f): ?>
          <div class="feature-card">
            <span class="feature-icon"><?= $f['icon'] ?></span>
            <h4><?= htmlspecialchars($f['title']) ?></h4>
            <p><?= htmlspecialchars($f['description']) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="booking-card" id="booking">
        <div class="booking-header">
          <div>
            <p class="booking-label">Daily Rate</p>
            <p class="booking-price">$<?= number_format($car['price_per_day'], 0) ?><span class="booking-unit">/day</span></p>
          </div>
          <div class="booking-rating">★ 4.8</div>
        </div>

        <?php if ($redirectMsg): ?>
        <div class="alert alert-error" style="margin-bottom:1rem">
          <?= htmlspecialchars($redirectMsg) ?>
          <div style="margin-top:.75rem;display:flex;gap:.5rem">
            <a href="login.php" class="btn btn-primary btn-sm">LOG IN</a>
            <a href="register.php" class="btn btn-ghost btn-sm">REGISTER</a>
          </div>
        </div>
        <?php elseif ($bSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($bSuccess) ?></div>
        <?php elseif ($bError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($bError) ?></div>
        <?php endif; ?>

        <form method="POST" action="#booking" id="bookingForm">
          <input type="hidden" name="action" value="book" />
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

          <label class="form-label">Pick-up Date</label>
          <div class="form-input-icon mb-4">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <input type="date" name="pickup_date" id="pickupDate" class="date-input" min="<?= $today ?>" value="<?= $defaultPickup ?>" required />
          </div>

          <label class="form-label">Drop-off Date</label>
          <div class="form-input-icon mb-4">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <input type="date" name="dropoff_date" id="dropoffDate" class="date-input" min="<?= $today ?>" value="<?= $defaultDropoff ?>" required />
          </div>

          <label class="form-label">Location</label>
          <div class="form-input-icon mb-6">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <input type="text" name="location" class="date-input" value="TD Garage, Los Angeles" />
          </div>

          <div class="booking-summary" id="bookingSummary">
            <div class="summary-row"><span>3 Days Rental</span><span id="rentalTotal">$<?= number_format($defaultTotal, 0) ?></span></div>
            <div class="summary-row"><span>Insurance (Full Coverage)</span><span id="insuranceTotal">$<?= number_format($defaultIns, 0) ?></span></div>
            <div class="summary-row summary-grand">
              <span>TOTAL</span><span class="grand-total" id="grandTotal">$<?= number_format($defaultGrand, 0) ?></span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg">CONFIRM BOOKING</button>
          <?php if (!$user): ?>
          <p class="booking-note">⚠ <a href="login.php" style="color:var(--primary)">Log in</a> or <a href="register.php" style="color:var(--primary)">register</a> to confirm your booking.</p>
          <?php else: ?>
          <p class="booking-note">No hidden fees. Cancellation up to 48h prior.</p>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="testimonials-section" id="about">
  <div class="container">
    <h2 class="section-title">CLIENT TESTIMONY <span class="title-line"></span></h2>
    <div class="testimonials-grid">
      <?php foreach ($testimonials as $t): ?>
      <div class="testimonial-card">
        <div class="stars"><?php for ($i = 0; $i < $t['rating']; $i++): ?>★<?php endfor; ?></div>
        <p class="testimonial-text">"<?= htmlspecialchars($t['text']) ?>"</p>
        <div class="testimonial-author">
          <div class="avatar <?= htmlspecialchars($t['color_class']) ?>"><?= htmlspecialchars($t['initials']) ?></div>
          <div>
            <p class="author-name"><?= htmlspecialchars($t['name']) ?></p>
            <p class="author-role"><?= htmlspecialchars($t['role']) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="fleet-section" id="fleet">
  <div class="container">
    <div class="fleet-header">
      <h2 class="section-title">EXPAND THE STABLE</h2>
      <a href="fleet.php" class="fleet-link">VIEW ENTIRE FLEET →</a>
    </div>
    <div class="fleet-grid">
      <?php foreach ($fleet as $fc): ?>
      <a href="car.php?id=<?= $fc['id'] ?>" class="fleet-card">
        <div class="fleet-img-wrap">
          <span class="fleet-tag"><?= htmlspecialchars($fc['tag']) ?></span>
          <img src="images/<?= htmlspecialchars($fc['image_file']) ?>" alt="<?= htmlspecialchars($fc['name']) ?>" loading="lazy" />
        </div>
        <div class="fleet-info">
          <div>
            <h3><?= htmlspecialchars($fc['name']) ?></h3>
            <p class="fleet-sub"><?= htmlspecialchars($fc['subtitle']) ?></p>
          </div>
          <div class="fleet-price-wrap">
            <span class="fleet-price">$<?= number_format($fc['price_per_day'], 0) ?></span>
            <span class="fleet-unit">/DAY</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<footer class="footer" id="contact">
  <div class="container">
    <div class="footer-grid">
      <div>
        <h4 class="footer-brand">TD RENTALS</h4>
        <p class="footer-desc">Defining the pinnacle of luxury automotive mobility.</p>
      </div>
      <div>
        <h4 class="footer-heading">Fleet Navigation</h4>
        <ul class="footer-links">
          <li><a href="#">Hypercars</a></li><li><a href="#">Luxury Sedans</a></li>
          <li><a href="#">Performance SUVs</a></li><li><a href="#">Custom Builds</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">The Company</h4>
        <ul class="footer-links">
          <li><a href="#">Fleet Guide</a></li><li><a href="#">Locations</a></li>
          <li><a href="#">Support</a></li><li><a href="#">Partnerships</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">Connect</h4>
        <p class="footer-desc">Global HQ: Los Angeles, CA<br />Available 24/7 for Elite Members.</p>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2024 TD RENTALS. ENGINEERED FOR PERFORMANCE.</span>
      <div class="footer-legal"><a href="#">Privacy Policy</a><a href="#">Terms of Service</a></div>
    </div>
  </div>
</footer>

<script src="assets/app.js"></script>
</body>
</html>
