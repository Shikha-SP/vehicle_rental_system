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

// Handle Cancellation (Permanent Deletion)
$cancelError = $cancelSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        
        // Verify the booking belongs to this user before cancelling
        $date_sql = "SELECT start_date FROM bookings WHERE id = ? AND user_id = ?";
        $d_stmt = $conn->prepare($date_sql);
        $d_stmt->bind_param("ii", $bookingId, $user_id);
        $d_stmt->execute();
        $res = $d_stmt->get_result()->fetch_assoc();
        
        if ($res) {
            // Update status to 'cancelled' instead of deleting the booking record
            $del_sql = "UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?";
            $del_stmt = $conn->prepare($del_sql);
            $del_stmt->bind_param("ii", $bookingId, $user_id);
            
            if ($del_stmt->execute()) {
                $cancelSuccess = "Booking #{$bookingId} has been successfully cancelled.";
            } else {
                $cancelError = "Failed to update booking status.";
            }
        } else {
            $cancelError = "Booking not found or you do not have permission to cancel it.";
        }
    } else {
        $cancelError = "Security token mismatch. Please refresh the page and try again.";
    }
}

// Ensure a single reusable CSRF token for all cancel buttons
$csrfToken = $_SESSION['csrf_token'] ?? generateCsrfToken();

// Fetch Bookings with more vehicle details
$sql = "SELECT b.*, v.model, v.image_path, v.transmission, v.fuel_type, v.top_speed, v.price_per_day,
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
    .bookings-container { padding: 5rem 40px; min-height: 80vh; width: 100%; max-width: none; margin: 0; }
    
    /* Policy Notice */
    .policy-box {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-left: 4px solid var(--red);
        padding: 1.5rem 2rem;
        border-radius: 12px;
        margin-bottom: 3.5rem;
        display: flex;
        gap: 1.5rem;
        align-items: center;
    }
    .policy-icon { font-size: 1.8rem; opacity: 0.8; }
    .policy-content h4 { font-family: 'Bebas Neue', sans-serif; font-size: 1.4rem; margin-bottom: 0.2rem; letter-spacing: 0.05em; }
    .policy-content p { font-size: 0.85rem; color: #888; line-height: 1.5; }

    /* Card Grid */
    .bookings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 2rem;
    }
    
    .booking-card { 
        background: rgba(20,20,20,0.7); 
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255,255,255,0.08); 
        border-radius: 20px; 
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
        display: flex;
        flex-direction: column;
        min-height: 440px;
    }
    .booking-card:hover {
        transform: translateY(-6px);
        border-color: rgba(224,48,48,0.3);
        box-shadow: 0 22px 40px rgba(0,0,0,0.35);
    }

    .card-banner { position: relative; width: 100%; height: 170px; }
    .card-banner img { width: 100%; height: 100%; object-fit: cover; }
    .price-tag {
        position: absolute; bottom: 15px; left: 15px;
        background: rgba(0,0,0,0.8);
        padding: 6px 14px; border-radius: 8px;
        font-weight: 700; font-size: 0.9rem;
        border: 1px solid rgba(255,255,255,0.1);
    }

    .card-content { padding: 1.4rem 1.6rem 1.6rem; flex-grow: 1; display: flex; flex-direction: column; }
    .card-content h3 { font-family: 'Bebas Neue', sans-serif; font-size: 1.85rem; margin-bottom: 0.9rem; color: #fff; }

    /* Specs Row */
    .card-specs { display: flex; gap: 0.9rem; flex-wrap: wrap; margin-bottom: 1.2rem; }
    .spec-pill { 
        background: rgba(255,255,255,0.05); 
        padding: 4px 10px; border-radius: 6px; 
        font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #aaa;
        display: flex; align-items: center; gap: 5px;
    }

    .rental-dates {
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        padding: 1rem; border-radius: 12px; margin-bottom: 1.8rem;
    }
    .date-row { display: flex; justify-content: space-between; font-size: 0.75rem; color: #666; margin-bottom: 0.4rem; font-weight: 500;}
    .date-val { color: #fff; font-weight: 600; font-size: 0.85rem; }

    .card-footer { 
        margin-top: auto; padding: 1.3rem 1.6rem; 
        border-top: 1px solid rgba(255,255,255,0.05);
        display: flex; justify-content: space-between; align-items: center; gap: 1rem;
        flex-wrap: wrap;
    }
    .footer-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center; }
    .btn-details {
        background: rgba(56,189,248,0.12);
        border: 1px solid rgba(56,189,248,0.35);
        color: #38bdf8;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        transition: all 0.25s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .btn-details:hover {
        background: rgba(56,189,248,0.95);
        color: #fff;
        transform: translateY(-1px);
        border-color: rgba(56,189,248,0.6);
    }
    
    .total-wrap { display: flex; flex-direction: column; }
    .total-label { font-size: 0.65rem; color: #555; text-transform: uppercase; font-weight: 800; }
    .total-val { font-size: 1.2rem; font-weight: 800; color: var(--red); }

    .empty-state { text-align: center; padding: 8rem 2rem; background: rgba(255,255,255,0.02); border-radius: 30px; border: 1px dashed rgba(255,255,255,0.1); }
    .empty-icon { font-size: 5rem; margin-bottom: 2rem; opacity: 0.2; }
    .empty-state h2 { font-family: 'Bebas Neue', sans-serif; font-size: 3.5rem; margin-bottom: 1rem; color: #fff; }
    
    .btn-cancel { 
        background: rgba(220,38,38,0.1); 
        border: 1px solid rgba(220,38,38,0.4); 
        color: #f87171; 
        padding: 10px 18px; 
        border-radius: 10px; 
        font-size: 0.7rem; font-weight: 800; 
        cursor: pointer; transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .btn-cancel:hover { background: #dc2626; color: #fff; transform: scale(1.05); box-shadow: 0 0 20px rgba(220,38,38,0.4); }

    .alert { padding: 1.2rem; border-radius: 12px; margin-bottom: 3rem; font-size: 0.95rem; display: flex; align-items: center; gap: 1rem; }
    .alert-success { background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2); }
    .alert-error { background: rgba(220,38,38,0.1); color: #f87171; border: 1px solid rgba(220,38,38,0.2); }

    /* Status Badge */
    .status-badge {
        position: absolute; top: 15px; right: 15px;
        padding: 8px 14px; border-radius: 8px;
        font-size: 0.75rem; font-weight: 900;
        text-transform: uppercase; letter-spacing: 0.08em;
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.1);
        z-index: 2;
    }
    .status-confirmed { background: rgba(34,197,94,0.2); color: #4ade80; border-color: rgba(34,197,94,0.3); }
    .status-cancelled { background: rgba(220,38,38,0.95); color: #fff; border-color: rgba(255,255,255,0.2); }
    .status-completed { background: rgba(59,130,246,0.2); color: #60a5fa; border-color: rgba(59,130,246,0.3); }
    .status-banner {
        margin-bottom: 1.5rem;
        padding: 1rem 1.2rem;
        border-radius: 14px;
        text-align: center;
        font-size: 0.9rem;
        font-weight: 900;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        border: 1px solid rgba(255,255,255,0.1);
    }
    .status-banner-confirmed {
        background: rgba(34,197,94,0.95);
        color: #fff;
        border-color: rgba(255,255,255,0.2);
    }
    .status-banner-cancelled {
        background: rgba(220,38,38,0.95);
        color: #fff;
        border-color: rgba(255,255,255,0.2);
    }
    .status-banner-completed {
        background: rgba(59,130,246,0.95);
        color: #fff;
        border-color: rgba(255,255,255,0.2);
    }
</style>

<div class="bookings-container">
    <h1 style="font-family:'Bebas Neue',sans-serif; font-size: 5rem; margin-bottom: 1rem; letter-spacing: 0.02em;">MY JOURNEYS</h1>
    <p style="color: #666; margin-bottom: 3.5rem; font-weight: 500;">History of your elite driving experiences</p>

    <?php if ($cancelSuccess): ?>
        <div class="alert alert-success">
            <span style="font-size:1.5rem">✓</span>
            <div><?= $cancelSuccess ?></div>
        </div>
    <?php endif; ?>
    <?php if ($cancelError): ?>
        <div class="alert alert-error">
            <span style="font-size:1.5rem">!</span>
            <div><?= $cancelError ?></div>
        </div>
    <?php endif; ?>

    <!-- Cancellation Policy Hub -->
    <div class="policy-box">
        <div class="policy-content">
            <h4 style="color: #f87171;">NO REFUND POLICY</h4>
            <p><strong>Important:</strong> All bookings are subject to our strict No Refund policy. Cancelling will permanently remove the record from your history.</p>
        </div>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏎️</div>
            <h2>NO RECENT DRIVES</h2>
            <p>Your garage is currently empty. Elevate your status today.</p>
            <a href="home_page.php" class="btn btn-red" style="padding: 1rem 3rem;">View Fleet</a>
        </div>
    <?php else: ?>
        <div class="bookings-grid">
            <?php foreach ($bookings as $b): ?>
                <div class="booking-card">
                    <div class="card-banner">
                        <?php
                          $img = $b['image_path'];
                          $src = (strpos($img, 'http') === 0) ? $img : '../../' . $img;
                        ?>
                        <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($b['model']) ?>" onerror="this.src='../../assets/images/car-placeholder.png'">
                        <div class="status-badge status-<?= $b['status'] ?>"><?= $b['status'] ?></div>
                        <div class="price-tag">NPR <?= number_format($b['price_per_day'], 0) ?> <span style="font-weight:400; font-size:0.7rem; opacity:0.6;">/ DAY</span></div>
                    </div>
                    
                    <div class="card-content">
                        <?php if ($b['status'] === 'confirmed'): ?>
                            <div class="status-banner status-banner-confirmed">CONFIRMED</div>
                        <?php elseif ($b['status'] === 'cancelled'): ?>
                            <div class="status-banner status-banner-cancelled">CANCELLED</div>
                        <?php elseif ($b['status'] === 'completed'): ?>
                            <div class="status-banner status-banner-completed">COMPLETED</div>
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($b['model']) ?></h3>
                        
                        <div class="card-specs">
                            <div class="spec-pill">⚙️ <?= htmlspecialchars($b['transmission']) ?></div>
                            <div class="spec-pill">⛽ <?= htmlspecialchars($b['fuel_type']) ?></div>
                            <div class="spec-pill">⚡ <?= htmlspecialchars($b['top_speed']) ?> KM/H</div>
                        </div>

                        <div class="rental-dates">
                            <div class="date-row">
                                <span>PICKUP</span>
                                <span class="date-val"><?= date('D, M d, Y', strtotime($b['start_date'])) ?></span>
                            </div>
                            <div class="date-row" style="margin-bottom:0.8rem">
                                <span>RETURN</span>
                                <span class="date-val"><?= date('D, M d, Y', strtotime($b['end_date'])) ?></span>
                            </div>
                            <div class="date-row" style="border-top: 1px solid rgba(255,255,255,0.05); padding-top:0.8rem">
                                <span>DURATION</span>
                                <span class="date-val" style="color:var(--red)"><?= $b['days'] ?> Days</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="total-wrap">
                            <span class="total-label">Total Investment</span>
                            <span class="total-val">NPR <?= number_format($b['total_price'], 0) ?></span>
                        </div>

                        <div class="footer-actions">
                            <a href="../vehicle/vehicle_detail.php?id=<?= $b['vehicle_id'] ?>" class="btn-details">View Details</a>
                            <?php 
                            // Policy change: Anyone can cancel now. No date check needed.
                            ?>
                            <?php if ($b['status'] === 'confirmed'): ?>
                            <form method="POST" onsubmit="return confirm('Wait! Are you sure you want to cancel this reservation? Note: No refunds are issued.')" style="margin:0;">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                <button type="submit" class="btn-cancel">CANCEL BOOKING</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
