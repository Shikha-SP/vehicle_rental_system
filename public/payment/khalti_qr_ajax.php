<?php
/**
 * khalti_qr_ajax.php
 * AJAX endpoint for Khalti QR Code payment flow.
 *
 * GET  ?action=create&discount_code=XXX  — initiate Khalti payment, return payment_url as QR payload
 * GET  ?action=status&pidx=XXX           — poll Khalti lookup, return status
 * POST ?action=finalize                  — mark booking paid
 */
header('Content-Type: application/json');

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';
$khalti_config = require_once '../../config/khalti.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

$action  = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

// ── CREATE (Khalti initiate → payment_url becomes QR payload) ────
if ($action === 'create') {
    $vehicle_id   = $_SESSION['payment_vehicle_id'] ?? 0;
    $pickup_date  = $_SESSION['payment_pickup']     ?? '';
    $dropoff_date = $_SESSION['payment_dropoff']    ?? '';
    $days         = (int)($_SESSION['payment_days'] ?? 0);

    if (!$vehicle_id) {
        echo json_encode(['success' => false, 'message' => 'No booking in progress.']);
        exit;
    }

    // Fetch vehicle + user
    $sql = "SELECT v.model, v.price_per_day, u.email, u.first_name, u.phone_number
            FROM vehicles v JOIN users u ON u.id = ?
            WHERE v.id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $vehicle_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Vehicle/user not found.']);
        exit;
    }

    $totalprice = ((float)$data['price_per_day'] * $days) + 500;

    $is_extension = (($_SESSION['payment_source'] ?? '') === 'extend_booking');
    $extend = $_SESSION['extend_payload'] ?? [];
    if ($is_extension) {
        $totalprice = (float)($extend['extra_cost'] ?? 0);
    }

    // ── Apply discount ────────────────────────────────────────────
    $discount_code    = trim($_GET['discount_code'] ?? '');
    $discount_amount  = 0.00;
    $discount_code_id = null;

    if (!empty($discount_code)) {
        $d_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
        $d_stmt->bind_param("s", $discount_code);
        $d_stmt->execute();
        $res = $d_stmt->get_result();

        if ($res->num_rows > 0) {
            $cd    = $res->fetch_assoc();
            $cid   = $cd['id'];
            $valid = true;

            if ($cd['expires_at'] && $cd['expires_at'] < date('Y-m-d'))                              $valid = false;
            if ($cd['max_uses'] !== null && $cd['used_count'] >= $cd['max_uses'])                    $valid = false;
            if ($cd['owner_user_id'] !== null && (int)$cd['owner_user_id'] !== (int)$user_id)        $valid = false;

            if ($valid) {
                $chk = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
                $chk->bind_param("ii", $user_id, $cid);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) $valid = false;
                $chk->close();
            }

            if ($valid) {
                $discount_amount = ($cd['type'] === 'flat')
                    ? min($cd['discount_flat'], $totalprice)
                    : ($totalprice * $cd['discount_percent']) / 100;
                $totalprice      -= $discount_amount;
                $discount_code_id = $cid;
            }
        }
        $d_stmt->close();
    }

    // Store discount in session for finalize
    $_SESSION['khalti_qr_discount_code']    = $discount_code;
    $_SESSION['khalti_qr_discount_amount']  = $discount_amount;
    $_SESSION['khalti_qr_discount_code_id'] = $discount_code_id;
    $_SESSION['khalti_qr_amount']           = $totalprice;

    $amount_paisa      = (int)($totalprice * 100);
    $purchase_order_id = "KHALTI-QR-" . $user_id . "-" . $vehicle_id . "-" . time();

    if ($is_extension) {
        $booking_id = (int)($extend['booking_id'] ?? 0);
        if (!$booking_id) {
            echo json_encode(['success' => false, 'message' => 'Missing extension booking.']);
            exit;
        }
    } else {
        // Pre-create pending booking for normal bookings only.
        $ins = $conn->prepare("INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, payment_status, purchase_order_id, discount_code, discount_amount, created_at)
                               VALUES (?, ?, ?, ?, ?, 'confirmed', 'pending', ?, ?, ?, NOW())");
        $ins->bind_param("iissdssd", $user_id, $vehicle_id, $pickup_date, $dropoff_date,
                         $totalprice, $purchase_order_id, $discount_code, $discount_amount);

        if (!$ins->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to create pending booking.']);
            exit;
        }
        $booking_id = $ins->insert_id;
    }
    $_SESSION['khalti_qr_booking_id']         = $booking_id;
    $_SESSION['khalti_qr_purchase_order_id']  = $purchase_order_id;

    // Call Khalti initiate
    $post_data = [
        'return_url'           => $khalti_config['return_url'],
        'website_url'          => $khalti_config['website_url'],
        'amount'               => $amount_paisa,
        'purchase_order_id'    => $purchase_order_id,
        'purchase_order_name'  => "TD Rentals – " . $data['model'],
        'customer_info'        => [
            'name'  => $data['first_name']    ?? 'User',
            'email' => $data['email']         ?? 'user@tdrentals.com',
            'phone' => $data['phone_number']  ?? '9800000000',
        ],
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $khalti_config['base_url'] . 'epayment/initiate/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode($post_data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Key ' . $khalti_config['secret_key'],
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    $err      = curl_error($curl);
    curl_close($curl);

    if ($err) {
        echo json_encode(['success' => false, 'message' => 'cURL error: ' . $err]);
        exit;
    }

    $rd = json_decode($response, true);

    if (!isset($rd['payment_url'])) {
        echo json_encode(['success' => false, 'message' => $rd['detail'] ?? 'Khalti initiate failed.']);
        exit;
    }

    $_SESSION['khalti_qr_pidx'] = $rd['pidx'];

    echo json_encode([
        'success'    => true,
        'pidx'       => $rd['pidx'],
        'qr_payload' => $rd['payment_url'],  // The Khalti app can scan this URL
        'amount'     => $totalprice,
        'expires_in' => 300,
    ]);
    exit;
}

// ── POLL STATUS ───────────────────────────────────────────────────
if ($action === 'status') {
    $pidx = $_GET['pidx'] ?? ($_SESSION['khalti_qr_pidx'] ?? '');

    if (!$pidx) {
        echo json_encode(['success' => false, 'message' => 'No pidx.']);
        exit;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $khalti_config['base_url'] . 'epayment/lookup/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode(['pidx' => $pidx]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Key ' . $khalti_config['secret_key'],
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $rd = json_decode($response, true);

    // Normalise to same shape the JS poller expects
    $khalti_status = strtolower($rd['status'] ?? 'pending');
    $status_map    = ['completed' => 'success', 'pending' => 'pending', 'expired' => 'expired', 'failed' => 'failed'];
    $normalized    = $status_map[$khalti_status] ?? 'pending';

    echo json_encode(['success' => true, 'status' => $normalized, 'raw' => $rd]);
    exit;
}

// ── FINALIZE BOOKING ──────────────────────────────────────────────
if ($action === 'finalize' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pidx       = $_POST['pidx']       ?? ($_SESSION['khalti_qr_pidx'] ?? '');
    $booking_id = (int)($_SESSION['khalti_qr_booking_id'] ?? 0);

    if (!$pidx || !$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing pidx or booking_id.']);
        exit;
    }

    // Verify with Khalti
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL            => $khalti_config['base_url'] . 'epayment/lookup/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => json_encode(['pidx' => $pidx]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Key ' . $khalti_config['secret_key'],
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($curl);
    curl_close($curl);
    $rd = json_decode($response, true);

    if (strtolower($rd['status'] ?? '') !== 'completed') {
        echo json_encode(['success' => false, 'message' => 'Khalti payment not completed. Status: ' . ($rd['status'] ?? 'unknown')]);
        exit;
    }

    $totalprice       = (float)($_SESSION['khalti_qr_amount']           ?? 0);
    $discount_code    = $_SESSION['khalti_qr_discount_code']            ?? '';
    $discount_amount  = (float)($_SESSION['khalti_qr_discount_amount']  ?? 0);
    $discount_code_id = $_SESSION['khalti_qr_discount_code_id']         ?? null;
    $is_extension     = (($_SESSION['payment_source'] ?? '') === 'extend_booking');
    $extend           = $_SESSION['extend_payload'] ?? [];
    $vehicle_id       = $_SESSION['payment_vehicle_id']                 ?? 0;
    $pickup_date      = $_SESSION['payment_pickup']                     ?? '';
    $dropoff_date     = $_SESSION['payment_dropoff']                    ?? '';
    $days             = (int)($_SESSION['payment_days']                 ?? 0);

    $transaction_ref = $rd['transaction_id'] ?? ('KH-QR-' . strtoupper(bin2hex(random_bytes(8))));

    $conn->begin_transaction();

    try {
        if ($is_extension) {
            $upd = $conn->prepare("UPDATE bookings
                                   SET end_date = ?, total_price = total_price + ?, payment_status = 'paid',
                                       discount_amount = discount_amount + ?,
                                       discount_code = COALESCE(NULLIF(?, ''), discount_code)
                                   WHERE id = ? AND user_id = ?");
            $upd->bind_param("sddsii", $dropoff_date, $totalprice, $discount_amount, $discount_code, $booking_id, $user_id);
        } else {
            $upd = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
            $upd->bind_param("i", $booking_id);
        }

        if (!$upd->execute() || $upd->affected_rows < 1) {
            throw new Exception('Failed to confirm booking.');
        }
        $upd->close();

        $txn = $conn->prepare("INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at) VALUES (?, ?, ?, 'khalti_qr', 'KQRR', 'Khalti QR', ?, NOW())");
        $txn->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
        if (!$txn->execute()) {
            throw new Exception('Failed to record transaction.');
        }

        // ── Discount usage + Gold reset ───────────────────────────────
        if ($discount_code_id) {
            $du = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
            $du->bind_param("ii", $user_id, $discount_code_id);
            $du->execute(); $du->close();
            $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");
            $gc = $conn->query("SELECT id FROM discount_codes WHERE id = $discount_code_id AND owner_user_id = $user_id AND discount_percent = 20 LIMIT 1");
            if ($gc && $gc->num_rows > 0) {
                $conn->query("UPDATE users SET medal = 'BRONZE', completed_rentals = 3 WHERE id = $user_id");
            }
        }
        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Khalti QR booking update failed: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Payment received but booking update failed. Please contact support.']);
        exit;
    }

    // ── Milestone progression ─────────────────────────────────────
    $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
    $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
    if ($res && $res->num_rows > 0) {
        $u = $res->fetch_assoc(); $r = (int)$u['completed_rentals']; $medal = $u['medal'];
        $nm=''; $mc=''; $mp=0;
        if ($r >= 3  && $medal==='NONE')   { $nm='BRONZE'; $mc='BRONZE5';  $mp=5;  }
        elseif ($r >= 7  && $medal==='BRONZE') { $nm='SILVER'; $mc='SILVER10'; $mp=10; }
        elseif ($r >= 15 && $medal==='SILVER') { $nm='GOLD';   $mc='GOLD20';   $mp=20; }
        if ($nm && $nm !== $medal) {
            $conn->query("UPDATE users SET medal = '$nm' WHERE id = $user_id");
            if ($mc) {
                $sfx = substr(md5(uniqid($user_id,true)),0,4);
                $pc  = $mc."-U".$user_id."-".$sfx;
                $conn->query("INSERT INTO discount_codes (code,type,discount_percent,discount_flat,max_uses,owner_user_id) VALUES ('$pc','percent',$mp,0,1,$user_id)");
            }
        }
    }

    // ── Email ─────────────────────────────────────────────────────
    $v_stmt = $conn->prepare("SELECT model, price_per_day FROM vehicles WHERE id = ?");
    $v_stmt->bind_param("i", $vehicle_id);
    $v_stmt->execute();
    $vehicle = $v_stmt->get_result()->fetch_assoc();

    $u_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $u_stmt->bind_param("i", $user_id);
    $u_stmt->execute();
    $ud = $u_stmt->get_result()->fetch_assoc();

    try {
        $invoice_data = [
            'booking_id'      => $booking_id,
            'first_name'      => $ud['first_name'],
            'email'           => $ud['email'],
            'model'           => $vehicle['model'],
            'pickup_date'     => $pickup_date,
            'dropoff_date'    => $dropoff_date,
            'days'            => $days,
            'price_per_day'   => $vehicle['price_per_day'],
            'total_price'     => $totalprice,
            'discount_code'   => $discount_code,
            'discount_amount' => $discount_amount,
        ];
        $pdf_string = generateInvoicePDF($invoice_data);

        $savings_line = $discount_amount > 0
            ? "<p style='color:#2ecc71;'><strong>You saved NPR " . number_format($discount_amount,2) . " with code " . htmlspecialchars($discount_code) . "!</strong></p>"
            : '';

        $mail = createMailer();
        $mail->addAddress($ud['email'], $ud['first_name'] . ' ' . $ud['last_name']);
        $mail->Subject = 'Booking Confirmation – TD Rentals';
        $mail->isHTML(true);
        $mail->Body = "<p>Hi {$ud['first_name']},</p>
            <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
            <p>Pickup: {$pickup_date}</p><p>Dropoff: {$dropoff_date}</p>
            <p>Total Paid: NPR " . number_format($totalprice,2) . "</p>
            <p>Payment Method: Khalti QR</p>
            <p>Transaction ID: {$transaction_ref}</p>
            {$savings_line}
            <p>Please find your invoice attached.</p>
            <p>Thank you for choosing TD Rentals 🚀</p><p>Best Regards,<br>TD Rentals Team</p>";
        $mail->AltBody = "Hi {$ud['first_name']}, your booking for {$vehicle['model']} is confirmed.";
        $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
        if (isNotificationEnabled($conn, $user_id)) $mail->send();
    } catch (Exception $e) {
        error_log("Khalti QR email failed: " . $e->getMessage());
    }

    unset(
        $_SESSION['payment_vehicle_id'], $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],    $_SESSION['payment_days'],
        $_SESSION['payment_source'], $_SESSION['extend_payload'],
        $_SESSION['khalti_qr_pidx'],     $_SESSION['khalti_qr_booking_id'],
        $_SESSION['khalti_qr_purchase_order_id'], $_SESSION['khalti_qr_amount'],
        $_SESSION['khalti_qr_discount_code'], $_SESSION['khalti_qr_discount_amount'],
        $_SESSION['khalti_qr_discount_code_id']
    );

    echo json_encode(['success' => true, 'booking_id' => $booking_id]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);
