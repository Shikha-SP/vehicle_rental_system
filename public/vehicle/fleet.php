<?php
/**
 * public/vehicle/fleet.php  —  Browse all available vehicles
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

$cars = db()->query("SELECT * FROM cars WHERE available = 1 ORDER BY is_featured DESC, id ASC")->fetchAll();

$pageTitle = 'Fleet — TD RENTALS';
$activeNav = 'brands';
$assetBase = '../../assets';
$siteBase  = '../..';

require_once __DIR__ . '/../../includes/header.php';
?>

<section class="page-hero">
  <div class="container">
    <h1 class="page-title">OUR FLEET</h1>
    <p style="color:var(--fg-muted);font-size:.9rem;margin-top:.5rem"><?= count($cars) ?> vehicles available</p>
  </div>
</section>

<section style="padding:3rem 0;background:var(--bg)">
  <div class="container">
    <div class="fleet-grid">
      <?php foreach ($cars as $fc): ?>
      <a href="car.php?id=<?= $fc['id'] ?>" class="fleet-card">
        <div class="fleet-img-wrap">
          <span class="fleet-tag"><?= htmlspecialchars($fc['tag']) ?></span>
          <?php if ($fc['is_featured']): ?>
          <span class="fleet-tag" style="top:0.75rem;right:0.75rem;left:auto;background:var(--primary);color:#fff">FEATURED</span>
          <?php endif; ?>
          <img src="../../assets/images/<?= htmlspecialchars($fc['image_file']) ?>"
               alt="<?= htmlspecialchars($fc['name']) ?>" loading="lazy" />
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
