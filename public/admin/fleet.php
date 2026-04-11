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
        $model = trim($_POST['car_name'] ?? '');
        $price = (float)($_POST['price_per_day'] ?? 0);
        $top_speed = (int)($_POST['top_speed'] ?? 0);
        $transmission = $_POST['engine'] ?? 'Manual';
        $fuel_type = $_POST['tag'] ?? 'Petrol';
        $status = isset($_POST['available']) ? 'available' : 'pending';

        if (!$model || !$price) {
            setFlash('error', 'Model and price are required.');
        } else {
            $imagePath = uploadVehicleImage($_FILES['image'] ?? []);
            if (!$imagePath) $imagePath = '';
            
            // assume admin is user_id 1 or current user
            $user_id = $_SESSION['user_id'] ?? 1;

            $stmt = $conn->prepare("INSERT INTO vehicles (user_id, model, price_per_day, top_speed, transmission, fuel_type, status, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isdissss", $user_id, $model, $price, $top_speed, $transmission, $fuel_type, $status, $imagePath);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                auditLog("vehicle_added", "vehicle", $newId, "Added: {$model}");
                setFlash('success', "Vehicle '{$model}' added successfully.");
            } else {
                setFlash('error', "Database error: " . $conn->error);
            }
        }
        header('Location: fleet.php');
        exit;
    }

    // Edit vehicle
    if ($action === 'edit_vehicle') {
        $cid = (int)$_POST['car_id'];
        $model = trim($_POST['car_name'] ?? '');
        $price = (float)($_POST['price_per_day'] ?? 0);
        $top_speed = (int)($_POST['top_speed'] ?? 0);
        $transmission = $_POST['engine'] ?? 'Manual';
        $fuel_type = $_POST['tag'] ?? 'Petrol';
        $status = isset($_POST['available']) ? 'available' : 'pending';

        if (!$model || !$price) {
            setFlash('error', 'Model and price are required.');
        } else {
            $newImg = uploadVehicleImage($_FILES['image'] ?? []);
            if ($newImg) {
                $stmt = $conn->prepare("UPDATE vehicles SET model=?, price_per_day=?, top_speed=?, transmission=?, fuel_type=?, status=?, image_path=? WHERE id=?");
                $stmt->bind_param("sdissssi", $model, $price, $top_speed, $transmission, $fuel_type, $status, $newImg, $cid);
            } else {
                $stmt = $conn->prepare("UPDATE vehicles SET model=?, price_per_day=?, top_speed=?, transmission=?, fuel_type=?, status=? WHERE id=?");
                $stmt->bind_param("sdisssi", $model, $price, $top_speed, $transmission, $fuel_type, $status, $cid);
            }

            if ($stmt->execute()) {
                auditLog("vehicle_edited", "vehicle", $cid, "Edited: {$model}");
                setFlash('success', "Vehicle '{$model}' updated.");
            } else {
                setFlash('error', "Database error: " . $conn->error);
            }
        }
        header('Location: fleet.php');
        exit;
    }

    // Delete vehicle
    if ($action === 'delete_vehicle') {
        $cid = (int)$_POST['car_id'];
        $activeRes = $conn->query("SELECT COUNT(*) FROM bookings WHERE vehicle_id = $cid AND status IN ('pending','confirmed')");
        $activeCount = $activeRes->fetch_row()[0];
        
        if ($activeCount > 0) {
            setFlash('error', 'Cannot delete: vehicle has active bookings. Cancel them first.');
        } else {
            $rowRes = $conn->query("SELECT model FROM vehicles WHERE id = $cid");
            $carModel = $rowRes->num_rows > 0 ? $rowRes->fetch_assoc()['model'] : 'unknown';
            
            $conn->query("DELETE FROM vehicles WHERE id = $cid");
            auditLog("vehicle_deleted", "vehicle", $cid, "Deleted: {$carModel}");
            setFlash('success', "Vehicle deleted.");
        }
        header('Location: fleet.php');
        exit;
    }
}

// Data queries
$totalCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles");
$totalCars = $totalCarsResult ? $totalCarsResult->fetch_row()[0] : 0;

$availCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'");
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
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="admin.css">

<div class="admin-wrapper">
  <div class="main">
    <?php if ($flash['msg']): ?>
    <div style="padding:1rem 2rem 0">
      <div class="flash <?= $flash['type']==='success'?'ok':'err' ?>"><?= htmlspecialchars($flash['msg']) ?></div>
    </div>
    <?php endif; ?>

    <div class="topbar">
      <div class="topbar-left">
        <h1>FLEET INVENTORY</h1>
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
          $isAvail = ($c['status'] === 'available');
          $statusLabel = !$isAvail ? ucfirst($c['status']) : ($ac > 0 ? 'Reserved' : 'Available');
          $statusClass = !$isAvail ? 'b-hidden' : ($ac > 0 ? 'b-upcoming' : 'b-available');
        ?>
        <div class="fleet-card">
          <div class="fleet-card-img">
            <img src="<?= htmlspecialchars(strpos($c['image_path'], 'http') !== false ? $c['image_path'] : '../../assets/images/' . $c['image_path']) ?>" alt=""
                 onerror="this.parentNode.style.background='var(--bg4)'" />
            <div class="status-pip"><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></div>
          </div>
          <div class="fleet-card-body">
            <div class="fleet-card-cat"><?= htmlspecialchars($c['fuel_type']) ?></div>
            <div class="fleet-card-name"><?= htmlspecialchars($c['model']) ?></div>
            <div class="fleet-specs">
              <div class="spec-item"><label>Top Speed</label><span><?= htmlspecialchars($c['top_speed'] ?? '—') ?> km/h</span></div>
              <div class="spec-item"><label>Trans.</label><span style="font-size:.7rem"><?= htmlspecialchars($c['transmission'] ?? '—') ?></span></div>
              <div class="spec-item"><label>Status</label><span style="font-size:.75rem"><?= ucfirst($c['status']) ?></span></div>
            </div>
            <div style="font-size:.8rem;color:var(--fg3);margin-bottom:.75rem">
              NPR<?= number_format($c['price_per_day'], 0) ?>/day
              <?php if ($ac > 0): ?> · <span style="color:var(--yellow)"><?= $ac ?> active booking<?= $ac > 1 ? 's' : '' ?></span><?php endif; ?>
            </div>
            <div class="fleet-card-actions">
              <button class="btn btn-ghost btn-sm" style="flex:1"
                      onclick='openEditModal(<?= json_encode([
                          'id' => $c['id'], 'name' => $c['model'], 'price_per_day' => $c['price_per_day'],
                          'top_speed' => $c['top_speed'], 'engine' => $c['transmission'], 'tag' => $c['fuel_type'],
                          'available' => ($c['status'] === 'available' ? 1 : 0)
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
          <div style="font-weight:600;font-size:.9rem">Expand Your Fleet</div>
          <div style="font-size:.75rem;color:var(--fg3);text-align:center">Add a new high-performance<br>machine to the inventory.</div>
          <button class="btn btn-ghost btn-sm">Start Entry</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-title">ADD VEHICLE</div>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="action"     value="add_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <div class="fg"><label>Model Name *</label><input type="text" name="car_name" required placeholder="e.g. FERRARI SF90"/></div>
      <div class="form-row">
        <div class="fg"><label>Price / Day (NPR) *</label><input type="number" name="price_per_day" min="1" step="0.01" required placeholder="1500"/></div>
        <div class="fg"><label>Fuel Type</label><input type="text" name="tag" placeholder="Petrol"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Top Speed (km/h)</label><input type="number" name="top_speed" placeholder="320"/></div>
        <div class="fg"><label>Transmission</label><select name="engine"><option value="Manual">Manual</option><option value="Automatic">Automatic</option></select></div>
      </div>
      <div class="fg"><label>Photo (JPG/PNG/WebP)</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"/></div>
      <div class="chk-row" style="margin-bottom:.85rem"><input type="checkbox" name="available" id="aAvail" value="1" checked/><label for="aAvail">Set Available Immediately</label></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('addModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Add Vehicle</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-title">EDIT VEHICLE</div>
    <form method="POST" enctype="multipart/form-data" id="editForm">
      <input type="hidden" name="action"     value="edit_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <input type="hidden" name="car_id"     id="eId"/>
      <div class="fg"><label>Model Name *</label><input type="text" name="car_name" id="eName" required/></div>
      <div class="form-row">
        <div class="fg"><label>Price / Day (NPR) *</label><input type="number" name="price_per_day" id="ePrice" min="1" step="0.01" required/></div>
        <div class="fg"><label>Fuel Type</label><input type="text" name="tag" id="eTag"/></div>
      </div>
      <div class="form-row">
        <div class="fg"><label>Top Speed (km/h)</label><input type="number" name="top_speed" id="eSpeed"/></div>
        <div class="fg"><label>Transmission</label><select name="engine" id="eEngine"><option value="Manual">Manual</option><option value="Automatic">Automatic</option></select></div>
      </div>
      <div class="fg"><label>Replace Photo (optional)</label><input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"/></div>
      <div style="display:flex;gap:1.5rem;margin-bottom:.85rem">
        <div class="chk-row"><input type="checkbox" name="available"   id="eAvail" value="1"/><label for="eAvail">Available (visible)</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('editModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
  <div class="modal-box" style="max-width:400px">
    <div class="modal-title">DELETE VEHICLE?</div>
    <p id="deleteMsg" style="color:var(--fg2);font-size:.85rem;margin-bottom:1.5rem;line-height:1.7"></p>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="action"     value="delete_vehicle"/>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
      <input type="hidden" name="car_id"     id="deleteCarId"/>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('deleteModal')">Cancel</button>
        <button type="submit" class="btn btn-red">Yes, Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
  m.addEventListener('click', function(e) { if (e.target === this) closeModal(this.id); });
});
function openEditModal(car) {
  document.getElementById('eId').value    = car.id;
  document.getElementById('eName').value  = car.name    || '';
  document.getElementById('ePrice').value = car.price_per_day || '';
  document.getElementById('eTag').value   = car.tag     || '';
  document.getElementById('eSpeed').value = car.top_speed     || '';
  document.getElementById('eEngine').value= car.engine  || 'Manual';
  document.getElementById('eAvail').checked = car.available   == 1;
  openModal('editModal');
}
function confirmDelete(id, name) {
  document.getElementById('deleteCarId').value = id;
  document.getElementById('deleteMsg').textContent =
    'Are you sure you want to permanently delete "' + name + '"? This cannot be undone.';
  openModal('deleteModal');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
