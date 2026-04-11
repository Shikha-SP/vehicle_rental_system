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
    WHERE status != 'cancelled' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
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
<script src="../../assets/js/chart.min.js"></script>

<div class="admin-wrapper">
  <div class="main">
    <div class="topbar">
      <div class="topbar-left">
        <h1>OPERATIONS CONTROL</h1>
        <p>Dashboard Insight · Last 30 Days Activity</p>
      </div>
      <div class="topbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" placeholder="Search insights..."/>
        </div>
      </div>
    </div>
    
    <div class="content">
      <!-- KPI ROW -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Total Revenue</div>
          <div class="kpi-value">NPR <?= number_format($totalRevenue, 0) ?></div>
          <div class="kpi-sub">Across all confirmed rentals</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Active Vehicle Listings</div>
          <div class="kpi-value"><?= $availCars ?> <small>/ <?= $totalCars ?></small></div>
          <div class="kpi-sub">Vehicles ready for rental</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total Bookings</div>
          <div class="kpi-value"><?= $totalBookings ?></div>
          <div class="kpi-sub">Lifetime transactions</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-label">Total Users</div>
          <div class="kpi-value"><?= $totalUsers ?></div>
          <div class="kpi-sub">Registered drivers</div>
        </div>
      </div>

      <div class="chart-row">
        <div class="chart-container full-chart">
          <div class="chart-header">
            <h3>Revenue Trend <small>(30 Days)</small></h3>
          </div>
          <div class="chart-body">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>

      <div class="chart-row secondary">
        <div class="chart-container">
          <div class="chart-header">
            <h3>User Registrations</h3>
          </div>
          <div class="chart-body">
            <canvas id="userTrendChart"></canvas>
          </div>
        </div>
        <div class="chart-container">
          <div class="chart-header">
            <h3>Top Performing Vehicles</h3>
          </div>
          <div class="chart-body">
            <canvas id="topVehiclesChart"></canvas>
          </div>
        </div>
      </div>

      <!-- RECENT ACTIVITY -->
      <div class="sec">
        <div class="sec-head">
          <span class="sec-title">Recent System Bookings</span>
          <div style="display:flex;gap:.5rem">
            <button class="btn btn-ghost btn-sm">Refresh</button>
            <a href="vehicle_listings.php" class="btn btn-red btn-sm">Manage Listings</a>
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead>
              <tr>
                <th>Vehicle</th>
                <th>Client</th>
                <th>Dates</th>
                <th>Status</th>
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
              <td><span class="badge b-<?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
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

    // 4. Top Vehicles Chart
    new Chart(document.getElementById('topVehiclesChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topVehicles, 'model')) ?>,
            datasets: [{
                label: 'Bookings',
                data: <?= json_encode(array_column($topVehicles, 'bookings')) ?>,
                backgroundColor: (context) => {
                    const ctx = context.chart.ctx;
                    const gradient = ctx.createLinearGradient(0, 0, 400, 0);
                    gradient.addColorStop(0, '#e03535');
                    gradient.addColorStop(1, 'rgba(224, 53, 53, 0.1)');
                    return gradient;
                },
                borderWidth: { top: 0, right: 3, bottom: 0, left: 0 },
                borderColor: '#f87171',
                borderRadius: 8,
                hoverBackgroundColor: '#f87171'
            }]
        },
        options: {
            ...commonOptions,
            indexAxis: 'y'
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
