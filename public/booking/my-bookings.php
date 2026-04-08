<?php
/**
 * public/booking/my-bookings.php  —  Logged-in user's booking history
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

requireLogin();
$user = currentUser();

$cancelError = $cancelSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verifyCsrf();
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    $stmt = db()->prepare("SELECT id, status, pickup_date FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$bookingId, $user['id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $cancelError = 'Booking not found.';
    } elseif (in_array($booking['status'], ['cancelled', 'completed'])) {
        $cancelError = 'This booking cannot be cancelled.';
    } else {
        db()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
        $cancelSuccess = "Booking #{$bookingId} has been successfully cancelled.";
    }
}

$bookings = db()->prepare("
    SELECT b.*, c.name AS car_name, c.image_file,
           DATEDIFF(b.dropoff_date, b.pickup_date) AS days
    FROM bookings b
    JOIN cars c ON c.id = b.car_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$bookings->execute([$user['id']]);
$bookings = $bookings->fetchAll();

$pageTitle = 'My Bookings — TD RENTALS';
$activeNav = 'bookings';
$assetBase = '../../assets';
$siteBase  = '../..';

$extraHead = '<style>
  .bookings-wrap  { padding: 6rem 0 4rem; min-height: 70vh; }
  .booking-item   { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1.25rem; display: grid; grid-template-columns: 80px 1fr auto; gap: 1.25rem; align-items: center; }
  @media (max-width: 600px) { .booking-item { grid-template-columns: 1fr; } }
  .booking-thumb  { width: 80px; height: 60px; object-fit: cover; border-radius: 6px; }
  .booking-meta   { font-size: .78rem; color: var(--fg-muted); margin-top: .25rem; display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; }
  .booking-car    { font-family: var(--font-display); font-size: 1.3rem; }
  .booking-total  { font-size: .85rem; color: var(--fg-muted); margin-top: .2rem; }
  .bstat          { display: inline-block; font-size: .6rem; font-weight: 700; padding: .2rem .55rem; border-radius: 3px; text-transform: uppercase; letter-spacing: .08em; }
  .bstat-confirmed  { background: hsl(140,50%,18%);  color: hsl(140,60%,72%); }
  .bstat-pending    { background: hsl(45,80%,20%);   color: hsl(45,90%,70%); }
  .bstat-cancelled  { background: hsl(0,50%,18%);    color: hsl(0,70%,72%); }
  .bstat-completed  { background: hsl(220,50%,18%);  color: hsl(220,70%,72%); }
  .cancel-btn     { background: transparent; border: 1px solid hsl(0,50%,40%); color: hsl(0,70%,72%); border-radius: var(--radius); padding: .4rem .9rem; font-size: .75rem; font-weight: 600; cursor: pointer; transition: background .15s; white-space: nowrap; }
  .cancel-btn:hover { background: hsl(0,50%,18%); }
  .empty-state    { text-align: center; padding: 4rem 1rem; color: var(--fg-muted); }
  .dialog-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.7); z-index: 1000; align-items: center; justify-content: center; }
  .dialog-backdrop.open { display: flex; }
  .dialog-box     { background: var(--bg-secondary); border: 1px solid var(--border); border-radius: var(--radius); padding: 2rem; max-width: 420px; width: 90%; }
  .dialog-title   { font-family: var(--font-display); font-size: 1.5rem; margin-bottom: .75rem; }
  .dialog-msg     { font-size: .875rem; color: var(--fg-muted); margin-bottom: 1.5rem; line-height: 1.6; }
  .dialog-actions { display: flex; gap: .75rem; justify-content: flex-end; }
</style>';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="bookings-wrap">
  <div class="container">
    <h1 style="font-family:var(--font-display);font-size:3rem;margin-bottom:.5rem">MY BOOKINGS</h1>
    <p style="color:var(--fg-muted);font-size:.85rem;margin-bottom:2rem">Logged in as <?= htmlspecialchars($user['name']) ?></p>

    <?php if ($cancelSuccess): ?>
    <div class="alert alert-success" style="margin-bottom:1.5rem"><?= htmlspecialchars($cancelSuccess) ?></div>
    <?php elseif ($cancelError): ?>
    <div class="alert alert-error"   style="margin-bottom:1.5rem"><?= htmlspecialchars($cancelError)   ?></div>
    <?php endif; ?>

    <?php if (!$bookings): ?>
    <div class="empty-state">
      <p style="font-size:2rem;margin-bottom:.5rem">🚗</p>
      <p style="font-size:1.1rem;margin-bottom:1rem">No bookings yet.</p>
      <a href="../vehicle/fleet.php" class="btn btn-primary">BROWSE VEHICLES</a>
    </div>
    <?php else: ?>
    <?php foreach ($bookings as $b): ?>
    <div class="booking-item">
      <img src="../../assets/images/<?= htmlspecialchars($b['image_file']) ?>"
           alt="<?= htmlspecialchars($b['car_name']) ?>" class="booking-thumb" />
      <div>
        <p class="booking-car"><?= htmlspecialchars($b['car_name']) ?></p>
        <div class="booking-meta">
          <span>📅 <?= htmlspecialchars($b['pickup_date']) ?> → <?= htmlspecialchars($b['dropoff_date']) ?></span>
          <span>📍 <?= htmlspecialchars($b['location']) ?></span>
          <span>🗓 <?= (int)$b['days'] ?> day<?= $b['days'] != 1 ? 's' : '' ?></span>
          <span>Booking #<?= $b['id'] ?></span>
        </div>
        <p class="booking-total" style="margin-top:.5rem">
          <span class="bstat bstat-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span>
          &nbsp; Total: <strong>$<?= number_format($b['grand_total'], 0) ?></strong>
        </p>
      </div>
      <div>
        <?php if (!in_array($b['status'], ['cancelled', 'completed'])): ?>
        <button class="cancel-btn"
                data-id="<?= $b['id'] ?>"
                data-car="<?= htmlspecialchars($b['car_name'], ENT_QUOTES) ?>"
                data-dates="<?= htmlspecialchars($b['pickup_date'] . ' → ' . $b['dropoff_date'], ENT_QUOTES) ?>"
                onclick="openCancelDialog(this)">
          CANCEL
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Cancel dialog -->
<div class="dialog-backdrop" id="cancelDialog">
  <div class="dialog-box">
    <p class="dialog-title">CANCEL BOOKING?</p>
    <p class="dialog-msg" id="dialogMsg">Are you sure you want to cancel this booking?</p>
    <div class="dialog-actions">
      <button class="btn btn-ghost btn-sm" onclick="closeCancelDialog()">KEEP BOOKING</button>
      <form method="POST" id="cancelForm" style="display:inline">
        <input type="hidden" name="action"     value="cancel" />
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>" />
        <input type="hidden" name="booking_id" id="cancelBookingId" value="" />
        <button type="submit" class="btn btn-sm"
                style="background:hsl(0,60%,45%);color:#fff;border:none;padding:.45rem 1.1rem;border-radius:var(--radius);font-weight:700;cursor:pointer">
          YES, CANCEL
        </button>
      </form>
    </div>
  </div>
</div>

<?php
$extraScripts = '<script>
function openCancelDialog(btn) {
  document.getElementById("cancelBookingId").value = btn.dataset.id;
  document.getElementById("dialogMsg").textContent =
    "Cancel your booking for " + btn.dataset.car + " (" + btn.dataset.dates + ")? This action cannot be undone.";
  document.getElementById("cancelDialog").classList.add("open");
}
function closeCancelDialog() {
  document.getElementById("cancelDialog").classList.remove("open");
}
document.getElementById("cancelDialog").addEventListener("click", function(e) {
  if (e.target === this) closeCancelDialog();
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>
