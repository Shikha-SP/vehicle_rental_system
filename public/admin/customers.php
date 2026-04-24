<?php
require_once 'admin_functions.php';
requireAdmin();

$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$totalUsersResult = $conn->query("SELECT COUNT(*) FROM users");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_row()[0] : 0;

$confirmedCount = $totalBookings;

$users = [];
$uRes = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
if ($uRes) {
    while($row = $uRes->fetch_assoc()) {
        $users[] = $row;
    }
}

$bookings = [];
$bRes = $conn->query("
    SELECT b.*, u.email AS customer_email
    FROM bookings b
    LEFT JOIN users u ON u.id = b.user_id
");
if ($bRes) {
    while($row = $bRes->fetch_assoc()) {
        $bookings[] = $row;
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
      <div class="topbar-left"><h1>CUSTOMER INSIGHTS</h1><p>Registered Drivers &amp; Behavioral Data</p></div>
      <div class="topbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search customer records..."/>
        </div>
      </div>
    </div>
    
    <div class="content">
      <div class="customer-stats">
        
        <div class="cust-stat"><div class="cust-stat-label">Rental Frequency</div><div class="cust-stat-value"><?= $totalUsers > 0 ? number_format($totalBookings / $totalUsers, 1) : 0 ?>x</div><div class="cust-stat-sub">Annual avg per user</div></div>
      
        <div class="cust-stat accent"><div class="cust-stat-label">Total Active Users</div><div class="cust-stat-value"><?= $totalUsers ?></div><div class="cust-stat-sub">All time registrations</div></div>
      </div>
      
      <div class="sec">
        <div class="sec-head"><span class="sec-title">Customer Directory</span></div>
        <div class="tbl-wrap">
          <table>
            <thead><tr><th>Customer</th><th>Status</th><th>Bookings</th><th>Lifetime Value</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
              $uBookings = array_filter($bookings, fn($b) => $b['customer_email'] === $u['email']);
              $uCount    = count($uBookings);
              $uRevenue  = array_sum(array_column($uBookings, 'total_price'));
              $statusClass = $u['is_verified'] ? 'b-verified' : 'b-pending';
              $statusLabel = $u['is_verified'] ? 'Verified'   : 'Standard';
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:.75rem">
                  <div class="avatar-circle"><?= strtoupper(substr($u['first_name'], 0, 2)) ?></div>
                  <div>
                    <div style="font-weight:600"><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></div>
                    <div style="font-size:.7rem;color:var(--fg3)"><?= htmlspecialchars($u['email']) ?></div>
                  </div>
                </div>
              </td>
              <td><span class="badge <?= $statusClass ?>"><?= $statusLabel ?></span></td>
              <td>
                <div style="display:flex;align-items:center;gap:.5rem">
                  <div class="freq-dots">
                    <?php for ($i = 0; $i < 5; $i++): ?><div class="dot <?= $i < min(5, $uCount) ? 'on' : '' ?>"></div><?php endfor; ?>
                  </div>
                  <span style="font-size:.72rem;color:var(--fg3)"><?= $uCount ?> Rental<?= $uCount !== 1 ? 's' : '' ?></span>
                </div>
              </td>
              <td style="font-weight:600">NPR<?= number_format($uRevenue, 2) ?></td>
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
