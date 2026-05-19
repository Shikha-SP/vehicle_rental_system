<?php
/**
 * kharcha_qr_ajax.php
 * AJAX endpoint for Kharcha Dynamic QR Code payment flow.
 *
 * GET  ?action=create&discount_code=XXX  — create QR session
 * GET  ?action=status&session_id=XXX     — poll status
 * POST ?action=finalize                  — confirm & create booking
 */
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/kharcha.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$action = $_GET['action'] ?? '';

// ── CREATE QR SESSION ────────────────────────────────────────────
if ($action === 'create') {
    $vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
    $pickup_date = $_SESSION['payment_pickup'] ?? '';
    $dropoff_date = $_SESSION['payment_dropoff'] ?? '';
    $days = (int) ($_SESSION['payment_days'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if (!$vehicle_id) {
        echo json_encode(['success' => false, 'message' => 'No booking in progress.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT price_per_day, model FROM vehicles WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();

    if (!$vehicle) {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found.']);
        exit;
    }

    $totalprice = ((float) $vehicle['price_per_day'] * $days) + 500;

    if (($_SESSION['payment_source'] ?? '') === 'extend_booking') {
        $extend = $_SESSION['extend_payload'] ?? [];
        $totalprice = (float) ($extend['extra_cost'] ?? 0);
    }

    // ── Apply discount if provided ────────────────────────────────
    $discount_code = trim($_GET['discount_code'] ?? '');
    $discount_amount = 0.00;
    $discount_code_id = null;

    if (!empty($discount_code)) {
        $d_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
        $d_stmt->bind_param("s", $discount_code);
        $d_stmt->execute();
        $res = $d_stmt->get_result();

        if ($res->num_rows > 0) {
            $code_data = $res->fetch_assoc();
            $code_id = $code_data['id'];
            $valid = true;

            if ($code_data['expires_at'] && $code_data['expires_at'] < date('Y-m-d'))
                $valid = false;
            if ($code_data['max_uses'] !== null && $code_data['used_count'] >= $code_data['max_uses'])
                $valid = false;
            if ($code_data['owner_user_id'] !== null && (int) $code_data['owner_user_id'] !== (int) $user_id)
                $valid = false;

            if ($valid) {
                $chk = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
                $chk->bind_param("ii", $user_id, $code_id);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0)
                    $valid = false;
                $chk->close();
            }

            if ($valid) {
                $discount_amount = ($code_data['type'] === 'flat')
                    ? min($code_data['discount_flat'], $totalprice)
                    : ($totalprice * $code_data['discount_percent']) / 100;
                $totalprice -= $discount_amount;
                $discount_code_id = $code_id;
            }
        }
        $d_stmt->close();
    }

    // Store discount in session so finalize can use it
    $_SESSION['kharcha_qr_discount_code'] = $discount_code;
    $_SESSION['kharcha_qr_discount_amount'] = $discount_amount;
    $_SESSION['kharcha_qr_discount_code_id'] = $discount_code_id;

    $note = "TD Rentals – {$vehicle['model']} ({$pickup_date} to {$dropoff_date})";
    $result = kharchaCreateQRSession($totalprice, $note);

    if (!($result['success'] ?? false)) {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to create QR session.']);
        exit;
    }

    $_SESSION['kharcha_qr_session_id'] = $result['session_id'];
    $_SESSION['kharcha_qr_amount'] = $totalprice;

    echo json_encode([
        'success' => true,
        'session_id' => $result['session_id'],
        'qr_payload' => $result['qr_payload'],
        'amount' => $totalprice,
        'expires_in' => 300,
    ]);
    exit;
}

// ── POLL STATUS ───────────────────────────────────────────────────
if ($action === 'status') {
    $session_id = $_GET['session_id'] ?? ($_SESSION['kharcha_qr_session_id'] ?? '');
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'No session_id.']);
        exit;
    }
    echo json_encode(kharchaGetQRSessionStatus($session_id));
    exit;
}

// ── FINALIZE BOOKING ──────────────────────────────────────────────
if ($action === 'finalize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'] ?? ($_SESSION['kharcha_qr_session_id'] ?? '');
    if (!$session_id) {
        echo json_encode(['success' => false, 'message' => 'No session_id.']);
        exit;
    }

    $statusResult = kharchaGetQRSessionStatus($session_id);
    $status = $statusResult['status'] ?? ($statusResult['session']['status'] ?? 'unknown');
    if ($status !== 'success') {
        echo json_encode(['success' => false, 'message' => 'Payment not yet confirmed. Status: ' . $status]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
    $pickup_date = $_SESSION['payment_pickup'] ?? '';
    $dropoff_date = $_SESSION['payment_dropoff'] ?? '';
    $days = (int) ($_SESSION['payment_days'] ?? 0);

    $discount_code = $_SESSION['kharcha_qr_discount_code'] ?? '';
    $discount_amount = (float) ($_SESSION['kharcha_qr_discount_amount'] ?? 0);
    $discount_code_id = $_SESSION['kharcha_qr_discount_code_id'] ?? null;
    $is_extension = (($_SESSION['payment_source'] ?? '') === 'extend_booking');
    $extend = $_SESSION['extend_payload'] ?? [];

    if (!$vehicle_id) {
        echo json_encode(['success' => false, 'message' => 'No booking in progress.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT v.*, u.first_name AS owner_name FROM vehicles v JOIN users u ON v.user_id = u.id WHERE v.id = ? LIMIT 1");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $vehicle = $stmt->get_result()->fetch_assoc();

    $totalprice = $_SESSION['kharcha_qr_amount'] ?? ($is_extension ? (float) ($extend['extra_cost'] ?? 0) : (((float) $vehicle['price_per_day'] * $days) + 500));
    $transaction_ref = $session_id;

    $conn->begin_transaction();

    try {
        if ($is_extension) {
            $booking_id = (int) ($extend['booking_id'] ?? 0);
            if (!$booking_id) {
                throw new Exception('Missing extension booking.');
            }

            $upd = $conn->prepare("UPDATE bookings
                                   SET end_date = ?, total_price = total_price + ?, payment_status = 'paid',
                                       discount_amount = discount_amount + ?,
                                       discount_code = COALESCE(NULLIF(?, ''), discount_code)
                                   WHERE id = ? AND user_id = ?");
            $upd->bind_param("sddsii", $dropoff_date, $totalprice, $discount_amount, $discount_code, $booking_id, $user_id);
            if (!$upd->execute() || $upd->affected_rows < 1) {
                throw new Exception('Failed to extend booking.');
            }
            $upd->close();
        } else {
            $ins = $conn->prepare("INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, payment_status, discount_code, discount_amount, created_at) VALUES (?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, NOW())");
            $ins->bind_param("iissdsd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $totalprice, $discount_code, $discount_amount);

            if (!$ins->execute()) {
                throw new Exception('Failed to create booking.');
            }

            $booking_id = $ins->insert_id;
        }

        $txn = $conn->prepare("INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at) VALUES (?, ?, ?, 'kharcha_qr', 'QR', 'Kharcha QR', ?, NOW())");
        $txn->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
        if (!$txn->execute()) {
            throw new Exception('Failed to record transaction.');
        }

        // ── Discount usage + Gold reset ───────────────────────────────
        if ($discount_code_id) {
            $du = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
            $du->bind_param("ii", $user_id, $discount_code_id);
            $du->execute();
            $du->close();
            $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");
            $gc = $conn->query("SELECT id FROM discount_codes WHERE id = $discount_code_id AND owner_user_id = $user_id AND discount_percent = 20 LIMIT 1");
            if ($gc && $gc->num_rows > 0) {
                $conn->query("UPDATE users SET medal = 'BRONZE', completed_rentals = 3 WHERE id = $user_id");
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Kharcha QR booking update failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Payment received but booking update failed. Please contact support.']);
        exit;
    }

    // ── Milestone progression ─────────────────────────────────────
    $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
    $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
    if ($res && $res->num_rows > 0) {
        $u = $res->fetch_assoc();
        $r = (int) $u['completed_rentals'];
        $medal = $u['medal'];
        $nm = $medal;
        $mc = '';
        $mp = 0;
        if ($r >= 3 && $medal === 'NONE') {
            $nm = 'BRONZE';
            $mc = 'BRONZE5';
            $mp = 5;
        } elseif ($r >= 7 && $medal === 'BRONZE') {
            $nm = 'SILVER';
            $mc = 'SILVER10';
            $mp = 10;
        } elseif ($r >= 15 && $medal === 'SILVER') {
            $nm = 'GOLD';
            $mc = 'GOLD20';
            $mp = 20;
        }
        if ($nm !== $medal) {
            $conn->query("UPDATE users SET medal = '$nm' WHERE id = $user_id");
            if ($mc !== '') {
                $sfx = substr(md5(uniqid($user_id, true)), 0, 4);
                $pcode = $mc . "-U" . $user_id . "-" . $sfx;
                $conn->query("INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, owner_user_id) VALUES ('$pcode','percent',$mp,0,1,$user_id)");
            }
        }
    }

    if ($is_extension) {
        sendBookingExtensionEmail($conn, $user_id, $vehicle, $dropoff_date, $totalprice, 'Kharcha QR');
    } else {
        // ── Email ─────────────────────────────────────────────────────
        $u_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
        $u_stmt->bind_param("i", $user_id);
        $u_stmt->execute();
        $ud = $u_stmt->get_result()->fetch_assoc();
        $email = $ud['email'] ?? '';
        $first_name = $ud['first_name'] ?? '';
        $last_name = $ud['last_name'] ?? '';

        try {
            $invoice_data = [
                'booking_id' => $booking_id,
                'first_name' => $first_name,
                'email' => $email,
                'model' => $vehicle['model'],
                'pickup_date' => $pickup_date,
                'dropoff_date' => $dropoff_date,
                'days' => $days,
                'price_per_day' => $vehicle['price_per_day'],
                'total_price' => $totalprice,
                'discount_code' => $discount_code,
                'discount_amount' => $discount_amount,
            ];
            $pdf_string = generateInvoicePDF($invoice_data);

	            // send payment confimation mail
	            if (isNotificationEnabled($conn, $user_id)) {
	                $AltBody = "Hi {$first_name} {$last_name}. Your payment has been confirmed. Thank you for choosing TD Rentals.";
	                $payment_vehicle_image_src = 'cid:vehicle_image';
	                $html = require '../../includes/payment_confirmation.php';
	                sendEmail(
	                    $email,
	                    $first_name,
	                    'Payment Confirmed!',
	                    $html,
	                    $AltBody,
	                    [
	                        'embedded_images' => [[
	                            'path' => getVehicleEmailImagePath($vehicle),
	                            'cid' => 'vehicle_image',
	                            'name' => 'vehicle-image'
	                        ]]
	                    ]
	                );
	            }
        } catch (Exception $e) {
            error_log("Kharcha QR email failed: " . $e->getMessage());
        }
    }

    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
        $_SESSION['payment_source'],
        $_SESSION['extend_payload'],
        $_SESSION['kharcha_qr_session_id'],
        $_SESSION['kharcha_qr_amount'],
        $_SESSION['kharcha_qr_discount_code'],
        $_SESSION['kharcha_qr_discount_amount'],
        $_SESSION['kharcha_qr_discount_code_id']
    );

    echo json_encode(['success' => true, 'booking_id' => $booking_id]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
