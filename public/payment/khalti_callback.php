<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';
$khalti_config = require_once '../../config/khalti.php';

session_start();

$pidx = $_GET['pidx'] ?? '';
$purchase_order_id = $_GET['purchase_order_id'] ?? '';

error_log("Khalti Callback Hit. PIDX: $pidx, POID: $purchase_order_id");

function khaltiClearPaymentSession(): void
{
    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
        $_SESSION['payment_pickup_time'],
        $_SESSION['payment_return_time'],
        $_SESSION['khalti_purchase_order_id'],
        $_SESSION['khalti_amount'],
        $_SESSION['khalti_pidx'],
        $_SESSION['khalti_discount_code'],
        $_SESSION['khalti_discount_amount'],
        $_SESSION['khalti_discount_code_id'],
        $_SESSION['payment_source'],
        $_SESSION['extend_payload']
    );
}

function khaltiCancelLegacyPendingBooking(mysqli $conn, string $purchase_order_id): void
{
    if ($purchase_order_id === '') {
        return;
    }
    $upd_stmt = $conn->prepare("UPDATE bookings SET status = 'cancelled', payment_status = 'failed' WHERE purchase_order_id = ? AND payment_status = 'pending'");
    $upd_stmt->bind_param("s", $purchase_order_id);
    $upd_stmt->execute();
    $upd_stmt->close();
}

if (isset($_GET['status']) && strtolower($_GET['status']) === 'user canceled') {
    error_log("Khalti Payment Canceled by user.");
    khaltiCancelLegacyPendingBooking($conn, $purchase_order_id);
    khaltiClearPaymentSession();
    header("Location: paymentdetail.php");
    exit;
}

if (empty($pidx) || empty($purchase_order_id)) {
    error_log("Khalti Callback Error: Missing parameters.");
    die("Invalid request. Missing parameters.");
}

if (!isset($_SESSION['user_id'])) {
    die("Session expired. Please log in and try again.");
}

if ($purchase_order_id !== ($_SESSION['khalti_purchase_order_id'] ?? '')) {
    die("Security verification failed. Order mismatch.");
}

$user_id = (int) $_SESSION['user_id'];
$vehicle_id = (int) ($_SESSION['payment_vehicle_id'] ?? 0);
$pickup_date = $_SESSION['payment_pickup'] ?? '';
$dropoff_date = $_SESSION['payment_dropoff'] ?? '';
$days = (int) ($_SESSION['payment_days'] ?? 0);
$totalprice = (float) ($_SESSION['khalti_amount'] ?? 0);
$discount_code_saved = (string) ($_SESSION['khalti_discount_code'] ?? '');
$discount_amount_saved = (float) ($_SESSION['khalti_discount_amount'] ?? 0);
$discount_code_id = isset($_SESSION['khalti_discount_code_id']) ? (int) $_SESSION['khalti_discount_code_id'] : 0;
$is_extension = (($_SESSION['payment_source'] ?? '') === 'extend_booking');
$extend = $_SESSION['extend_payload'] ?? [];

if (!$vehicle_id || !$pickup_date || !$dropoff_date) {
    die("Booking session expired.");
}

// Idempotency: booking may already exist if callback was hit twice
$existing_stmt = $conn->prepare("SELECT id, payment_status FROM bookings WHERE purchase_order_id = ? LIMIT 1");
$existing_stmt->bind_param("s", $purchase_order_id);
$existing_stmt->execute();
$existing_booking = $existing_stmt->get_result()->fetch_assoc();
$existing_stmt->close();

if (!$is_extension && $existing_booking && $existing_booking['payment_status'] === 'paid') {
    khaltiClearPaymentSession();
    header("Location: bookingconfirmed.php?id=" . (int) $existing_booking['id']);
    exit;
}

// Verify payment with Khalti
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $khalti_config['base_url'] . 'epayment/lookup/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode(['pidx' => $pidx]),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Key ' . $khalti_config['secret_key'],
        'Content-Type: application/json',
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("cURL Error #:" . $err);
}

$response_data = json_decode($response, true);

if (isset($response_data['status']) && $response_data['status'] === 'Completed') {
    error_log("Khalti Payment Verified Successfully for POID: $purchase_order_id");

    $conn->begin_transaction();

    try {
        if ($is_extension) {
            $booking_id = (int)($extend['booking_id'] ?? 0);
            if (!$booking_id) {
                throw new Exception("Missing extension booking.");
            }

            $update_sql = "UPDATE bookings
                           SET end_date = ?, total_price = total_price + ?, payment_status = 'paid',
                               discount_amount = discount_amount + ?,
                               discount_code = COALESCE(NULLIF(?, ''), discount_code)
                           WHERE id = ? AND user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sddsii", $dropoff_date, $totalprice, $discount_amount_saved, $discount_code_saved, $booking_id, $user_id);
            if (!$update_stmt->execute() || $update_stmt->affected_rows < 1) {
                throw new Exception("Failed to extend booking.");
            }
            $update_stmt->close();
            
        } elseif ($existing_booking) {
            $booking_id = (int) $existing_booking['id'];
            $update_sql = "UPDATE bookings SET payment_status = 'paid', status = 'confirmed' WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $booking_id);
            if (!$update_stmt->execute()) {
                throw new Exception("Failed to update booking status.");
            }
            $update_stmt->close();
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
                $purchase_order_id,
                $discount_code_saved,
                $discount_amount_saved
            );
            if (!$insert_stmt->execute()) {
                throw new Exception("Failed to create booking.");
            }
            $booking_id = (int) $insert_stmt->insert_id;
            $insert_stmt->close();
        }

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

                // ── GOLD RESET CYCLE ──────────────────────────────────────────
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

        // Milestone logic — increment AFTER possible Gold reset
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

        // Insert into transactions table
        $has_txn = false;
        if (!$is_extension) {
            $check_txn = $conn->prepare("SELECT id FROM transactions WHERE booking_id = ? LIMIT 1");
            $check_txn->bind_param("i", $booking_id);
            $check_txn->execute();
            $has_txn = $check_txn->get_result()->num_rows > 0;
            $check_txn->close();
        }

        if (!$has_txn) {
            $transaction_ref = $response_data['transaction_id'] ?? ('KH-' . strtoupper(bin2hex(random_bytes(8))));
            $txn_sql = "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                        VALUES (?, ?, ?, 'khalti', 'KHLT', 'Khalti', ?, NOW())";
            $txn_stmt = $conn->prepare($txn_sql);
            $txn_stmt->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
            if (!$txn_stmt->execute()) {
                throw new Exception("Failed to record transaction.");
            }
            $txn_stmt->close();
        }

        $v_stmt = $conn->prepare("SELECT model, price_per_day, image_path FROM vehicles WHERE id = ?");
        $v_stmt->bind_param("i", $vehicle_id);
        $v_stmt->execute();
        $vehicle = $v_stmt->get_result()->fetch_assoc();
        $v_stmt->close();

        $user_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $user_data = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();

        $email = $user_data['email'] ?? '';
        $first_name = $user_data['first_name'] ?? '';
        $last_name = $user_data['last_name'] ?? '';
        $transaction_ref = $response_data['transaction_id'] ?? ('KH-' . strtoupper(bin2hex(random_bytes(8))));

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
            // send booking confirmation mail
                if (isNotificationEnabled($conn, $user_id)) {
                    $html = require '../../includes/booking_confirmation.php';
                    $altBody = "Hi $first_name, your booking for {$vehicle['model']} is confirmed. Dates: $pickup_date - $dropoff_date.";
                    sendEmail($email, $first_name, 'Booking Confirmation', $html, $altBody, [
                        'data' => $pdf_string,
                        'filename' => "invoice_{$booking_id}.pdf",
                        'mime' => 'application/pdf'
                    ]);
                }
            // send payment confimation mail
	            if (isNotificationEnabled($conn, $user_id)) {
	                $AltBody = "Hi {$first_name} {$last_name}. Your payment has been confirmed. Thank you for choosing TD Rentals.";
	                $payment_vehicle_image_src = 'cid:vehicle_image';
	                $payment_confirmation_method = 'Khalti';
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
            error_log("Booking email failed: " . $e->getMessage());
        }

        $conn->commit();
        khaltiClearPaymentSession();
        header("Location: bookingconfirmed.php?id=" . $booking_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing booking: " . $e->getMessage());
    }
}

// Payment failed or not completed — cancel any legacy pending row, do not create a booking
khaltiCancelLegacyPendingBooking($conn, $purchase_order_id);
khaltiClearPaymentSession();
header("Location: paymentdetail.php?error=payment_failed");
exit;
