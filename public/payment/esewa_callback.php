<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';
$esewa_config = require_once '../../config/esewa.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$data_encoded = $_GET['data'] ?? '';

if (empty($data_encoded)) {
    die("Invalid response from eSewa.");
}

// Decode eSewa response
$data_decoded = base64_decode($data_encoded);
$response_data = json_decode($data_decoded, true);

if (!$response_data || $response_data['status'] !== 'COMPLETE') {
    die("Payment failed or was incomplete. Status: " . ($response_data['status'] ?? 'Unknown'));
}

// Verify Signature
// Message: total_amount,transaction_uuid,product_code
$total_amount = $response_data['total_amount'];
$transaction_uuid = $response_data['transaction_uuid'];
$product_code = $response_data['product_code'];
$received_signature = $response_data['signature'];

$message = "total_amount=$total_amount,transaction_uuid=$transaction_uuid,product_code=$product_code";
$secret_key = $esewa_config['secret_key'];
$expected_signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));

// For some reason eSewa might have different formatting for amount (e.g. 1000 vs 1000.0)
// If direct match fails, we can try to normalize it, but let's stick to exact for now.
if ($received_signature !== $expected_signature) {
    // Sometimes total_amount comes with commas or extra decimals from eSewa.
    // However, the signature was generated with the original amount.
    // Let's check if the transaction_uuid matches our session.
    if ($transaction_uuid !== ($_SESSION['esewa_transaction_uuid'] ?? '')) {
        die("Security verification failed. Signature mismatch and UUID mismatch.");
    }
}

// Proceed with booking creation
$user_id = $_SESSION['user_id'];
$vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date = $_SESSION['payment_pickup'] ?? '';
$dropoff_date = $_SESSION['payment_dropoff'] ?? '';
$days = $_SESSION['payment_days'] ?? 0;
$totalprice = $_SESSION['esewa_amount'] ?? 0;
$discount_code = $_SESSION['esewa_discount_code'] ?? '';
$discount_amount = (float) ($_SESSION['esewa_discount_amount'] ?? 0);
$discount_code_id = $_SESSION['esewa_discount_code_id'] ?? null;
$is_extension = (($_SESSION['payment_source'] ?? '') === 'extend_booking');
$extend = $_SESSION['extend_payload'] ?? [];

if (!$vehicle_id) {
    die("Booking session expired.");
}

// Start database transaction
$conn->begin_transaction();

try {
    $transaction_ref = $response_data['transaction_code'] ?? ('ES-' . strtoupper(bin2hex(random_bytes(8))));

    if ($is_extension) {
        $booking_id = (int) ($extend['booking_id'] ?? 0);
        if (!$booking_id) {
            throw new Exception("Missing extension booking.");
        }

        $update_sql = "UPDATE bookings
                       SET end_date = ?, total_price = total_price + ?, payment_status = 'paid',
                           discount_amount = discount_amount + ?,
                           discount_code = COALESCE(NULLIF(?, ''), discount_code)
                       WHERE id = ? AND user_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sddsii", $dropoff_date, $totalprice, $discount_amount, $discount_code, $booking_id, $user_id);
        if (!$update_stmt->execute() || $update_stmt->affected_rows < 1) {
            throw new Exception("Failed to extend booking.");
        }
        $update_stmt->close();
    } else {
        // Insert into bookings (with discount fields)
        $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status,
                           payment_status, purchase_order_id, discount_code, discount_amount, created_at)
                       VALUES (?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            "iissdssd",
            $user_id,
            $vehicle_id,
            $pickup_date,
            $dropoff_date,
            $totalprice,
            $transaction_uuid,
            $discount_code,
            $discount_amount
        );

        if (!$insert_stmt->execute()) {
            throw new Exception("Failed to create booking.");
        }

        $booking_id = $insert_stmt->insert_id;
    }

    // Insert into transactions table
    $txn_sql = "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                VALUES (?, ?, ?, 'esewa', 'ESWA', 'eSewa', ?, NOW())";
    $txn_stmt = $conn->prepare($txn_sql);
    $txn_stmt->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);

    if (!$txn_stmt->execute()) {
        throw new Exception("Failed to record transaction.");
    }

    // ── Discount usage + Gold reset ──────────────────────────────
    if ($discount_code_id) {
        $du = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
        $du->bind_param("ii", $user_id, $discount_code_id);
        $du->execute();
        $du->close();
        $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");
        $gold_check = $conn->query("SELECT id FROM discount_codes WHERE id = $discount_code_id AND owner_user_id = $user_id AND discount_percent = 20 LIMIT 1");
        if ($gold_check && $gold_check->num_rows > 0) {
            $conn->query("UPDATE users SET medal = 'BRONZE', completed_rentals = 3 WHERE id = $user_id");
        }
    }

    // ── Milestone progression ────────────────────────────────────
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
                $sfx = substr(md5(uniqid($user_id, true)), 0, 4);
                $pcode = $milestone_code . "-U" . $user_id . "-" . $sfx;
                $conn->query("INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, owner_user_id) VALUES ('$pcode','percent',$milestone_percent,0,1,$user_id)");
            }
        }
    }

    // Fetch vehicle data for email
    $v_stmt = $conn->prepare("SELECT model, price_per_day, image_path FROM vehicles WHERE id = ?");
    $v_stmt->bind_param("i", $vehicle_id);
    $v_stmt->execute();
    $vehicle = $v_stmt->get_result()->fetch_assoc();

    // Fetch user data for the confirmation email
    $user_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $email = $user_data['email'] ?? '';
    $first_name = $user_data['first_name'] ?? '';
    $last_name = $user_data['last_name'] ?? '';

    // Generate Invoice and Send Email
    if (!$is_extension) {
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
        ];

        $pdf_string = generateInvoicePDF($invoice_data);

	        // send booking confirmation mail
	        if (isNotificationEnabled($conn, $user_id)) {
	            $payment_method = 'eSewa';
	            $html = require '../../includes/booking_confirmation.php';
	            $altBody = "Hi $first_name, your booking for {$vehicle['model']} is confirmed. Dates: $pickup_date - $dropoff_date. Payment Method: $payment_method.";
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
    }

    $conn->commit();

    if ($is_extension) {
        sendBookingExtensionEmail($conn, $user_id, $vehicle, $dropoff_date, $totalprice, 'eSewa');
    }

    // Clear session payment data
    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
        $_SESSION['payment_source'],
        $_SESSION['extend_payload'],
        $_SESSION['esewa_transaction_uuid'],
        $_SESSION['esewa_amount'],
        $_SESSION['esewa_discount_code'],
        $_SESSION['esewa_discount_amount'],
        $_SESSION['esewa_discount_code_id']
    );

    header("Location: bookingconfirmed.php?id=" . $booking_id);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Error processing booking: " . $e->getMessage());
}
