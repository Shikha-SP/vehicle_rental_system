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

        // Verify booking belongs to this user and is still confirmed (not already cancelled/completed)
        $d_stmt = $conn->prepare("SELECT id, status FROM bookings WHERE id = ? AND user_id = ? AND status = 'confirmed'");
        $d_stmt->bind_param("ii", $bookingId, $user_id);
        $d_stmt->execute();
        $res = $d_stmt->get_result()->fetch_assoc();

        if ($res) {
            // Mark booking as cancelled
            $del_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $del_stmt->bind_param("ii", $bookingId, $user_id);

            if ($del_stmt->execute()) {

                // ── DECREMENT RENTAL COUNT & RE-EVALUATE MEDAL ───────────────
                // Capture BEFORE state first
                $before = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id")->fetch_assoc();
                $rentals_before = (int)$before['completed_rentals'];
                $medal_before   = $before['medal'];

                // Decrement by 1 (never below 0)
                $conn->query("UPDATE users SET completed_rentals = GREATEST(0, completed_rentals - 1) WHERE id = $user_id");

                // Re-fetch AFTER state
                $after = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id")->fetch_assoc();
                $rentals_after = (int)$after['completed_rentals'];

                // Determine correct medal for new count
                if ($rentals_after >= 15)     $correct_medal = 'GOLD';
                elseif ($rentals_after >= 7)  $correct_medal = 'SILVER';
                elseif ($rentals_after >= 3)  $correct_medal = 'BRONZE';
                else                          $correct_medal = 'NONE';

                // Only downgrade medal (never upgrade on cancel)
                $medal_rank = ['NONE' => 0, 'BRONZE' => 1, 'SILVER' => 2, 'GOLD' => 3];
                $medal_changed = false;
                if ($medal_rank[$correct_medal] < $medal_rank[$medal_before]) {
                    $conn->query("UPDATE users SET medal = '$correct_medal' WHERE id = $user_id");
                    $medal_changed = true;
                } else {
                    $correct_medal = $medal_before; // no change
                }
                // ─────────────────────────────────────────────────────────────

                // Build visible success message with point change info
                $cancelSuccess = "Booking #{$bookingId} cancelled. ";
                if ($rentals_before > 0) {
                    $cancelSuccess .= "Rental points: <strong>{$rentals_before} → {$rentals_after}</strong>. ";
                }
                if ($medal_changed) {
                    $medal_labels = ['NONE' => 'No Medal', 'BRONZE' => '🥉 Bronze', 'SILVER' => '🥈 Silver', 'GOLD' => '🥇 Gold'];
                    $cancelSuccess .= "Medal downgraded: {$medal_labels[$medal_before]} → <strong>{$medal_labels[$correct_medal]}</strong>.";
                }

            } else {
                $cancelError = "Failed to cancel booking.";
            }
        } else {
            $cancelError = "Booking not found, already cancelled, or you do not have permission.";
        }
    } else {
        $cancelError = "Security token mismatch. Please refresh the page and try again.";
    }
}

// Ensure a single reusable CSRF token for all cancel buttons
$csrfToken = $_SESSION['csrf_token'] ?? generateCsrfToken();

// Fetch User Medal & Rentals
$u_stmt = $conn->prepare("SELECT medal, completed_rentals FROM users WHERE id = ?");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result()->fetch_assoc();
$user_medal    = $u_res['medal'] ?? 'NONE';
$user_rentals  = (int)($u_res['completed_rentals'] ?? 0);
$u_stmt->close();

// Medal tier definitions (must match paymentdetail.php thresholds)
$medal_tiers = [
    'BRONZE' => ['rentals' => 3,  'discount' => 5,  'icon' => '🥉', 'color' => '#cd7f32', 'label' => 'Bronze'],
    'SILVER' => ['rentals' => 7,  'discount' => 10, 'icon' => '🥈', 'color' => '#aaaaaa', 'label' => 'Silver'],
    'GOLD'   => ['rentals' => 15, 'discount' => 20, 'icon' => '🥇', 'color' => '#ffd700', 'label' => 'Gold'],
];

// Progress calculation
$next_threshold = 3; // default to Bronze
$next_label = 'Bronze';
if ($user_medal === 'NONE')   { $next_threshold = 3;  $next_label = 'Bronze'; }
elseif ($user_medal === 'BRONZE') { $next_threshold = 7;  $next_label = 'Silver'; }
elseif ($user_medal === 'SILVER') { $next_threshold = 15; $next_label = 'Gold'; }
elseif ($user_medal === 'GOLD')   { $next_threshold = 15; $next_label = 'Gold'; } // maxed

$prev_threshold = 0;
if ($user_medal === 'BRONZE') $prev_threshold = 3;
elseif ($user_medal === 'SILVER') $prev_threshold = 7;
elseif ($user_medal === 'GOLD') $prev_threshold = 15;

$user_reward = match($user_medal) {
    'BRONZE' => 5,
    'SILVER' => 10,
    'GOLD'   => 20,
    default  => 0
};

// Fetch Bookings with more vehicle details
// Exclude 'pending' payment bookings — these are pre-created placeholders
// that get updated to 'paid' on successful callback. Showing them would
// cause confirmed bookings to falsely appear as "PENDING".
$sql = "SELECT b.*, v.model, v.image_path, v.transmission, v.fuel_type, v.top_speed, v.price_per_day,
               DATEDIFF(b.end_date, b.start_date) AS days
        FROM bookings b
        JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.user_id = ? AND b.payment_status != 'pending'
        ORDER BY b.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'My Bookings – TD Rentals';
include '../../includes/header.php';
?>

<link rel="stylesheet" href="../../assets/css/chat.css">
<style>
    .btn-chat {
        background: rgba(46,204,113,0.12);
        border: 1px solid rgba(46,204,113,0.35);
        color: #2ecc71;
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
        cursor: pointer;
    }
    .btn-chat:hover {
        background: rgba(46,204,113,0.95);
        color: #fff;
        transform: translateY(-1px);
    }
    .bookings-container { 
        max-width: 1440px; 
        margin: 0 auto; 
        padding: 80px 24px; 
        min-height: 80vh; 
        width: 100%; 
    }
    
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
    .policy-content h4 { font-family: 'Inter', sans-serif; font-weight: 700; font-size: 1.2rem; margin-bottom: 0.2rem; letter-spacing: 0.02em; }

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
    .card-content h3 { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 1.5rem; margin-bottom: 0.9rem; color: #fff; }


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
    .empty-state h2 { font-family: 'Inter', sans-serif; font-weight: 800; font-size: 2.5rem; margin-bottom: 1rem; color: #fff; }

    
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
    
    .medal-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-family: 'Inter', sans-serif;
        font-weight: 800;
        font-size: 1.2rem;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-left: 20px;
        vertical-align: middle;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
    }
    .medal-BRONZE { background: linear-gradient(135deg, #cd7f32, #8b5a2b); color: #fff; }
    .medal-SILVER { background: linear-gradient(135deg, #e0e0e0, #9e9e9e); color: #222; }
    .medal-GOLD { background: linear-gradient(135deg, #ffd700, #b8860b); color: #222; }

    .savings-badge {
        background: rgba(46, 204, 113, 0.15);
        color: #4ade80;
        border: 1px solid rgba(46, 204, 113, 0.3);
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 0.75rem;
        font-weight: 800;
        margin-bottom: 1rem;
        display: inline-block;
        letter-spacing: 0.05em;
    }

    /* Medal Progress Panel */
    .medal-panel {
        background: rgba(20,20,20,0.8);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 20px;
        padding: 28px 32px;
        margin-bottom: 3rem;
    }

    .medal-tier-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 28px;
    }

    .medal-tier-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 14px;
        padding: 20px 16px;
        text-align: center;
        transition: border-color 0.2s;
    }
    .medal-tier-card.active-tier {
        border-color: rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.06);
    }
    .tier-icon   { font-size: 2rem; display: block; margin-bottom: 8px; }
    .tier-name   { font-size: 0.95rem; font-weight: 700; margin-bottom: 4px; }
    .tier-sub    { font-size: 0.75rem; color: #666; margin-bottom: 4px; }
    .tier-discount { font-size: 0.85rem; font-weight: 600; }

    .medal-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 24px;
    }
    .stat-box {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.06);
        border-radius: 12px;
        padding: 18px 12px;
        text-align: center;
    }
    .stat-val   { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; }
    .stat-label { font-size: 0.65rem; color: #555; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; }

    .progress-section { margin-top: 4px; }
    .progress-header  { display: flex; justify-content: space-between; font-size: 0.8rem; color: #888; margin-bottom: 8px; }
    .progress-track   { background: #222; border-radius: 100px; height: 6px; overflow: hidden; margin-bottom: 8px; }
    .progress-fill    { height: 100%; background: linear-gradient(90deg, #e03030, #ff6b6b); border-radius: 100px; transition: width 0.5s ease; }
    .progress-ticks   { display: flex; justify-content: space-between; font-size: 0.65rem; color: #555; }
</style>
<link rel="stylesheet" href="../../assets/css/style.css">

<section class="page-hero">
    <div class="page-hero-content">
        <h1>MY BOOKINGS</h1>
        <?php if ($user_medal !== 'NONE'): ?>
            <div style="margin-top: 10px; margin-bottom: 20px;">
                <span class="medal-badge medal-<?= $user_medal ?>" style="font-size: 0.7rem; letter-spacing: 0.12em; padding: 6px 16px; font-weight: 800; border-radius: 4px;"> <?= $user_medal ?> MEMBER </span>
            </div>
        <?php endif; ?>
        <p>A high-performance chronicle of your elite driving experiences</p>
    </div>
</section>

<div class="bookings-container">

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

    <!-- Medal Progress Panel -->
    <div class="medal-panel">
        <!-- Medal Tier Cards -->
        <div class="medal-tier-grid">
            <?php foreach ($medal_tiers as $key => $tier): ?>
            <div class="medal-tier-card <?= ($user_medal === $key) ? 'active-tier' : '' ?>">
                <span class="tier-icon"><?= $tier['icon'] ?></span>
                <div class="tier-name" style="color: <?= $tier['color'] ?>"><?= $tier['label'] ?></div>
                <div class="tier-sub"><?= $tier['rentals'] ?> rentals</div>
                <div class="tier-discount" style="color: <?= $tier['color'] ?>"><?= $tier['discount'] ?>% off</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Stats Row -->
        <div class="medal-stats">
            <div class="stat-box">
                <div class="stat-val" style="color: #e03030"><?= $user_rentals ?></div>
                <div class="stat-label">RENTALS DONE</div>
            </div>
            <div class="stat-box">
                <div class="stat-val" style="color: <?= $user_medal !== 'NONE' ? ($medal_tiers[$user_medal]['color'] ?? '#e03030') : '#555' ?>">
                    <?= $user_medal !== 'NONE' ? $medal_tiers[$user_medal]['label'] : '—' ?>
                </div>
                <div class="stat-label">MEDAL</div>
            </div>
            <div class="stat-box">
                <div class="stat-val" style="color: #e03030"><?= $user_reward ?>%</div>
                <div class="stat-label">REWARD</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <?php if ($user_medal !== 'GOLD'): ?>
        <div class="progress-section">
            <div class="progress-header">
                <span>Progress to <?= $next_label ?></span>
                <span><?= $user_rentals ?>/<?= $next_threshold ?></span>
            </div>
            <div class="progress-track">
                <?php
                $pct = min(100, ($user_rentals / $next_threshold) * 100);
                ?>
                <div class="progress-fill" style="width: <?= $pct ?>%"></div>
            </div>
            <div class="progress-ticks">
                <span>0</span>
                <span>Bronze <?= $medal_tiers['BRONZE']['rentals'] ?></span>
                <span>Silver <?= $medal_tiers['SILVER']['rentals'] ?></span>
                <span>Gold <?= $medal_tiers['GOLD']['rentals'] ?></span>
            </div>
        </div>
        <?php else: ?>
        <div style="text-align:center; color: #ffd700; font-weight: 700; padding: 12px 0;">🏆 You have reached Gold status!</div>
        <?php endif; ?>
    </div>

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
                <div class="booking-card" data-booking-id="<?= $b['id'] ?>">
                    <div class="card-banner">
                        <?php
                          $img = $b['image_path'];
                          $src = (strpos($img, 'http') === 0) ? $img : '../../' . $img;
                        ?>
                        <img src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($b['model']) ?>" onerror="this.src='../../assets/images/car-placeholder.png'">
                        <div class="status-badge status-<?= $b['status'] ?>"><?= $b['status'] ?></div>
                        <div class="status-badge payment-status-<?= $b['payment_status'] ?>" style="right: auto; left: 15px; top: 15px;
                            <?php
                                if ($b['payment_status'] === 'paid') echo 'background: rgba(34,197,94,0.9); color: white;';
                                elseif ($b['payment_status'] === 'failed') echo 'background: rgba(220,38,38,0.9); color: white;';
                                else echo 'background: rgba(245,158,11,0.9); color: white;';
                            ?>">
                            <?= strtoupper($b['payment_status']) ?>
                        </div>
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
                        
                        <?php if (isset($b['discount_amount']) && $b['discount_amount'] > 0): ?>
                            <div class="savings-badge">
                                Saved NPR <?= number_format($b['discount_amount'], 2) ?> with <?= htmlspecialchars($b['discount_code']) ?>
                            </div>
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
                            <button type="button" class="btn-chat open-chat-btn" data-booking-id="<?= $b['id'] ?>" data-model="<?= htmlspecialchars($b['model']) ?>">Chat with Admin</button>
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
                            <?php elseif ($b['status'] === 'cancelled'): ?>
                                <button type="button" onclick="dismissBooking(<?= $b['id'] ?>, this)" class="btn-cancel" style="background: transparent; border: 1px solid rgba(255,255,255,0.1); color: #888;">DISMISS FROM VIEW</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>

<!-- Chat Modal -->
<div class="chat-modal-overlay" id="chatModal">
    <div class="chat-window">
        <div class="chat-header">
            <h3>Chat: <span id="chatBookingModel">Booking</span></h3>
            <button class="close-chat" id="closeChat">&times;</button>
        </div>
        <div class="chat-messages" id="chatMessages">
            <!-- Messages load here -->
        </div>
        <div class="chat-input-area">
            <input type="text" id="chatInput" placeholder="Type your message...">
            <button class="btn-send-msg" id="sendMessage">Send</button>
        </div>
    </div>
</div>

<script>
let currentBookingId = null;
let chatInterval = null;

function openChat(bookingId, model) {
    currentBookingId = bookingId;
    document.getElementById('chatBookingModel').innerText = model;
    document.getElementById('chatModal').classList.add('active');
    loadMessages();
    
    // Start polling
    if (chatInterval) clearInterval(chatInterval);
    chatInterval = setInterval(loadMessages, 3000);
}

function loadMessages() {
    if (!currentBookingId) return;
    fetch(`../api/messages_action.php?action=get&booking_id=${currentBookingId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('chatMessages');
                const atBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 50;
                
                container.innerHTML = data.messages.map(m => `
                    <div class="message ${m.sender_is_admin ? 'received' : 'sent'}">
                        ${m.message}
                        <span class="message-time">${new Date(m.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</span>
                    </div>
                `).join('');
                
                if (atBottom) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        });
}

document.getElementById('sendMessage').addEventListener('click', () => {
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg || !currentBookingId) return;
    
    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('booking_id', currentBookingId);
    formData.append('message', msg);
    
    fetch('../api/messages_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            loadMessages();
        }
    });
});

document.getElementById('closeChat').addEventListener('click', () => {
    document.getElementById('chatModal').classList.remove('active');
    clearInterval(chatInterval);
    currentBookingId = null;
});

document.querySelectorAll('.open-chat-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        openChat(btn.dataset.bookingId, btn.dataset.model);
    });
});

function dismissBooking(id, btn) {
    let dismissed = JSON.parse(localStorage.getItem('dismissedBookings')) || [];
    if (!dismissed.includes(id)) {
        dismissed.push(id);
        localStorage.setItem('dismissedBookings', JSON.stringify(dismissed));
    }
    btn.closest('.booking-card').style.display = 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    let dismissed = JSON.parse(localStorage.getItem('dismissedBookings')) || [];
    document.querySelectorAll('.booking-card').forEach(card => {
        let bookingId = card.getAttribute('data-booking-id');
        if (bookingId && dismissed.includes(parseInt(bookingId))) {
            card.style.display = 'none';
        }
    });
});
</script>
