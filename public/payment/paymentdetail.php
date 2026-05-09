<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../config/mailer.php';
require_once '../../includes/functions.php';
require_once 'generate_invoice.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: bookingconfirmed.php');
    exit;
}

$errors = [];
$cardholdername = $cardnumber = $cvv = $street = $city = $zip = '';
$expiry_month = $expiry_year = '';


// Get vehicle/booking data from POST or SESSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store booking params in session so they survive re-display
    if (isset($_POST['vehicle_id'])) {
        $_SESSION['payment_vehicle_id'] = (int) $_POST['vehicle_id'];
        $_SESSION['payment_pickup'] = $_POST['pickup_date'] ?? '';
        $_SESSION['payment_dropoff'] = $_POST['dropoff_date'] ?? '';
        $_SESSION['payment_days'] = (int) ($_POST['days'] ?? 0);
    }
}

$vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date = $_SESSION['payment_pickup'] ?? '';
$dropoff_date = $_SESSION['payment_dropoff'] ?? '';
$days = $_SESSION['payment_days'] ?? 0;

if (!$vehicle_id) {
    header('Location: invoice.php');
    exit;
}

// Fetch vehicle
$sql = "SELECT v.*, u.first_name AS owner_name
        FROM vehicles v
        JOIN users u ON v.user_id = u.id
        WHERE v.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

$basicprice = 500;
$price_per_day = (float) $vehicle['price_per_day'];
$totalprice = ($price_per_day * $days) + $basicprice;


// Only validate when submitting payment fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cardnumber'])) {
    $cardholdername = filter_input(INPUT_POST, 'cardholder-name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cardnumber = filter_input(INPUT_POST, 'cardnumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expiry_month = filter_input(INPUT_POST, 'expiry_month', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expiry_year = filter_input(INPUT_POST, 'expiry_year', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cvv = filter_input(INPUT_POST, 'cvv', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $street = filter_input(INPUT_POST, 'street', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zip = filter_input(INPUT_POST, 'zip', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($cardholdername)) {
        $errors['cardholdername'] = "Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $cardholdername)) {
        $errors['cardholdername'] = "Name can only contain letters and spaces.";
    }

    $cardnumber_clean = preg_replace('/\s+/', '', $cardnumber);
    if (empty($cardnumber)) {
        $errors['cardnumber'] = "Card number is required.";
    } elseif (!preg_match('/^\d{16}$/', $cardnumber_clean)) {
        $errors['cardnumber'] = "Card number should be exactly 16 digits long.";
    } elseif (!isValidLuhn($cardnumber_clean)) {
        $errors['cardnumber'] = "Invalid card number. Please check for typos and enter a valid 16-digit number.";
    }

    $card_type = getCardType($cardnumber_clean);
    if (empty($expiry_month) || empty($expiry_year)) {
        $errors['expirydate'] = "Expiry date is required.";
    } else {
        $expiryMonth = (int) $expiry_month;
        $expiryYear = (int) $expiry_year;
        $currentMonth = (int) date('m');
        $currentYear = (int) date('Y');
        if ($expiryYear < $currentYear || ($expiryYear === $currentYear && $expiryMonth < $currentMonth)) {
            $errors['expirydate'] = "Card has expired.";
        }
    }

    if (empty($cvv)) {
        $errors['cvv'] = "CVV is required.";
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = "CVV must be exactly 3 digits.";
    }

    if (empty($street))
        $errors['street'] = "Street is required.";
    if (empty($city))
        $errors['city'] = "City is required.";

    if (empty($zip)) {
        $errors['zip'] = "Zip code is required.";
    } elseif (!preg_match('/^\d{5}$/', $zip)) {
        $errors['zip'] = "Zip code must be 5 digits.";
    }

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id)
            die("User not logged in.");

        $discount_code = filter_input(INPUT_POST, 'applied_discount_code', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $discount_amount = 0.00;
        $discount_code_id = null;

        if (!empty($discount_code)) {
            $stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
            $stmt->bind_param("s", $discount_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $code_data = $result->fetch_assoc();
                $code_id   = $code_data['id'];
                $stmt->close();

                // Check expiry
                if ($code_data['expires_at'] && $code_data['expires_at'] < date('Y-m-d')) {
                    $errors['discount'] = "This discount code has expired.";
                }
                // Check max uses
                elseif ($code_data['max_uses'] !== null && $code_data['used_count'] >= $code_data['max_uses']) {
                    $errors['discount'] = "This code has reached its maximum uses.";
                } 
                // Check ownership if it's a personal code
                elseif ($code_data['owner_user_id'] !== null && (int)$code_data['owner_user_id'] !== (int)$user_id) {
                    $errors['discount'] = "This is a personal discount code and cannot be used by you.";
                } else {
                    // Check user already used it
                    $stmt = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
                    $stmt->bind_param("ii", $user_id, $code_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) {
                        $errors['discount'] = "You have already used this discount code.";
                    } else {
                        // Calculate discount
                        if ($code_data['type'] === 'flat') {
                            $discount_amount = min($code_data['discount_flat'], $totalprice);
                        } else {
                            $discount_amount = ($totalprice * $code_data['discount_percent']) / 100;
                        }
                        $totalprice -= $discount_amount;
                        $discount_code_id = $code_id;
                    }
                    $stmt->close();
                }
            } else {
                $stmt->close();
                $errors['discount'] = "Invalid or inactive discount code.";
            }
        }
        
        if (empty($errors)) {
            $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, discount_code, discount_amount, created_at)
                           VALUES (?, ?, ?, ?, ?, 'confirmed', ?, ?, NOW())";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissdsd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $totalprice, $discount_code, $discount_amount);

        if ($insert_stmt->execute()) {
            $booking_id = $insert_stmt->insert_id;

            // Insert into transactions table
            $card_last4 = substr($cardnumber_clean, -4);
            $transaction_ref = 'TXN-' . strtoupper(bin2hex(random_bytes(8)));
            $txn_sql = "INSERT INTO transactions (booking_id, user_id, amount, payment_method, card_last4, card_type, transaction_ref, created_at)
                        VALUES (?, ?, ?, 'card', ?, ?, ?, NOW())";
            $txn_stmt = $conn->prepare($txn_sql);
            $txn_stmt->bind_param("iidsss", $booking_id, $user_id, $totalprice, $card_last4, $card_type, $transaction_ref);
            $txn_stmt->execute();

            // Record discount usage & increment used_count
            if ($discount_code_id) {
                $stmt = $conn->prepare("INSERT INTO discount_code_uses (user_id, code_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $user_id, $discount_code_id);
                $stmt->execute();
                $stmt->close();
                // Increment the global used_count
                $conn->query("UPDATE discount_codes SET used_count = used_count + 1 WHERE id = $discount_code_id");

                // ── GOLD RESET CYCLE ──────────────────────────────────────────
                // If the code used was the user's personal GOLD code (20% off),
                // reset their medal back to BRONZE so they climb the cycle again:
                //   BRONZE → SILVER → GOLD → [use Gold code] → BRONZE → ...
                $gold_check = $conn->query("
                    SELECT id FROM discount_codes
                    WHERE id = $discount_code_id
                      AND owner_user_id = $user_id
                      AND discount_percent = 20
                    LIMIT 1
                ");
                if ($gold_check && $gold_check->num_rows > 0) {
                    // Reset to Bronze level (completed_rentals = 3 keeps them at Bronze threshold)
                    $conn->query("UPDATE users SET medal = 'BRONZE', completed_rentals = 3 WHERE id = $user_id");
                }
                // ─────────────────────────────────────────────────────────────
            }

            // Milestone logic — increment AFTER possible Gold reset
            $conn->query("UPDATE users SET completed_rentals = completed_rentals + 1 WHERE id = $user_id");
            $res = $conn->query("SELECT completed_rentals, medal FROM users WHERE id = $user_id");
            if ($res && $res->num_rows > 0) {
                $u_data = $res->fetch_assoc();
                $rentals = (int)$u_data['completed_rentals'];
                $current_medal = $u_data['medal'];
                
                $new_medal      = $current_medal;
                $milestone_code    = '';
                $milestone_percent = 0;

                // Updated thresholds: Bronze=3, Silver=7, Gold=15
                if ($rentals >= 3 && $current_medal === 'NONE') {
                    $new_medal         = 'BRONZE';
                    $milestone_code    = 'BRONZE5';
                    $milestone_percent = 5;
                } elseif ($rentals >= 7 && $current_medal === 'BRONZE') {
                    $new_medal         = 'SILVER';
                    $milestone_code    = 'SILVER10';
                    $milestone_percent = 10;
                } elseif ($rentals >= 15 && $current_medal === 'SILVER') {
                    $new_medal         = 'GOLD';
                    $milestone_code    = 'GOLD20';
                    $milestone_percent = 20;
                }
                
                if ($new_medal !== $current_medal) {
                    $conn->query("UPDATE users SET medal = '$new_medal' WHERE id = $user_id");
                    
                    if ($milestone_code !== '') {
                        // Create a UNIQUE personal code for this user (max_uses = 1)
                        $unique_suffix = substr(md5(uniqid($user_id, true)), 0, 4);
                        $personal_code = $milestone_code . "-U" . $user_id . "-" . $unique_suffix;
                        
                        $conn->query("
                            INSERT INTO discount_codes (code, type, discount_percent, discount_flat, max_uses, owner_user_id) 
                            VALUES ('$personal_code', 'percent', $milestone_percent, 0, 1, $user_id)
                        ");
                    }
                }
            }

            // Fetch user data for the confirmation email
            $user_stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
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
                    'days' => $days,
                    'price_per_day' => $vehicle['price_per_day'],
                    'total_price' => $totalprice,
                    'discount_code' => $discount_code,
                    'discount_amount' => $discount_amount,
                ];

                $pdf_string = generateInvoicePDF($invoice_data);

                $savings_msg = '';
                if ($discount_amount > 0) {
                    $savings_msg = "<p style='color: #2ecc71;'><strong>You saved NPR " . number_format($discount_amount, 2) . " with code " . htmlspecialchars($discount_code) . "!</strong></p>";
                }

                // send mail
                $mail = createMailer();
                $mail->addAddress($email, $first_name . ' ' . $last_name);
                $mail->Subject = 'Booking Confirmation';
                $mail->isHTML(true);
                $mail->Body = "
                            <p>Hi {$first_name},</p>
        <h2>Your booking for {$vehicle['model']} is confirmed.</h2>
        <p>Pickup date: {$pickup_date}</p>
        <p>Dropoff date: {$dropoff_date}</p>
        <p>Total Paid: NPR " . number_format($totalprice, 2) . "</p>
        {$savings_msg}
        <p>Please find your invoice attached.</p>
        <p>Thank you for choosing TD Rentals 🚀</p>
        
        <p>Best Regards,</p>
        <p>TD Rentals Team</p>

                        ";
                $mail->AltBody = "Hi {$first_name}, Your booking for {$vehicle['model']} is confirmed.";
                // Attach PDF from string (no temp file needed)
                $mail->addStringAttachment($pdf_string, "invoice_{$booking_id}.pdf", 'base64', 'application/pdf');
                $mail->send();
            } catch (Exception $e) {
                // Mail failed — log it, but don't block the booking
                error_log("Booking email failed: " . $e->getMessage());
            }
            // Clear session payment data
            unset(
                $_SESSION['payment_vehicle_id'],
                $_SESSION['payment_pickup'],
                $_SESSION['payment_dropoff'],
                $_SESSION['payment_days']
            );
            header("Location: bookingconfirmed.php?id=" . $booking_id);
            exit;
        } else {
            $errors['database'] = "Failed to create booking. Please try again.";
        }
        }
    }
}

// Fetch available discount codes for the user
$available_codes = [];
$uid = $_SESSION['user_id'] ?? 0;
if ($uid) {
    // Get user medal for filtering
    $u_res = $conn->query("SELECT medal FROM users WHERE id = $uid");
    $u_row = $u_res->fetch_assoc();
    $u_medal = $u_row['medal'] ?? 'NONE';

    // 1. Show global codes (owner_user_id IS NULL)
    // 2. Show personal codes (owner_user_id = UID)
    // 3. Exclude already used codes
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

    // Filter out global milestone codes if user doesn't have the level
    foreach ($raw_codes as $rc) {
        if ($rc['owner_user_id'] === null) {
            $cname = strtoupper($rc['code']);
            if (strpos($cname, 'BRONZE') !== false && $u_medal === 'NONE') continue;
            if (strpos($cname, 'SILVER') !== false && ($u_medal === 'NONE' || $u_medal === 'BRONZE')) continue;
            if (strpos($cname, 'GOLD') !== false && $u_medal !== 'GOLD') continue;
        }
        $available_codes[] = $rc;
    }
}
?>


<?php require_once '../../includes/header.php'; ?>
<link rel="stylesheet" href="../../assets/css/paymentdetail.css">
<script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>

<!-- Main Container -->
<div id="payment-page">

    <!-- Left Section: Form -->
    <div id="payment-form-section">

        <h2 class="subtitle">SECURE CHECKOUT</h2>
        <h1 class="title">PAYMENT DETAILS</h1>

        <!-- Credit Card Info -->
        <form action="paymentdetail.php" method="POST" id="checkout-form">
            <!-- Add hidden fields to carry booking data through re-submission -->
            <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
            <input type="hidden" name="pickup_date" value="<?= htmlspecialchars($pickup_date) ?>">
            <input type="hidden" name="dropoff_date" value="<?= htmlspecialchars($dropoff_date) ?>">
            <input type="hidden" name="days" value="<?= $days ?>">
            <input type="hidden" name="applied_discount_code" id="applied_discount_code" value="<?= htmlspecialchars($_POST['applied_discount_code'] ?? '') ?>">

            <?php if (!empty($errors['discount'])): ?>
                <div class="field-error" style="margin-bottom: 15px; padding: 10px; background: rgba(224, 48, 48, 0.1); border-left: 3px solid #e03030;">
                    <?= e($errors['discount']) ?>
                </div>
            <?php endif; ?>

            <section class="card-section">
                <h3 class="section-title"><i class="fa-solid fa-credit-card icon"></i>CREDIT CARD INFORMATION</h3>

                <div class="form-group">
                    <label class="labels" for="cardholder-name">Cardholder Name</label><br>
                    <input type="text" name="cardholder-name" id="cardholder-name" class="input-field"
                        placeholder="James T. Sterling" value="<?php echo htmlspecialchars($cardholdername ?? ''); ?>">
                    <?php if (!empty($errors['cardholdername'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['cardholdername']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="card-number" class="labels">Card Number</label><br>
                    <input type="text" name="cardnumber" id="card-number" class="input-field"
                        placeholder="4111 1111 1111 1111" value="<?php echo htmlspecialchars($cardnumber ?? ''); ?>">
                    <?php if (!empty($errors['cardnumber'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['cardnumber']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="labels">Expiry Date</label><br>
                        <div class="expiry-dropdowns">
                            <select name="expiry_month" id="expiry-month" class="input-field">
                                <option value="" disabled <?= empty($expiry_month) ? 'selected' : '' ?>>Month
                                </option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= sprintf('%02d', $m) ?>" <?= ($expiry_month == sprintf('%02d', $m)) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <select name="expiry_year" id="expiry-year" class="input-field">
                                <option value="" disabled <?= empty($expiry_year) ? 'selected' : '' ?>>Year</option>
                                <?php for ($y = (int) date('Y'); $y <= (int) date('Y') + 10; $y++): ?>
                                    <option value="<?= $y ?>" <?= ($expiry_year == $y) ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php if (!empty($errors['expirydate'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['expirydate']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cvv">CVV / CVC</label><br>
                        <input type="password" name="cvv" id="cvv" class="input-field" placeholder="***" maxlength="3"
                            pattern="\d{3}" inputmode="numeric" required>
                        <?php if (!empty($errors['cvv'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['cvv']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Billing Address -->
            <section class="billing-section">
                <h3 class="section-title"><i class="fa-solid fa-location-dot icon"></i>
                    BILLING ADDRESS</h3>

                <div class="form-group">
                    <label for="street" class="labels">Street Address</label><br>
                    <input type="text" name="street" id="street" class="input-field" placeholder="123 Performance Way"
                        value="<?php echo htmlspecialchars($street ?? ''); ?>">
                    <?php if (!empty($errors['street'])): ?>
                        <span class="field-error"><?= htmlspecialchars($errors['street']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="city" class="labels">City</label><br>
                        <input type="text" name="city" id="city" class="input-field" placeholder="Los Angeles"
                            value="<?php echo htmlspecialchars($city ?? ''); ?>">
                        <?php if (!empty($errors['city'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['city']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="zip" class="labels">Zip Code</label><br>
                        <input type="text" name="zip" id="zip" class="input-field" placeholder="44600"
                            value="<?php echo htmlspecialchars($zip ?? ''); ?>" maxlength="5" pattern="\d{5}"
                            inputmode="numeric" required>
                        <?php if (!empty($errors['zip'])): ?>
                            <span class="field-error"><?= htmlspecialchars($errors['zip']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <button id="pay-button" class="primary-btn" type="submit">
                CONFIRM & PAY NPR <?= number_format($totalprice, 0) ?>
            </button>
        </form>

        <div class="payment-divider">
            <span>OR PAY WITH</span>
        </div>

        <div class="khalti-section">
            <a href="khalti_initiate.php" id="khalti-button">
                <img src="https://khalti.com/static/img/logo1.png" alt="Khalti" class="khalti-logo">
                PAY NPR <?= number_format($totalprice, 0) ?>
            </a>
        </div>

        <p class="security-note">
            Your transaction is encrypted and secured via TLS 1.3 protocol.
        </p>

    </div>

    <!-- Right Section: Summary -->
    <div id="summary-section">

        <div class="car-info">
            <img class="car-image"
                src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/car_1775474575.jpg') ?>"
                alt="<?= htmlspecialchars($vehicle['model']) ?>">
            <div class="car-image-overlay"></div>
            <h2 class="car-title"><?= htmlspecialchars($vehicle['model']) ?></h2>
        </div>

        <div class="booking-panel">

            <!-- Reservation Dates -->
            <div class="booking-meta">
                <div>
                    <p class="booking-meta-label">Reservation Dates</p>
                    <p class="booking-meta-value"><?= htmlspecialchars($pickup_date) ?> - <?= htmlspecialchars($dropoff_date) ?></p>
                </div>
                <div style="text-align: right;">
                    <p class="booking-meta-label">Duration</p>
                    <p class="booking-meta-value"><?= $days ?> DAYS</p>
                </div>
            </div>

            <!-- Price Breakdown -->
            <p class="price-breakdown-label">Price Breakdown</p>

            <div class="price-row">
                <span class="price-row-name">Daily Rate (NPR <?= htmlspecialchars($vehicle['price_per_day']) ?> *
                    <?= $days ?>)</span>
                <span class="price-row-amount">NPR <?= number_format($vehicle['price_per_day'] * $days, 2) ?></span>
            </div>
            <div class="price-row">
                <span class="price-row-name">Insurance & Protection</span>
                <span class="price-row-amount">NPR 350.00</span>
            </div>
            <div class="price-row">
                <span class="price-row-name">Premium Handling Fee</span>
                <span class="price-row-amount">NPR 150.00</span>
            </div>

            <hr class="price-divider" />

            <!-- Discount Input Section -->
            <div class="discount-section" style="margin-bottom: 20px;">
                <p class="price-breakdown-label">Discount Code</p>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="discount-input" class="input-field" placeholder="Enter code" style="margin-bottom: 0;">
                    <button type="button" id="apply-discount-btn" class="primary-btn" style="width: auto; padding: 0 15px; font-size: 0.9rem;">APPLY</button>
                </div>
                <p id="discount-message" style="margin-top: 5px; font-size: 0.85rem;"></p>
                <div style="margin-top: 10px; font-size: 0.8rem; color: #888;">
                    <span style="display: block; margin-bottom: 6px; color: #aaa; text-transform: uppercase; font-weight: 600; font-size: 0.7rem;">Available Rewards:</span>
                    <?php if (!empty($available_codes)): ?>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <?php foreach($available_codes as $ac): ?>
                                <span class="available-code-badge" onclick="document.getElementById('discount-input').value='<?= htmlspecialchars($ac['code']) ?>'" style="background: rgba(224,48,48,0.1); border: 1px solid rgba(224,48,48,0.3); color: var(--red); padding: 4px 8px; border-radius: 4px; cursor: pointer; transition: all 0.2s; font-weight: 600;" onmouseover="this.style.background='rgba(224,48,48,0.2)'" onmouseout="this.style.background='rgba(224,48,48,0.1)'">
                                    <?= htmlspecialchars($ac['code']) ?> (-<?= floatval($ac['discount_percent']) ?>%)
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span style="font-style: italic; opacity: 0.7;">No unused codes available right now.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dynamic Discount Row (Hidden initially) -->
            <div class="price-row" id="discount-row" style="display: none;">
                <span class="price-row-name" id="discount-name" style="color: #2ecc71;">Discount</span>
                <span class="price-row-amount" id="discount-amount" style="color: #2ecc71;">- NPR 0.00</span>
            </div>

            <!-- Total -->
            <div class="total-row">
                <span class="total-label">Total Due</span>
                <span class="total-amount" id="final-total-display" data-base-total="<?= $totalprice ?>">NPR
                    <?= number_format($totalprice, 2) ?></span>
            </div>

            <!-- Spec Badges -->
            <div class="spec-badges">
                <div class="spec-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z" />
                    </svg>
                    986 HP
                </div>
                <div class="spec-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 2h10l2 7H5L7 2zm0 9h10v11H7V11z" />
                    </svg>
                    <?= htmlspecialchars($vehicle['fuel_type']) ?>
                </div>
                <div class="spec-badge">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z" />
                    </svg>
                    2.5s 0-60
                </div>
            </div>

            <!-- Notice Box -->
            <div class="notice-box">
                <i class="fa-solid fa-circle-info notice-icon"></i>
                <p class="notice-text">
                    Free cancellation until 48 hours prior to pickup. A security deposit
                    of NPR 500 will be held during the rental period.
                </p>
            </div>

        </div>

    </div>
</div>

<?php require '../../includes/footer.php'; ?>

<script>
    // Auto-format card number: space after every 4 digits
    const cardInput = document.getElementById('card-number');
    if (cardInput) {
        cardInput.addEventListener('input', function () {
            let value = this.value.replace(/\D/g, '');
            value = value.substring(0, 16);
            this.value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
        });
    }

    // Restrict CVV to 3 digits
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 3);
        });
    }

    // Restrict Zip to 5 digits
    const zipInput = document.getElementById('zip');
    if (zipInput) {
        zipInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').substring(0, 5);
        });
    }

    // Discount Code AJAX
    const applyBtn = document.getElementById('apply-discount-btn');
    const discountInput = document.getElementById('discount-input');
    const discountMsg = document.getElementById('discount-message');
    const finalTotalDisplay = document.getElementById('final-total-display');
    const baseTotal = parseFloat(finalTotalDisplay.getAttribute('data-base-total'));
    const discountRow = document.getElementById('discount-row');
    const discountName = document.getElementById('discount-name');
    const discountAmountDisplay = document.getElementById('discount-amount');
    const appliedDiscountInput = document.getElementById('applied_discount_code');
    const payButton = document.getElementById('pay-button');

    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            const code = discountInput.value.trim();
            if (!code) {
                discountMsg.textContent = 'Please enter a code.';
                discountMsg.style.color = '#e74c3c';
                return;
            }

            // Reset UI to loading
            applyBtn.textContent = '...';
            applyBtn.disabled = true;

            fetch('check_discount.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'code=' + encodeURIComponent(code) + '&total_price=' + encodeURIComponent(baseTotal)
            })
            .then(response => response.json())
            .then(data => {
                applyBtn.textContent = 'APPLY';
                applyBtn.disabled = false;

                if (data.valid) {
                    discountMsg.textContent = data.message;
                    discountMsg.style.color = '#2ecc71';
                    
                    // Update UI Total
                    finalTotalDisplay.innerHTML = 'NPR ' + data.new_total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    payButton.innerHTML = 'CONFIRM & PAY NPR ' + data.new_total.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    
                    // Show discount row
                    discountRow.style.display = 'flex';
                    discountName.textContent = 'Discount (' + data.discount_percent + '% - ' + code + ')';
                    discountAmountDisplay.textContent = '- NPR ' + data.discount_amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    
                    // Store applied code to hidden form input
                    appliedDiscountInput.value = code;

                    // Update Khalti Link
                    const khaltiLink = document.getElementById('khalti-button');
                    if (khaltiLink) {
                        khaltiLink.href = 'khalti_initiate.php?code=' + encodeURIComponent(code);
                        khaltiLink.innerHTML = '<img src="https://khalti.com/static/img/logo1.png" alt="Khalti" class="khalti-logo"> PAY NPR ' + Math.round(data.new_total).toLocaleString();
                    }
                } else {
                    discountMsg.textContent = data.message;
                    discountMsg.style.color = '#e74c3c';
                    
                    // Reset to base total
                    finalTotalDisplay.innerHTML = 'NPR ' + baseTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    payButton.innerHTML = 'CONFIRM & PAY NPR ' + baseTotal.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    discountRow.style.display = 'none';
                    appliedDiscountInput.value = '';

                    // Reset Khalti Link
                    const khaltiLink = document.getElementById('khalti-button');
                    if (khaltiLink) {
                        khaltiLink.href = 'khalti_initiate.php';
                        khaltiLink.innerHTML = '<img src="https://khalti.com/static/img/logo1.png" alt="Khalti" class="khalti-logo"> PAY NPR ' + Math.round(baseTotal).toLocaleString();
                    }
                }
            })
            .catch(error => {
                applyBtn.textContent = 'APPLY';
                applyBtn.disabled = false;
                discountMsg.textContent = 'Error verifying code. Try again.';
                discountMsg.style.color = '#e74c3c';
            });
        });
    }

    // Prevent multiple form submissions & show loading feedback
    const paymentForm = document.querySelector('form[action="paymentdetail.php"]');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function (e) {
            const submitBtn = document.getElementById('pay-button');
            if (submitBtn.disabled) {
                e.preventDefault();
                return;
            }
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'PROCESSING... <i class="fa-solid fa-spinner fa-spin"></i>';
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
        });
    }
</script>
</body>
</html>