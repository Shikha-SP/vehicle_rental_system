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

if (isset($_GET['status']) && strtolower($_GET['status']) === 'user canceled') {
    error_log("Khalti Payment Canceled by user.");
    if ($purchase_order_id) {
        $upd_stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE purchase_order_id = ?");
        $upd_stmt->bind_param("s", $purchase_order_id);
        $upd_stmt->execute();
    }
    header("Location: paymentdetail.php");
    exit;
}

if (empty($pidx) || empty($purchase_order_id)) {
    error_log("Khalti Callback Error: Missing parameters.");
    die("Invalid request. Missing parameters.");
}

// Fetch booking from DB instead of relying on Session
error_log("Searching for booking with POID: $purchase_order_id");
$b_stmt = $conn->prepare("SELECT * FROM bookings WHERE purchase_order_id = ? LIMIT 1");
$b_stmt->bind_param("s", $purchase_order_id);
$b_stmt->execute();
$booking = $b_stmt->get_result()->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

$user_id = $booking['user_id'];
$vehicle_id = $booking['vehicle_id'];
$pickup_date = $booking['start_date'];
$dropoff_date = $booking['end_date'];
$totalprice = $booking['total_price'];
$booking_id = $booking['id'];

// Calculate days
$datetime1 = new DateTime($pickup_date);
$datetime2 = new DateTime($dropoff_date);
$days = $datetime1->diff($datetime2)->days;
if ($days == 0)
    $days = 1;

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
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Key ' . $khalti_config['secret_key'],
        'Content-Type: application/json',
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

if ($err) {
    die("cURL Error #:" . $err);
}

$response_data = json_decode($response, true);

if (isset($response_data['status']) && $response_data['status'] === 'Completed') {
    // Payment successful!
    error_log("Khalti Payment Verified Successfully for POID: $purchase_order_id");

    $conn->begin_transaction();

    try {
        // Update booking payment_status
        $update_sql = "UPDATE bookings SET payment_status = 'paid' WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $booking_id);

        if (!$update_stmt->execute()) {
            error_log("Failed to update booking status for ID: $booking_id");
            throw new Exception("Failed to update booking status.");
        }
        error_log("Booking status updated to 'paid' for ID: $booking_id");

        $discount_code = $_SESSION['khalti_discount_code'] ?? null;
        $discount_amount = $_SESSION['khalti_discount_amount'] ?? 0;
        $discount_code_id = $_SESSION['khalti_discount_code_id'] ?? null;

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
                'discount_code' => $discount_code ?? null,
                'discount_amount' => $discount_amount ?? 0,
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
            $_SESSION['khalti_amount'],
            $_SESSION['khalti_discount_code'],
            $_SESSION['khalti_discount_amount'],
            $_SESSION['khalti_discount_code_id']
        );

        header("Location: bookingconfirmed.php?id=" . $booking_id);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        die("Error processing booking: " . $e->getMessage());
    }

} else {
    // Payment failed
    $upd_stmt = $conn->prepare("UPDATE bookings SET payment_status = 'failed' WHERE id = ?");
    $upd_stmt->bind_param("i", $booking_id);
    $upd_stmt->execute();

    header("Location: paymentdetail.php");
    exit;
}
