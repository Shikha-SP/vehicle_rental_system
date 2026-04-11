<?php
/**
 * public/user/bookings.php — User booking history
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle Cancellation
$cancelError = $cancelSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        
        $upd_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?";
        $u_stmt = $conn->prepare($upd_sql);
        $u_stmt->bind_param("ii", $bookingId, $user_id);
        
        if ($u_stmt->execute()) {
            $cancelSuccess = "Booking #{$bookingId} has been successfully cancelled.";
        } else {
            $cancelError = "Failed to cancel booking.";
        }
    }
}

// Fetch Bookings
$sql = "SELECT b.*, v.model, v.image_path, 
               DATEDIFF(b.end_date, b.start_date) AS days
        FROM bookings b
        JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My Bookings – TD Rentals';
include '../../includes/header.php';
?>

<style>
    .bookings-container { padding: 4rem 0; min-height: 70vh; }
    .booking-card { 
        background: #111; 
        border: 1px solid rgba(255,255,255,0.05); 
        border-radius: 12px; 
        padding: 1.5rem; 
        margin-bottom: 1.5rem; 
        display: grid; 
        grid-template-columns: 100px 1fr auto; 
        gap: 2rem; 
        align-items: center; 
    }
    .car-thumb { width: 100px; height: 70px; object-fit: cover; border-radius: 6px; }
    .car-info h3 { font-family: 'Bebas Neue', sans-serif; font-size: 1.6rem; margin-bottom: 0.5rem; }
    .meta-info { font-size: 0.8rem; color: #888; display: flex; gap: 1.5rem; }
    
    .status-tag { 
        font-size: 10px; 
        font-weight: 700; 
        padding: 4px 10px; 
        border-radius: 4px; 
        text-transform: uppercase; 
    }
    .status-confirmed  { background: rgba(34,197,94,0.1); color: #4ade80; }
    .status-pending    { background: rgba(245,158,11,0.1); color: #fbbf24; }
    .status-cancelled  { background: rgba(220,38,38,0.1); color: #f87171; }
    
    .empty-state { text-align: center; padding: 6rem 1rem; }
    .empty-icon { font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3; }
    .empty-state h2 { font-family: 'Bebas Neue', sans-serif; font-size: 3rem; margin-bottom: 1rem; letter-spacing: 0.05em; }
    .empty-state p { color: #888; margin-bottom: 2.5rem; }

    .btn-cancel { 
        background: transparent; 
        border: 1px solid rgba(220,38,38,0.3); 
        color: #f87171; 
        padding: 8px 16px; 
        border-radius: 6px; 
        font-size: 11px; 
        font-weight: 700; 
        cursor: pointer;
    }
</style>

<div class="bookings-container container">
    <h1 style="font-family:'Bebas Neue',sans-serif; font-size: 4rem; margin-bottom: 2.5rem;">MY BOOKINGS</h1>

    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">📂</div>
            <h2>You don't have any bookings rn</h2>
            <p>It looks like you haven't reserved any vehicles yet. Let's find your perfect ride.</p>
            <a href="../vehicle/vehicles.php" class="btn-primary" style="display:inline-block; text-decoration:none; padding:14px 30px;">Browse Collections</a>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
            <div class="booking-card">
                <img src="../../<?= htmlspecialchars($b['image_path']) ?>" class="car-thumb" alt="">
                <div class="car-info">
                    <span class="status-tag status-<?= $b['status'] ?>"><?= $b['status'] ?></span>
                    <h3><?= htmlspecialchars($b['model']) ?></h3>
                    <div class="meta-info">
                        <span>📅 <?= date('M d', strtotime($b['start_date'])) ?> – <?= date('M d, Y', strtotime($b['end_date'])) ?></span>
                        <span>💰 NPR <?= number_format($b['total_price'], 0) ?></span>
                    </div>
                </div>
                <div>
                    <?php if ($b['status'] !== 'cancelled'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                            <button type="submit" class="btn-cancel">CANCEL</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
