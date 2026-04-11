<?php
require_once 'admin_functions.php';
requireAdmin();

$flash = getFlash();

// POST actions for Fleet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Add vehicle
    if ($action === 'add_vehicle') {
        $model        = trim($_POST['car_name']      ?? '');
        $price        = (float)($_POST['price_per_day'] ?? 0);
        $top_speed    = (int)($_POST['top_speed']    ?? 0);
        $transmission = $_POST['transmission']       ?? 'Manual';
        $fuel_type    = $_POST['fuel_type']          ?? 'Petrol';
        $license_type = $_POST['license_type']       ?? 'B';
        $color        = $_POST['color']              ?? '#e03030';
        $status       = isset($_POST['available'])   ? 'approved' : 'pending';

        if (!$model || !$price) {
            setFlash('error', 'Model and price are required.');
        } else {
            // uploadVehicleImage() now returns 'uploads/vehicles/filename.ext' or null
            $imagePath = uploadVehicleImage($_FILES['image'] ?? []) ?? '';

            $user_id = $_SESSION['user_id'] ?? 1;

            $stmt = $conn->prepare(
                "INSERT INTO vehicles (user_id, model, license_type, transmission, fuel_type, price_per_day, color, top_speed, image_path, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            // Types: i=user_id, s=model, s=license_type, s=transmission, s=fuel_type, d=price, s=color, i=top_speed, s=image_path, s=status
            $stmt->bind_param("issssdsiss", $user_id, $model, $license_type, $transmission, $fuel_type, $price, $color, $top_speed, $imagePath, $status);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                auditLog("vehicle_added", "vehicle", $newId, "Added: {$model}");
                setFlash('success', "Vehicle '{$model}' added successfully.");
            } else {
                setFlash('error', "Database error: " . $conn->error);
            }
        }
        header('Location: vehicle_listings.php');
        exit;
    }

    // Edit vehicle
    if ($action === 'edit_vehicle') {
        $cid          = (int)$_POST['car_id'];
        $model        = trim($_POST['car_name']      ?? '');
        $price        = (float)($_POST['price_per_day'] ?? 0);
        $top_speed    = (int)($_POST['top_speed']    ?? 0);
        $transmission = $_POST['transmission']       ?? 'Manual';
        $fuel_type    = $_POST['fuel_type']          ?? 'Petrol';
        $license_type = $_POST['license_type']       ?? 'B';
        $color        = $_POST['color']              ?? '#e03030';
        $status       = isset($_POST['available'])   ? 'approved' : 'pending';

        if (!$model || !$price) {
            setFlash('error', 'Model and price are required.');
        } else {
            $newImg = uploadVehicleImage($_FILES['image'] ?? []);
            if ($newImg) {
                // Delete the old image file from disk before replacing
                $oldRes = $conn->query("SELECT image_path FROM vehicles WHERE id = $cid");
                if ($oldRes && $oldRow = $oldRes->fetch_assoc()) {
                    deleteVehicleImage($oldRow['image_path']);
                }
                $stmt = $conn->prepare("UPDATE vehicles SET model=?, license_type=?, transmission=?, fuel_type=?, price_per_day=?, color=?, top_speed=?, status=?, image_path=? WHERE id=?");
                // Types: s=model, s=license_type, s=transmission, s=fuel_type, d=price, s=color, i=top_speed, s=status, s=newImg, i=cid
                $stmt->bind_param("ssssdsissi", $model, $license_type, $transmission, $fuel_type, $price, $color, $top_speed, $status, $newImg, $cid);
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET model=?, license_type=?, transmission=?, fuel_type=?, price_per_day=?, color=?, top_speed=?, status=? WHERE id=?");
                // Types: s=model, s=license_type, s=transmission, s=fuel_type, d=price, s=color, i=top_speed, s=status, i=id
                $stmt->bind_param("ssssdsisi", $model, $license_type, $transmission, $fuel_type, $price, $color, $top_speed, $status, $cid);
            }

            if ($stmt->execute()) {
                auditLog("vehicle_edited", "vehicle", $cid, "Edited: {$model}");
                setFlash('success', "Vehicle '{$model}' updated.");
            } else {
                setFlash('error', "Database error: " . $conn->error);
            }
        }
        header('Location: vehicle_listings.php');
        exit;
    }

    // Delete vehicle
    if ($action === 'delete_vehicle') {
        $cid = (int)$_POST['car_id'];
        $activeRes   = $conn->query("SELECT COUNT(*) FROM bookings WHERE vehicle_id = $cid AND status IN ('pending','confirmed')");
        $activeCount = $activeRes->fetch_row()[0];

        if ($activeCount > 0) {
            setFlash('error', 'Cannot delete: vehicle has active bookings. Cancel them first.');
        } else {
            $rowRes = $conn->query("SELECT model, image_path FROM vehicles WHERE id = $cid");
            $carRow = $rowRes->num_rows > 0 ? $rowRes->fetch_assoc() : [];
            $carModel = $carRow['model'] ?? 'unknown';

            // Delete image file from disk before removing the DB record
            if (!empty($carRow['image_path'])) {
                deleteVehicleImage($carRow['image_path']);
            }

            $conn->query("DELETE FROM vehicles WHERE id = $cid");
            auditLog("vehicle_deleted", "vehicle", $cid, "Deleted: {$carModel}");
            setFlash('success', "Vehicle deleted.");
        }
        header('Location: vehicle_listings.php');
        exit;
    }
}

// Data queries
$totalCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles");
$totalCars = $totalCarsResult ? $totalCarsResult->fetch_row()[0] : 0;

$availCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'approved'");
$availCars = $availCarsResult ? $availCarsResult->fetch_row()[0] : 0;

$cars = [];
$carsRes = $conn->query("SELECT * FROM vehicles ORDER BY id ASC");
if ($carsRes) {
    while($row = $carsRes->fetch_assoc()) {
        $cars[] = $row;
    }
}

$activePerCar = [];
$cRes = $conn->query("SELECT vehicle_id, COUNT(*) AS cnt FROM bookings WHERE status IN ('pending','confirmed') GROUP BY vehicle_id");
if ($cRes) {
    while($r = $cRes->fetch_assoc()) {
        $activePerCar[$r['vehicle_id']] = (int)$r['cnt'];
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/admin.css">

<style>
/* ── Fleet-form overrides (list_car-style palette inside admin shell) ── */
.lf-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.85); z-index: 200;
    align-items: center; justify-content: center; padding: 1rem;
    backdrop-filter: blur(4px);
}
.lf-overlay.open { display: flex; }

.lf-box {
    background: #161616; border: 1px solid #2c2c2c;
    border-radius: 10px; width: 100%; max-width: 860px;
    max-height: 92vh; overflow-y: auto;
    animation: lfSlide .28s cubic-bezier(.22,1,.36,1);
}
@keyframes lfSlide {
    from { opacity:0; transform: translateY(28px); }
    to   { opacity:1; transform: translateY(0); }
}

.lf-header {
    display: flex; align-items: baseline; gap: 12px;
    padding: 1.6rem 2rem 1.2rem;
    border-bottom: 1px solid #2c2c2c;
}
.lf-num {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 11px; font-weight: 700; letter-spacing: .18em;
    color: #e03030;
}
.lf-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 22px; font-weight: 800; letter-spacing: .06em;
    text-transform: uppercase; color: #f0f0f0;
}

.lf-body { padding: 1.6rem 2rem 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 600px) { .lf-body { grid-template-columns: 1fr; } }

.lf-section {
    background: #1e1e1e; border: 1px solid #2c2c2c;
    border-radius: 8px; padding: 20px 22px 22px;
    display: flex; flex-direction: column; gap: 14px;
}
.lf-sec-head {
    display: flex; align-items: baseline; gap: 10px;
    padding-bottom: 10px; border-bottom: 1px solid #2c2c2c;
}
.lf-sec-num  { font-family:'Barlow Condensed',sans-serif; font-size:11px; font-weight:700; letter-spacing:.18em; color:#e03030; }
.lf-sec-ttl  { font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#f0f0f0; }

.lf-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.lf-field { display: flex; flex-direction: column; gap: 6px; }
.lf-label {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 11px; font-weight: 600; letter-spacing: .18em;
    text-transform: uppercase; color: #999;
}
.lf-input {
    background: #252525; border: 1px solid #2c2c2c;
    border-radius: 4px; color: #f0f0f0;
    font-family: 'Inter', sans-serif; font-size: 14px;
    padding: 10px 12px; outline: none; width: 100%;
    transition: border-color .2s, box-shadow .2s;
}
.lf-input::placeholder { color: #3a3a3a; }
.lf-input:focus { border-color: #e03030; box-shadow: 0 0 0 3px rgba(224,48,48,.15); }

.lf-select-wrap { position: relative; }
.lf-select {
    appearance: none; -webkit-appearance: none;
    background: #252525; border: 1px solid #2c2c2c;
    border-radius: 4px; color: #f0f0f0;
    font-family: 'Inter', sans-serif; font-size: 14px;
    padding: 10px 32px 10px 12px; outline: none; width: 100%;
    cursor: pointer; transition: border-color .2s, box-shadow .2s;
}
.lf-select:focus { border-color: #e03030; box-shadow: 0 0 0 3px rgba(224,48,48,.15); }
.lf-select option { background: #1e1e1e; }
.lf-select-icon {
    position: absolute; right: 10px; top: 50%;
    transform: translateY(-50%); width: 14px; height: 14px;
    color: #666; pointer-events: none;
}

/* Color picker row */
.lf-color-row {
    display: flex; align-items: center; gap: 12px;
    background: #252525; border: 1px solid #2c2c2c;
    border-radius: 4px; padding: 9px 12px; cursor: pointer;
    transition: border-color .2s;
}
.lf-color-row:hover { border-color: #e03030; }
#aColorPicker, #eColorPicker { position:absolute; opacity:0; width:0; height:0; pointer-events:none; }
.lf-color-swatch {
    width: 32px; height: 32px; border-radius: 50%;
    border: 2px solid rgba(255,255,255,.1); flex-shrink: 0;
    transition: background .2s, transform .2s;
}
.lf-color-row:hover .lf-color-swatch { transform: scale(1.1); }
.lf-color-meta { display: flex; flex-direction: column; gap: 1px; }
.lf-color-name { font-family:'Barlow Condensed',sans-serif; font-size:15px; font-weight:700; color:#f0f0f0; letter-spacing:.03em; }
.lf-color-hex  { font-size:11px; color:#555; font-family:monospace; letter-spacing:.06em; }

.lf-color-presets { display:flex; flex-direction:column; gap:8px; margin-top:6px; }
.lf-color-presets__label { font-family:'Barlow Condensed',sans-serif; font-size:11px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:#999; }
.lf-color-presets__grid { display:flex; flex-wrap:wrap; gap:7px; }
.lf-preset-sw {
    width:24px; height:24px; border-radius:50%; border:2px solid transparent;
    cursor:pointer; transition:transform .2s, border-color .2s, box-shadow .2s; flex-shrink:0;
}
.lf-preset-sw:hover { transform:scale(1.2); border-color:rgba(255,255,255,.3); }
.lf-preset-sw.active { border-color:#e03030; box-shadow:0 0 0 2px rgba(224,48,48,.3); transform:scale(1.15); }

/* Dropzone */
.lf-dropzone {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 8px;
    border: 1px dashed #2c2c2c; border-radius: 4px;
    padding: 24px 16px; cursor: pointer; color: #666;
    text-align: center; transition: border-color .2s, background .2s, color .2s;
}
.lf-dropzone:hover, .lf-dropzone.drag-over {
    border-color: #e03030; background: rgba(224,48,48,.07); color: #f0f0f0;
}
.lf-dropzone svg { width:24px; height:24px; transition:color .2s; }
.lf-dropzone:hover svg { color: #e03030; }
.lf-dropzone__text { font-family:'Barlow Condensed',sans-serif; font-size:13px; font-weight:600; letter-spacing:.06em; }
.lf-dropzone__hint { font-size:11px; color:#555; }

.lf-preview-wrap { display:flex; flex-direction:column; gap:8px; }
.lf-img-preview { width:100%; border-radius:4px; border:1px solid #2c2c2c; object-fit:cover; max-height:180px; }
.lf-remove-btn {
    display:flex; align-items:center; justify-content:center; gap:6px;
    width:100%; padding:8px 12px; background:transparent;
    border:1px solid #2c2c2c; border-radius:4px; color:#666;
    font-family:'Barlow Condensed',sans-serif; font-size:12px; font-weight:600;
    letter-spacing:.12em; text-transform:uppercase; cursor:pointer;
    transition:border-color .2s,color .2s,background .2s;
}
.lf-remove-btn svg { width:13px; height:13px; flex-shrink:0; }
.lf-remove-btn:hover { border-color:#e03030; color:#e03030; background:rgba(224,48,48,.07); }

/* Availability toggle */
.lf-avail-row {
    display:flex; align-items:center; gap:.75rem; font-size:.82rem; color:#999;
    background:#252525; border:1px solid #2c2c2c; border-radius:4px; padding:10px 12px;
}
.lf-avail-row input[type=checkbox] { accent-color:#e03030; width:16px; height:16px; cursor:pointer; }
.lf-avail-row label { cursor:pointer; }

/* Footer */
.lf-footer {
    display: flex; gap: .75rem; justify-content: flex-end;
    padding: 1.2rem 2rem 1.8rem;
    border-top: 1px solid #2c2c2c;
}
.lf-cancel {
    background:transparent; color:#999; border:1px solid #2c2c2c;
    border-radius:6px; font-family:'Inter',sans-serif; font-weight:600;
    font-size:.72rem; letter-spacing:.05em; text-transform:uppercase;
    padding:.5rem 1.2rem; cursor:pointer; transition:.15s;
}
.lf-cancel:hover { background:#252525; color:#f0f0f0; }
.lf-submit {
    display:flex; align-items:center; gap:8px;
    background:linear-gradient(135deg,#c82020 0%,#f04040 100%);
    border:none; border-radius:6px; color:#fff;
    font-family:'Inter',sans-serif; font-weight:700;
    font-size:.72rem; letter-spacing:.05em; text-transform:uppercase;
    padding:.5rem 1.4rem; cursor:pointer;
    transition:transform .2s, box-shadow .2s;
    position:relative; overflow:hidden;
}
.lf-submit::after {
    content:''; position:absolute; top:0; left:-75%;
    width:50%; height:100%;
    background:linear-gradient(120deg,transparent,rgba(255,255,255,.18),transparent);
    transform:skewX(-20deg); transition:left .5s ease;
}
.lf-submit:hover::after { left:140%; }
.lf-submit:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(224,48,48,.35); }
.lf-submit:active { transform:translateY(0); box-shadow:none; }
.lf-submit svg { width:14px; height:14px; transition:transform .2s; }
.lf-submit:hover svg { transform:translateX(3px); }
</style>

<div class="admin-wrapper">
  <div class="main">
    <?php if ($flash['msg']): ?>
    <div style="padding:1rem 2rem 0">
      <div class="flash <?= $flash['type']==='success'?'ok':'err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    </div>
    <?php endif; ?>

    <div class="topbar">
      <div class="topbar-left">
        <h1>VEHICLE LISTINGS</h1>
        <p><span style="color:var(--red);font-weight:600"><?= $totalCars ?></span> Total &nbsp;·&nbsp; <?= $totalCars-$availCars ?> Hidden &nbsp;·&nbsp; <span style="color:var(--red)"><?= $availCars ?></span> Active</p>
      </div>
      <div class="topbar-right">
        <button class="btn btn-red" onclick="openModal('addModal')">Add Vehicle</button>
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search model or plate..."/>
        </div>
      </div>
    </div>

    <div class="content">
      <div class="fleet-grid">
        <?php foreach ($cars as $c):
          $ac = $activePerCar[$c['id']] ?? 0;
          $isAvail = ($c['status'] === 'approved');
          $statusLabel = !$isAvail ? ucfirst($c['status']) : ($ac > 0 ? 'Reserved' : 'Available');
          $statusClass = !$isAvail ? 'b-hidden' : ($ac > 0 ? 'b-upcoming' : 'b-available');
        ?>
        <div class="fleet-card">
          <div class="fleet-card-img">
            <?php
            $imgPath = $c['image_path'] ?? '';
            if (strpos($imgPath, 'http') === 0) {
                $imgSrc = $imgPath;
            } elseif (strpos($imgPath, 'uploads/') === 0 || strpos($imgPath, 'assets/images/') === 0) {
                $imgSrc = '../../' . $imgPath;
            } else {
                $imgSrc = '../../assets/images/' . $imgPath;
            }
            ?>
            <img src="<?= htmlspecialchars($imgSrc) ?>" alt=""
                 onerror="this.parentNode.style.background='var(--bg4)'" />
            <div class="status-pip"><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></div>
          </div>
          <div class="fleet-card-body">
            <div class="fleet-card-cat"><?= htmlspecialchars($c['fuel_type'] ?? '—') ?></div>
            <div class="fleet-card-name"><?= htmlspecialchars($c['model']) ?></div>
            <div class="fleet-specs">
              <div class="spec-item"><label>Top Speed</label><span><?= htmlspecialchars($c['top_speed'] ?? '—') ?> km/h</span></div>
              <div class="spec-item"><label>Trans.</label><span style="font-size:.7rem"><?= htmlspecialchars($c['transmission'] ?? '—') ?></span></div>
              <div class="spec-item"><label>Status</label><span style="font-size:.75rem"><?= ucfirst($c['status']) ?></span></div>
            </div>
            <div style="font-size:.8rem;color:var(--fg3);margin-bottom:.75rem">
              NPR <?= number_format($c['price_per_day'], 0) ?>/day
              <?php if ($ac > 0): ?> · <span style="color:var(--yellow)"><?= $ac ?> active booking<?= $ac > 1 ? 's' : '' ?></span><?php endif; ?>
            </div>
            <div class="fleet-card-actions">
              <button class="btn btn-ghost btn-sm" style="flex:1"
                      onclick='openEditModal(<?= json_encode([
                          'id'          => $c['id'],
                          'name'        => $c['model'],
                          'price_per_day'=> $c['price_per_day'],
                          'top_speed'   => $c['top_speed'],
                          'transmission'=> $c['transmission'],
                          'fuel_type'   => $c['fuel_type'],
                          'license_type'=> $c['license_type'] ?? 'B',
                          'color'       => $c['color'] ?? '#e03030',
                          'available'   => ($c['status'] === 'approved' ? 1 : 0)
                      ], JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Edit
              </button>
              <button class="btn btn-outline-red" style="flex:1"
                      <?= $ac > 0 ? 'disabled title="Cancel active bookings first"' : '' ?>
                      onclick="confirmDelete(<?= $c['id'] ?>,'<?= htmlspecialchars($c['model'], ENT_QUOTES) ?>')">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <?= $ac > 0 ? 'Has Bookings' : 'Delete' ?>
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="fleet-add-card" onclick="openModal('addModal')">
          <div class="fleet-add-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </div>
          <div style="font-weight:600;font-size:.9rem">Expand Vehicle Listings</div>
          <div style="font-size:.75rem;color:var(--fg3);text-align:center">Add a new high-performance<br>machine to the inventory.</div>
          <button class="btn btn-ghost btn-sm">Start Entry</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     ADD VEHICLE MODAL
════════════════════════════════════════════════════ -->
<div class="lf-overlay" id="addModal">
  <div class="lf-box">
    <div class="lf-header">
      <span class="lf-num">LISTINGS</span>
      <span class="lf-title">Add New Vehicle</span>
    </div>

    <form method="POST" enctype="multipart/form-data" id="addForm">
      <input type="hidden" name="action"     value="add_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>

      <div class="lf-body">
        <!-- LEFT column -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <!-- Section 01: Identity -->
          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">01</span>
              <span class="lf-sec-ttl">Vehicle Identity</span>
            </div>
            <div class="lf-field">
              <label class="lf-label" for="aModel">Make &amp; Model *</label>
              <input class="lf-input" type="text" id="aModel" name="car_name"
                     placeholder="e.g. Honda Civic 2021" required>
            </div>
            <div class="lf-field">
              <label class="lf-label" for="aLicenseType">Vehicle Class</label>
              <div class="lf-select-wrap">
                <select class="lf-select" id="aLicenseType" name="license_type">
                  <option value="A">A — Motorcycles &amp; Scooters</option>
                  <option value="B" selected>B — Cars, Jeeps, Vans</option>
                  <option value="C">C — Commercial Heavy</option>
                  <option value="D">D — Public Service</option>
                  <option value="E">E — Heavy with Trailers</option>
                </select>
                <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </div>
            </div>
            <div class="lf-row">
              <div class="lf-field">
                <label class="lf-label" for="aTransmission">Transmission</label>
                <div class="lf-select-wrap">
                  <select class="lf-select" id="aTransmission" name="transmission">
                    <option value="Manual">Manual</option>
                    <option value="Automatic">Automatic</option>
                  </select>
                  <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
              </div>
              <div class="lf-field">
                <label class="lf-label" for="aFuelType">Fuel Type</label>
                <div class="lf-select-wrap">
                  <select class="lf-select" id="aFuelType" name="fuel_type">
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Electric">Electric</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="CNG">CNG</option>
                  </select>
                  <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 02: Specs -->
          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">02</span>
              <span class="lf-sec-ttl">Specs &amp; Pricing</span>
            </div>
            <div class="lf-row">
              <div class="lf-field">
                <label class="lf-label" for="aTopSpeed">Top Speed (km/h)</label>
                <input class="lf-input" type="number" id="aTopSpeed" name="top_speed"
                       placeholder="e.g. 160" min="0">
              </div>
              <div class="lf-field">
                <label class="lf-label" for="aPrice">Price / Day (NPR) *</label>
                <input class="lf-input" type="number" id="aPrice" name="price_per_day"
                       min="1" step="0.01" required placeholder="2500">
              </div>
            </div>
            <div class="lf-field">
              <label class="lf-label">Vehicle Colour</label>
              <div class="lf-color-row" id="aColorTrigger" title="Click to pick colour">
                <input type="color" id="aColorPicker" name="color" value="#e03030">
                <div class="lf-color-swatch" id="aColorSwatch" style="background:#e03030"></div>
                <div class="lf-color-meta">
                  <span class="lf-color-name" id="aColorName">Racing Red</span>
                  <span class="lf-color-hex"  id="aColorHex">#e03030</span>
                </div>
              </div>
              <div class="lf-color-presets">
                <span class="lf-color-presets__label">Quick Colours</span>
                <div class="lf-color-presets__grid" id="aColorGrid"></div>
              </div>
            </div>
          </div>

        </div><!-- /left -->

        <!-- RIGHT column -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <!-- Section 03: Photo -->
          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">03</span>
              <span class="lf-sec-ttl">Photos</span>
            </div>
            <div class="lf-field">
              <label class="lf-label">Upload Vehicle Image</label>
              <label class="lf-dropzone" id="aDropzone">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
                <span class="lf-dropzone__text">Click to upload or drag &amp; drop</span>
                <span class="lf-dropzone__hint">JPG, PNG, WEBP — max 8 MB</span>
                <input type="file" id="aImageInput" name="image"
                       accept=".jpg,.jpeg,.png,.webp" hidden>
              </label>
              <div class="lf-preview-wrap" id="aPreviewWrap" style="display:none;">
                <img id="aImagePreview" src="#" alt="Preview" class="lf-img-preview">
                <button type="button" class="lf-remove-btn" id="aRemoveImage">
                  <svg viewBox="0 0 20 20" fill="none">
                    <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                  </svg>
                  Remove photo
                </button>
              </div>
            </div>
          </div>

          <!-- Section 04: Availability -->
          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">04</span>
              <span class="lf-sec-ttl">Availability</span>
            </div>
            <div class="lf-avail-row">
              <input type="checkbox" name="available" id="aAvail" value="1" checked>
              <label for="aAvail">Set Available Immediately (visible to renters)</label>
            </div>
          </div>

        </div><!-- /right -->
      </div>

      <div class="lf-footer">
        <button type="button" class="lf-cancel" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="lf-submit">
          <span>Add Vehicle</span>
          <svg viewBox="0 0 20 20" fill="none"><path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════
     EDIT VEHICLE MODAL
════════════════════════════════════════════════════ -->
<div class="lf-overlay" id="editModal">
  <div class="lf-box">
    <div class="lf-header">
      <span class="lf-num">LISTINGS</span>
      <span class="lf-title">Edit Vehicle</span>
    </div>

    <form method="POST" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action"     value="edit_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <input type="hidden" name="car_id"     id="eId"/>

      <div class="lf-body">
        <!-- LEFT column -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">01</span>
              <span class="lf-sec-ttl">Vehicle Identity</span>
            </div>
            <div class="lf-field">
              <label class="lf-label" for="eName">Make &amp; Model *</label>
              <input class="lf-input" type="text" id="eName" name="car_name" required>
            </div>
            <div class="lf-field">
              <label class="lf-label" for="eLicenseType">Vehicle Class</label>
              <div class="lf-select-wrap">
                <select class="lf-select" id="eLicenseType" name="license_type">
                  <option value="A">A — Motorcycles &amp; Scooters</option>
                  <option value="B">B — Cars, Jeeps, Vans</option>
                  <option value="C">C — Commercial Heavy</option>
                  <option value="D">D — Public Service</option>
                  <option value="E">E — Heavy with Trailers</option>
                </select>
                <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
              </div>
            </div>
            <div class="lf-row">
              <div class="lf-field">
                <label class="lf-label" for="eTransmission">Transmission</label>
                <div class="lf-select-wrap">
                  <select class="lf-select" id="eTransmission" name="transmission">
                    <option value="Manual">Manual</option>
                    <option value="Automatic">Automatic</option>
                  </select>
                  <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
              </div>
              <div class="lf-field">
                <label class="lf-label" for="eFuelType">Fuel Type</label>
                <div class="lf-select-wrap">
                  <select class="lf-select" id="eFuelType" name="fuel_type">
                    <option value="Petrol">Petrol</option>
                    <option value="Diesel">Diesel</option>
                    <option value="Electric">Electric</option>
                    <option value="Hybrid">Hybrid</option>
                    <option value="CNG">CNG</option>
                  </select>
                  <svg class="lf-select-icon" viewBox="0 0 20 20" fill="none"><path d="M5 8l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                </div>
              </div>
            </div>
          </div>

          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">02</span>
              <span class="lf-sec-ttl">Specs &amp; Pricing</span>
            </div>
            <div class="lf-row">
              <div class="lf-field">
                <label class="lf-label" for="eTopSpeed">Top Speed (km/h)</label>
                <input class="lf-input" type="number" id="eTopSpeed" name="top_speed" placeholder="e.g. 160" min="0">
              </div>
              <div class="lf-field">
                <label class="lf-label" for="ePrice">Price / Day (NPR) *</label>
                <input class="lf-input" type="number" id="ePrice" name="price_per_day" min="1" step="0.01" required>
              </div>
            </div>
            <div class="lf-field">
              <label class="lf-label">Vehicle Colour</label>
              <div class="lf-color-row" id="eColorTrigger" title="Click to pick colour">
                <input type="color" id="eColorPicker" name="color" value="#e03030">
                <div class="lf-color-swatch" id="eColorSwatch" style="background:#e03030"></div>
                <div class="lf-color-meta">
                  <span class="lf-color-name" id="eColorName">Racing Red</span>
                  <span class="lf-color-hex"  id="eColorHex">#e03030</span>
                </div>
              </div>
              <div class="lf-color-presets">
                <span class="lf-color-presets__label">Quick Colours</span>
                <div class="lf-color-presets__grid" id="eColorGrid"></div>
              </div>
            </div>
          </div>

        </div><!-- /left -->

        <!-- RIGHT column -->
        <div style="display:flex;flex-direction:column;gap:14px;">

          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">03</span>
              <span class="lf-sec-ttl">Replace Photo</span>
            </div>
            <div class="lf-field">
              <label class="lf-label">New Image (optional)</label>
              <label class="lf-dropzone" id="eDropzone">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
                </svg>
                <span class="lf-dropzone__text">Click to upload or drag &amp; drop</span>
                <span class="lf-dropzone__hint">JPG, PNG, WEBP — max 8 MB</span>
                <input type="file" id="eImageInput" name="image"
                       accept=".jpg,.jpeg,.png,.webp" hidden>
              </label>
              <div class="lf-preview-wrap" id="ePreviewWrap" style="display:none;">
                <img id="eImagePreview" src="#" alt="Preview" class="lf-img-preview">
                <button type="button" class="lf-remove-btn" id="eRemoveImage">
                  <svg viewBox="0 0 20 20" fill="none">
                    <path d="M6 6l8 8M14 6l-8 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                  </svg>
                  Remove photo
                </button>
              </div>
            </div>
          </div>

          <div class="lf-section">
            <div class="lf-sec-head">
              <span class="lf-sec-num">04</span>
              <span class="lf-sec-ttl">Availability</span>
            </div>
            <div class="lf-avail-row">
              <input type="checkbox" name="available" id="eAvail" value="1">
              <label for="eAvail">Available (visible to renters)</label>
            </div>
          </div>

        </div><!-- /right -->
      </div>

      <div class="lf-footer">
        <button type="button" class="lf-cancel" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="lf-submit">
          <span>Save Changes</span>
          <svg viewBox="0 0 20 20" fill="none"><path d="M4 10h12M11 5l5 5-5 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE MODAL (kept simple/dark) -->
<div class="lf-overlay" id="deleteModal">
  <div class="lf-box" style="max-width:420px">
    <div class="lf-header">
      <span class="lf-num">⚠</span>
      <span class="lf-title">Delete Vehicle?</span>
    </div>
    <div style="padding:1.4rem 2rem;">
      <p id="deleteMsg" style="color:#999;font-size:.88rem;line-height:1.75;margin-bottom:1.5rem"></p>
      <form method="POST" id="deleteForm">
        <input type="hidden" name="action"     value="delete_vehicle"/>
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
        <input type="hidden" name="car_id"     id="deleteCarId"/>
        <div class="lf-footer" style="padding:0;border:none;margin-top:0;">
          <button type="button" class="lf-cancel" onclick="closeModal('deleteModal')">Cancel</button>
          <button type="submit" class="lf-submit">Yes, Delete</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
/* ── Modal helpers ── */
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.lf-overlay').forEach(m => {
    m.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.lf-overlay.open').forEach(m => closeModal(m.id)); });

/* ── Colour presets shared data ── */
const COLOR_PRESETS = [
    { hex:'#e03030', name:'Racing Red'    },
    { hex:'#c82020', name:'Deep Red'      },
    { hex:'#ffffff', name:'White'         },
    { hex:'#f0f0f0', name:'Pearl White'   },
    { hex:'#c0c0c0', name:'Silver'        },
    { hex:'#4a4a4a', name:'Graphite'      },
    { hex:'#1a1a1a', name:'Midnight Black'},
    { hex:'#000000', name:'Black'         },
    { hex:'#003366', name:'Navy Blue'     },
    { hex:'#4169e1', name:'Royal Blue'    },
    { hex:'#1a3c1a', name:'Forest Green'  },
    { hex:'#006400', name:'Dark Green'    },
    { hex:'#d4a017', name:'Gold'          },
    { hex:'#ff8c00', name:'Dark Orange'   },
    { hex:'#4b0082', name:'Indigo'        },
    { hex:'#6a0dad', name:'Purple'        },
];

/* ── Build colour picker for a form (add or edit) ── */
function buildColorPicker(opts) {
    // opts: { triggerId, pickerId, swatchId, nameId, hexId, gridId }
    const trigger = document.getElementById(opts.triggerId);
    const picker  = document.getElementById(opts.pickerId);
    const swatch  = document.getElementById(opts.swatchId);
    const nameEl  = document.getElementById(opts.nameId);
    const hexEl   = document.getElementById(opts.hexId);
    const grid    = document.getElementById(opts.gridId);

    // Build preset swatches
    COLOR_PRESETS.forEach(({ hex, name }) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'lf-preset-sw';
        btn.style.background = hex;
        btn.title = name;
        btn.dataset.hex = hex;
        btn.addEventListener('click', () => applyColor(hex, name));
        grid.appendChild(btn);
    });

    function applyColor(hex, name) {
        picker.value          = hex;
        swatch.style.background = hex;
        nameEl.textContent    = name || hex;
        hexEl.textContent     = hex;
        // mark active preset
        grid.querySelectorAll('.lf-preset-sw').forEach(b => {
            b.classList.toggle('active', b.dataset.hex.toLowerCase() === hex.toLowerCase());
        });
    }

    // Open native picker on click
    trigger.addEventListener('click', () => picker.click());

    picker.addEventListener('input', () => {
        applyColor(picker.value, picker.value);
    });

    // Expose setter
    opts.setColor = applyColor;
}

/* ── Initialize colour pickers ── */
const addColor = {};
buildColorPicker({
    triggerId:'aColorTrigger', pickerId:'aColorPicker',
    swatchId:'aColorSwatch', nameId:'aColorName', hexId:'aColorHex', gridId:'aColorGrid',
    ...addColor
});

const editColorPicker = {};
buildColorPicker({
    triggerId:'eColorTrigger', pickerId:'eColorPicker',
    swatchId:'eColorSwatch', nameId:'eColorName', hexId:'eColorHex', gridId:'eColorGrid',
    ...editColorPicker
});

/* ── Image dropzone / preview ── */
function setupDropzone(inputId, dropzoneId, previewWrapId, previewImgId, removeBtnId) {
    const input     = document.getElementById(inputId);
    const dropzone  = document.getElementById(dropzoneId);
    const wrap      = document.getElementById(previewWrapId);
    const preview   = document.getElementById(previewImgId);
    const removeBtn = document.getElementById(removeBtnId);

    function showPreview(file) {
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            dropzone.style.display = 'none';
            wrap.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    }

    input.addEventListener('change', () => showPreview(input.files[0]));
    removeBtn.addEventListener('click', () => {
        input.value = '';
        preview.src = '#';
        wrap.style.display = 'none';
        dropzone.style.display = 'flex';
    });

    // Drag & drop
    dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', e => {
        e.preventDefault(); dropzone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file) {
            const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
            showPreview(file);
        }
    });
}

setupDropzone('aImageInput','aDropzone','aPreviewWrap','aImagePreview','aRemoveImage');
setupDropzone('eImageInput','eDropzone','ePreviewWrap','eImagePreview','eRemoveImage');

/* ── Edit modal population ── */
function openEditModal(car) {
    document.getElementById('eId').value             = car.id;
    document.getElementById('eName').value           = car.name         || '';
    document.getElementById('ePrice').value          = car.price_per_day|| '';
    document.getElementById('eTopSpeed').value       = car.top_speed    || '';
    document.getElementById('eTransmission').value   = car.transmission || 'Manual';
    document.getElementById('eFuelType').value       = car.fuel_type    || 'Petrol';
    document.getElementById('eLicenseType').value    = car.license_type || 'B';
    document.getElementById('eAvail').checked        = car.available    == 1;

    // Set colour
    const col = car.color || '#e03030';
    document.getElementById('eColorPicker').value    = col;
    document.getElementById('eColorSwatch').style.background = col;
    document.getElementById('eColorName').textContent = col;
    document.getElementById('eColorHex').textContent  = col;
    document.getElementById('eColorGrid').querySelectorAll('.lf-preset-sw').forEach(b => {
        b.classList.toggle('active', b.dataset.hex.toLowerCase() === col.toLowerCase());
    });

    // Reset image dropzone
    document.getElementById('eImageInput').value = '';
    document.getElementById('eImagePreview').src = '#';
    document.getElementById('ePreviewWrap').style.display  = 'none';
    document.getElementById('eDropzone').style.display     = 'flex';

    openModal('editModal');
}

/* ── Delete modal ── */
function confirmDelete(id, name) {
    document.getElementById('deleteCarId').value = id;
    document.getElementById('deleteMsg').textContent =
        'Are you sure you want to permanently delete "' + name + '"? This action cannot be undone.';
    openModal('deleteModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
