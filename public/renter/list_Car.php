<?php
/**
 * List Car Form
 * 
 * This page provides the user interface for a host (renter) to list a new vehicle.
 * It captures identity, specifications, pricing, and an image of the vehicle.
 * Form data is sent to `../vehicle/add.php` for database insertion.
 */
session_start();

// Ensure only logged-in non-admin users (renters) can list vehicles
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';

$license_type  = $_POST['license_type']  ?? '';
$transmission  = $_POST['transmission']  ?? '';
$fuel_type     = $_POST['fuel_type']     ?? '';
$price         = $_POST['price']         ?? 2500;
$color         = $_POST['color']         ?? '#e03030';
?>
<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/list_car.css">

<main class="lc-page">

    <!-- ── Hero ─────────────────────────────────────────────── -->
    <section class="lc-hero">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= ($_SESSION['message_type'] === 'success') ? 'ok' : 'err' ?>" style="margin-bottom: 2rem;">
                <?= htmlspecialchars($_SESSION['message']) ?>
                <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>
        <div class="lc-hero__label">TD Rentals · Host Programme</div>
        <h1 class="lc-hero__title">
            <span>List Your</span>
            <span class="lc-hero__title--accent">Vehicle</span>
        </h1>
        <p class="lc-hero__sub">Turn idle wheels into steady income. Fill in the details below and go live today.</p>
    </section>

    <!-- ── Form ──────────────────────────────────────────────── -->
    <!-- Change this line in list_Car.php -->
<form class="lc-form" action="../vehicle/add.php" method="POST" enctype="multipart/form-data" id="listCarForm">
        <!-- LEFT COLUMN -->
        <div class="lc-col">

            <!-- Section 01: Identity -->
            <div class="lc-section">
                <div class="lc-section__head">
                    <span class="lc-section__num">01</span>
                    <h2 class="lc-section__title">Vehicle Identity</h2>
                </div>

                <div class="lc-field">
                    <label class="lc-label" for="model">Make &amp; Model</label>
                    <input class="lc-input" type="text" id="model" name="model"
                           placeholder="e.g. Honda Civic 2021" required>
                </div>

                <div class="lc-field">
                    <label class="lc-label" for="license_type">Vehicle Class</label>
                    <div class="lc-select-wrap">
                        <select class="lc-select" id="license_type" name="license_type" required>
                            <option value="">Select vehicle class</option>
                            <option value="A" <?= ($license_type==="A")?"selected":"" ?>>A — Motorcycles &amp; Scooters</option>
                            <option value="B" <?= ($license_type==="B")?"selected":"" ?>>B — Cars, Jeeps, Vans</option>
                            <option value="C" <?= ($license_type==="C")?"selected":"" ?>>C — Commercial Heavy</option>
                            <option value="D" <?= ($license_type==="D")?"selected":"" ?>>D — Public Service</option>
                            <option value="E" <?= ($license_type==="E")?"selected":"" ?>>E — Heavy with Trailers</option>
                        </select>
                        <svg class="lc-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    </div>
                </div>

                <div class="lc-row">
                    <div class="lc-field">
                        <label class="lc-label" for="transmission">Transmission</label>
                        <div class="lc-select-wrap">
                            <select class="lc-select" id="transmission" name="transmission" required>
                                <option value="">Select</option>
                                <option value="Manual"    <?= ($transmission==="Manual")?"selected":"" ?>>Manual</option>
                                <option value="Automatic" <?= ($transmission==="Automatic")?"selected":"" ?>>Automatic</option>
                            </select>
                            <svg class="lc-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </div>
                    </div>
                    <div class="lc-field">
                        <label class="lc-label" for="fuel_type">Fuel Type</label>
                        <div class="lc-select-wrap">
                            <select class="lc-select" id="fuel_type" name="fuel_type" required>
                                <option value="">Select</option>
                                <option value="Petrol"   <?= ($fuel_type==="Petrol")?"selected":"" ?>>Petrol</option>
                                <option value="Diesel"   <?= ($fuel_type==="Diesel")?"selected":"" ?>>Diesel</option>
                                <option value="Electric" <?= ($fuel_type==="Electric")?"selected":"" ?>>Electric</option>
                                <option value="Hybrid"   <?= ($fuel_type==="Hybrid")?"selected":"" ?>>Hybrid</option>
                                <option value="CNG"      <?= ($fuel_type==="CNG")?"selected":"" ?>>CNG</option>
                            </select>
                            <svg class="lc-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 02: Specs -->
            <div class="lc-section">
                <div class="lc-section__head">
                    <span class="lc-section__num">02</span>
                    <h2 class="lc-section__title">Specs</h2>
                </div>

                <div class="lc-row">
                    <div class="lc-field">
                        <label class="lc-label" for="kms">Top Speed (km/h)</label>
                        <input class="lc-input" type="number" id="kms" name="kms"
                               placeholder="e.g. 160" min="0" required>
                    </div>
                    <div class="lc-field">
                        <label class="lc-label" for="fuel_capacity">Fuel Capacity (L)</label>
                        <input class="lc-input" type="number" id="fuel_capacity" name="fuel_capacity"
                               placeholder="e.g. 45" min="0" required>
                    </div>
                </div>

                <div class="lc-field">
                    <label class="lc-label">Vehicle Colour</label>
                    <div class="lc-color-row" id="colorTrigger" title="Click to pick colour">
                        <input type="color" id="colorPicker" name="color" value="<?= htmlspecialchars($color) ?>">
                        <div class="lc-color-swatch" id="colorPreview" style="background:<?= htmlspecialchars($color) ?>"></div>
                        <div class="lc-color-meta">
                            <span class="lc-color-name" id="colorName">Loading…</span>
                            <span class="lc-color-cat"  id="colorCategory"></span>
                            <span class="lc-color-hex"  id="hexValue"><?= htmlspecialchars($color) ?></span>
                        </div>
                    </div>
                    <div class="lc-color-presets">
    <span class="lc-color-presets__label">Basic Colours</span>
    <div class="lc-color-presets__grid" id="colorPresetGrid"></div>
</div>
                </div>
            </div>

        </div><!-- /lc-col left -->

        <!-- RIGHT COLUMN -->
        <div class="lc-col">

            <!-- Section 03: Pricing -->
            <div class="lc-section">
                <div class="lc-section__head">
                    <span class="lc-section__num">03</span>
                    <h2 class="lc-section__title">Pricing</h2>
                </div>

                <div class="lc-field">
                    <label class="lc-label" for="priceRange">Rent per Day</label>
                    <input type="range" id="priceRange" name="price"
                           min="500" max="1500000" step="500" value="<?= $price ?>">
                    <span id="priceDisplay">Rs. <?= number_format($price) ?>/day</span>
                </div>

                <div class="lc-earnings-card">
                    <div class="lc-earnings-card__label">Estimated Monthly Earnings</div>
                    <div class="lc-earnings-card__amount" id="earnings">
                        Rs. <?= number_format((int)$price * 18) ?>
                    </div>
                    <div class="lc-earnings-card__note">Based on ~60% occupancy · 30 days</div>
                </div>
            </div>

            <!-- Section 04: Photos -->
            <div class="lc-section">
                <div class="lc-section__head">
                    <span class="lc-section__num">04</span>
                    <h2 class="lc-section__title">Photos</h2>
                </div>

                <div class="lc-field">
                    <label class="lc-label">Upload Vehicle Image</label>

                    <!-- Dropzone — hidden once an image is chosen -->
                    <label class="lc-dropzone" id="dropzone">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                        </svg>
                        <span class="lc-dropzone__text">Click to upload or drag &amp; drop</span>
                        <span class="lc-dropzone__hint">JPG, PNG, WEBP — max 8 MB</span>
                        <input type="file" id="vehicleImage" name="vehicle_image" accept="image/*" hidden>
                    </label>

                    <!-- Preview + remove — hidden until an image is chosen -->
                    <div class="lc-preview-wrap" id="previewWrap" style="display:none;">
                        <img id="imagePreview" src="#" alt="Preview" class="lc-img-preview">
                        <button type="button" class="lc-remove-btn" id="removeImage">
                            <svg viewBox="0 0 20 20" fill="none">
                                <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                            Remove photo
                        </button>
                    </div>
                </div>
            </div>

            <!-- Submit -->
            <button class="lc-submit" type="submit">
                <span>List My Vehicle</span>
                <svg viewBox="0 0 20 20" fill="none">
                    <path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                </svg>
            </button>

        </div><!-- /lc-col right -->

    </form>

</main>

<script src="../../assets/js/list_car.js"></script>
<?php require_once '../../includes/footer.php'; ?>