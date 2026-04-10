<?php
/**
 * My Vehicles Page (Renter Dashboard)
 * 
 * Displays all vehicles that the current logged-in user has listed for rent.
 * Provides a UI to view vehicle statuses (pending, approved, rejected) and 
 * actions to manage them (edit, remove).
 */
session_start();
require_once '../../config/db.php';

// Check if user is logged in and is a standard user (not an admin)
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all vehicles owned by the current user to display in the list
$sql = "SELECT * FROM vehicles WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/my_vehicles.css">

<main class="my-vehicles">
    <h1>My Vehicle Listings</h1>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?>">
            <?= $_SESSION['message'] ?>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <div class="vehicles-list">
            <?php while ($vehicle = $result->fetch_assoc()): ?>
                <div class="vehicle-item">
                    <h3><?= htmlspecialchars($vehicle['model']) ?></h3>
                    
                    <p>Rs. <?= number_format($vehicle['price_per_day']) ?>/day</p>
                    
                    <p>
                        <span class="status-<?= htmlspecialchars($vehicle['status']) ?>">
                            <?= ucfirst(htmlspecialchars($vehicle['status'])) ?>
                        </span>
                    </p>

                    <div class="vehicle-actions">
                        <?php if ($vehicle['status'] === 'pending'): ?>
                            <!-- Pending: Only show edit button -->
                            <a href="edit_renter_vehicle.php?id=<?= $vehicle['id'] ?>" class="btn btn-edit">Edit</a>
                            <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn btn-remove">Remove</button>
                        
                        <?php elseif ($vehicle['status'] === 'approved'): ?>
                            <!-- Approved: No edit, only remove -->
                            <span class="info-text">✓ Live on platform</span>
                            <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn btn-remove">Remove</button>
                        
                        <?php elseif ($vehicle['status'] === 'rejected'): ?>
                            <!-- Rejected: No edit, just resubmit and remove -->
                            <button onclick="confirmDelete(<?= $vehicle['id'] ?>)" class="btn btn-remove">Remove</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>You haven't listed any vehicles yet.</p>
        <a href="../renter/list_Car.php" class="btn btn-primary">List Your First Vehicle</a>
    <?php endif; ?>
</main>

<script>
function confirmDelete(vehicleId) {
    if (confirm('Are you sure you want to remove this vehicle from your listings? This action cannot be undone.')) {
        window.location.href = 'delete_renter_vehicle.php?id=' + vehicleId;
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>