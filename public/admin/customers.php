<?php
require_once 'admin_functions.php';
requireAdmin();

$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$totalUsersResult = $conn->query("SELECT COUNT(*) FROM users WHERE is_verified = 1");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_row()[0] : 0;

$confirmedCount = $totalBookings;

$users = [];
$uRes = $conn->query("SELECT * FROM users WHERE is_verified = 1 ORDER BY created_at DESC LIMIT 50");
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet"/>
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
            <thead><tr><th>Customer</th><th>Status</th><th>Bookings</th><th>Lifetime Value</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
              $uBookings = array_filter($bookings, fn($b) => $b['customer_email'] === $u['email']);
              $uCount    = count($uBookings);
              $uRevenue  = array_sum(array_column($uBookings, 'total_price'));

              if ($u['status'] === 'timeout' && $u['ban_expires_at']) {
                  if (new DateTime() >= new DateTime($u['ban_expires_at'])) {
                      $conn->query("UPDATE users SET status = 'active', ban_expires_at = NULL WHERE id = " . (int)$u['id']);
                      $u['status'] = 'active';
                      $u['ban_expires_at'] = null;
                  }
              }

              if ($u['status'] === 'banned') {
                  $statusClass = 'b-cancelled';
                  $statusLabel = 'BANNED';
              } elseif ($u['status'] === 'timeout') {
                  $statusClass = 'b-pending';
                  $statusLabel = 'TIMEOUT';
              } else {
                  $statusClass = $u['is_verified'] ? 'b-verified' : 'b-pending';
                  $statusLabel = $u['is_verified'] ? 'Verified'   : 'Unverified';
              }
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
              <td><span style="font-weight:700;font-size:1.1rem;color:var(--fg);"><?= $uCount ?></span></td>
              <td style="font-weight:600">NPR<?= number_format($uRevenue, 2) ?></td>
              <td>
                <div style="display:flex;gap:0.5rem">
                <?php if ($u['status'] === 'active'): ?>
                  <button class="btn btn-outline btn-sm" style="border-color:#f59e0b;color:#f59e0b;" onclick="openTimeoutModal(<?= $u['id'] ?>)">Timeout</button>
                  <button class="btn btn-outline-red btn-sm" onclick="banUser(<?= $u['id'] ?>)">Ban</button>
                <?php else: ?>
                  <button class="btn btn-outline btn-sm" style="border-color:#4ade80;color:#4ade80;" onclick="unbanUser(<?= $u['id'] ?>)">Restore</button>
                <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Timeout Modal -->
<div id="timeoutModal" class="modal-overlay" style="display:none;">
  <div class="modal-box" style="max-width:420px;">
    <h3 class="modal-title" style="color:#C0392B;font-size:1.6rem;">Set Timeout Duration</h3>
    <p class="tm-desc">The user will be temporarily suspended for the specified number of days.</p>
    <input type="hidden" id="timeoutUserId">
    <div class="form-row tm-fields">
      <div class="fg">
        <label for="timeoutValue">Duration</label>
        <input type="number" id="timeoutValue" value="7" min="1" max="365">
      </div>
      <div class="fg">
        <label for="timeoutUnit">Unit</label>
        <select id="timeoutUnit">
          <option value="days">Days</option>
          <option value="minutes">Minutes</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="document.getElementById('timeoutModal').style.display='none'">Cancel</button>
      <button class="btn btn-red" onclick="submitTimeout()">Apply Timeout</button>
    </div>
  </div>
</div>

<script>
function openTimeoutModal(id) {
    document.getElementById('timeoutUserId').value = id;
    document.getElementById('timeoutModal').style.display = 'flex';
}

function submitTimeout() {
    const id    = document.getElementById('timeoutUserId').value;
    const value = document.getElementById('timeoutValue').value;
    const unit  = document.getElementById('timeoutUnit').value;
    handleAction(id, 'timeout', value, unit);
}

function banUser(id) {
    if (confirm('Are you sure you want to permanently ban this user? Their account will be deleted in 3 days.')) {
        handleAction(id, 'ban', 3);
    }
}

function unbanUser(id) {
    if (confirm('Restore this user to active status?')) {
        handleAction(id, 'unban', 0);
    }
}

function handleAction(userId, action, value, unit = 'days') {
    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('action', action);
    formData.append('value', value);
    formData.append('unit', unit);

    fetch('user_actions.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert('Error: ' + data.message); }
        })
        .catch(err => { console.error(err); alert('An unexpected error occurred.'); });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>