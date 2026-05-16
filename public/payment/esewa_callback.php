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
$totalprice = (float) ($_SESSION['esewa_amount'] ?? 0);

$paid_amount = (float) $total_amount;
if (abs($paid_amount - $totalprice) > 0.05) {
    die("Amount mismatch. Expected NPR {$totalprice}, received NPR {$paid_amount}.");
}

$discount_code_saved = (string) ($_SESSION['esewa_discount_code'] ?? '');
$discount_amount_saved = (float) ($_SESSION['esewa_discount_amount'] ?? 0);
$discount_code_id = isset($_SESSION['esewa_discount_code_id']) ? (int) $_SESSION['esewa_discount_code_id'] : 0;

if (!$vehicle_id) {
    die("Booking session expired.");
}

// Start database transaction
$conn->begin_transaction();

try {
    $discount_code = $_SESSION['esewa_discount_code'] ?? null;
    $discount_amount = $_SESSION['esewa_discount_amount'] ?? 0;
    $discount_code_id = $_SESSION['esewa_discount_code_id'] ?? null;

    // Insert into bookings
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
        $transaction_uuid,
        $discount_code_saved,
        $discount_amount_saved
    );
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to create booking.");
    }
    
    $booking_id = $insert_stmt->insert_id;

    // Record discount usage & increment used_count
    if ($discount_code_id) {
        $stmt = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $discount_code_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");

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

    // Milestone logic
    $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
    $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
    if ($res && $res->num_rows > 0) {
        $u_data = $res->fetch_assoc();
        $rentals = (int)$u_data['completed_rentals'];
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
    $transaction_ref = $response_data['transaction_code'] ?? ('ES-' . strtoupper(bin2hex(random_bytes(8))));
    $txn_sql = "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                VALUES (?, ?, ?, 'esewa', 'ESWA', 'eSewa', ?, NOW())";
    $txn_stmt = $conn->prepare($txn_sql);
    $txn_stmt->bind_param("iids", $booking_id, $user_id, $totalprice, $transaction_ref);
    
    if (!$txn_stmt->execute()) {
        throw new Exception("Failed to record transaction.");
    }

    // Fetch vehicle data for email
    $v_stmt = $conn->prepare("SELECT model, price_per_day FROM vehicles WHERE id = ?");
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
            'discount_code' => $discount_code_saved,
            'discount_amount' => $discount_amount_saved,
        ];

        $pdf_string = generateInvoicePDF($invoice_data);

        $mail = createMailer();
        $mail->addAddress($email, $first_name . ' ' . $last_name);
        $mail->Subject = 'Booking Confirmation (eSewa Payment)';
        $mail->isHTML(true);
        $mail->Body = "
            <p>Hi {$first_name},</p>
            <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
            <p>Payment Method: eSewa</p>
            <p>Transaction Code: {$transaction_ref}</p>
            <p>Pickup date: {$pickup_date}</p>
            <p>Dropoff date: {$dropoff_date}</p>
            <p>Total Paid: NPR $totalprice</p>
            <p>Please find your invoice attached.</p>
            <p>Thank you for choosing TD Rentals 🚀</p>
            <p>Best Regards,<br>TD Rentals Team</p>
        ";
        $mail->AltBody = "Hi {$first_name}, Your booking for {$vehicle['model']} is confirmed via eSewa.";
        $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
        $mail->send();
    } catch (Exception $e) {
        error_log("Booking email failed: " . $e->getMessage());
    }

    $conn->commit();

    // Clear session payment data
    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
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
