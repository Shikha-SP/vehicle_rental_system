<?php
require_once 'admin_functions.php';
requireAdmin();

// --- 1. DATA QUERIES ---

// KPI Totals
$totalBookingsResult = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $totalBookingsResult ? $totalBookingsResult->fetch_row()[0] : 0;

$totalRevenueResult = $conn->query("SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE status != 'cancelled'");
$totalRevenue = $totalRevenueResult ? $totalRevenueResult->fetch_row()[0] : 0;

$totalUsersResult = $conn->query("SELECT COUNT(*) FROM users");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_row()[0] : 0;

$totalCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles");
$totalCars = $totalCarsResult ? $totalCarsResult->fetch_row()[0] : 0;

$availCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'available' OR status = 'approved'");
$availCars = $availCarsResult ? $availCarsResult->fetch_row()[0] : 0;

$pendingCarsResult = $conn->query("SELECT COUNT(*) FROM vehicles WHERE status = 'pending'");
$pendingCars = $pendingCarsResult ? $pendingCarsResult->fetch_row()[0] : 0;

$activeRentalsResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled' AND start_date <= CURDATE() AND end_date >= CURDATE()");
$activeRentals = $activeRentalsResult ? $activeRentalsResult->fetch_row()[0] : 0;

$checkoutsTodayResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled' AND start_date = CURDATE()");
$checkoutsToday = $checkoutsTodayResult ? $checkoutsTodayResult->fetch_row()[0] : 0;

$returnsTodayResult = $conn->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled' AND end_date = CURDATE()");
$returnsToday = $returnsTodayResult ? $returnsTodayResult->fetch_row()[0] : 0;


// 30-Day Revenue Trend
$revenueTrend = [];
$dateLabels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabels[] = date('M d', strtotime($date));
    $revenueTrend[$date] = 0;
}

$revRes = $conn->query("
    SELECT DATE(created_at) as d, SUM(total_price) as total 
    FROM bookings 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status != 'cancelled'
    GROUP BY DATE(created_at)
");
if ($revRes) {
    while ($r = $revRes->fetch_assoc()) {
        if (isset($revenueTrend[$r['d']])) {
            $revenueTrend[$r['d']] = (float)$r['total'];
        }
    }
}

// 30-Day User Trend
$userTrend = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $userTrend[$date] = 0;
}
$uTrendRes = $conn->query("
    SELECT DATE(created_at) as d, COUNT(*) as cnt 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
");
if ($uTrendRes) {
    while ($r = $uTrendRes->fetch_assoc()) {
        if (isset($userTrend[$r['d']])) {
            $userTrend[$r['d']] = (int)$r['cnt'];
        }
    }
}

// Top 5 Vehicles
$topVehicles = [];
$topVRes = $conn->query("
    SELECT v.model, COUNT(b.id) as bookings 
    FROM vehicles v 
    JOIN bookings b ON v.id = b.vehicle_id 
    WHERE b.status != 'cancelled'
    GROUP BY v.id 
    ORDER BY bookings DESC 
    LIMIT 5
");
if ($topVRes) {
    while ($r = $topVRes->fetch_assoc()) {
        $topVehicles[] = $r;
    }
}

// Recent Bookings (Last 10)
$recentBookings = [];
$bRes = $conn->query("
    SELECT b.*, v.model AS car_name, v.image_path,
           u.first_name, u.last_name, u.email
    FROM bookings b
    JOIN vehicles v ON v.id = b.vehicle_id
    LEFT JOIN users u ON u.id = b.user_id
    ORDER BY b.created_at DESC LIMIT 10
");
if ($bRes) {
    while($row = $bRes->fetch_assoc()) {
        $start = new DateTime($row['start_date']);
        $end = new DateTime($row['end_date']);
        $row['days'] = max(1, $end->diff($start)->days);
        $recentBookings[] = $row;
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../../assets/css/admin.css">
<style>
@keyframes spin { 100% { transform: rotate(360deg); } }
</style>
<script src="../../assets/js/chart.min.js"></script>

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <h1>OPERATIONS CONTROL</h1>
        <p>Dashboard Insight · Last 30 Days Activity</p>
      </div>
      <div class="topbar-right">
      </div>
    </div>
    
    <div class="content">
      <div class="dash-layout">
        <!-- LEFT COLUMN: Main Snapshot & Analytics -->
        <div class="dash-main">
          <!-- KPI Row -->
          <div class="kpi-row">
            <div class="kpi-card">
              <div class="kpi-label">Total Revenue</div>
              <div class="kpi-value">NPR <?= number_format($totalRevenue, 0) ?></div>
              <div class="kpi-sub">Across all confirmed rentals</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Total Vehicles</div>
              <div class="kpi-value"><?= $totalCars ?></div>
              <div class="kpi-sub"><?= $availCars ?> currently available</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Total Bookings</div>
              <div class="kpi-value"><?= $totalBookings ?></div>
              <div class="kpi-sub">Lifetime transactions</div>
            </div>
            <div class="kpi-card">
              <div class="kpi-label">Active Bookings</div>
              <div class="kpi-value"><?= $activeRentals ?></div>
              <div class="kpi-sub">Vehicles currently on rent</div>
            </div>
          </div>

          <!-- Revenue Trend -->
          <div class="chart-container" style="flex: 1; min-height: 440px;">
            <div class="chart-header">
              <h3>Revenue Trend <small>(Last 30 Days)</small></h3>
            </div>
            <div class="chart-body">
              <canvas id="revenueChart"></canvas>
            </div>
          </div>
        </div><!-- /dash-main -->

        <!-- RIGHT COLUMN: Actions & Side Insights -->
        <div class="dash-sidebar">
          
          <!-- Immediate Action Required -->
          <?php if ($pendingCars > 0): ?>
          <div class="action-block">
            <div class="action-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                ACTION REQUIRED
            </div>
            <div class="action-value"><?= $pendingCars ?></div>
            <div class="action-desc">Vehicles awaiting your approval. Review them to allow renters to go live.</div>
            <a href="review_rental_requests.php" class="btn btn-red btn-sm" style="width:100%; justify-content:center;">Review Listings Now</a>
          </div>
          <?php else: ?>
          <div class="action-block" style="background:rgba(34,197,94,0.05); border-color:rgba(34,197,94,0.3);">
            <div class="action-title" style="color:#4ade80;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                SYSTEM CLEAR
            </div>
            <div class="action-desc" style="margin-bottom:0;">No pending vehicles requiring admin review. The fleet is fully updated.</div>
          </div>
          <?php endif; ?>

          <!-- Today's Operations -->
          <div class="side-panel">
            <div class="side-panel-title" style="font-size:1.1rem; margin-bottom:0.8rem;">Today's Operations</div>
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom:0.5rem;">
                <span style="color:var(--fg3); font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Check-outs (Starting)</span>
                <span style="color:var(--blue); font-weight:bold; font-size:1.2rem;"><?= $checkoutsToday ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="color:var(--fg3); font-size:0.75rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">Returns Expected</span>
                <span style="color:var(--green); font-weight:bold; font-size:1.2rem;"><?= $returnsToday ?></span>
            </div>
          </div>

          <!-- Top Assets List -->
          <div class="side-panel">
            <div class="side-panel-title">Top Performing Assets</div>
            <div class="asset-list">
              <?php if (empty($topVehicles)): ?>
                <div style="color:var(--fg3); font-size:0.8rem;">No data available.</div>
              <?php else: ?>
                <?php $rank=1; foreach($topVehicles as $tv): ?>
                  <div class="asset-item">
                    <div style="display:flex; align-items:center;">
                        <span class="asset-rank">#<?= $rank++ ?></span>
                        <span class="asset-name"><?= htmlspecialchars($tv['model']) ?></span>
                    </div>
                    <span class="asset-count"><?= $tv['bookings'] ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- User Trend (Minified) -->
          <div class="chart-container sm-chart">
            <div class="chart-header">
              <h3>User Registrations</h3>
            </div>
            <div class="chart-body">
              <canvas id="userTrendChart"></canvas>
            </div>
          </div>

        </div><!-- /dash-sidebar -->
      </div><!-- /dash-layout -->

      <!-- FULL WIDTH RECENT ACTIVITY -->
      <div class="sec" style="margin-top:1.5rem; background:var(--bg2); backdrop-filter:var(--glass);">
        <div class="sec-head" style="border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
          <span class="sec-title" style="font-family:var(--display); font-size:1.4rem; color:var(--fg); letter-spacing:0.05em; text-transform:none; flex-shrink:0;">Recent Bookings</span>
          <div style="display:flex; gap:0.75rem; align-items:center;">
            <div class="search-box" style="padding:0.4rem 0.8rem; width: 220px; border-radius: 6px; background: rgba(0,0,0,0.2);">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
              <input type="text" id="dashboardSearch" placeholder="Search recent bookings..." style="font-size:0.75rem;" />
            </div>
            <button id="btnRefresh" class="btn btn-ghost btn-sm" style="display:flex; align-items:center; gap:6px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;"><path d="M21 12a9 9 0 11-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>
                Refresh
            </button>
            <a href="vehicle_listings.php" class="btn btn-red btn-sm">Manage Listings</a>
          </div>
        </div>
        <div class="tbl-wrap" style="border:none;">
          <table>
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Client</th>
                <th>Dates</th>
                <th>Amount</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recentBookings as $b): ?>
            <tr>
              <td>
                <div class="tbl-item">
                  <div class="tbl-img">
                    <?php
                      $img = $b['image_path'];
                      $src = (strpos($img, 'http') === 0) ? $img : '../../' . $img;
                    ?>
                    <img src="<?= htmlspecialchars($src) ?>" onerror="this.src='../../assets/images/car-placeholder.png'">
                  </div>
                  <div>
                    <div class="tbl-main"><?= htmlspecialchars($b['car_name']) ?></div>
                    <div class="tbl-sub">ID: #<?= $b['id'] ?></div>
                  </div>
                </div>
              </td>
              <td>
                <div class="tbl-main"><?= htmlspecialchars($b['first_name'] . ' ' . $b['last_name']) ?></div>
                <div class="tbl-sub"><?= htmlspecialchars($b['email']) ?></div>
              </td>
              <td>
                <div class="tbl-main"><?= date('M d', strtotime($b['start_date'])) ?> - <?= date('M d', strtotime($b['end_date'])) ?></div>
                <div class="tbl-sub"><?= $b['days'] ?> Days</div>
              </td>
              <td class="tbl-price">NPR <?= number_format($b['total_price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Shared Chart Config
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            intersect: false,
            mode: 'index',
        },
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: 'rgba(15, 15, 15, 0.95)',
                titleFont: { family: 'Inter', size: 14, weight: 'bold' },
                bodyFont: { family: 'Inter', size: 13 },
                padding: 16,
                cornerRadius: 12,
                displayColors: true,
                boxWidth: 8,
                boxHeight: 8,
                boxPadding: 6,
                borderColor: 'rgba(255,255,255,0.15)',
                borderWidth: 1,
                backdropFilter: 'blur(8px)'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: { 
                    color: 'rgba(255,255,255,0.03)',
                    borderDash: [4, 4],
                    drawBorder: false
                },
                ticks: { 
                    color: '#888', 
                    font: { size: 11, family: 'Inter' },
                    padding: 10
                }
            },
            x: {
                grid: { display: false },
                ticks: { 
                    color: '#888', 
                    font: { size: 11, family: 'Inter' },
                    padding: 10
                }
            }
        }
    };

    // 1. Revenue Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($dateLabels) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_values($revenueTrend)) ?>,
                borderColor: '#e03535',
                borderWidth: 4,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, 'rgba(224, 53, 53, 0.4)');
                    gradient.addColorStop(0.5, 'rgba(224, 53, 53, 0.1)');
                    gradient.addColorStop(1, 'rgba(224, 53, 53, 0)');
                    return gradient;
                },
                fill: true,
                tension: 0.4,
                pointRadius: 0,
                pointHitRadius: 20,
                pointHoverRadius: 6,
                pointHoverBackgroundColor: '#fff',
                pointHoverBorderColor: '#e03535',
                pointHoverBorderWidth: 3
            }]
        },
        options: commonOptions
    });


    // 3. User Trend Chart
    new Chart(document.getElementById('userTrendChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dateLabels) ?>,
            datasets: [{
                label: 'New Users',
                data: <?= json_encode(array_values($userTrend)) ?>,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, '#3b82f6');
                    gradient.addColorStop(0.8, 'rgba(59, 130, 246, 0.2)');
                    return gradient;
                },
                borderWidth: { top: 3, right: 0, bottom: 0, left: 0 },
                borderColor: '#60a5fa',
                borderRadius: 8,
                hoverBackgroundColor: '#60a5fa'
            }]
        },
        options: commonOptions
    });


});
</script>
<script src="../../assets/js/admin_dashboard.js"></script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
