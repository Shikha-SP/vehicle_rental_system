<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/**
 * public/user/index.php  —  TD Rentals homepage (featured car + booking widget)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$car = db()->query("SELECT * FROM cars WHERE is_featured = 1 LIMIT 1")->fetch();

$featuresStmt = db()->prepare("SELECT * FROM car_features WHERE car_id = ? ORDER BY sort_order");
$featuresStmt->execute([$car['id']]);
$features = $featuresStmt->fetchAll();

$testimonials = db()->query("SELECT * FROM testimonials WHERE active = 1 ORDER BY id")->fetchAll();
$fleet        = db()->query("SELECT * FROM cars WHERE is_featured = 0 AND available = 1 LIMIT 3")->fetchAll();

$user = currentUser();

// ── Booking form POST ──────────────────────────────────────────────────────────
$bError = $bSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    if (!$user) {
        header('Location: ' . SITE_URL . '/public/authentication/login.php?redirect=user/index.php&msg=login_to_book');
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
        $costs = calcBooking((float)$car['price_per_day'], $pickup, $dropoff);

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
                db()->prepare("UPDATE bookings SET status='cancelled'
                               WHERE car_id=? AND user_id=? AND status='pending'
                                 AND NOT (pickup_date >= ? OR dropoff_date <= ?)")
                   ->execute([$car['id'], $user['id'], $dropoff, $pickup]);

                $stmt = db()->prepare("INSERT INTO bookings
                    (user_id,car_id,guest_name,guest_email,pickup_date,dropoff_date,location,rental_total,insurance_fee,grand_total,status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,'confirmed')");
                $stmt->execute([
                    $user['id'], $car['id'], $user['name'], $user['email'],
                    $pickup, $dropoff, $location ?: 'TD Garage, Los Angeles',
                    $costs['rental_total'], $costs['insurance_fee'], $costs['grand_total'],
                ]);
                $bookingId = db()->lastInsertId();
                db()->commit();
                $bSuccess = "Booking #{$bookingId} confirmed! Total: $" . number_format($costs['grand_total'], 0);
            }
        } catch (Exception $e) {
            if (db()->inTransaction()) db()->rollBack();
            $bError = 'A booking error occurred. Please try again.';
        }
    }
}

$redirectMsg = ($_GET['msg'] ?? '') === 'login_to_book'
    ? 'Please log in or register to confirm your booking.'
    : '';

$today          = date('Y-m-d');
$defaultPickup  = date('Y-m-d', strtotime('+1 day'));
$defaultDropoff = date('Y-m-d', strtotime('+4 days'));
$defaults       = calcBooking((float)$car['price_per_day'], $defaultPickup, $defaultDropoff);

// ── View variables for header partial ─────────────────────────────────────────
$pageTitle = 'TD RENTALS — Performance Luxury Car Rental';
$activeNav = 'vehicles';
$assetBase = '../../assets';
$siteBase  = '../..';

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ── Hero ──────────────────────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-bg">
    <img src="../../assets/images/<?= htmlspecialchars($car['image_file']) ?>"
         alt="<?= htmlspecialchars($car['name']) ?>" />
    <div class="hero-overlay"></div>
  </div>
  <div class="hero-content container">
    <span class="badge">Performance King</span>
    <h1 class="hero-title">PORSCHE 911<br /><span class="text-primary">GT3 RS</span></h1>
  </div>
</section>

<!-- ── Specs + Booking ────────────────────────────────────────────────────────── -->
<section class="specs-section">
  <div class="container">
    <div class="specs-grid">

      <!-- Left column: specs & features -->
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

      <!-- Right column: booking widget -->
      <div class="booking-card" id="booking">
        <div class="booking-header">
          <div>
            <p class="booking-label">Daily Rate</p>
            <p class="booking-price">NPR<?= number_format($car['price_per_day'], 0) ?><span class="booking-unit">/day</span></p>
          </div>
          <div class="booking-rating">★ 4.8</div>
        </div>

        <?php if ($redirectMsg): ?>
        <div class="alert alert-error" style="margin-bottom:1rem">
          <?= htmlspecialchars($redirectMsg) ?>
          <div style="margin-top:.75rem;display:flex;gap:.5rem">
            <a href="../../public/authentication/login.php"    class="btn btn-primary btn-sm">LOG IN</a>
            <a href="../../public/authentication/register.php" class="btn btn-ghost  btn-sm">REGISTER</a>
          </div>
        </div>
        <?php elseif ($bSuccess): ?>
        <div class="alert alert-success"><?= htmlspecialchars($bSuccess) ?></div>
        <?php elseif ($bError): ?>
        <div class="alert alert-error"><?= htmlspecialchars($bError) ?></div>
        <?php endif; ?>

        <form method="POST" action="#booking" id="bookingForm">
          <input type="hidden" name="action"     value="book" />
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />

        
          <label class="form-label">Pick-up Date</label>
          <div class="form-input-icon mb-4">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <input type="date" name="pickup_date"  id="pickupDate"  class="date-input" min="<?= $today ?>" value="<?= $defaultPickup ?>"  required />
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
            <div class="summary-row"><span><?= $defaults['days'] ?> Days Rental</span><span id="rentalTotal">NPR<?= number_format($defaults['rental_total'], 0) ?></span></div>
            <div class="summary-row"><span>Insurance (Full Coverage)</span><span id="insuranceTotal">NPR<?= number_format($defaults['insurance_fee'], 0) ?></span></div>
            <div class="summary-row summary-grand">
              <span>TOTAL</span><span class="grand-total" id="grandTotal">NPR<?= number_format($defaults['grand_total'], 0) ?></span>
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-full btn-lg">CONFIRM BOOKING</button>
          <?php if (!$user): ?>
          <p class="booking-note">⚠ <a href="../../public/authentication/login.php" style="color:var(--primary)">Log in</a> or
             <a href="../../public/authentication/register.php" style="color:var(--primary)">register</a> to confirm your booking.</p>
          <?php else: ?>
          <p class="booking-note">No hidden fees. Cancellation up to 48h prior.</p>
          <?php endif; ?>
        </form>
      </div>

    </div>
  </div>
</section>

<!-- ── Testimonials ───────────────────────────────────────────────────────────── -->
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

<!-- ── Fleet preview ──────────────────────────────────────────────────────────── -->
<section class="fleet-section" id="fleet">
  <div class="container">
    <div class="fleet-header">
      <h2 class="section-title">EXPAND THE STABLE</h2>
      <a href="../vehicle/fleet.php" class="fleet-link">VIEW ENTIRE FLEET →</a>
    </div>
    <div class="fleet-grid">
      <?php foreach ($fleet as $fc): ?>
      <a href="../vehicle/car.php?id=<?= $fc['id'] ?>" class="fleet-card">
        <div class="fleet-img-wrap">
          <span class="fleet-tag"><?= htmlspecialchars($fc['tag']) ?></span>
          <img src="../../assets/images/<?= htmlspecialchars($fc['image_file']) ?>"
               alt="<?= htmlspecialchars($fc['name']) ?>" loading="lazy" />
        </div>
        <div class="fleet-info">
          <div>
            <h3><?= htmlspecialchars($fc['name']) ?></h3>
            <p class="fleet-sub"><?= htmlspecialchars($fc['subtitle']) ?></p>
          </div>
          <div class="fleet-price-wrap">
            <span class="fleet-price">NPR<?= number_format($fc['price_per_day'], 0) ?></span>
            <span class="fleet-unit">/DAY</span>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
