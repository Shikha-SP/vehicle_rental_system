<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// ✅ Correct admin check
if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../landing_page.php");
    exit;
}

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

// ✅ FIXED QUERY (first_name instead of username)
$sql = "SELECT v.*, u.first_name, u.email 
        FROM vehicles v 
        JOIN users u ON v.user_id = u.id 
        WHERE v.status = 'pending' 
        ORDER BY v.created_at DESC";

$result = $conn->query($sql);

// ✅ DEBUG (remove later)
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>

<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/admin_review.css">

<main class="admin-review">
    <h1>Pending Vehicle Approvals</h1>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <?php if ($result && $result->num_rows > 0): ?>
        <div class="vehicles-grid">
            <?php while ($vehicle = $result->fetch_assoc()): ?>
                <div class="vehicle-card">
                    
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
                        <p><strong>Transmission:</strong> <?= htmlspecialchars($vehicle['transmission']) ?></p>
                        <p><strong>Fuel:</strong> <?= htmlspecialchars($vehicle['fuel_type']) ?></p>
                        <p><strong>Top Speed:</strong> <?= htmlspecialchars($vehicle['top_speed']) ?> km/h</p>
                        <p><strong>Fuel Capacity:</strong> <?= htmlspecialchars($vehicle['fuel_capacity']) ?> L</p>
                        <p><strong>Submitted:</strong> <?= date('Y-m-d H:i', strtotime($vehicle['created_at'])) ?></p>
                    </div>
                    
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