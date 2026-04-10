<?php
/**
 * includes/footer.php
 * Shared footer + closing </body></html> partial.
 *
 * Expected variables provided by the including page (all optional):
 *   $siteBase   (string)  — relative path back to project root  (default '../../')
 *   $assetBase  (string)  — relative path to assets/            (default '../../assets')
 *   $extraScripts (string) — any additional <script> tags to inject before </body>
 *
 * How to include:
 *   require_once ROOT_PATH . '/includes/footer.php';
 */

$assetBase = $assetBase ?? '../../assets';
$siteBase  = $siteBase  ?? '../..';
?>

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
          <li><a href="<?= $siteBase ?>/public/vehicle/fleet.php">Hypercars</a></li>
          <li><a href="<?= $siteBase ?>/public/vehicle/fleet.php">Luxury Sedans</a></li>
          <li><a href="<?= $siteBase ?>/public/vehicle/fleet.php">Performance SUVs</a></li>
          <li><a href="<?= $siteBase ?>/public/vehicle/fleet.php">Custom Builds</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer-heading">The Company</h4>
        <ul class="footer-links">
          <li><a href="<?= $siteBase ?>/public/vehicle/fleet.php">Fleet Guide</a></li>
          <li><a href="#">Locations</a></li>
          <li><a href="#">Support</a></li>
          <li><a href="#">Partnerships</a></li>
        </ul>
      </div>

      <div>
        <h4 class="footer-heading">Connect</h4>
        <p class="footer-desc">
          Global HQ: Los Angeles, CA<br />
          Available 24/7 for Elite Members.
        </p>
      </div>

    </div>

    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> TD RENTALS. ENGINEERED FOR PERFORMANCE.</span>
      <div class="footer-legal">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= $assetBase ?>/js/app.js"></script>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
