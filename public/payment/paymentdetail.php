<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$errors = [];
$cardholdername = $cardnumber = $expirydate = $cvv = $street = $city = $zip = '';

// Get vehicle/booking data from POST or SESSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Store booking params in session so they survive re-display
    if (isset($_POST['vehicle_id'])) {
        $_SESSION['payment_vehicle_id'] = (int)$_POST['vehicle_id'];
        $_SESSION['payment_pickup']     = $_POST['pickup_date'] ?? '';
        $_SESSION['payment_dropoff']    = $_POST['dropoff_date'] ?? '';
        $_SESSION['payment_days']       = (int)($_POST['days'] ?? 0);
    }
}

$vehicle_id  = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date = $_SESSION['payment_pickup'] ?? '';
$dropoff_date= $_SESSION['payment_dropoff'] ?? '';
$days        = $_SESSION['payment_days'] ?? 0;

if (!$vehicle_id) {
    header('Location: vehicles.php');
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
$price_per_day = (float)$vehicle['price_per_day'];
$totalprice = ($price_per_day * $days) + $basicprice;


// Only validate when submitting payment fields
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cardnumber'])) {
    $cardholdername = filter_input(INPUT_POST, 'cardholder-name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cardnumber     = filter_input(INPUT_POST, 'cardnumber',      FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expirydate     = filter_input(INPUT_POST, 'expirydate',      FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cvv            = filter_input(INPUT_POST, 'cvv',             FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $street         = filter_input(INPUT_POST, 'street',          FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city           = filter_input(INPUT_POST, 'city',            FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zip            = filter_input(INPUT_POST, 'zip',             FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (empty($cardholdername)) {
        $errors['cardholdername'] = "Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $cardholdername)) {
        $errors['cardholdername'] = "Name can only contain letters and spaces.";
    }

    $cardnumber_clean = preg_replace('/\s+/', '', $cardnumber);
    if (empty($cardnumber)) {
        $errors['cardnumber'] = "Card number is required.";
    } elseif (!preg_match('/^\d{16}$/', $cardnumber_clean)) {
        $errors['cardnumber'] = "Card number must be 16 digits.";
    }

    if (empty($expirydate)) {
        $errors['expirydate'] = "Expiry date is required.";
    }

    if (empty($cvv)) {
        $errors['cvv'] = "CVV is required.";
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors['cvv'] = "CVV must be exactly 3 digits.";
    }

    if (empty($street)) $errors['street'] = "Street is required.";
    if (empty($city))   $errors['city']   = "City is required.";

    if (empty($zip)) {
        $errors['zip'] = "Zip code is required.";
    } elseif (!preg_match('/^\d{5}$/', $zip)) {
        $errors['zip'] = "Zip code must be 5 digits.";
    }

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'] ?? null;
        if (!$user_id) die("User not logged in.");

        $insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, created_at)
                       VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $totalprice);

        if ($insert_stmt->execute()) {
            $booking_id = $insert_stmt->insert_id;
            // Clear session payment data
            unset($_SESSION['payment_vehicle_id'], $_SESSION['payment_pickup'],
                  $_SESSION['payment_dropoff'],    $_SESSION['payment_days']);
            header("Location: bookingconfirmed.php?id=" . $booking_id);
            exit;
        } else {
            $errors['database'] = "Failed to create booking. Please try again.";
        } 
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../../assets/css/paymentdetail.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
      <link rel="stylesheet" href="../../assets/css/footer.css">
    <title>Payment Details</title>
</head>

<body>
    <?php require '../../includes/paymentheader.php'; ?>
    <!-- Main Container -->
    <div id="payment-page">

        <!-- Left Section: Form -->
        <div id="payment-form-section">

            <h2 class="subtitle">SECURE CHECKOUT</h2>
            <h1 class="title">PAYMENT DETAILS</h1>

            <!-- Credit Card Info -->
            <form action="paymentdetail.php" method="POST">
                <!-- Add hidden fields to carry booking data through re-submission -->
                <input type="hidden" name="vehicle_id" value="<?= $vehicle_id ?>">
                <input type="hidden" name="pickup_date" value="<?= htmlspecialchars($pickup_date) ?>">
                <input type="hidden" name="dropoff_date" value="<?= htmlspecialchars($dropoff_date) ?>">
                <input type="hidden" name="days" value="<?= $days ?>">

                <section class="card-section">
                    <h3 class="section-title"><i class="fa-solid fa-credit-card icon"></i>CREDIT CARD INFORMATION</h3>

                    <div class="form-group">
                        <label class="labels" for="cardholder-name">Cardholder Name</label><br>
                        <input type="text" name="cardholder-name" id="cardholder-name" class="input-field"
                            placeholder="James T. Sterling"
                            value="<?php echo htmlspecialchars($cardholdername ?? ''); ?>">
                        <?php if (!empty($errors['cardholdername'])): ?>
                            <span class="field-error"><?= e($errors['cardholdername']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="card-number" class="labels">Card Number</label><br>
                        <input type="text" name="cardnumber" id="card-number" class="input-field"
                            placeholder="0000 0000 0000 0000"
                            value="<?php echo htmlspecialchars($cardnumber ?? ''); ?>">
                        <?php if (!empty($errors['cardnumber'])): ?>
                            <span class="field-error"><?= e($errors['cardnumber']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry-date" class="labels">Expiry Date</label><br>
                            <input type="date" name="expirydate" id="expiry-date" class="input-field"
                                placeholder="MM / YY" value="<?php echo htmlspecialchars($expirydate ?? ''); ?>">
                            <?php if (!empty($errors['expirydate'])): ?>
                                <span class="field-error"><?= e($errors['expirydate']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group" class="labels">
                            <label for="cvv">CVV / CVC</label><br>
                            <input type="password" name="cvv" id="cvv" class="input-field" placeholder="***"
                                value="<?php echo htmlspecialchars($cvv ?? ''); ?>">
                            <?php if (!empty($errors['cvv'])): ?>
                                <span class="field-error"><?= e($errors['cvv']) ?></span>
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
                        <input type="text" name="street" id="street" class="input-field"
                            placeholder="123 Performance Way" value="<?php echo htmlspecialchars($street ?? ''); ?>">
                        <?php if (!empty($errors['street'])): ?>
                            <span class="field-error"><?= e($errors['street']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="labels">City</label><br>
                            <input type="text" name="city" id="city" class="input-field" placeholder="Los Angeles"
                                value="<?php echo htmlspecialchars($city ?? ''); ?>">
                            <?php if (!empty($errors['city'])): ?>
                                <span class="field-error"><?= e($errors['city']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="zip" class="labels">Zip Code</label><br>
                            <input type="text" name="zip" id="zip" class="input-field" placeholder="90001"
                                value="<?php echo htmlspecialchars($zip ?? ''); ?>">
                            <?php if (!empty($errors['zip'])): ?>
                                <span class="field-error"><?= e($errors['zip']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Save Card -->
                <!-- <div class="checkbox-group">
                    <input type="checkbox" id="save-card">
                    <label for="save-card">Save card information for future bookings</label>
                </div> -->

                <!-- Button -->
                <button id="pay-button" class="primary-btn">
                    CONFIRM & PAY NPR <?= number_format($totalprice, 0) ?>
                </button>
            </form>

            <!-- Security Note -->
            <p class="security-note">
                Your transaction is encrypted and secured via TLS 1.3 protocol.
            </p>

        </div>

        <!-- Right Section: Summary -->
        <div id="summary-section">

            <div class="car-info">
                <img class="car-image"
                    src="../../<?= htmlspecialchars($vehicle['image_path'] ?? 'assets/images/car_1775474575.jpg') ?>"
                    alt="">
                <div class="car-image-overlay"></div>
                <!-- <h2 class="car-title">2024 Ferrari SF90</h2> -->
                <h2 class="car-title"><?= htmlspecialchars($vehicle['model']) ?></h2>
            </div>

            <div class="booking-panel">

                <!-- Reservation Dates -->
                <div class="booking-meta">
                    <div>
                        <p class="booking-meta-label">Reservation Dates</p>
                        <!-- <p class="booking-meta-value">Oct 12 — Oct 15, 2024</p> -->
                        <p class="booking-meta-value"><?= $pickup_date ?> - <?= $dropoff_date ?></p>
                    </div>
                    <div style="text-align: right;">
                        <p class="booking-meta-label">Duration</p>
                        <p class="booking-meta-value"><?= $days ?> DAYS</p>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <p class="price-breakdown-label">Price Breakdown</p>

                <div class="price-row">
                    <span class="price-row-name">Daily Rate (NPR<?= htmlspecialchars($vehicle['price_per_day']) ?> ×
                        <?= $days ?>)</span>
                    <!-- <span class="price-row-amount">NPR 3,750.00</span> -->
                    <span class="price-row-amount"><?= htmlspecialchars($vehicle['price_per_day']) ?></span>
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

                <!-- Total -->
                <div class="total-row">
                    <span class="total-label">Total Due</span>
                    <span class="total-amount">NPR
                        <?= number_format($vehicle['price_per_day'] * $days + $basicprice, 0) ?></span>
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
                    <!-- <span class="notice-icon">ℹ️</span> -->
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
</body>
</html>