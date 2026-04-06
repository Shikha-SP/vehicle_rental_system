<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    header("Location: ../landing_page.php");
    exit;
}

$user_id = $_SESSION['user_id'];

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

                    <?php if ($vehicle['status'] === 'rejected'): ?>
                        <a href="../renter/list_Car.php" class="btn">Resubmit</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>You haven't listed any vehicles yet.</p>
        <a href="../renter/list_Car.php" class="btn">List Your First Vehicle</a>
    <?php endif; ?>
</main>

<?php require_once '../../includes/footer.php'; ?>