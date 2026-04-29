<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';
$khalti_config = require_once '../../config/khalti.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$pidx = $_GET['pidx'] ?? '';
$purchase_order_id = $_GET['purchase_order_id'] ?? '';

if (isset($_GET['status']) && strtolower($_GET['status']) === 'user canceled') {
    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
        $_SESSION['khalti_purchase_order_id'],
        $_SESSION['khalti_amount']
    );
    header("Location: ../vehicle/vehicles.php");
    exit;
}

if (empty($pidx)) {
    die("Invalid request. pidx is missing.");
}

if ($purchase_order_id !== ($_SESSION['khalti_purchase_order_id'] ?? '')) {
    die("Invalid request. Purchase order ID mismatch.");
}

// Verify payment with Khalti
$post_data = ['pidx' => $pidx];

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
    CURLOPT_POSTFIELDS => json_encode($post_data),
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
    // Payment successful!
    
    $user_id = $_SESSION['user_id'];
    $vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
    $pickup_date = $_SESSION['payment_pickup'] ?? '';
    $dropoff_date = $_SESSION['payment_dropoff'] ?? '';
    $days = $_SESSION['payment_days'] ?? 0;
    $totalprice = $_SESSION['khalti_amount'] ?? 0;

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
        $transaction_ref = $response_data['transaction_id'] ?? ('KH-' . strtoupper(bin2hex(random_bytes(8))));
        $txn_sql = "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                    VALUES (?, ?, ?, 'khalti', 'KHLT', 'Khalti', ?, NOW())";
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
            $mail->Subject = 'Booking Confirmation (Khalti Payment)';
            $mail->isHTML(true);
            $mail->Body = "
                <p>Hi {$first_name},</p>
                <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
                <p>Payment Method: Khalti</p>
                <p>Transaction ID: {$transaction_ref}</p>
                <p>Pickup date: {$pickup_date}</p>
                <p>Dropoff date: {$dropoff_date}</p>
                <p>Total Paid: NPR $totalprice</p>
                <p>Please find your invoice attached.</p>
                <p>Thank you for choosing TD Rentals 🚀</p>
                <p>Best Regards,<br>TD Rentals Team</p>
            ";
            $mail->AltBody = "Hi {$first_name}, Your booking for {$vehicle['model']} is confirmed via Khalti.";
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
            $_SESSION['khalti_purchase_order_id'],
            $_SESSION['khalti_amount']
        );

        header("Location: bookingconfirmed.php?id=" . $booking_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing booking: " . $e->getMessage());
    }

} else {
    // Payment failed or cancelled
    unset(
        $_SESSION['payment_vehicle_id'],
        $_SESSION['payment_pickup'],
        $_SESSION['payment_dropoff'],
        $_SESSION['payment_days'],
        $_SESSION['khalti_purchase_order_id'],
        $_SESSION['khalti_amount']
    );
    header("Location: ../vehicle/vehicles.php");
    exit;
}
