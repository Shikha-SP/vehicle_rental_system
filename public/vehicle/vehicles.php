<?php
session_start();

// Ensure the user is logged in and is NOT admin
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

require_once '../../config/db.php';
require_once '../../includes/functions.php';

$username = $_SESSION['username'] ?? 'User';

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$transmission = isset($_GET['transmission']) ? $_GET['transmission'] : '';
$fuel_type = isset($_GET['fuel_type']) ? $_GET['fuel_type'] : '';
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;

// Build the query for approved vehicles
$sql = "SELECT v.*, u.first_name, u.email 
        FROM vehicles v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.status = 'approved'";

// Apply filters
if (!empty($search)) {
    $sql .= " AND (v.model LIKE ? OR v.brand LIKE ?)";
}
if (!empty($transmission)) {
    $sql .= " AND v.transmission = ?";
}
if (!empty($fuel_type)) {
    $sql .= " AND v.fuel_type = ?";
}
if ($min_price > 0) {
    $sql .= " AND v.price_per_day >= ?";
}
if ($max_price > 0) {
    $sql .= " AND v.price_per_day <= ?";
}

$sql .= " ORDER BY v.created_at DESC";

// Prepare and execute query
$stmt = $conn->prepare($sql);

// Bind parameters dynamically
$param_types = "";
$params = [];

if (!empty($search)) {
    $search_param = "%$search%";
    $param_types .= "ss";
    $params[] = $search_param;
    $params[] = $search_param;
}
if (!empty($transmission)) {
    $param_types .= "s";
    $params[] = $transmission;
}
if (!empty($fuel_type)) {
    $param_types .= "s";
    $params[] = $fuel_type;
}
if ($min_price > 0) {
    $param_types .= "i";
    $params[] = $min_price;
}
if ($max_price > 0) {
    $param_types .= "i";
    $params[] = $max_price;
}

if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get unique filter options for dropdowns
$filter_sql = "SELECT DISTINCT transmission, fuel_type FROM vehicles WHERE status = 'approved'";
$filter_result = $conn->query($filter_sql);

$transmissions = [];
$fuel_types = [];
while ($row = $filter_result->fetch_assoc()) {
    if (!empty($row['transmission'])) $transmissions[] = $row['transmission'];
    if (!empty($row['fuel_type'])) $fuel_types[] = $row['fuel_type'];
}
$transmissions = array_unique($transmissions);
$fuel_types = array_unique($fuel_types);
?>

<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/vehicles.css">

<main class="vehicles-page">
    <div class="vehicles-hero">
        <div class="vehicles-hero-content">
            <h1>Available Vehicles</h1>
            <p>Browse our collection of premium rental vehicles</p>
        </div>
    </div>

    <div class="vehicles-container">
        <!-- Search and Filters at the top -->
        <div class="vehicles-search-section">
            <form method="GET" action="vehicles.php" class="search-filters-form">
                <div class="search-row">
                    <div class="search-input-wrapper">
                        <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <path d="M21 21l-4.35-4.35"></path>
                        </svg>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               placeholder="Search by model or brand..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>

                    <div class="filter-group">
                        <select id="transmission" name="transmission">
                            <option value="">All Transmissions</option>
                            <?php foreach ($transmissions as $trans): ?>
                                <option value="<?= htmlspecialchars($trans) ?>" 
                                    <?= $transmission == $trans ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($trans) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <select id="fuel_type" name="fuel_type">
                            <option value="">All Fuel Types</option>
                            <?php foreach ($fuel_types as $fuel): ?>
                                <option value="<?= htmlspecialchars($fuel) ?>" 
                                    <?= $fuel_type == $fuel ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fuel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="price-range-filters">
                        <input type="number" 
                               name="min_price" 
                               placeholder="Min Price" 
                               value="<?= $min_price ?: '' ?>"
                               step="100">
                        <span>-</span>
                        <input type="number" 
                               name="max_price" 
                               placeholder="Max Price" 
                               value="<?= $max_price ?: '' ?>"
                               step="100">
                    </div>

                    <button type="submit" class="apply-filters-btn">Apply Filters</button>
                    <a href="vehicles.php" class="reset-filters-btn">Reset</a>
                </div>
            </form>
        </div>

        <!-- Vehicles Grid -->
        <div class="vehicles-content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="vehicles-stats">
                <p><?= $result->num_rows ?> vehicle(s) found</p>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <div class="vehicles-grid">
                    <?php while ($vehicle = $result->fetch_assoc()): ?>
                        <div class="vehicle-card">
                            <?php if (!empty($vehicle['image_path'])): ?>
                                <div class="vehicle-image-wrapper">
                                    <img src="../../<?= htmlspecialchars($vehicle['image_path']) ?>" 
                                         alt="<?= htmlspecialchars($vehicle['model']) ?>" 
                                         class="vehicle-image">
                                    <div class="vehicle-badge">Available</div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="vehicle-info">
                                <h3 class="vehicle-model"><?= htmlspecialchars($vehicle['model']) ?></h3>
                                <p class="vehicle-owner">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <?= htmlspecialchars($vehicle['first_name']) ?>
                                </p>
                                
                                <div class="vehicle-specs">
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['transmission']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M5 12h14M12 5l7 7-7 7"/>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['fuel_type']) ?></span>
                                    </div>
                                    <div class="spec">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <circle cx="12" cy="12" r="10"/>
                                            <path d="M12 6v6l4 2"/>
                                        </svg>
                                        <span><?= htmlspecialchars($vehicle['top_speed']) ?> km/h</span>
                                    </div>
                                </div>

                                <div class="vehicle-price">
                                    <span class="price">Rs. <?= number_format($vehicle['price_per_day']) ?></span>
                                    <span class="price-period">/day</span>
                                </div>

                                <div class="vehicle-actions">
                                    <a href="view_vehicle.php?id=<?= $vehicle['id'] ?>" class="btn-view">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-vehicles">
                    <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    <h3>No vehicles found</h3>
                    <p>Try adjusting your filters or check back later for new listings.</p>
                    <a href="vehicles.php" class="reset-link">Clear all filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>