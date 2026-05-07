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
            <thead><tr><th>Customer</th><th>Status</th><th>Bookings</th><th>Lifetime Value</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u):
              $uBookings = array_filter($bookings, fn($b) => $b['customer_email'] === $u['email']);
              $uCount    = count($uBookings);
              $uRevenue  = array_sum(array_column($uBookings, 'total_price'));
              
              if ($u['status'] === 'timeout' && $u['ban_expires_at']) {
                  if (new DateTime() >= new DateTime($u['ban_expires_at'])) {
                      // Auto-restore in DB so they are treated as normal customers
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
              <td>
                <span style="font-weight:700; font-size:1.1rem; color:var(--fg);"><?= $uCount ?></span>
              </td>
              <td style="font-weight:600">NPR<?= number_format($uRevenue, 2) ?></td>
              <td>
                <div style="display:flex;gap:0.5rem">
                <?php if ($u['status'] === 'active'): ?>
                  <button class="btn btn-outline" style="padding: 4px 8px; font-size: 0.7rem; border-color: #f59e0b; color: #f59e0b;" onclick="openTimeoutModal(<?= $u['id'] ?>)">Timeout</button>
                  <button class="btn btn-outline" style="padding: 4px 8px; font-size: 0.7rem; border-color: var(--red); color: var(--red);" onclick="banUser(<?= $u['id'] ?>)">Ban</button>
                <?php else: ?>
                  <button class="btn btn-outline" style="padding: 4px 8px; font-size: 0.7rem; border-color: #4ade80; color: #4ade80;" onclick="unbanUser(<?= $u['id'] ?>)">Restore</button>
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
<div id="timeoutModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center;">
    <div class="modal-content" style="background:#111; border:1px solid #333; padding:2rem; border-radius:12px; width:400px; max-width:90%;">
        <h3 style="margin-top:0; color:#f59e0b; font-family:'Bebas Neue',sans-serif; font-size:2rem;">Set Timeout Duration</h3>
        <p style="color:#aaa; font-size:0.9rem; margin-bottom:1.5rem;">The user will be temporarily suspended for the specified number of days.</p>
        <input type="hidden" id="timeoutUserId">
        <div style="margin-bottom:1.5rem; display:flex; gap:1rem;">
            <div style="flex:1;">
                <label style="display:block; margin-bottom:0.5rem; color:#888; font-size:0.8rem;">Duration</label>
                <input type="number" id="timeoutValue" value="7" min="1" max="365" style="width:100%; padding:0.8rem; background:#000; border:1px solid #333; color:#fff; border-radius:6px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; margin-bottom:0.5rem; color:#888; font-size:0.8rem;">Unit</label>
                <select id="timeoutUnit" style="width:100%; padding:0.8rem; background:#000; border:1px solid #333; color:#fff; border-radius:6px; height:46px;">
                    <option value="days">Days</option>
                    <option value="minutes">Minutes</option>
                </select>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:1rem;">
            <button onclick="document.getElementById('timeoutModal').style.display='none'" style="background:transparent; border:1px solid #444; color:#aaa; padding:0.5rem 1rem; border-radius:6px; cursor:pointer;">Cancel</button>
            <button onclick="submitTimeout()" style="background:#f59e0b; border:none; color:#000; font-weight:bold; padding:0.5rem 1rem; border-radius:6px; cursor:pointer;">Apply Timeout</button>
        </div>
    </div>
</div>

<script>
function openTimeoutModal(id) {
    document.getElementById('timeoutUserId').value = id;
    document.getElementById('timeoutModal').style.display = 'flex';
}

function submitTimeout() {
    const id = document.getElementById('timeoutUserId').value;
    const value = document.getElementById('timeoutValue').value;
    const unit = document.getElementById('timeoutUnit').value;
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

    fetch('user_actions.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('An unexpected error occurred.');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
