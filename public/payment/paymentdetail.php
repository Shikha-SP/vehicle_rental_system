<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../config/kharcha.php';   // Kharcha gateway config
require_once '../../config/khalti.php';    // Khalti ePay config
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';

session_start();

// ── Cache-control: never let the browser cache this page ──────────
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (!isset($_SESSION['user_id'])) {
    header('Location: bookingconfirmed.php');
    exit;
}

$errors = [];
$cardholdername = $cardnumber = $cvv = $street = $city = $zip = '';
$expiry_month = $expiry_year = '';
$payment_method = 'card'; // 'card' | 'kharcha' | 'esewa' | 'khalti'

// ── PRG: initial booking POST from vehicle-detail page ───────────
// This is the FIRST post (no card data) — just store params in
// session and immediately redirect to GET so the browser never
// records a cacheable POST for this page.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vehicle_id']) && !isset($_POST['cardnumber'])) {
    $_SESSION['payment_vehicle_id']  = (int) $_POST['vehicle_id'];
    $_SESSION['payment_pickup']      = $_POST['pickup_date']   ?? '';
    $_SESSION['payment_dropoff']     = $_POST['dropoff_date']  ?? '';
    $_SESSION['payment_days']        = (int) ($_POST['days']   ?? 0);
    $_SESSION['payment_pickup_time'] = $_POST['pickup_time']   ?? '09:00:00';
    $_SESSION['payment_return_time'] = $_POST['return_time']   ?? '18:00:00';
    // 303 See Other → browser switches to GET, back-button is safe
    header('Location: paymentdetail.php', true, 303);
    exit;
}

// ── Card payment POST (has cardnumber) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    $payment_method = in_array($_POST['payment_method'], ['card', 'kharcha', 'esewa', 'khalti'])
        ? $_POST['payment_method'] : 'card';
}

$vehicle_id   = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date  = $_SESSION['payment_pickup']      ?? '';
$dropoff_date = $_SESSION['payment_dropoff']     ?? '';
$days         = $_SESSION['payment_days']        ?? 0;
$pickup_time  = $_SESSION['payment_pickup_time'] ?? '09:00:00';
$return_time  = $_SESSION['payment_return_time'] ?? '18:00:00';

if (!$vehicle_id) {
    header('Location: invoice.php');
    exit;
}

// ── Fetch vehicle ─────────────────────────────────────────────────
$sql  = "SELECT v.*, u.first_name AS owner_name
          FROM vehicles v
          JOIN users u ON v.user_id = u.id
          WHERE v.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

$basicprice    = 500;
$price_per_day = (float) $vehicle['price_per_day'];
$totalprice    = ($price_per_day * $days) + $basicprice;

// ════════════════════════════════════════════════════════════════
//  CARD FORM SUBMISSION
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cardnumber'])) {

    $cardholdername = filter_input(INPUT_POST, 'cardholder-name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cardnumber     = filter_input(INPUT_POST, 'cardnumber',       FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expiry_month   = filter_input(INPUT_POST, 'expiry_month',     FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expiry_year    = filter_input(INPUT_POST, 'expiry_year',      FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cvv            = filter_input(INPUT_POST, 'cvv',              FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $street         = filter_input(INPUT_POST, 'street',           FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city           = filter_input(INPUT_POST, 'city',             FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zip            = filter_input(INPUT_POST, 'zip',              FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $cardnumber_clean = preg_replace('/\s+/', '', $cardnumber);
    $card_type        = getCardType($cardnumber_clean);
    $is_kharcha       = ($card_type === 'Kharcha') || ($payment_method === 'kharcha');

    // ── Shared validations ────────────────────────────────────────
    if (empty($cardholdername)) {
        $errors['cardholdername'] = "Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $cardholdername)) {
        $errors['cardholdername'] = "Name can only contain letters and spaces.";
    }

    if (empty($cardnumber)) {
        $errors['cardnumber'] = "Card number is required.";
    } elseif (!preg_match('/^\d{16}$/', $cardnumber_clean)) {
        $errors['cardnumber'] = "Card number should be exactly 16 digits long.";
    } elseif (!$is_kharcha && !isValidLuhn($cardnumber_clean)) {
        $errors['cardnumber'] = "Invalid card number. Please check for typos.";
    }

    if (empty($cvv)) {
        $errors['cvv'] = "CVV is required.";
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = "CVV must be exactly 3 digits.";
    }

    // Expiry & billing only for regular cards
    if (!$is_kharcha) {
        if (empty($expiry_month) || empty($expiry_year)) {
            $errors['expirydate'] = "Expiry date is required.";
        } else {
            $expiryMonth  = (int) $expiry_month;
            $expiryYear   = (int) $expiry_year;
            $currentMonth = (int) date('m');
            $currentYear  = (int) date('Y');
            if ($expiryYear < $currentYear || ($expiryYear === $currentYear && $expiryMonth < $currentMonth)) {
                $errors['expirydate'] = "Card has expired.";
            }
        }
        if (empty($street)) $errors['street'] = "Street is required.";
        if (empty($city))   $errors['city']   = "City is required.";
        if (empty($zip)) {
            $errors['zip'] = "Zip code is required.";
        } elseif (!preg_match('/^\d{5}$/', $zip)) {
            $errors['zip'] = "Zip code must be 5 digits.";
        }
    }

    // ── Kharcha card charge ───────────────────────────────────────
    $kharcha_transaction_id = null;
    if (empty($errors) && $is_kharcha) {
        $remarks  = "TD Rentals – {$vehicle['model']} ({$pickup_date} to {$dropoff_date})";
        $response = kharchaChargeCard($cardnumber_clean, $cvv, $totalprice, $remarks);

        if (!$response['success']) {
            $kharcha_errors = [
                'CARD_NOT_FOUND'       => "Your Kharcha card was not found. Please check the card number.",
                'INVALID_CVV'          => "CVV verification failed. Please check your Kharcha card CVV.",
                'CARD_INACTIVE'        => "Your Kharcha card is " . ($response['card_status'] ?? 'inactive') . ". Contact Kharcha support.",
                'DAILY_LIMIT_EXCEEDED' => "Your Kharcha daily spend limit is reached.",
                'INSUFFICIENT_BALANCE' => "Insufficient Kharcha wallet balance. Please top up and try again.",
                'SELF_CHARGE'          => "Cannot charge your own Kharcha account.",
                'GATEWAY_ERROR'        => $response['message'] ?? "Could not connect to Kharcha gateway.",
            ];
            $code              = $response['error_code'] ?? 'GATEWAY_ERROR';
            $errors['kharcha'] = $kharcha_errors[$code] ?? ($response['message'] ?? "Kharcha payment failed.");
        } else {
            $kharcha_transaction_id = $response['transaction']['transaction_id'] ?? null;
        }
    }

    // ── Process booking + discount + milestones ───────────────────
    if (empty($errors)) {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) die("User not logged in.");

        $discount_code    = filter_input(INPUT_POST, 'applied_discount_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $discount_amount  = 0.00;
        $discount_code_id = null;

        if (!empty($discount_code)) {
            $dc_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
            $dc_stmt->bind_param("s", $discount_code);
            $dc_stmt->execute();
            $dc_result = $dc_stmt->get_result();

            if ($dc_result->num_rows > 0) {
                $code_data = $dc_result->fetch_assoc();
                $code_id   = $code_data['id'];
                $dc_stmt->close();

                if ($code_data['expires_at'] && $code_data['expires_at'] < date('Y-m-d')) {
                    $errors['discount'] = "This discount code has expired.";
                } elseif ($code_data['max_uses'] !== null && $code_data['used_count'] >= $code_data['max_uses']) {
                    $errors['discount'] = "This code has reached its maximum uses.";
                } elseif ($code_data['owner_user_id'] !== null && (int) $code_data['owner_user_id'] !== (int) $user_id) {
                    $errors['discount'] = "This is a personal discount code and cannot be used by you.";
                } else {
                    $use_stmt = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
                    $use_stmt->bind_param("ii", $user_id, $code_id);
                    $use_stmt->execute();
                    if ($use_stmt->get_result()->num_rows > 0) {
                        $errors['discount'] = "You have already used this discount code.";
                    } else {
                        if ($code_data['type'] === 'flat') {
                            $discount_amount = min($code_data['discount_flat'], $totalprice);
                        } else {
                            $discount_amount = ($totalprice * $code_data['discount_percent']) / 100;
                        }
                        $totalprice      -= $discount_amount;
                        $discount_code_id = $code_id;
                    }
                    $use_stmt->close();
                }
            } else {
                $dc_stmt->close();
                $errors['discount'] = "Invalid or inactive discount code.";
            }
        }

        if (empty($errors)) {
            // ── Insert booking ──────────────────────────────────────
            $insert_sql  = "INSERT INTO bookings
                                (user_id, vehicle_id, start_date, end_date, pickup_time, return_time,
                                 total_price, status, payment_status, discount_code, discount_amount, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'confirmed', 'paid', ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param(
                "iissssdsd",
                $user_id, $vehicle_id,
                $pickup_date, $dropoff_date,
                $pickup_time, $return_time,
                $totalprice, $discount_code, $discount_amount
            );

            if ($insert_stmt->execute()) {
                $booking_id = $insert_stmt->insert_id;

                // ── Insert transaction ────────────────────────────────
                $card_last4      = substr($cardnumber_clean, -4);
                $transaction_ref = $kharcha_transaction_id ?? ('TXN-' . strtoupper(bin2hex(random_bytes(8))));
                $db_card_type    = $is_kharcha ? 'Kharcha' : $card_type;
                $db_pay_method   = $is_kharcha ? 'kharcha_card' : 'card';

                $txn_stmt = $conn->prepare(
                    "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                );
                $txn_stmt->bind_param("iidssss", $booking_id, $user_id, $totalprice, $db_pay_method, $card_last4, $db_card_type, $transaction_ref);
                $txn_stmt->execute();

                // ── Discount usage + Gold reset ───────────────────────
                if ($discount_code_id) {
                    $du_stmt = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
                    $du_stmt->bind_param("ii", $user_id, $discount_code_id);
                    $du_stmt->execute();
                    $du_stmt->close();
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

                // ── Milestone progression ─────────────────────────────
                $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
                $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
                if ($res && $res->num_rows > 0) {
                    $u_data        = $res->fetch_assoc();
                    $rentals       = (int) $u_data['completed_rentals'];
                    $current_medal = $u_data['medal'];
                    $new_medal         = $current_medal;
                    $milestone_code    = '';
                    $milestone_percent = 0;

                    if ($rentals >= 3 && $current_medal === 'NONE') {
                        $new_medal = 'BRONZE'; $milestone_code = 'BRONZE5'; $milestone_percent = 5;
                    } elseif ($rentals >= 7 && $current_medal === 'BRONZE') {
                        $new_medal = 'SILVER'; $milestone_code = 'SILVER10'; $milestone_percent = 10;
                    } elseif ($rentals >= 15 && $current_medal === 'SILVER') {
                        $new_medal = 'GOLD'; $milestone_code = 'GOLD20'; $milestone_percent = 20;
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

                // ── Confirmation email ────────────────────────────────
                $user_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_data  = $user_stmt->get_result()->fetch_assoc();
                $email      = $user_data['email']      ?? '';
                $first_name = $user_data['first_name'] ?? '';
                $last_name  = $user_data['last_name']  ?? '';

                $payment_label    = $is_kharcha ? "Kharcha Card (ending {$card_last4})" : "{$card_type} Card (ending {$card_last4})";
                $kharcha_txn_line = $kharcha_transaction_id ? "<p>Kharcha Transaction ID: {$kharcha_transaction_id}</p>" : "";
                $savings_msg      = '';
                if ($discount_amount > 0) {
                    $savings_msg = "<p style='color:#2ecc71;'><strong>You saved NPR "
                        . number_format($discount_amount, 2) . " with code " . htmlspecialchars($discount_code) . "!</strong></p>";
                }

                try {
                    $invoice_data = [
                        'booking_id'      => $booking_id,
                        'first_name'      => $first_name,
                        'email'           => $email,
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
                    $mail->addAddress($email, $first_name . ' ' . $last_name);
                    $mail->Subject = 'Booking Confirmation – TD Rentals';
                    $mail->isHTML(true);
                    $mail->Body = "
                        <p>Hi {$first_name},</p>
                        <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
                        <p>Pickup date: {$pickup_date}</p>
                        <p>Dropoff date: {$dropoff_date}</p>
                        <p>Total Paid: NPR " . number_format($totalprice, 2) . "</p>
                        <p>Payment Method: {$payment_label}</p>
                        {$kharcha_txn_line}
                        {$savings_msg}
                        <p>Please find your invoice attached.</p>
                        <p>Thank you for choosing TD Rentals 🚀</p>
                        <p>Best Regards,<br>TD Rentals Team</p>
                    ";
                    $mail->AltBody = "Hi {$first_name}, your booking for {$vehicle['model']} is confirmed.";
                    $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
                    if (isNotificationEnabled($conn, $user_id)) {
                        $mail->send();
                    }
                } catch (Throwable $e) {
                    error_log("Booking email failed: " . $e->getMessage());
                }

                unset($_SESSION['payment_vehicle_id'], $_SESSION['payment_pickup'],
                      $_SESSION['payment_dropoff'],    $_SESSION['payment_days']);
                header("Location: bookingconfirmed.php?id=" . $booking_id);
                exit;
            } else {
                $errors['database'] = "Failed to create booking. Please try again.";
            }
        }
    }
}

// ── Fetch available discount codes for sidebar ────────────────────
$available_codes = [];
$uid = $_SESSION['user_id'] ?? 0;
if ($uid) {
    $u_res   = $conn->query("SELECT medal FROM users WHERE id = $uid");
    $u_row   = $u_res->fetch_assoc();
    $u_medal = $u_row['medal'] ?? 'NONE';

    $codes_sql = "
        SELECT c.code, c.discount_percent, c.owner_user_id
        FROM discount_codes c
        WHERE c.is_active = 1
          AND (c.owner_user_id IS NULL OR c.owner_user_id = ?)
          AND c.id NOT IN (SELECT code_id FROM discount_code_uses WHERE user_id = ?)
        ORDER BY c.discount_percent DESC
    ";
    $c_stmt = $conn->prepare($codes_sql);
    $c_stmt->bind_param("ii", $uid, $uid);
    $c_stmt->execute();
    $raw_codes = $c_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $c_stmt->close();

    foreach ($raw_codes as $rc) {
        if ($rc['owner_user_id'] === null) {
            $cname = strtoupper($rc['code']);
            if (strpos($cname, 'BRONZE') !== false && $u_medal === 'NONE')                    continue;
            if (strpos($cname, 'SILVER') !== false && in_array($u_medal, ['NONE', 'BRONZE'])) continue;
            if (strpos($cname, 'GOLD')   !== false && $u_medal !== 'GOLD')                    continue;
        }
        $available_codes[] = $rc;
    }
}
?>

<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/paymentdetail.css">
<link rel="stylesheet" href="../../assets/css/kharcha_payment.css">
<script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>

<div id="payment-page">

    <!-- ════════════ LEFT: PAYMENT FORM ════════════ -->
    <div id="payment-form-section">

        <h2 class="subtitle">SECURE CHECKOUT</h2>
        <h1 class="title">PAYMENT DETAILS</h1>

        <!-- ── Payment Method Tabs ── -->
        <div class="payment-tabs" id="payment-tabs">
            <button type="button" class="payment-tab active" data-tab="card" onclick="switchTab('card')">
                <i class="fa-solid fa-credit-card"></i>&nbsp; Card
            </button>
            <button type="button" class="payment-tab" data-tab="kharcha" onclick="switchTab('kharcha')">
                <img src="https://kharcha-omega.vercel.app/assets/KharchaLogo-D0Y-PzIR.svg" style="height:14px;">
                <span class="kharcha-tab-label">Kharcha</span>
            </button>
            <button type="button" class="payment-tab" data-tab="esewa" onclick="switchTab('esewa')">
                <img src="../../assets/images/eSewa.png" style="height: 16px; ">eSewa
            </button>
            <button type="button" class="payment-tab" data-tab="khalti" onclick="switchTab('khalti')">
                <img src="../../assets/images/khalti.png" style="height: 18px;"> Khalti
            </button>
        </div>

        <!-- ── Global error alerts ── -->
        <?php if (!empty($errors['kharcha'])): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= e($errors['kharcha']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors['database'])): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= e($errors['database']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors['discount'])): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i> <?= e($errors['discount']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['khalti_error'])): ?>
            <div class="alert-error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($_SESSION['khalti_error']) ?>
            </div>
            <?php unset($_SESSION['khalti_error']); ?>
        <?php endif; ?>

        <form action="paymentdetail.php" method="POST" id="payment-form">
            <input type="hidden" name="vehicle_id"            value="<?= $vehicle_id ?>">
            <input type="hidden" name="pickup_date"           value="<?= htmlspecialchars($pickup_date) ?>">
            <input type="hidden" name="dropoff_date"          value="<?= htmlspecialchars($dropoff_date) ?>">
            <input type="hidden" name="days"                  value="<?= $days ?>">
            <input type="hidden" name="payment_method"        id="payment_method_input" value="<?= htmlspecialchars($payment_method) ?>">
            <input type="hidden" name="applied_discount_code" id="applied_discount_code"
                   value="<?= htmlspecialchars($_POST['applied_discount_code'] ?? '') ?>">

            <!-- ══════════════════════════════════════════════════
                 TAB 1: Credit / Debit Card
                 ══════════════════════════════════════════════════ -->
            <div id="tab-card" class="tab-content">

                <section class="card-section">
                    <h3 class="section-title">
                        <i class="fa-solid fa-credit-card icon"></i>CREDIT CARD INFORMATION
                    </h3>

                    <div class="form-group">
                        <label class="labels" for="cardholder-name">Cardholder Name</label><br>
                        <input type="text" name="cardholder-name" id="cardholder-name" class="input-field"
                               placeholder="James T. Sterling"
                               value="<?= htmlspecialchars($cardholdername ?? '') ?>">
                        <?php if (!empty($errors['cardholdername'])): ?>
                            <span class="field-error"><?= e($errors['cardholdername']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="card-number" class="labels">Card Number</label><br>
                        <div class="kharcha-card-input-wrap">
                            <input type="text" name="cardnumber" id="card-number" class="input-field"
                                   placeholder="4111 1111 1111 1111"
                                   value="<?= htmlspecialchars($cardnumber ?? '') ?>">
                            <span class="card-type-badge card-type-badge--visa"       id="badge-visa"       style="display:none;"><i class="fa-brands fa-cc-visa"></i> Visa</span>
                            <span class="card-type-badge card-type-badge--mastercard" id="badge-mastercard" style="display:none;"><i class="fa-brands fa-cc-mastercard"></i> MC</span>
                            <span class="card-type-badge card-type-badge--amex"       id="badge-amex"       style="display:none;"><i class="fa-brands fa-cc-amex"></i> Amex</span>
                            <span class="card-type-badge card-type-badge--kharcha"    id="badge-kharcha"    style="display:none;"><i class="fa-solid fa-circle-check"></i> Kharcha</span>
                        </div>
                        <?php if (!empty($errors['cardnumber']) && $payment_method === 'card'): ?>
                            <span class="field-error"><?= e($errors['cardnumber']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="labels">Expiry Date</label><br>
                            <div class="expiry-dropdowns">
                                <select name="expiry_month" id="expiry-month" class="input-field">
                                    <option value="" disabled <?= empty($expiry_month) ? 'selected' : '' ?>>Month</option>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= sprintf('%02d', $m) ?>"
                                            <?= ($expiry_month == sprintf('%02d', $m)) ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                                <select name="expiry_year" id="expiry-year" class="input-field">
                                    <option value="" disabled <?= empty($expiry_year) ? 'selected' : '' ?>>Year</option>
                                    <?php for ($y = (int) date('Y'); $y <= (int) date('Y') + 10; $y++): ?>
                                        <option value="<?= $y ?>" <?= ($expiry_year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <?php if (!empty($errors['expirydate'])): ?>
                                <span class="field-error"><?= e($errors['expirydate']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="cvv">CVV / CVC</label><br>
                            <input type="password" name="cvv" id="cvv" class="input-field"
                                   placeholder="***" maxlength="3" pattern="\d{3}" inputmode="numeric">
                            <?php if (!empty($errors['cvv']) && $payment_method === 'card'): ?>
                                <span class="field-error"><?= e($errors['cvv']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="billing-section">
                    <h3 class="section-title">
                        <i class="fa-solid fa-location-dot icon"></i>BILLING ADDRESS
                    </h3>
                    <div class="form-group">
                        <label for="street" class="labels">Street Address</label><br>
                        <input type="text" name="street" id="street" class="input-field"
                               placeholder="123 Performance Way"
                               value="<?= htmlspecialchars($street ?? '') ?>">
                        <?php if (!empty($errors['street'])): ?>
                            <span class="field-error"><?= e($errors['street']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="labels">City</label><br>
                            <input type="text" name="city" id="city" class="input-field" placeholder="Kathmandu"
                                   value="<?= htmlspecialchars($city ?? '') ?>">
                            <?php if (!empty($errors['city'])): ?>
                                <span class="field-error"><?= e($errors['city']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="zip" class="labels">Zip Code</label><br>
                            <input type="text" name="zip" id="zip" class="input-field" placeholder="44600"
                                   value="<?= htmlspecialchars($zip ?? '') ?>"
                                   maxlength="5" pattern="\d{5}" inputmode="numeric">
                            <?php if (!empty($errors['zip'])): ?>
                                <span class="field-error"><?= e($errors['zip']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

            </div><!-- #tab-card -->

            <!-- ══════════════════════════════════════════════════
                 TAB 2: Kharcha (Portal + Dynamic QR)
                 ══════════════════════════════════════════════════ -->
            <div id="tab-kharcha" class="tab-content" style="display:none;">
                <div class="kharcha-card-panel">

                    <div class="kharcha-subtabs" id="kharcha-subtabs">
                        <button type="button" class="kh-subtab active" data-khtab="kh-portal" onclick="switchKharchaTab('kh-portal')">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Payment Portal
                        </button>
                        <button type="button" class="kh-subtab" data-khtab="kh-qr" onclick="switchKharchaTab('kh-qr')">
                            <i class="fa-solid fa-qrcode"></i> Dynamic QR
                        </button>
                    </div>

                    <!-- Kharcha Portal -->
                    <div id="kh-portal" class="kh-subtab-content">
                        <div class="kharcha-banner kharcha-banner--portal">
                            <div class="kharcha-banner-icon kharcha-banner-icon--portal">
                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            </div>
                            <div>
                                <p class="kharcha-banner-title">Kharcha Payment Portal</p>
                                <p class="kharcha-banner-sub">
                                    You'll be securely redirected to Kharcha's hosted checkout.<br>
                                    Log in with your Kharcha account and confirm the payment.
                                </p>
                            </div>
                        </div>
                        <div class="portal-amount-display">
                            <span class="portal-amount-label">Amount to Pay</span>
                            <span class="portal-amount-value" id="kharcha-portal-amount">NPR <?= number_format($totalprice, 0) ?></span>
                        </div>
                        <?php if (!empty($_SESSION['portal_error'])): ?>
                            <div class="alert-error">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                <?= htmlspecialchars($_SESSION['portal_error']) ?>
                            </div>
                            <?php unset($_SESSION['portal_error']); ?>
                        <?php endif; ?>
                        <button type="button" class="portal-pay-btn" onclick="submitKharchaPortal()">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i>
                            Continue to Kharcha Portal
                        </button>
                        <div class="kharcha-info-box" style="margin-top:16px;">
                            <i class="fa-solid fa-lock"></i>
                            <p>Your payment credentials are handled entirely by Kharcha.
                               TD Rentals never receives your Kharcha login or wallet details.</p>
                        </div>
                    </div><!-- #kh-portal -->

                    <!-- Kharcha Dynamic QR -->
                    <div id="kh-qr" class="kh-subtab-content" style="display:none;">
                        <div class="kharcha-banner kharcha-banner--qr">
                            <div class="kharcha-banner-icon kharcha-banner-icon--qr">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <p class="kharcha-banner-title">Dynamic QR Code</p>
                                <p class="kharcha-banner-sub">
                                    Generate a QR code and scan it with the Kharcha app.<br>
                                    Your booking is confirmed automatically once payment is detected.
                                </p>
                            </div>
                        </div>
                        <div class="qr-panel" id="qr-panel">
                            <div class="qr-idle" id="qr-idle">
                                <div class="qr-idle-icon"><i class="fa-solid fa-qrcode"></i></div>
                                <p class="qr-idle-text">Click below to generate your payment QR code</p>
                                <div class="qr-amount-badge">NPR <?= number_format($totalprice, 0) ?></div>
                                <button type="button" class="qr-generate-btn" id="qr-generate-btn" onclick="generateQRCode()">
                                    <i class="fa-solid fa-bolt"></i> Generate QR Code
                                </button>
                            </div>
                            <div class="qr-active" id="qr-active" style="display:none;">
                                <div class="qr-code-wrap" id="qr-code-wrap"></div>
                                <div class="qr-status-row">
                                    <span class="qr-status-dot" id="qr-status-dot"></span>
                                    <span class="qr-status-text" id="qr-status-text">Waiting for payment…</span>
                                </div>
                                <div class="qr-timer-row">
                                    <i class="fa-regular fa-clock"></i>
                                    <span id="qr-timer">5:00</span> remaining
                                </div>
                                <p class="qr-scan-hint">
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                    Open the <strong>Kharcha app</strong>, tap <em>Scan &amp; Pay</em>, then scan this QR code.
                                </p>
                                <button type="button" class="qr-refresh-btn" id="qr-refresh-btn" onclick="generateQRCode()" style="display:none;">
                                    <i class="fa-solid fa-rotate-right"></i> Generate New QR
                                </button>
                            </div>
                            <div class="qr-success" id="qr-success" style="display:none;">
                                <div class="qr-success-icon"><i class="fa-solid fa-circle-check"></i></div>
                                <p class="qr-success-title">Payment Received!</p>
                                <p class="qr-success-sub">Finalizing your booking…</p>
                                <div class="qr-success-spinner"></div>
                            </div>
                        </div>
                        <div class="kharcha-info-box" style="margin-top:8px;">
                            <i class="fa-solid fa-shield-halved"></i>
                            <p>QR sessions expire in 5 minutes. Generate a new one if it expires.</p>
                        </div>
                    </div><!-- #kh-qr -->

                </div>
            </div><!-- #tab-kharcha -->

            <!-- ══════════════════════════════════════════════════
                 TAB 3: eSewa
                 ══════════════════════════════════════════════════ -->
            <div id="tab-esewa" class="tab-content" style="display:none;">
                <div class="esewa-panel">

                    <div class="esewa-banner">
                        <div class="esewa-banner-icon">
                            <img src="../../assets/images/eSewa.png" style="height: 32px;" alt="eSewa">
                        </div>
                        <div>
                            <p class="esewa-banner-title">eSewa Digital Wallet</p>
                            <p class="esewa-banner-sub">
                                You'll be securely redirected to eSewa's hosted checkout.<br>
                                Log in with your eSewa account, confirm the amount, and pay instantly.
                            </p>
                        </div>
                    </div>

                    <div class="esewa-amount-display">
                        <span class="esewa-amount-label">Amount to Pay</span>
                        <span class="esewa-amount-value" id="esewa-amount-display">NPR <?= number_format($totalprice, 0) ?></span>
                    </div>

                    <button type="button" class="esewa-pay-btn" id="esewa-pay-btn" onclick="submitESewa()">
                        <img src="../../assets/images/eSewa.png" alt="eSewa" style="height:20px;">
                        Pay with eSewa &nbsp;·&nbsp; NPR <?= number_format($totalprice, 0) ?>
                    </button>

                    <div class="esewa-info-box">
                        <i class="fa-solid fa-lock"></i>
                        <p>Your payment credentials are handled entirely by eSewa.
                           TD Rentals never receives your eSewa PIN or wallet details.
                           You'll be redirected back automatically after payment.</p>
                    </div>

                    <div class="esewa-info-box" style="border-color:rgba(96,187,70,0.15);">
                        <i class="fa-solid fa-circle-info"></i>
                        <p>
                            <strong style="color:#78d458;">Sandbox test credentials:</strong><br>
                            eSewa ID: <code>9806800001</code> &nbsp;|&nbsp;
                            Password: <code>Nepal@123</code> &nbsp;|&nbsp;
                            MPIN: <code>1122</code>
                        </p>
                    </div>

                </div>
            </div><!-- #tab-esewa -->

            <!-- ══════════════════════════════════════════════════
                 TAB 4: Khalti  (Portal + QR sub-tabs)
                 ══════════════════════════════════════════════════ -->
            <div id="tab-khalti" class="tab-content" style="display:none;">
                <div class="khalti-panel">

                    <!-- Sub-tab switcher -->
                    <div class="khalti-subtabs" id="khalti-subtabs">
                        <button type="button" class="kh2-subtab active" data-kh2tab="kh2-portal" onclick="switchKhaltiTab('kh2-portal')">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Payment Portal
                        </button>
                        <button type="button" class="kh2-subtab" data-kh2tab="kh2-qr" onclick="switchKhaltiTab('kh2-qr')">
                            <i class="fa-solid fa-qrcode"></i> QR Code
                        </button>
                    </div>

                    <!-- ── Portal sub-tab ── -->
                    <div id="kh2-portal" class="kh2-subtab-content">
                        <div class="khalti-banner">
                            <div class="khalti-banner-icon">
                                <img src="../../assets/images/khalti.png" style="height: 32px;">
                            </div>
                            <div>
                                <p class="khalti-banner-title">Khalti Payment Portal</p>
                                <p class="khalti-banner-sub">
                                    You'll be securely redirected to Khalti's hosted checkout.<br>
                                    Log in with your Khalti account, confirm the amount, and pay instantly.
                                </p>
                            </div>
                        </div>

                        <div class="khalti-amount-display">
                            <span class="khalti-amount-label">Amount to Pay</span>
                            <span class="khalti-amount-value" id="khalti-amount-display">NPR <?= number_format($totalprice, 0) ?></span>
                        </div>

                        <button type="button" class="khalti-pay-btn" id="khalti-pay-btn" onclick="submitKhalti()">
                            <img src="../../assets/images/khaltilogo.png" alt="Khalti"
                                 style="height:20px; filter:brightness(10);">
                            Pay with Khalti &nbsp;·&nbsp; NPR <?= number_format($totalprice, 0) ?>
                        </button>

                        <div class="khalti-info-box">
                            <i class="fa-solid fa-lock"></i>
                            <p>Your payment credentials are handled entirely by Khalti.
                               TD Rentals never receives your Khalti PIN or wallet details.
                               You'll be redirected back automatically after payment.</p>
                        </div>

                        <div class="khalti-info-box" style="border-color:rgba(229,201,126,0.2);">
                            <i class="fa-solid fa-circle-info" style="color:#b89a50;"></i>
                            <p>
                                <strong style="color:#c4aa6a;">Sandbox test credentials:</strong><br>
                                Khalti ID: <code>9800000001</code> &nbsp;|&nbsp;
                                MPIN: <code>1111</code> &nbsp;|&nbsp;
                                OTP: <code>987654</code>
                            </p>
                        </div>
                    </div><!-- #kh2-portal -->

                    <!-- ── QR sub-tab ── -->
                    <div id="kh2-qr" class="kh2-subtab-content" style="display:none;">
                        <div class="khalti-banner">
                            <div class="khalti-banner-icon">
                                <i class="fa-solid fa-qrcode"></i>
                            </div>
                            <div>
                                <p class="khalti-banner-title">Khalti QR Code</p>
                                <p class="khalti-banner-sub">
                                    Generate a QR code and scan it with the Khalti app.<br>
                                    Your booking is confirmed automatically once payment is detected.
                                </p>
                            </div>
                        </div>

                        <div class="qr-panel" id="khalti-qr-panel">
                            <!-- Idle state -->
                            <div class="qr-idle" id="khalti-qr-idle">
                                <div class="qr-idle-icon" style="color:#e5c97e;"><i class="fa-solid fa-qrcode"></i></div>
                                <p class="qr-idle-text">Click below to generate your Khalti payment QR</p>
                                <div class="qr-amount-badge" style="background:rgba(229,201,126,0.12); border-color:rgba(229,201,126,0.3); color:#e5c97e;">NPR <?= number_format($totalprice, 0) ?></div>
                                <button type="button" class="qr-generate-btn" id="khalti-qr-generate-btn"
                                        style="background:linear-gradient(135deg,#b8891a,#e5c97e); color:#1a1a1a;"
                                        onclick="generateKhaltiQR()">
                                    <i class="fa-solid fa-bolt"></i> Generate QR Code
                                </button>
                            </div>

                            <!-- Active state -->
                            <div class="qr-active" id="khalti-qr-active" style="display:none;">
                                <div class="qr-code-wrap" id="khalti-qr-code-wrap"></div>
                                <div class="qr-status-row">
                                    <span class="qr-status-dot" id="khalti-qr-status-dot"></span>
                                    <span class="qr-status-text" id="khalti-qr-status-text">Waiting for payment…</span>
                                </div>
                                <div class="qr-timer-row">
                                    <i class="fa-regular fa-clock" style="color:#e5c97e;"></i>
                                    <span id="khalti-qr-timer">5:00</span> remaining
                                </div>
                                <p class="qr-scan-hint">
                                    <i class="fa-solid fa-mobile-screen-button"></i>
                                    Open the <strong>Khalti app</strong>, tap <em>Scan QR</em>, then scan this code.
                                </p>
                                <button type="button" class="qr-refresh-btn" id="khalti-qr-refresh-btn"
                                        onclick="generateKhaltiQR()" style="display:none; border-color:rgba(229,201,126,0.4); color:#e5c97e;">
                                    <i class="fa-solid fa-rotate-right"></i> Generate New QR
                                </button>
                            </div>

                            <!-- Success state -->
                            <div class="qr-success" id="khalti-qr-success" style="display:none;">
                                <div class="qr-success-icon" style="color:#e5c97e;"><i class="fa-solid fa-circle-check"></i></div>
                                <p class="qr-success-title">Payment Received!</p>
                                <p class="qr-success-sub">Finalizing your booking…</p>
                                <div class="qr-success-spinner"></div>
                            </div>
                        </div>

                        <div class="khalti-info-box" style="margin-top:8px;">
                            <i class="fa-solid fa-shield-halved"></i>
                            <p>QR sessions expire in 5 minutes. Generate a new one if it expires.
                               Payment is confirmed in real-time via the Khalti gateway.</p>
                        </div>
                    </div><!-- #kh2-qr -->

                </div>
            </div><!-- #tab-khalti -->

            <!-- Main pay button (card tab only) -->
            <button id="pay-button" class="primary-btn" type="submit">
                CONFIRM &amp; PAY &nbsp;NPR <?= number_format($totalprice, 0) ?>
            </button>

        </form>

        <p class="security-note">
            Your transaction is encrypted and secured via TLS 1.3 protocol.
        </p>

    </div><!-- #payment-form-section -->

    <!-- ════════════ RIGHT: BOOKING SUMMARY ════════════ -->
    <div id="summary-section">

        <div class="car-info">
            <img class="car-image"
                 src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/car_1775474575.jpg') ?>"
                 alt="">
            <div class="car-image-overlay"></div>
            <h2 class="car-title"><?= htmlspecialchars($vehicle['model']) ?></h2>
        </div>

        <div class="booking-panel">

            <div class="booking-meta">
                <div>
                    <p class="booking-meta-label">Reservation Dates</p>
                    <p class="booking-meta-value">
                        📅 <?= htmlspecialchars($pickup_date) ?> at
                        <strong><?= date('g:i A', strtotime($pickup_time)) ?></strong>
                    </p>
                    <p class="booking-meta-value">
                        🔁 <?= htmlspecialchars($dropoff_date) ?> at
                        <strong><?= date('g:i A', strtotime($return_time)) ?></strong>
                    </p>
                </div>
                <div style="text-align:right;">
                    <p class="booking-meta-label">Duration</p>
                    <p class="booking-meta-value"><?= $days ?> DAYS</p>
                </div>
            </div>

            <p class="price-breakdown-label">Price Breakdown</p>

            <div class="price-row">
                <span class="price-row-name">Daily Rate (NPR <?= htmlspecialchars($vehicle['price_per_day']) ?> × <?= $days ?>)</span>
                <span class="price-row-amount">NPR <?= number_format($vehicle['price_per_day'] * $days, 2) ?></span>
            </div>
            <div class="price-row">
                <span class="price-row-name">Insurance &amp; Protection</span>
                <span class="price-row-amount">NPR 350.00</span>
            </div>
            <div class="price-row">
                <span class="price-row-name">Premium Handling Fee</span>
                <span class="price-row-amount">NPR 150.00</span>
            </div>

            <hr class="price-divider">

            <!-- ── Discount Code ── -->
            <div class="discount-section" style="margin-bottom:20px;">
                <p class="price-breakdown-label">Discount Code</p>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="discount-input" class="input-field" placeholder="Enter code" style="margin-bottom:0;">
                    <button type="button" id="apply-discount-btn" class="primary-btn"
                            style="width:auto; padding:0 15px; font-size:0.9rem;">APPLY</button>
                </div>
                <p id="discount-message" style="margin-top:5px; font-size:0.85rem;"></p>
                <div style="margin-top:10px; font-size:0.8rem; color:#888;">
                    <span style="display:block; margin-bottom:6px; color:#aaa; text-transform:uppercase; font-weight:600; font-size:0.7rem;">Available Rewards:</span>
                    <?php if (!empty($available_codes)): ?>
                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <?php foreach ($available_codes as $ac): ?>
                                <span class="available-code-badge"
                                      onclick="document.getElementById('discount-input').value='<?= htmlspecialchars($ac['code']) ?>'"
                                      style="background:rgba(224,48,48,0.1); border:1px solid rgba(224,48,48,0.3); color:var(--red); padding:4px 8px; border-radius:4px; cursor:pointer; transition:all 0.2s; font-weight:600;"
                                      onmouseover="this.style.background='rgba(224,48,48,0.2)'"
                                      onmouseout="this.style.background='rgba(224,48,48,0.1)'">
                                    <?= htmlspecialchars($ac['code']) ?> (-<?= floatval($ac['discount_percent']) ?>%)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span style="font-style:italic; opacity:0.7;">No unused codes available right now.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Discount row -->
            <div class="price-row" id="discount-row" style="display:none;">
                <span class="price-row-name" id="discount-name" style="color:#2ecc71;">Discount</span>
                <span class="price-row-amount" id="discount-amount-display" style="color:#2ecc71;">- NPR 0.00</span>
            </div>

            <div class="total-row">
                <span class="total-label">Total Due</span>
                <span class="total-amount" id="final-total-display"
                      data-base-total="<?= $totalprice ?>">NPR <?= number_format($totalprice, 2) ?></span>
            </div>

            <div class="notice-box">
                <i class="fa-solid fa-circle-info notice-icon"></i>
                <p class="notice-text">
                    Free cancellation until 48 hours prior to pickup. A security deposit
                    of NPR 500 will be held during the rental period.
                </p>
            </div>

        </div>
    </div><!-- #summary-section -->

</div><!-- #payment-page -->

<?php require '../../includes/footer.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// ══ TAB SWITCHING ══════════════════════════════════════════════
function switchTab(tab) {
    document.querySelectorAll('.payment-tab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(p => p.style.display = 'none');
    document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
    document.getElementById('tab-' + tab).style.display = 'block';
    document.getElementById('payment_method_input').value = tab;
    const payBtn = document.getElementById('pay-button');
    if (payBtn) payBtn.style.display = (tab !== 'card') ? 'none' : 'flex';
}

function switchKharchaTab(subtab) {
    document.querySelectorAll('.kh-subtab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.kh-subtab-content').forEach(p => p.style.display = 'none');
    document.querySelector('[data-khtab="' + subtab + '"]').classList.add('active');
    document.getElementById(subtab).style.display = 'block';
}

function switchKhaltiTab(subtab) {
    document.querySelectorAll('.kh2-subtab').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.kh2-subtab-content').forEach(p => p.style.display = 'none');
    document.querySelector('[data-kh2tab="' + subtab + '"]').classList.add('active');
    document.getElementById(subtab).style.display = 'block';
}

// Restore tab after POST error
(function () {
    const m = <?= json_encode($payment_method) ?>;
    if (m !== 'card') switchTab(m);
    else { const b = document.getElementById('pay-button'); if (b) b.style.display = 'flex'; }
})();

// ══ CARD NUMBER FORMATTING + BADGE ════════════════════════════
const cardInput = document.getElementById('card-number');
const cardBadges = {
    visa: document.getElementById('badge-visa'),
    mastercard: document.getElementById('badge-mastercard'),
    amex: document.getElementById('badge-amex'),
    kharcha: document.getElementById('badge-kharcha'),
};

function detectCardType(v) {
    if (/^733333/.test(v))                                                        return 'kharcha';
    if (/^3[47]/.test(v))                                                         return 'amex';
    if (/^5[1-5]/.test(v) || /^2(2[2-9][1-9]|[3-6]\d{2}|7[01]\d|720)/.test(v)) return 'mastercard';
    if (/^4/.test(v))                                                             return 'visa';
    return null;
}

function showCardBadge(type) {
    Object.keys(cardBadges).forEach(k => {
        if (cardBadges[k]) cardBadges[k].style.display = (k === type) ? 'inline-flex' : 'none';
    });
}

if (cardInput) {
    cardInput.addEventListener('input', function () {
        const raw = this.value.replace(/\D/g, '');
        const type = detectCardType(raw);
        const r = raw.substring(0, type === 'amex' ? 15 : 16);
        this.value = type === 'amex'
            ? r.replace(/^(\d{0,4})(\d{0,6})(\d{0,5})$/, (_, a, b, c) => [a, b, c].filter(Boolean).join(' '))
            : r.replace(/(\d{4})(?=\d)/g, '$1 ');
        showCardBadge(raw.length >= 1 ? type : null);
    });
    if (cardInput.value) {
        const raw = cardInput.value.replace(/\D/g, '');
        showCardBadge(raw.length >= 1 ? detectCardType(raw) : null);
    }
}

const cvvEl = document.getElementById('cvv');
if (cvvEl) cvvEl.addEventListener('input', function () { this.value = this.value.replace(/\D/g,'').substring(0,3); });
const zipEl = document.getElementById('zip');
if (zipEl) zipEl.addEventListener('input', function () { this.value = this.value.replace(/\D/g,'').substring(0,5); });

// ══ DISCOUNT CODE AJAX ════════════════════════════════════════
const applyBtn             = document.getElementById('apply-discount-btn');
const discountInput        = document.getElementById('discount-input');
const discountMsg          = document.getElementById('discount-message');
const finalTotalDisplay    = document.getElementById('final-total-display');
const baseTotal            = parseFloat(finalTotalDisplay.getAttribute('data-base-total'));
const discountRow          = document.getElementById('discount-row');
const discountNameEl       = document.getElementById('discount-name');
const discountAmountEl     = document.getElementById('discount-amount-display');
const appliedDiscountInput = document.getElementById('applied_discount_code');
const payButton            = document.getElementById('pay-button');

function updateAllAmounts(newTotal) {
    finalTotalDisplay.innerHTML = 'NPR ' + newTotal.toLocaleString('en-US', {minimumFractionDigits:2,maximumFractionDigits:2});
    if (payButton) payButton.innerHTML = 'CONFIRM &amp; PAY &nbsp;NPR ' + Math.round(newTotal).toLocaleString();
    ['khalti-amount-display','esewa-amount-display','kharcha-portal-amount'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = 'NPR ' + Math.round(newTotal).toLocaleString();
    });
    // Update wallet button labels
    ['khalti-pay-btn','esewa-pay-btn'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = el.innerHTML.replace(/NPR\s[\d,]+/, 'NPR ' + Math.round(newTotal).toLocaleString());
    });
    // Update QR idle amount badges
    document.querySelectorAll('.qr-amount-badge').forEach(el => {
        el.textContent = 'NPR ' + Math.round(newTotal).toLocaleString();
    });
}

if (applyBtn) {
    applyBtn.addEventListener('click', function () {
        const code = discountInput.value.trim();
        if (!code) { discountMsg.textContent = 'Please enter a code.'; discountMsg.style.color='#e74c3c'; return; }
        applyBtn.textContent = '...'; applyBtn.disabled = true;

        fetch('check_discount.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'code=' + encodeURIComponent(code) + '&total_price=' + encodeURIComponent(baseTotal)
        })
        .then(r => r.json())
        .then(data => {
            applyBtn.textContent = 'APPLY'; applyBtn.disabled = false;
            if (data.valid) {
                discountMsg.textContent = data.message; discountMsg.style.color = '#2ecc71';
                updateAllAmounts(data.new_total);
                discountRow.style.display = 'flex';
                discountNameEl.textContent = 'Discount (' + data.discount_percent + '% - ' + code + ')';
                discountAmountEl.textContent = '- NPR ' + data.discount_amount.toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});
                appliedDiscountInput.value = code;
            } else {
                discountMsg.textContent = data.message; discountMsg.style.color = '#e74c3c';
                updateAllAmounts(baseTotal);
                discountRow.style.display = 'none';
                appliedDiscountInput.value = '';
            }
        })
        .catch(() => {
            applyBtn.textContent='APPLY'; applyBtn.disabled=false;
            discountMsg.textContent='Error verifying code. Try again.'; discountMsg.style.color='#e74c3c';
        });
    });
}

// ══ WALLET REDIRECTS ══════════════════════════════════════════
function submitKharchaPortal() {
    const form = document.getElementById('payment-form');
    document.getElementById('payment_method_input').value = 'kharcha';
    form.action = 'kharcha_portal_pay.php';
    form.method = 'POST';
    form.submit();
}

function submitESewa() {
    const btn = document.getElementById('esewa-pay-btn');
    if (btn) { btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Redirecting to eSewa…'; }
    const code = document.getElementById('applied_discount_code').value;
    window.location.href = 'esewa_initiate.php' + (code ? '?discount_code=' + encodeURIComponent(code) : '');
}

function submitKhalti() {
    const btn = document.getElementById('khalti-pay-btn');
    if (btn) { btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Redirecting to Khalti…'; }
    const code = document.getElementById('applied_discount_code').value;
    window.location.href = 'khalti_initiate.php' + (code ? '?discount_code=' + encodeURIComponent(code) : '');
}

// ══ KHARCHA DYNAMIC QR ════════════════════════════════════════
let qrPollInterval = null, qrTimerInterval = null;

function generateQRCode() {
    const btn = document.getElementById('qr-generate-btn');
    if (btn) { btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Generating…'; }
    clearInterval(qrPollInterval); clearInterval(qrTimerInterval);

    // Pass the currently applied discount code so the QR amount matches
    const discountCode = document.getElementById('applied_discount_code').value;
    const url = 'kharcha_qr_ajax.php?action=create&discount_code=' + encodeURIComponent(discountCode);

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + (data.message || 'Could not generate QR code.'));
                if (btn) { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-bolt"></i> Generate QR Code'; }
                return;
            }
            const sid = data.session_id;
            document.getElementById('qr-idle').style.display   = 'none';
            document.getElementById('qr-active').style.display = 'flex';
            const wrap = document.getElementById('qr-code-wrap');
            wrap.innerHTML = '';
            new QRCode(wrap, { text: data.qr_payload, width:200, height:200,
                colorDark:'#131313', colorLight:'#ffffff', correctLevel: QRCode.CorrectLevel.H });
            setQRStatus('pending');
            startQRTimer(data.expires_in || 300);
            startQRPolling(sid);
        })
        .catch(() => {
            alert('Network error. Please try again.');
            if (btn) { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-bolt"></i> Generate QR Code'; }
        });
}

function setQRStatus(s) {
    const dot=document.getElementById('qr-status-dot'), text=document.getElementById('qr-status-text');
    if (!dot||!text) return;
    dot.className = 'qr-status-dot qr-status-dot--' + s;
    text.textContent = {pending:'Waiting for payment…', success:'Payment received!', expired:'QR code expired'}[s] || s;
}

function startQRTimer(secs) {
    let rem = secs; const el = document.getElementById('qr-timer');
    clearInterval(qrTimerInterval);
    qrTimerInterval = setInterval(() => {
        rem--;
        if (el) el.textContent = Math.floor(rem/60)+':'+String(rem%60).padStart(2,'0');
        if (rem <= 0) {
            clearInterval(qrTimerInterval); clearInterval(qrPollInterval);
            setQRStatus('expired');
            document.getElementById('qr-refresh-btn').style.display = 'inline-flex';
        }
    }, 1000);
}

function startQRPolling(sid) {
    clearInterval(qrPollInterval);
    qrPollInterval = setInterval(() => {
        fetch('kharcha_qr_ajax.php?action=status&session_id=' + encodeURIComponent(sid))
            .then(r => r.json())
            .then(data => {
                const st = data.status || (data.session && data.session.status) || 'pending';
                if (st === 'success') {
                    clearInterval(qrPollInterval); clearInterval(qrTimerInterval);
                    setQRStatus('success'); finalizeQRBooking(sid);
                } else if (st === 'expired') {
                    clearInterval(qrPollInterval); clearInterval(qrTimerInterval);
                    setQRStatus('expired');
                    document.getElementById('qr-refresh-btn').style.display = 'inline-flex';
                }
            }).catch(() => {});
    }, 2500);
}

function finalizeQRBooking(sid) {
    document.getElementById('qr-active').style.display  = 'none';
    document.getElementById('qr-success').style.display = 'flex';
    const fd = new FormData(); fd.append('session_id', sid);
    fetch('kharcha_qr_ajax.php?action=finalize', {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.href = 'bookingconfirmed.php?id=' + data.booking_id;
            else document.getElementById('qr-success').innerHTML =
                '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i>' +
                (data.message || 'Booking finalization failed. Please contact support.') + '</div>';
        })
        .catch(() => {
            document.getElementById('qr-success').innerHTML =
                '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i>Network error finalizing booking.</div>';
        });
}

// ══ KHALTI QR ════════════════════════════════════════════════
let khaltiQrPollInterval = null, khaltiQrTimerInterval = null;
let khaltiQrPidx = null;

function generateKhaltiQR() {
    const btn = document.getElementById('khalti-qr-generate-btn');
    if (btn) { btn.disabled=true; btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Generating…'; }
    clearInterval(khaltiQrPollInterval); clearInterval(khaltiQrTimerInterval);
    khaltiQrPidx = null;

    const discountCode = document.getElementById('applied_discount_code').value;
    const url = 'khalti_qr_ajax.php?action=create&discount_code=' + encodeURIComponent(discountCode);

    fetch(url)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                alert('Error: ' + (data.message || 'Could not generate Khalti QR.'));
                if (btn) { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-bolt"></i> Generate QR Code'; }
                return;
            }
            khaltiQrPidx = data.pidx;
            document.getElementById('khalti-qr-idle').style.display   = 'none';
            document.getElementById('khalti-qr-active').style.display = 'flex';
            const wrap = document.getElementById('khalti-qr-code-wrap');
            wrap.innerHTML = '';
            new QRCode(wrap, { text: data.qr_payload, width:200, height:200,
                colorDark:'#1a1a1a', colorLight:'#ffffff', correctLevel: QRCode.CorrectLevel.H });
            setKhaltiQRStatus('pending');
            startKhaltiQRTimer(data.expires_in || 300);
            startKhaltiQRPolling(data.pidx);
        })
        .catch(() => {
            alert('Network error. Please try again.');
            if (btn) { btn.disabled=false; btn.innerHTML='<i class="fa-solid fa-bolt"></i> Generate QR Code'; }
        });
}

function setKhaltiQRStatus(s) {
    const dot=document.getElementById('khalti-qr-status-dot'), text=document.getElementById('khalti-qr-status-text');
    if (!dot||!text) return;
    dot.className = 'qr-status-dot qr-status-dot--' + s;
    text.textContent = {pending:'Waiting for payment…', success:'Payment received!', expired:'QR code expired'}[s] || s;
}

function startKhaltiQRTimer(secs) {
    let rem = secs; const el = document.getElementById('khalti-qr-timer');
    clearInterval(khaltiQrTimerInterval);
    khaltiQrTimerInterval = setInterval(() => {
        rem--;
        if (el) el.textContent = Math.floor(rem/60)+':'+String(rem%60).padStart(2,'0');
        if (rem <= 0) {
            clearInterval(khaltiQrTimerInterval); clearInterval(khaltiQrPollInterval);
            setKhaltiQRStatus('expired');
            document.getElementById('khalti-qr-refresh-btn').style.display = 'inline-flex';
        }
    }, 1000);
}

function startKhaltiQRPolling(pidx) {
    clearInterval(khaltiQrPollInterval);
    khaltiQrPollInterval = setInterval(() => {
        fetch('khalti_qr_ajax.php?action=status&pidx=' + encodeURIComponent(pidx))
            .then(r => r.json())
            .then(data => {
                const st = data.status || 'pending';
                if (st === 'success') {
                    clearInterval(khaltiQrPollInterval); clearInterval(khaltiQrTimerInterval);
                    setKhaltiQRStatus('success'); finalizeKhaltiQRBooking(pidx);
                } else if (st === 'expired' || st === 'failed') {
                    clearInterval(khaltiQrPollInterval); clearInterval(khaltiQrTimerInterval);
                    setKhaltiQRStatus('expired');
                    document.getElementById('khalti-qr-refresh-btn').style.display = 'inline-flex';
                }
            }).catch(() => {});
    }, 3000);
}

function finalizeKhaltiQRBooking(pidx) {
    document.getElementById('khalti-qr-active').style.display  = 'none';
    document.getElementById('khalti-qr-success').style.display = 'flex';
    const fd = new FormData(); fd.append('pidx', pidx);
    fetch('khalti_qr_ajax.php?action=finalize', {method:'POST', body:fd})
        .then(r => r.json())
        .then(data => {
            if (data.success) window.location.href = 'bookingconfirmed.php?id=' + data.booking_id;
            else document.getElementById('khalti-qr-success').innerHTML =
                '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i>' +
                (data.message || 'Booking finalization failed. Please contact support.') + '</div>';
        })
        .catch(() => {
            document.getElementById('khalti-qr-success').innerHTML =
                '<div class="alert-error"><i class="fa-solid fa-triangle-exclamation"></i>Network error finalizing booking.</div>';
        });
}

// ══ PREVENT DOUBLE-SUBMIT ════════════════════════════════════
const paymentForm = document.getElementById('payment-form');
if (paymentForm) {
    paymentForm.addEventListener('submit', function (e) {
        const btn = document.getElementById('pay-button');
        if (btn && btn.disabled) { e.preventDefault(); return; }
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'PROCESSING… <i class="fa-solid fa-spinner fa-spin"></i>';
            btn.style.opacity = '0.7'; btn.style.cursor = 'not-allowed';
        }
    });
}
</script>
</body>
</html>
