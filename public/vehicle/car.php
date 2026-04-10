<?php
/**
 * public/vehicle/car.php  —  Individual vehicle detail page + booking widget
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: fleet.php'); exit; }

$car = db()->prepare("SELECT * FROM cars WHERE id = ? AND available = 1 LIMIT 1");
$car->execute([$id]);
$car = $car->fetch();
if (!$car) { header('Location: fleet.php'); exit; }

$featuresStmt = db()->prepare("SELECT * FROM car_features WHERE car_id = ? ORDER BY sort_order");
$featuresStmt->execute([$id]);
$features = $featuresStmt->fetchAll();

$user  = currentUser();
$today = date('Y-m-d');

// ── Booking POST ───────────────────────────────────────────────────────────────
$bError = $bSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book') {
    if (!$user) {
        header('Location: ' . SITE_URL . '/public/authentication/login.php?redirect=vehicle%2Fcar.php%3Fid=' . $id . '&msg=login_to_book');
        exit;
    }
    verifyCsrf();
    $pickup   = $_POST['pickup_date']  ?? '';
    $dropoff  = $_POST['dropoff_date'] ?? '';
    $location = trim($_POST['location'] ?? 'TD Garage, Los Angeles');

    if (!$pickup || !$dropoff) {
        $bError = 'Please select dates.';
    } elseif ($pickup < $today) {
        $bError = 'Pickup date cannot be in the past.';
    } elseif (strtotime($dropoff) <= strtotime($pickup)) {
        $bError = 'Drop-off date must be after the pickup date.';
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
                    $user['id'], $car['id'],
                    $user['name'], $user['email'],
                    $pickup, $dropoff, $location,
                    $costs['rental_total'], $costs['insurance_fee'], $costs['grand_total'],
                ]);
                $newId = db()->lastInsertId();
                db()->commit();
                $bSuccess = "Booking confirmed! Booking ID: {$newId} — Total: $" . number_format($costs['grand_total'], 0);
            }
        } catch (Exception $e) {
            if (db()->inTransaction()) db()->rollBack();
            $bError = 'A booking error occurred. Please try again.';
        }
    }
}

$defaultPickup  = date('Y-m-d', strtotime('+1 day'));
$defaultDropoff = date('Y-m-d', strtotime('+4 days'));
$defaults       = calcBooking((float)$car['price_per_day'], $defaultPickup, $defaultDropoff);

$pageTitle = htmlspecialchars($car['name']) . ' — TD RENTALS';
$activeNav = 'brands';
$assetBase = '../../assets';
$siteBase  = '../..';

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Hero image -->
<div style="position:relative;height:55vh;overflow:hidden;background:var(--bg-surface);padding-top:4rem">
  <img src="../../assets/images/<?= htmlspecialchars($car['image_file']) ?>"
       alt="<?= htmlspecialchars($car['name']) ?>"
       style="width:100%;height:100%;object-fit:cover;opacity:.7" />
  <div style="position:absolute;inset:0;background:linear-gradient(to top,var(--bg) 0%,transparent 70%)"></div>
  <div style="position:absolute;bottom:2rem;left:0;right:0" class="container">
    <span class="badge"><?= htmlspecialchars($car['tag']) ?></span>
    <h1 style="font-family:var(--font-display);font-size:clamp(2.5rem,6vw,6rem);line-height:1">
      <?= htmlspecialchars($car['name']) ?>
    </h1>
  </div>
</div>

<section style="background:var(--bg)">
  <div class="container car-detail-grid">

    <!-- Specs & features -->
    <div>
      <?php if ($car['top_speed']): ?>
      <div class="specs-chips">
        <div class="spec-chip"><p class="spec-label">TOP SPEED</p><p class="spec-value"><?= htmlspecialchars($car['top_speed']) ?> <span class="spec-unit">KM/H</span></p></div>
        <div class="spec-chip"><p class="spec-label">0-100 KM/H</p><p class="spec-value"><?= htmlspecialchars($car['acceleration']) ?> <span class="spec-unit">SEC</span></p></div>
        <div class="spec-chip"><p class="spec-label">MAX POWER</p><p class="spec-value"><?= htmlspecialchars($car['max_power']) ?> <span class="spec-unit">PS</span></p></div>
        <div class="spec-chip"><p class="spec-label">ENGINE</p><p class="spec-value"><?= htmlspecialchars($car['engine']) ?></p></div>
      </div>
      <?php endif; ?>

      <h2 class="section-title"><?= htmlspecialchars($car['subtitle']) ?></h2>
      <div class="specs-desc">
        <?php if ($car['description1']): ?><p><?= htmlspecialchars($car['description1']) ?></p><?php endif; ?>
        <?php if ($car['description2']): ?><p><?= htmlspecialchars($car['description2']) ?></p><?php endif; ?>
      </div>

      <?php if ($features): ?>
      <div class="feature-grid" style="margin-top:2rem">
        <?php foreach ($features as $f): ?>
        <div class="feature-card">
          <span class="feature-icon"><?= $f['icon'] ?></span>
          <h4><?= htmlspecialchars($f['title']) ?></h4>
          <p><?= htmlspecialchars($f['description']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Booking widget -->
    <div class="booking-card" id="booking">
      <div class="booking-header">
        <div>
          <p class="booking-label">Daily Rate</p>
          <p class="booking-price">रू<?= number_format($car['price_per_day'], 0) ?><span class="booking-unit">/day</span></p>
        </div>
        <div class="booking-rating">★ 4.8</div>
      </div>

      <?php if ($bSuccess): ?>
      <div class="alert alert-success"><?= htmlspecialchars($bSuccess) ?></div>
      <?php elseif ($bError): ?>
      <div class="alert alert-error"><?= htmlspecialchars($bError) ?></div>
      <?php endif; ?>

      <?php if (!$user): ?>
      <div class="alert alert-error" style="margin-bottom:1rem">
        <a href="../authentication/login.php?redirect=vehicle%2Fcar.php%3Fid=<?= $car['id'] ?>" style="color:var(--primary)">Log in</a> or
        <a href="../authentication/register.php" style="color:var(--primary)">register</a> to book this vehicle.
      </div>
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
          <div class="summary-row"><span><?= $defaults['days'] ?> Days Rental</span><span id="rentalTotal">रू<?= number_format($defaults['rental_total'], 0) ?></span></div>
          <div class="summary-row"><span>Insurance (Full Coverage)</span><span id="insuranceTotal">रू<?= number_format($defaults['insurance_fee'], 0) ?></span></div>
          <div class="summary-row summary-grand">
            <span>TOTAL</span><span class="grand-total" id="grandTotal">रू<?= number_format($defaults['grand_total'], 0) ?></span>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-full btn-lg">CONFIRM BOOKING</button>
        <?php if (!$user): ?>
        <p class="booking-note">⚠ You must <a href="../authentication/login.php" style="color:var(--primary)">log in</a> to confirm.</p>
        <?php else: ?>
        <p class="booking-note">No hidden fees. Cancellation up to 48h prior.</p>
        <?php endif; ?>
      </form>
    </div>

  </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
