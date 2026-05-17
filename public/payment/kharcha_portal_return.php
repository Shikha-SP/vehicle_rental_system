<?php
/**
 * kharcha_portal_return.php
 * Landing page after user completes (or cancels) payment on Kharcha's hosted portal.
 * Verifies the portal session status, then creates the booking if successful.
 */
require_once '../../config/db.php';
require_once '../../config/kharcha.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

$portal_session_id = $_SESSION['kharcha_portal_session_id'] ?? '';
$user_id           = $_SESSION['user_id'];
$vehicle_id        = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date       = $_SESSION['payment_pickup']     ?? '';
$dropoff_date      = $_SESSION['payment_dropoff']    ?? '';
$days              = (int)($_SESSION['payment_days'] ?? 0);

if (!$portal_session_id || !$vehicle_id) {
    header('Location: paymentdetail.php');
    exit;
}

// Check portal session status
$statusResult = kharchaGetPortalSessionStatus($portal_session_id);
$status       = $statusResult['session']['status'] ?? ($statusResult['status'] ?? 'unknown');

if ($status !== 'success') {
    // Payment not completed — send back with error
    $_SESSION['portal_error'] = match($status) {
        'expired'  => 'Your Kharcha payment session expired. Please try again.',
        'pending'  => 'Payment was not completed. Please try again.',
        default    => 'Kharcha payment could not be verified. Please try again or use another method.',
    };
    header('Location: paymentdetail.php');
    exit;
}

// ── Payment verified — create booking ─────────────────────────────
$stmt = $conn->prepare("SELECT v.*, u.first_name AS owner_name FROM vehicles v JOIN users u ON v.user_id = u.id WHERE v.id = ? LIMIT 1");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

$totalprice = ((float)$vehicle['price_per_day'] * $days) + 500;

// ── Apply discount stored by kharcha_portal_pay.php ───────────────
$discount_code    = $_SESSION['kharcha_portal_discount_code']    ?? '';
$discount_amount  = (float)($_SESSION['kharcha_portal_discount_amount']  ?? 0);
$discount_code_id = $_SESSION['kharcha_portal_discount_code_id'] ?? null;

if ($discount_amount > 0) {
    $totalprice -= $discount_amount;
}

$ins = $conn->prepare("INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, payment_status, discount_code, discount_amount, created_at) VALUES (?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, NOW())");
$ins->bind_param("iissdsd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $totalprice, $discount_code, $discount_amount);

if (!$ins->execute()) {
    $_SESSION['portal_error'] = 'Payment received but booking record failed. Please contact support.';
    header('Location: paymentdetail.php');
    exit;
}

$booking_id      = $ins->insert_id;

// ── Record discount usage ──────────────────────────────────────────
if ($discount_code_id) {
    $du = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
    $du->bind_param("ii", $user_id, $discount_code_id);
    $du->execute(); $du->close();
    $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");
}

$transaction_ref = $portal_session_id;
$card_last4      = 'KHP'; // Kharcha Portal
$db_pay_method   = 'kharcha_portal';
$db_card_type    = 'Kharcha Portal';

$txn = $conn->prepare("INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
$txn->bind_param("iidssss", $booking_id, $user_id, $totalprice, $db_pay_method, $card_last4, $db_card_type, $transaction_ref);
$txn->execute();

// Send confirmation email
$u_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
$u_stmt->bind_param("i", $user_id);
$u_stmt->execute();
$user_data = $u_stmt->get_result()->fetch_assoc();

try {
    $invoice_data = [
        'booking_id'      => $booking_id,
        'first_name'      => $user_data['first_name'],
        'email'           => $user_data['email'],
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

    $mail = createMailer();
    $mail->addAddress($user_data['email'], $user_data['first_name'] . ' ' . $user_data['last_name']);
    $mail->Subject = 'Booking Confirmation – TD Rentals';
    $mail->isHTML(true);
    $mail->Body = "<p>Hi {$user_data['first_name']},</p>
        <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
        <p>Pickup: {$pickup_date}</p><p>Dropoff: {$dropoff_date}</p>
        <p>Total Paid: NPR " . number_format($totalprice, 2) . "</p>
        <p>Payment Method: Kharcha Payment Portal</p>
        <p>Kharcha Session ID: {$portal_session_id}</p>
        <p>Please find your invoice attached.</p>
        <p>Thank you for choosing TD Rentals 🚀</p><p>Best Regards,<br>TD Rentals Team</p>";
    $mail->AltBody = "Hi {$user_data['first_name']}, your booking for {$vehicle['model']} is confirmed.";
    $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
    $mail->send();
} catch (Exception $e) {
    error_log("Portal booking email failed: " . $e->getMessage());
}

// Clean up session
unset($_SESSION['payment_vehicle_id'], $_SESSION['payment_pickup'],
      $_SESSION['payment_dropoff'], $_SESSION['payment_days'],
      $_SESSION['kharcha_portal_session_id'], $_SESSION['kharcha_portal_amount'],
      $_SESSION['kharcha_portal_discount_code'], $_SESSION['kharcha_portal_discount_amount'],
      $_SESSION['kharcha_portal_discount_code_id']);

header("Location: bookingconfirmed.php?id=" . $booking_id);
exit;
