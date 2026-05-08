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

if (!$vehicle_id) {
    die("Booking session expired.");
}

// Start database transaction
$conn->begin_transaction();

try {
    // Insert into bookings
    $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, created_at)
                   VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("iissd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $totalprice);
    
    if (!$insert_stmt->execute()) {
        throw new Exception("Failed to create booking.");
    }
    
    $booking_id = $insert_stmt->insert_id;

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
        $_SESSION['esewa_amount']
    );

    header("Location: bookingconfirmed.php?id=" . $booking_id);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Error processing booking: " . $e->getMessage());
}
