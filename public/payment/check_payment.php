<?php
/**
 * check_payment.php — QR payment polling endpoint.
 * Creates the booking only after Khalti reports payment as Completed (same as eSewa).
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

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

if ($order_id !== ($_SESSION['khalti_purchase_order_id'] ?? '')) {
    echo json_encode(['status' => 'error', 'message' => 'Order mismatch']);
    exit;
}

// Already completed (e.g. callback ran first)
$existing_stmt = $conn->prepare("SELECT id, payment_status FROM bookings WHERE purchase_order_id = ? LIMIT 1");
$existing_stmt->bind_param("s", $order_id);
$existing_stmt->execute();
$existing_booking = $existing_stmt->get_result()->fetch_assoc();
$existing_stmt->close();

if ($existing_booking && $existing_booking['payment_status'] === 'paid') {
    echo json_encode(['status' => 'paid', 'booking_id' => (int) $existing_booking['id']]);
    exit;
}

if ($existing_booking && $existing_booking['payment_status'] === 'failed') {
    echo json_encode(['status' => 'failed']);
    exit;
}

$pidx = $_SESSION['khalti_pidx'] ?? '';
if (empty($pidx)) {
    echo json_encode(['status' => 'pending']);
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
$curl_err = curl_error($curl);
curl_close($curl);

if ($curl_err) {
    echo json_encode(['status' => 'pending']);
    exit;
}

$khalti_data = json_decode($response, true);

if (isset($khalti_data['status']) && $khalti_data['status'] === 'Completed') {
    $user_id = (int) $_SESSION['user_id'];
    $vehicle_id = (int) ($_SESSION['payment_vehicle_id'] ?? 0);
    $pickup_date = $_SESSION['payment_pickup'] ?? '';
    $dropoff_date = $_SESSION['payment_dropoff'] ?? '';
    $days = (int) ($_SESSION['payment_days'] ?? 0);
    $totalprice = (float) ($_SESSION['khalti_amount'] ?? 0);
    $discount_code_saved = (string) ($_SESSION['khalti_discount_code'] ?? '');
    $discount_amount_saved = (float) ($_SESSION['khalti_discount_amount'] ?? 0);
    $discount_code_id = isset($_SESSION['khalti_discount_code_id']) ? (int) $_SESSION['khalti_discount_code_id'] : 0;

    if (!$vehicle_id || !$pickup_date || !$dropoff_date) {
        echo json_encode(['status' => 'error', 'message' => 'Booking session expired']);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($existing_booking) {
            $booking_id = (int) $existing_booking['id'];
            $upd = $conn->prepare("UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = ?");
            $upd->bind_param("i", $booking_id);
            if (!$upd->execute()) {
                throw new Exception("DB update failed.");
            }
            $upd->close();
        } else {
            $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, payment_status, purchase_order_id, discount_code, discount_amount, created_at)
                           VALUES (?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(
                "iissdssd",
                $user_id,
                $vehicle_id,
                $pickup_date,
                $dropoff_date,
                $totalprice,
                $order_id,
                $discount_code_saved,
                $discount_amount_saved
            );
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to create booking.");
            }
            $booking_id = (int) $insert_stmt->insert_id;
            $insert_stmt->close();
        }

        // Milestone logic
        $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
        $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
        if ($res && $res->num_rows > 0) {
            $u_data = $res->fetch_assoc();
            $rentals = (int) $u_data['completed_rentals'];
            $current_medal = $u_data['medal'];

            $new_medal = $current_medal;
            $milestone_code = '';
            $milestone_percent = 0;

            if ($rentals >= 3 && $current_medal === 'NONE') {
                $new_medal = 'BRONZE';
                $milestone_code = 'BRONZE5';
                $milestone_percent = 5;
            } elseif ($rentals >= 7 && $current_medal === 'BRONZE') {
                $new_medal = 'SILVER';
                $milestone_code = 'SILVER10';
                $milestone_percent = 10;
            } elseif ($rentals >= 15 && $current_medal === 'SILVER') {
                $new_medal = 'GOLD';
                $milestone_code = 'GOLD20';
                $milestone_percent = 20;
            }

            if ($new_medal !== $current_medal) {
                $conn->query("UPDATE users SET medal = '$new_medal' WHERE id = $user_id");
                if ($milestone_code !== '') {
                    $unique_suffix = substr(md5(uniqid($user_id, true)), 0, 4);
                    $personal_code = $milestone_code . "-U" . $user_id . "-" . $unique_suffix;
                    $conn->query("
                        INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, owner_user_id) 
                        VALUES ('$personal_code', 'percent', $milestone_percent, 0, 1, $user_id)
                    ");
                }
            }
        }

        // Insert transaction record (avoid duplicate if already inserted)
        $check_txn = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? LIMIT 1");
        $check_txn->bind_param("i", $booking_id);
        $check_txn->execute();
        $has_txn = $check_txn->get_result()->num_rows > 0;
        $check_txn->close();

        if (!$has_txn) {
            $transaction_ref = $khalti_data['transaction_id'] ?? ('KH-' . strtoupper(bin2hex(random_bytes(8))));
            $txn = $conn->prepare("INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at) VALUES (?, ?, ?, 'khalti', 'KHLT', 'Khalti', ?, NOW())");
            $txn->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
            if (!$txn->execute()) {
                throw new Exception("Transaction insert failed.");
            }
            $txn->close();

            // Record discount usage & increment used_count
            if ($discount_code_id > 0 && $discount_amount_saved > 0) {
                $chk_use = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ? LIMIT 1");
                $chk_use->bind_param("ii", $user_id, $discount_code_id);
                $chk_use->execute();
                if ($chk_use->get_result()->num_rows === 0) {
                    $ins_use = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
                    $ins_use->bind_param("ii", $user_id, $discount_code_id);
                    $ins_use->execute();
                    $ins_use->close();
                    $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = " . (int) $discount_code_id);

                    // GOLD RESET CYCLE
                    $gold_check = $conn->query("
                        SELECT id FROM discount_codes 
                        WHERE id = $discount_code_id 
                          AND owner_user_id = $user_id 
                          AND discount_percent = 20 
                        LIMIT 1
                    ");
                    if ($gold_check && $gold_check->num_rows > 0) {
                        $conn->query("UPDATE users SET medal = 'BRONZE', completed_rentals = 3 WHERE id = $user_id");
                    }
                }
                $chk_use->close();
            }

            $v_stmt = $conn->prepare("SELECT model, price_per_day FROM vehicles WHERE id = ?");
            $v_stmt->bind_param("i", $vehicle_id);
            $v_stmt->execute();
            $vehicle = $v_stmt->get_result()->fetch_assoc();
            $v_stmt->close();

            $u_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $u_stmt->bind_param("i", $user_id);
            $u_stmt->execute();
            $user_data = $u_stmt->get_result()->fetch_assoc();
            $u_stmt->close();

            $email = $user_data['email'] ?? '';
            $first_name = $user_data['first_name'] ?? '';
            $last_name = $user_data['last_name'] ?? '';

            try {
                $invoice_data = [
                    'booking_id' => $booking_id,
                    'first_name' => $first_name,
                    'email' => $email,
                    'model' => $vehicle['model'],
                    'pickup_date' => $pickup_date,
                    'dropoff_date' => $dropoff_date,
                    'days' => max(1, $days),
                    'price_per_day' => $vehicle['price_per_day'],
                    'total_price' => $totalprice,
                    'discount_code' => $discount_code_saved,
                    'discount_amount' => $discount_amount_saved,
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
}

if (isset($khalti_data['status']) && in_array($khalti_data['status'], ['Failed', 'Expired', 'User canceled'], true)) {
    if ($existing_booking) {
        $upd = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'failed' WHERE id = ? AND payment_status = 'pending'");
        $upd->bind_param("i", $existing_booking['id']);
        $upd->execute();
        $upd->close();
    }
    echo json_encode(['status' => 'failed']);
    exit;
}

echo json_encode(['status' => 'pending']);
