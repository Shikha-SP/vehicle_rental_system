<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Verify that the current user is authenticated and holds administrator privileges
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../landing_page.php");
    exit;
}

// Process an admin's decision to either approve or reject a rental request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Extract the submitted vehicle ID and the intended action (approve/reject)
    $vehicle_id = $_POST['vehicle_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $sql = "UPDATE vehicles SET status = 'approved', approved_at = NOW() WHERE id = ?";
        $message = "Vehicle approved successfully";
    } elseif ($action === 'reject') {
        $sql = "UPDATE vehicles SET status = 'rejected', rejected_at = NOW() WHERE id = ?";
        $message = "Vehicle rejected";
    }
    
    if (isset($sql)) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $vehicle_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = $message;
        } else {
            $_SESSION['error'] = "Failed to update vehicle status";
        }
        $stmt->close();
    }
    
    header("Location: review_rental_requests.php");
    exit;
}

// Retrieve all pending vehicle requests along with the owner's contact details
$sql = "SELECT v.*, u.first_name, u.email 
        FROM vehicles v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.status = 'pending' 
        ORDER BY v.created_at DESC";

$result = $conn->query($sql);

// Verify query execution and halt on failure with an error message
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/admin.css">

<div class="admin-wrapper">
  <div class="topbar">
    <div class="topbar-left">
      <h1>PENDING VEHICLES</h1>
      <p>Approve or reject vehicles submitted by users</p>
    </div>
  </div>

  <div style="padding: 0 5%; padding-bottom: 2rem;">
      <?php if (isset($_SESSION['success'])): ?>
          <div style="padding: 1rem; background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); border-radius: 8px; margin-bottom: 1.5rem;">
              <?= $_SESSION['success']; unset($_SESSION['success']); ?>
          </div>
      <?php endif; ?>
      <?php if (isset($_SESSION['error'])): ?>
          <div style="padding: 1rem; background: rgba(224,53,53,0.1); color: #e03535; border: 1px solid rgba(224,53,53,0.3); border-radius: 8px; margin-bottom: 1.5rem;">
              <?= $_SESSION['error']; unset($_SESSION['error']); ?>
          </div>
      <?php endif; ?>

      <div class="sec" style="background:var(--bg2); backdrop-filter:var(--glass);">
        <div class="sec-head" style="border-bottom:1px solid var(--border); padding: 1.5rem;">
          <span class="sec-title" style="font-family:var(--display); font-size:1.4rem; color:var(--fg); letter-spacing:0.05em; text-transform:none;">Vehicles Awaiting Action</span>
        </div>
        
        <?php if ($result && $result->num_rows > 0): ?>
        <div class="tbl-wrap" style="border:none;">
          <table>
            <thead>
              <tr>
                <th>Vehicle Details</th>
                <th>Owner Info</th>
                <th>Specifications</th>
                <th>Daily Rate</th>
                <th style="text-align: right;">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($vehicle = $result->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="tbl-item">
                  <div class="tbl-img" style="width: 280px; height: 180px; border-radius: 8px; flex-shrink: 0; box-shadow: 0 4px 12px rgba(0,0,0,0.4);">
                    <?php
                      $img = $vehicle['image_path'];
                      $src = (!empty($img) && strpos($img, 'http') === 0) ? $img : (!empty($img) ? '../../' . $img : '../../assets/images/car-placeholder.png');
                    ?>
                    <img src="<?= htmlspecialchars($src) ?>" onerror="this.src='../../assets/images/car-placeholder.png'">
                  </div>
                  <div>
                    <div class="tbl-main"><?= htmlspecialchars($vehicle['model']) ?></div>
                    <div class="tbl-sub">ID: #<?= $vehicle['id'] ?> | <?= date('M d, Y', strtotime($vehicle['created_at'])) ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="tbl-main"><?= htmlspecialchars($vehicle['first_name']) ?></div>
                <div class="tbl-sub"><?= htmlspecialchars($vehicle['email']) ?></div>
              </td>
              <td>
                <div class="tbl-sub" style="margin: 0; line-height: 1.6;">
                  <strong>Trans:</strong> <?= htmlspecialchars($vehicle['transmission']) ?> <br>
                  <strong>Fuel:</strong> <?= htmlspecialchars($vehicle['fuel_type']) ?> (<?= htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A') ?>L)<br>
                  <strong>Speed:</strong> <?= htmlspecialchars($vehicle['top_speed'] ?? 'N/A') ?> km/h <br>
                  <strong>License:</strong> <?= htmlspecialchars($vehicle['license_type'] ?? 'N/A') ?>
                </div>
              </td>
              <td class="tbl-price">NPR <?= number_format($vehicle['price_per_day']) ?></td>
              <td style="text-align: right;">
                <div style="display:flex; justify-content:flex-end; gap: 0.5rem;">
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-sm" style="background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.3);">
                            Approve
                        </button>
                    </form>
                    <form method="POST" style="margin: 0;">
                        <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                        <button type="submit" name="action" value="reject" class="btn btn-red btn-sm">
                            Reject
                        </button>
                    </form>
                </div>
              </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
            <div style="padding: 3rem; text-align: center; color: var(--fg3);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div style="font-size: 1.2rem; font-weight: 600; color: #fff; margin-bottom: 0.5rem;">No Pending Vehicles</div>
                <div>There are currently no vehicles waiting for your review.</div>
            </div>
        <?php endif; ?>
      </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>