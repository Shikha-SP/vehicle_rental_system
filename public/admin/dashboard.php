<?php
require_once 'admin_functions.php';
requireAdmin();

// Data Queries
$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status != 'cancelled'");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$totalUsersResult = $conn->query("SELECT COUNT(*) FROM users");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_row()[0] : 0;

$pendingCountResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$pendingCount = $pendingCountResult ? $pendingCountResult->fetch_row()[0] : 0;

$confirmedCountResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
$confirmedCount = $confirmedCountResult ? $confirmedCountResult->fetch_row()[0] : 0;

$totalCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles");
$totalCars = $totalCarsResult ? $totalCarsResult->fetch_row()[0] : 0;

$availCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available'");
$availCars = $availCarsResult ? $availCarsResult->fetch_row()[0] : 0;

// Revenue Chart
$revenueByDay = [];
try {
    $rows = $conn->query("SELECT DATE(created_at) AS d, SUM(total_price) AS rev
                         FROM bookings WHERE status != 'cancelled'
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(created_at) ORDER BY d ASC");
    if($rows) {
        while($r = $rows->fetch_assoc()) {
            $revenueByDay[$r['d']] = $r['rev'];
        }
    }
} catch (Exception $e) {}

// Recent Users
$users = [];
$uRes = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
if ($uRes) {
    while($row = $uRes->fetch_assoc()) {
        $users[] = $row;
    }
}

// Recent Bookings
$bookings = [];
$bRes = $conn->query("
    SELECT b.*, v.model AS car_name, v.image_path AS image_file,
           u.first_name, u.last_name, u.email AS customer_email
    FROM bookings b
    JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC LIMIT 6
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
    <div class="topbar">
      <div class="topbar-left"><h1>OPERATIONS CONTROL</h1><p>Status: All Systems Functional</p></div>
      <div class="topbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search VIN, user, or ID..."/>
        </div>
      </div>
    </div>
    
    <div class="content">
      <div class="dash-grid">
        <div class="fleet-overview-card">
          <h2>Fleet Overview</h2>
          <div class="fleet-big-num"><?= $totalCars ?></div>
          <div class="fleet-big-label">Vehicles Active</div>
          <div class="fleet-sub-stats">
            <div class="fleet-sub-item">
              <label>Maintenance</label>
              <span><?= $totalCars - $availCars ?></span>
              <div class="fleet-sub-bar maintenance-bar" style="background:var(--blue);width:<?= $totalCars>0?round(($totalCars-$availCars)/$totalCars*100):0 ?>%"></div>
            </div>
            <div class="fleet-sub-item">
              <label>Available</label>
              <span><?= $availCars ?></span>
              <div class="fleet-sub-bar available-bar" style="background:var(--fg3);width:<?= $totalCars>0?round($availCars/$totalCars*100):0 ?>%"></div>
            </div>
            <div class="fleet-sub-item">
              <label>Utilization</label>
              <span><?= $totalCars>0?round($confirmedCount/$totalCars*100).'%':'0%' ?></span>
              <div class="fleet-sub-bar utilization-bar" style="background:var(--fg3);width:<?= $totalCars>0?min(100,round($confirmedCount/$totalCars*100)):0 ?>%"></div>
            </div>
          </div>
        </div>
        <div>
          <div class="revenue-card">
            <div class="rev-label">Total Revenue <span class="rev-badge">+12.4%</span></div>
            <div class="rev-value">NPR<?= number_format($totalRevenue, 0) ?></div>
            <div class="mini-chart">
              <?php
              $vals = array_values($revenueByDay);
              if (count($vals) < 7) $vals = array_merge(array_fill(0, 7-count($vals), 0), $vals);
              $max = max($vals) ?: 1;
              foreach ($vals as $i => $v):
                $h = max(4, round($v / $max * 100));
              ?>
              <div class="mini-bar <?= $i===count($vals)-1?'hi':'' ?>" style="height:<?= $h ?>%"></div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="verif-card">
            <div style="font-size:.6rem;text-transform:uppercase;letter-spacing:.12em;color:var(--fg3);margin-bottom:.75rem">Recent Users</div>
            <?php foreach (array_slice($users, 0, 2) as $u): ?>
            <div class="verif-item">
              <div class="verif-avatar"><?= strtoupper(substr($u['first_name'], 0, 1)) ?></div>
              <div>
                <div class="verif-name"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                <div class="verif-role"><?= $u['is_verified'] ? 'Verified User' : 'Standard' ?></div>
              </div>
              <button class="verif-action">Review</button>
            </div>
            <?php endforeach; ?>
            <button class="verif-all">View All (<?= $pendingCount ?> Pending)</button>
          </div>
        </div>
      </div>

      <div class="sec">
        <div class="sec-head">
          <span class="sec-title">Recent Global Bookings</span>
          <div style="display:flex;gap:.5rem">
            <button class="btn btn-ghost btn-sm">Filter by Status</button>
            <button class="btn btn-ghost btn-sm">Export CSV</button>
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Vehicle</th><th>Client</th><th>Duration</th><th>Status</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:.75rem">
                  <div style="width:44px;height:32px;background:var(--bg4);border-radius:4px;overflow:hidden;flex-shrink:0">
                    <img src="<?= htmlspecialchars(strpos($b['image_file'], 'http') !== false ? $b['image_file'] : '../../assets/images/' . $b['image_file']) ?>"
                         style="width:100%;height:100%;object-fit:cover"
                         onerror="this.parentNode.style.background='var(--bg4)'" />
                  </div>
                  <div>
                    <div style="font-weight:600;font-size:.8rem"><?= htmlspecialchars($b['car_name']) ?></div>
                    <div style="font-size:.65rem;color:var(--fg3)">ID #<?= $b['id'] ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div style="font-weight:500"><?= htmlspecialchars($b['customer'] ?? '—') ?></div>
                <div style="font-size:.65rem;color:var(--fg3)"><?= htmlspecialchars($b['customer_email'] ?? '') ?></div>
              </td>
              <td><?= (int)($b['days'] ?? 0) ?> Day<?= ($b['days'] ?? 0) != 1 ? 's' : '' ?></td>
              <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
              <td style="font-weight:600">NPR<?= number_format($b['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
