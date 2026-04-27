<?php
session_start();

// FIXED: Only redirect if NOT logged in OR if user is an admin
// Regular users should see the vehicles page, admins and guests should be redirected
if (!isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1)) {
    header("Location: ../landing_page.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

$username     = $_SESSION['username'] ?? 'User';
$search       = isset($_GET['search'])       ? trim($_GET['search'])     : '';
$transmission = isset($_GET['transmission']) ? $_GET['transmission']     : '';
$fuel_type    = isset($_GET['fuel_type'])    ? $_GET['fuel_type']        : '';
$min_price    = isset($_GET['min_price'])    ? (float)$_GET['min_price'] : 0;
$max_price    = isset($_GET['max_price'])    ? (float)$_GET['max_price'] : 0;
$color        = isset($_GET['color'])        ? trim($_GET['color'])      : '';

// Build the SQL query - showing ALL approved vehicles from ALL users
// LEFT JOIN ensures admin-added vehicles (without a matching user row) still appear
$sql = "SELECT v.*, COALESCE(u.first_name, 'Admin') AS first_name, u.email 
        FROM vehicles v 
        LEFT JOIN users u ON v.user_id = u.id 
        WHERE v.status = 'approved'
        AND v.id NOT IN (SELECT vehicle_id FROM bookings WHERE status != 'cancelled' AND end_date >= CURDATE())
        ";

$conditions = [];
$param_types = "";
$params = [];

// Add search condition (searching by model)
if (!empty($search)) {
    $conditions[] = "(v.model LIKE ?)";
    $param_types .= "s";
    $params[] = "%$search%";
}

// Add transmission filter
if (!empty($transmission)) {
    $conditions[] = "v.transmission = ?";
    $param_types .= "s";
    $params[] = $transmission;
}

// Add fuel type filter
if (!empty($fuel_type)) {
    $conditions[] = "v.fuel_type = ?";
    $param_types .= "s";
    $params[] = $fuel_type;
}

// Add min price filter
if ($min_price > 0) {
    $conditions[] = "v.price_per_day >= ?";
    $param_types .= "d";
    $params[] = $min_price;
}

// Add max price filter
if ($max_price > 0) {
    $conditions[] = "v.price_per_day <= ?";
    $param_types .= "d";
    $params[] = $max_price;
}

// Add color filter
if (!empty($color)) {
    $conditions[] = "v.color = ?";
    $param_types .= "s";
    $params[] = $color;
}

// Append conditions to SQL
if (!empty($conditions)) {
    $sql .= " AND " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY v.created_at DESC";

// Prepare and execute the statement with error handling
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Database prepare error: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

if (!$stmt->execute()) {
    die("Database execute error: " . $stmt->error);
}

$result = $stmt->get_result();

// Get filter options for dropdowns
$filter_result = $conn->query("SELECT DISTINCT transmission, fuel_type FROM vehicles WHERE status = 'approved'");
$transmissions = [];
$fuel_types = [];

if ($filter_result && $filter_result->num_rows > 0) {
    while ($row = $filter_result->fetch_assoc()) {
        if (!empty($row['transmission'])) $transmissions[] = $row['transmission'];
        if (!empty($row['fuel_type']))    $fuel_types[]    = $row['fuel_type'];
    }
    $transmissions = array_unique($transmissions);
    $fuel_types    = array_unique($fuel_types);
}

// Sort arrays for better display
sort($transmissions);
sort($fuel_types);
?>
<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/vehicles.css">

<main class="vehicles-page">
    <div class="vehicles-hero">
        <div class="vehicles-hero-content">
            <h1>Available Vehicles</h1>
            <p>Browse our collection of premium rental vehicles</p>
        </div>
    </div>

    <div class="vehicles-container">
        <div class="vehicles-search-section">
            <form method="GET" action="vehicles.php" class="search-filters-form">
                <div class="search-row">
                    <div class="search-input-wrapper">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" name="search" placeholder="Search by model..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="filter-group">
                        <select name="transmission">
                            <option value="">All Transmissions</option>
                            <?php foreach ($transmissions as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $transmission==$t?'selected':'' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select name="fuel_type">
                            <option value="">All Fuel Types</option>
                            <?php foreach ($fuel_types as $f): ?>
                                <option value="<?= htmlspecialchars($f) ?>" <?= $fuel_type==$f?'selected':'' ?>><?= htmlspecialchars($f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Colour filter -->
                    <div class="color-filter-group" id="colorFilterGroup">
                        <input type="hidden" id="colorValue" name="color" value="<?= htmlspecialchars($color) ?>">

                        <button type="button" class="color-trigger" id="colorTrigger">
                            <span class="color-trigger-swatch" id="colorTriggerSwatch"
                                  style="<?= !empty($color) ? 'background:'.htmlspecialchars($color) : '' ?>"></span>
                            <span class="color-trigger-label" id="colorTriggerLabel">
                                <?= !empty($color) ? htmlspecialchars($color) : 'Any Colour' ?>
                            </span>
                            <svg class="color-trigger-chevron" width="14" height="14" viewBox="0 0 20 20" fill="none">
                                <path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            </svg>
                        </button>

                        <div class="color-popup" id="colorPopup">
                            <div class="color-popup-inner">
                                <div class="custom-picker-row">
                                    <label class="custom-picker-swatch-wrap" for="nativePicker" title="Open colour picker">
                                        <span class="custom-picker-swatch" id="customSwatch"
                                              style="background: <?= !empty($color) ? htmlspecialchars($color) : '#e03030' ?>"></span>
                                        <input type="color" id="nativePicker"
                                               value="<?= !empty($color) ? htmlspecialchars($color) : '#e03030' ?>">
                                    </label>
                                    <span class="custom-picker-hex" id="customHex">
                                        <?= !empty($color) ? htmlspecialchars($color) : '#e03030' ?>
                                    </span>
                                    <button type="button" class="custom-apply-btn" id="customApply">Apply</button>
                                </div>

                                <div class="color-popup-divider"></div>

                                <div class="color-popup-section-label">Basic Colours</div>
                                <div class="color-presets-grid" id="colorPresetGrid"></div>

                                <button type="button" class="color-clear-btn" id="colorClear">
                                    Clear colour filter
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="price-range-filters">
                        <input type="number" name="min_price" placeholder="Min Price"
                               value="<?= $min_price ?: '' ?>" step="100">
                        <span>–</span>
                        <input type="number" name="max_price" placeholder="Max Price"
                               value="<?= $max_price ?: '' ?>" step="100">
                    </div>

                    <button type="submit" class="apply-filters-btn">Apply Filters</button>
                    <a href="vehicles.php" class="reset-filters-btn">Reset</a>
                </div>
            </form>
        </div>

        <div class="vehicles-content">
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="vehicles-stats">
                    <p><?= $result->num_rows ?> vehicle(s) found</p>
                </div>
                <div class="vehicles-grid">
                    <?php while ($vehicle = $result->fetch_assoc()): ?>
                        <div class="vehicle-card">
                            <?php
                            $imgPath = $vehicle['image_path'] ?? '';
                            if (!empty($imgPath) && $imgPath !== '0'):
                                // Resolve the correct URL from public/vehicle/ (2 levels up = project root)
                                if (strpos($imgPath, 'http') === 0) {
                                    $imgSrc = $imgPath; // absolute URL
                                } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, 'assets/images/') === 0) {
                                    $imgSrc = '../../' . $imgPath; // full relative path from project root
                                } else {
                                    $imgSrc = '../../uploads/vehicles/' . $imgPath; // bare filename (legacy)
                                }
                            ?>
                                <div class="vehicle-image-wrapper">
                                    <div class="td-img-shimmer"></div>
                                    <img src="<?= htmlspecialchars($imgSrc) ?>"
                                         alt="<?= htmlspecialchars($vehicle['model']) ?>" class="vehicle-image td-img-real"
                                         loading="lazy"
                                         onerror="this.previousElementSibling.style.display='none'; this.closest('.vehicle-image-wrapper').style.display='none';"
                                         style="opacity:0;">
                                    <div class="vehicle-badge">Available</div>
                                </div>
                            <?php endif; ?>
                            <div class="vehicle-info">
                                <h3 class="vehicle-model"><?= htmlspecialchars($vehicle['model']) ?></h3>
                                <p class="vehicle-owner">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    <?= htmlspecialchars($vehicle['first_name']) ?>
                                </p>
                                <div class="vehicle-specs">
                                    <div class="spec">
                                        <span style="display:inline-block;width:16px;height:16px;border-radius:50%;background:<?= htmlspecialchars($vehicle['color']) ?>;border:1px solid rgba(255,255,255,0.15);flex-shrink:0;"></span>
                                        <span><?= htmlspecialchars($vehicle['color']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['transmission']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['fuel_type']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['top_speed']) ?> km/h</span>
                                    </div>
                                </div>
                                <div class="vehicle-price">
                                    <span class="price">Rs. <?= number_format($vehicle['price_per_day']) ?></span>
                                    <span class="price-period">/day</span>
                                </div>
                                <div class="vehicle-actions">
                                    <a href="vehicle_detail.php?id=<?= $vehicle['id'] ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-vehicles">
                    <h3>No vehicles found</h3>
                    <p>Try adjusting your filters or check back later for new listings.</p>
                    <a href="vehicles.php" class="reset-link">Clear all filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
(function () {
    const presets = [
        { hex: "#e03030", name: "Racing Red"     },
        { hex: "#c82020", name: "Deep Red"       },
        { hex: "#8b0000", name: "Dark Red"       },
        { hex: "#ff6347", name: "Tomato"         },
        { hex: "#ffffff", name: "White"          },
        { hex: "#f0f0f0", name: "Pearl White"    },
        { hex: "#c0c0c0", name: "Silver"         },
        { hex: "#a9a9a9", name: "Dark Silver"    },
        { hex: "#4a4a4a", name: "Graphite"       },
        { hex: "#2f2f2f", name: "Charcoal"       },
        { hex: "#1a1a1a", name: "Midnight Black" },
        { hex: "#000000", name: "Black"          },
        { hex: "#003366", name: "Navy Blue"      },
        { hex: "#1e3a5f", name: "Deep Blue"      },
        { hex: "#4169e1", name: "Royal Blue"     },
        { hex: "#6495ed", name: "Cornflower"     },
        { hex: "#1a3c1a", name: "Forest Green"   },
        { hex: "#006400", name: "Dark Green"     },
        { hex: "#2e8b57", name: "Sea Green"      },
        { hex: "#556b2f", name: "Olive Green"    },
        { hex: "#3d1f00", name: "Dark Brown"     },
        { hex: "#8b4513", name: "Saddle Brown"   },
        { hex: "#d4a017", name: "Gold"           },
        { hex: "#b8860b", name: "Dark Gold"      },
        { hex: "#ff8c00", name: "Dark Orange"    },
        { hex: "#ff4500", name: "Orange Red"     },
        { hex: "#ffcc00", name: "Yellow"         },
        { hex: "#f5c518", name: "Amber"          },
        { hex: "#4b0082", name: "Indigo"         },
        { hex: "#6a0dad", name: "Purple"         },
        { hex: "#9370db", name: "Violet"         },
        { hex: "#483d8b", name: "Slate Blue"     },
    ];

    const trigger       = document.getElementById('colorTrigger');
    const popup         = document.getElementById('colorPopup');
    const triggerSwatch = document.getElementById('colorTriggerSwatch');
    const triggerLabel  = document.getElementById('colorTriggerLabel');
    const colorValue    = document.getElementById('colorValue');
    const nativePicker  = document.getElementById('nativePicker');
    const customSwatch  = document.getElementById('customSwatch');
    const customHex     = document.getElementById('customHex');
    const customApply   = document.getElementById('customApply');
    const colorClear    = document.getElementById('colorClear');
    const presetGrid    = document.getElementById('colorPresetGrid');

    let currentHex = colorValue.value || '';

    /* Build preset swatches */
    presets.forEach(({ hex, name }) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'cp-swatch';
        btn.style.background = hex;
        btn.title = name;
        btn.dataset.hex = hex;
        btn.addEventListener('click', () => { applyColor(hex); closePopup(); });
        presetGrid.appendChild(btn);
    });

    /* Toggle */
    trigger.addEventListener('click', e => {
        e.stopPropagation();
        const open = popup.classList.toggle('open');
        trigger.classList.toggle('active', open);
    });

    document.addEventListener('click', e => {
        if (!document.getElementById('colorFilterGroup').contains(e.target)) closePopup();
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopup(); });

    function closePopup() {
        popup.classList.remove('open');
        trigger.classList.remove('active');
    }

    /* Native picker live preview */
    nativePicker.addEventListener('input', () => {
        customSwatch.style.background = nativePicker.value;
        customHex.textContent = nativePicker.value;
        markActivePreset(nativePicker.value);
    });

    /* Apply custom */
    customApply.addEventListener('click', () => { applyColor(nativePicker.value); closePopup(); });

    /* Clear */
    colorClear.addEventListener('click', () => {
        currentHex = '';
        colorValue.value = '';
        triggerSwatch.style.background = '';
        triggerLabel.textContent = 'Any Colour';
        markActivePreset('');
        closePopup();
    });

    function applyColor(hex) {
        currentHex = hex;
        colorValue.value = hex;
        triggerSwatch.style.background = hex;
        triggerLabel.textContent = hex;
        nativePicker.value = hex;
        customSwatch.style.background = hex;
        customHex.textContent = hex;
        markActivePreset(hex);
    }

    function markActivePreset(hex) {
        document.querySelectorAll('.cp-swatch').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.hex.toLowerCase() === (hex||'').toLowerCase());
        });
    }

    if (currentHex) applyColor(currentHex);
})();
</script>

<?php require_once '../../includes/footer.php'; ?>