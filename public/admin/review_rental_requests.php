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
<link rel="stylesheet" href="../../assets/css/admin_review.css">

<main class="admin-review">
    <h1>Pending Vehicle Approvals</h1>
    <!-- Display success or error notifications based on recent admin actions -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if ($result && $result->num_rows > 0): ?>
        <!-- Output a grid containing individual cards for each pending vehicle request -->
        <div class="vehicles-grid">
            <?php while ($vehicle = $result->fetch_assoc()): ?>
                <div class="vehicle-card">
                    <!-- Conditionally render the vehicle image if provided by the owner -->
                    <?php if (!empty($vehicle['image_path'])): ?>
                        <img src="../../<?= htmlspecialchars($vehicle['image_path']) ?>" 
                             alt="<?= htmlspecialchars($vehicle['model']) ?>" 
                             class="vehicle-image">
                    <?php endif; ?>
                    
                    <div class="vehicle-info">
                        <h3><?= htmlspecialchars($vehicle['model']) ?></h3>
                        <p><strong>Owner:</strong> <?= htmlspecialchars($vehicle['first_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($vehicle['email']) ?></p>
                        <p><strong>Price:</strong> Rs. <?= number_format($vehicle['price_per_day']) ?>/day</p>
                        
                        <!-- Extended vehicle specifications -->
                        <p><strong>Color:</strong> 
                            <span style="display: inline-block; width: 20px; height: 20px; background-color: <?= htmlspecialchars($vehicle['color'] ?? '#cccccc') ?>; border: 1px solid #ddd; vertical-align: middle;"></span>
                            <?= htmlspecialchars($vehicle['color'] ?? 'Not specified') ?>
                        </p>
                        <p><strong>License Type:</strong> <?= htmlspecialchars($vehicle['license_type'] ?? 'Not specified') ?></p>
                        <p><strong>Transmission:</strong> <?= htmlspecialchars($vehicle['transmission']) ?></p>
                        <p><strong>Fuel Type:</strong> <?= htmlspecialchars($vehicle['fuel_type']) ?></p>
                        <p><strong>Top Speed:</strong> <?= htmlspecialchars($vehicle['top_speed'] ?? 'N/A') ?> km/h</p>
                        <p><strong>Fuel Capacity:</strong> <?= htmlspecialchars($vehicle['fuel_capacity'] ?? 'N/A') ?> L</p>
                        <p><strong>Submitted:</strong> <?= date('Y-m-d H:i', strtotime($vehicle['created_at'])) ?></p>
                    </div>
                    <!-- Admin action buttons to approve or reject the request -->
                    <div class="vehicle-actions">
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                            <button type="submit" name="action" value="approve" class="btn approve">
                                ✓ Approve
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline-block;">
                            <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">
                            <button type="submit" name="action" value="reject" class="btn reject">
                                ✗ Reject
                            </button>
                        </form>
                    </div>
                    
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p class="no-vehicles">No pending vehicle requests to review.</p>
    <?php endif; ?>
</main>

<?php require_once '../../includes/footer.php'; ?>