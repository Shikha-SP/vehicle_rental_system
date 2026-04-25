<?php
require_once 'admin_functions.php';
requireAdmin();

$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price),0) FROM bookings");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$pendingCount = 0; // Booking status removed

$auditLogs = [];
$logRes = $conn->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 50");
if ($logRes) {
    while($row = $logRes->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/admin.css">

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left"><h1>ANALYTICS &amp; AUDIT LOG</h1><p>System activity and admin actions</p></div>
    </div>
    <div class="content">
      <div class="stats-row">
        <div class="stat-card"><div class="stat-label">Total Bookings</div><div class="stat-value"><?= $totalBookings ?></div><div class="stat-sub">All time</div></div>
        <div class="stat-card"><div class="stat-label">Total Revenue</div><div class="stat-value" style="font-size:1.8rem">NPR
          <?= number_format($totalRevenue, 0) ?></div><div class="stat-sub">Excl. cancelled</div></div>
        
      </div>

      <div class="sec">
        <div class="sec-head"><span class="sec-title">Audit Log</span></div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Time</th><th>Action</th><th>Admin</th><th>Target</th><th>Detail</th></tr></thead>
            <tbody>
            <?php if ($auditLogs): foreach ($auditLogs as $log): ?>
            <tr>
              <td style="font-size:.72rem;color:var(--fg3);white-space:nowrap"><?= htmlspecialchars($log['created_at']) ?></td>
              <td><span style="font-size:.72rem;font-weight:600;color:var(--red);text-transform:uppercase;letter-spacing:.04em"><?= htmlspecialchars(str_replace('_', ' ', $log['action'])) ?></span></td>
              <td style="font-size:.78rem"><?= htmlspecialchars($log['admin_name'] ?? '—') ?></td>
              <td style="font-size:.72rem;color:var(--fg3)"><?= htmlspecialchars($log['target_type'] ?? '') ?> <?= $log['target_id'] ? '#'.$log['target_id'] : '' ?></td>
              <td style="font-size:.72rem;color:var(--fg3)"><?= htmlspecialchars($log['detail'] ?? '') ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--fg3);padding:2rem">No audit entries yet.</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
