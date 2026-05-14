<?php
/**
 * check_payment.php
 *
 * Polling endpoint called by qr_initiate.php every 3 seconds.
 *
 * Problem solved here:
 *   When the user scans the QR on their phone and pays, Khalti redirects
 *   the PHONE browser to khalti_callback.php. But since the return_url uses
 *   'localhost', the phone cannot reach it — so the DB booking stays 'pending'
 *   and the laptop never gets redirected.
 *
 * Fix:
 *   This endpoint now actively verifies the payment with Khalti's API using
 *   the pidx stored in the booking row. If Khalti reports 'Completed', we
 *   do the full DB update + transaction insert right here, on the laptop side.
 *   The QR page's JS polling then detects 'paid' and redirects immediately.
 */

session_start();
require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';
$khalti_config = require_once '../../config/khalti.php';

header('Content-Type: application/json');

$order_id = $_GET['order_id'] ?? '';

if (empty($order_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing order_id']);
    exit;
}

// Fetch booking from DB
$stmt = $conn->prepare("SELECT id, payment_status, user_id, vehicle_id, start_date, end_date, total_price, purchase_order_id FROM bookings WHERE purchase_order_id = ? LIMIT 1");
$stmt->bind_param("s", $order_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

// Booking not found yet
if (!$booking) {
    echo json_encode(['status' => 'pending']);
    exit;
}

// Already resolved — just return the current status
if ($booking['payment_status'] === 'paid') {
    echo json_encode(['status' => 'paid', 'booking_id' => $booking['id']]);
    exit;
}

if ($booking['payment_status'] === 'failed') {
    echo json_encode(['status' => 'failed']);
    exit;
}

// --- Status is still 'pending': actively verify with Khalti API ---
// Use the purchase_order_id as the pidx lookup key via Khalti's lookup endpoint.
// Khalti lookup accepts pidx. We stored purchase_order_id which is our internal ID,
// not the pidx. We need pidx. Check if we stored it in session.
$pidx = $_SESSION['khalti_pidx'] ?? '';

if (empty($pidx)) {
    // pidx not in session (e.g. page refreshed) — we can't verify without it.
    // Just return pending and wait.
    echo json_encode(['status' => 'pending']);
    exit;
}

// Call Khalti lookup API
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

$response     = curl_exec($curl);
$curl_err     = curl_error($curl);
curl_close($curl);

if ($curl_err) {
    // Network error — return pending, will retry next poll
    echo json_encode(['status' => 'pending']);
    exit;
}

$khalti_data = json_decode($response, true);

// Khalti confirmed the payment
if (isset($khalti_data['status']) && $khalti_data['status'] === 'Completed') {

    $booking_id  = $booking['id'];
    $user_id     = $booking['user_id'];
    $vehicle_id  = $booking['vehicle_id'];
    $pickup_date = $booking['start_date'];
    $dropoff_date = $booking['end_date'];
    $totalprice  = $booking['total_price'];

    $conn->begin_transaction();
    try {
        // Update booking to paid
        $upd = $conn->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
        $upd->bind_param("i", $booking_id);
        if (!$upd->execute()) {
            throw new Exception("DB update failed.");
        }

        // Insert transaction record (avoid duplicate if already inserted)
        $check_txn = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? LIMIT 1");
        $check_txn->bind_param("i", $booking_id);
        $check_txn->execute();
        $existing_txn = $check_txn->get_result()->fetch_assoc();

        if (!$existing_txn) {
            $transaction_ref = $khalti_data['transaction_id'] ?? ('KH-' . strtoupper(bin2hex(random_bytes(8))));
            $txn = $conn->prepare("INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at) VALUES (?, ?, ?, 'khalti', 'KHLT', 'Khalti', ?, NOW())");
            $txn->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
            if (!$txn->execute()) {
                throw new Exception("Transaction insert failed.");
            }

            $disc_id = (int) ($_SESSION['khalti_discount_code_id'] ?? 0);
            $disc_amt = (float) ($_SESSION['khalti_discount_amount'] ?? 0);
            $disc_code = (string) ($_SESSION['khalti_discount_code'] ?? '');
            if ($disc_id > 0 && $disc_amt > 0 && $disc_code !== '') {
                $ub = $conn->prepare("UPDATE bookings SET discount_code = ?, discount_amount = ? WHERE id = ?");
                $ub->bind_param("sdi", $disc_code, $disc_amt, $booking_id);
                if (!$ub->execute()) {
                    throw new Exception("Failed to save discount on booking.");
                }
                $chk_use = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ? LIMIT 1");
                $chk_use->bind_param("ii", $user_id, $disc_id);
                $chk_use->execute();
                if ($chk_use->get_result()->num_rows === 0) {
                    $ins_use = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
                    $ins_use->bind_param("ii", $user_id, $disc_id);
                    $ins_use->execute();
                    $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = " . (int) $disc_id);
                }
                $chk_use->close();
            }

            // Calculate days for email
            $days = max(1, (new DateTime($dropoff_date))->diff(new DateTime($pickup_date))->days);

            // Fetch vehicle & user for email
            $v_stmt = $conn->prepare("SELECT model, price_per_day FROM vehicles WHERE id = ?");
            $v_stmt->bind_param("i", $vehicle_id);
            $v_stmt->execute();
            $vehicle = $v_stmt->get_result()->fetch_assoc();

            $u_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $user_data  = $u_stmt->get_result()->fetch_assoc();
            $email      = $user_data['email'] ?? '';
            $first_name = $user_data['first_name'] ?? '';
            $last_name  = $user_data['last_name'] ?? '';

            // Send confirmation email
            try {
                $invoice_data = [
                    'booking_id'   => $booking_id,
                    'first_name'   => $first_name,
                    'email'        => $email,
                    'model'        => $vehicle['model'],
                    'pickup_date'  => $pickup_date,
                    'dropoff_date' => $dropoff_date,
                    'days'         => $days,
                    'price_per_day' => $vehicle['price_per_day'],
                    'total_price'  => $totalprice,
                    'discount_code' => (string) ($_SESSION['khalti_discount_code'] ?? ''),
                    'discount_amount' => (float) ($_SESSION['khalti_discount_amount'] ?? 0),
                ];
                $pdf_string = generateInvoicePDF($invoice_data);

                $mail = createMailer();
                $mail->addAddress($email, $first_name . ' ' . $last_name);
                $mail->Subject = 'Booking Confirmation (Khalti QR Payment)';
                $mail->isHTML(true);
                $mail->Body = "
                    <p>Hi {$first_name},</p>
                    <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
                    <p>Payment Method: Khalti (QR)</p>
                    <p>Transaction ID: {$transaction_ref}</p>
                    <p>Pickup date: {$pickup_date}</p>
                    <p>Dropoff date: {$dropoff_date}</p>
                    <p>Total Paid: NPR {$totalprice}</p>
                    <p>Please find your invoice attached.</p>
                    <p>Thank you for choosing TD Rentals 🚀</p>
                    <p>Best Regards,<br>TD Rentals Team</p>
                ";
                $mail->AltBody = "Hi {$first_name}, Your booking for {$vehicle['model']} is confirmed via Khalti QR.";
                $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
                $mail->send();
            } catch (Exception $e) {
                error_log("QR booking email failed: " . $e->getMessage());
            }
        }

        $conn->commit();

        // Clear session payment data
        unset(
            $_SESSION['payment_vehicle_id'],
            $_SESSION['payment_pickup'],
            $_SESSION['payment_dropoff'],
            $_SESSION['payment_days'],
            $_SESSION['khalti_purchase_order_id'],
            $_SESSION['khalti_amount'],
            $_SESSION['khalti_pidx'],
            $_SESSION['khalti_discount_code'],
            $_SESSION['khalti_discount_amount'],
            $_SESSION['khalti_discount_code_id']
        );

        echo json_encode(['status' => 'paid', 'booking_id' => $booking_id]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("check_payment.php commit error: " . $e->getMessage());
        echo json_encode(['status' => 'pending']);
        exit;
    }

} elseif (isset($khalti_data['status']) && in_array($khalti_data['status'], ['Failed', 'Expired', 'User canceled'])) {
    // Mark as failed
    $upd = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE id = ?");
    $upd->bind_param("i", $booking['id']);
    $upd->execute();
    echo json_encode(['status' => 'failed']);
    exit;
}

// Khalti says it's still in progress (Initiated, Pending, etc.)
echo json_encode(['status' => 'pending']);