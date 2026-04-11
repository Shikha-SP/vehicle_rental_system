<?php
require_once 'admin_functions.php';
requireAdmin();

$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // Update booking status
    if ($action === 'update_status') {
        $bid     = (int)$_POST['booking_id'];
        $status  = $_POST['status'];
        $allowed = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (in_array($status, $allowed)) {
            $oldRes = $conn->query("SELECT status FROM bookings WHERE id = $bid");
            $oldRow = $oldRes->fetch_assoc();
            
            $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $bid);
            if ($stmt->execute()) {
                auditLog("booking_status_changed", "booking", $bid, "from {$oldRow['status']} to {$status}");
                setFlash('success', "Booking #$bid status updated to $status.");
            } else {
                setFlash('error', "Database error: " . $conn->error);
            }
        }
        header('Location: reservations.php');
        exit;
    }
}

// Data queries
$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status != 'cancelled'");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$pendingCountResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$pendingCount = $pendingCountResult ? $pendingCountResult->fetch_row()[0] : 0;

$confirmedCountResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$confirmedCount = $confirmedCountResult ? $confirmedCountResult->fetch_row()[0] : 0;

$bookings = [];
$bRes = $conn->query("
    SELECT b.*, v.model AS car_name,
           u.first_name, u.last_name, u.email AS customer_email
    FROM bookings b
    JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC
");
if ($bRes) {
    while($row = $bRes->fetch_assoc()) {
        $row['customer'] = $row['first_name'] . ' ' . $row['last_name'];
        // calculate duration
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $row['days'] = max(1, $end->diff($start)->days);
        
        $bookings[] = $row;
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
      <div class="topbar-left"><h1>RESERVATIONS</h1></div>
      <div class="topbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search Bookings..."/>
        </div>
      </div>
    </div>
    
    <div class="content">
      <div class="res-stats">
        <div class="res-stat"><div class="res-stat-label">Total Active</div><div class="res-stat-value"><?= $confirmedCount ?></div></div>
        <div class="res-stat accent"><div class="res-stat-label">Pending Review</div><div class="res-stat-value red"><?= $pendingCount ?></div></div>
        <div class="res-stat"><div class="res-stat-label">Revenue (All Time)</div><div class="res-stat-value">NPR<?= number_format($totalRevenue, 2) ?></div></div>
      </div>
      
      <div class="filter-row">
        <span class="filter-label">Booking Status</span>
        <div class="filter-tabs">
          <button class="filter-tab on" onclick="filterBookings('all',this)">All</button>
          <button class="filter-tab" onclick="filterBookings('confirmed',this)">Confirmed</button>
          <button class="filter-tab" onclick="filterBookings('pending',this)">Pending</button>
          <button class="filter-tab" onclick="filterBookings('completed',this)">Completed</button>
        </div>
      </div>
      
      <div class="sec">
        <div class="tbl-wrap">
          <table id="bookings-table">
            <thead><tr>
              <th style="width:100px">Booking ID</th>
              <th>Customer</th><th>Vehicle</th><th>Dates</th><th>Status</th><th>Total</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr data-status="<?= $b['status'] ?>">
              <td style="color:var(--red);font-weight:700;font-size:.8rem">#TD-<?= str_pad($b['id'], 4, '0', STR_PAD_LEFT) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:.6rem">
                  <div class="avatar-circle" style="width:30px;height:30px;font-size:.65rem"><?= strtoupper(substr($b['customer'] ?? '?', 0, 2)) ?></div>
                  <span style="font-weight:500"><?= htmlspecialchars($b['customer'] ?? '—') ?></span>
                </div>
              </td>
              <td>
                <div style="font-weight:500"><?= htmlspecialchars($b['car_name']) ?></div>
                <div style="font-size:.65rem;color:var(--fg3)"><?= (int)($b['days'] ?? 0) ?> day<?= ($b['days'] ?? 0) != 1 ? 's' : '' ?></div>
              </td>
              <td style="font-size:.75rem"><?= htmlspecialchars($b['start_date']) ?> →<br><?= htmlspecialchars($b['end_date']) ?></td>
              <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
              <td style="font-weight:700">NPR<?= number_format($b['total_price'], 2) ?></td>
              <td>
                <form method="POST" style="display:inline">
                  <input type="hidden" name="action"     value="update_status"/>
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>"/>
                  <input type="hidden" name="booking_id" value="<?= $b['id'] ?>"/>
                  <select name="status" class="status-sel" onchange="this.form.submit()">
                    <?php foreach (['pending','confirmed','cancelled','completed'] as $s): ?>
                    <option value="<?= $s ?>" <?= $b['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="padding:.75rem 1rem;font-size:.7rem;color:var(--fg3);border-top:1px solid var(--border)">
          Showing <?= count($bookings) ?> of <?= $totalBookings ?> entries
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function filterBookings(status, btn) {
  document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('on'));
  btn.classList.add('on');
  document.querySelectorAll('#bookings-table tbody tr').forEach(tr => {
    tr.style.display = (status === 'all' || tr.dataset.status === status) ? '' : 'none';
  });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
