<?php


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cardholdername = filter_input(INPUT_POST, 'cardholder-name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cardnumber = filter_input(INPUT_POST, 'cardnumber', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expirydate = filter_input(INPUT_POST, 'expirydate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $cvv = filter_input(INPUT_POST, 'cvv', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $street = filter_input(INPUT_POST, 'street', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $zip = filter_input(INPUT_POST, 'zip', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $errors = [];

    if (empty($cardholdername)) {
        $errors[] = "Name is required.";
    } elseif (!preg_match("/^[a-zA-Z\s]+$/", $cardholdername)) {
        $errors[] = "Name can only contain letters and spaces.";
    }

    // Card number: remove spaces, check 16 digits
    $cardnumber_clean = preg_replace('/\s+/', '', $cardnumber);
    if (empty($cardnumber)) {
        $errors[] = "Card Number is required.";
    } elseif (!preg_match('/^\d{16}$/', $cardnumber_clean)) {
        $errors[] = "Card Number must be 16 digits.";
    }

    // Expiry date: MM/YY format and not expired
    if (empty($expirydate)) {
        $errors[] = "Expiry Date is required.";
    }
    // elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expirydate)) {
    //     $errors[] = "Expiry Date must be in MM/YY format.";
    // } 
    // else {
    //     [$month, $year] = explode('/', $expirydate);
    //     $expiry = \DateTime::createFromFormat('m/y', "$month/$year");
    //     if (!$expiry || $expiry < new \DateTime('first day of this month')) {
    //         $errors[] = "Card has expired.";
    //     }
    // }

    // CVV: exactly 3 digits
    if (empty($cvv)) {
        $errors[] = "CVV is required.";
    } elseif (!preg_match('/^\d{3}$/', $cvv)) {
        $errors[] = "CVV must be exactly 3 digits.";
    }
    if (empty($street)) {
        $errors[] = "Street is required.";
    }
    if (empty($city)) {
        $errors[] = "City is required.";
    }

    // Zip code: 5 digits
    if (empty($zip)) {
        $errors[] = "Zip Code is required.";
    } elseif (!preg_match('/^\d{5}$/', $zip)) {
        $errors[] = "Zip Code must be 5 digits.";
    }

    echo "<hr>";
    if (empty($errors)) {
        //go to bookingconfirmed.php
        header("Location: bookingconfirmed.php");
    } else {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="/vehicle_rental_system/assets/css/paymentdetail.css">
    <title>Payment Details</title>
</head>

<body>
     <!-- <?php require '../../includes/paymentheader.php'; ?> -->
    <!-- Main Container -->
    <div id="payment-page">

        <!-- Left Section: Form -->
        <div id="payment-form-section">

            <h2 class="subtitle">SECURE CHECKOUT</h2>
            <h1 class="title">PAYMENT DETAILS</h1>

            <!-- Credit Card Info -->
            <form action="" method="POST">

                <section class="card-section">
                    <h3 class="section-title"><i class="fa-solid fa-credit-card icon"></i>CREDIT CARD INFORMATION</h3>

                    <div class="form-group">
                        <label class="labels" for="cardholder-name">Cardholder Name</label><br>
                        <input type="text" name="cardholder-name" id="cardholder-name" class="input-field"
                            placeholder="James T. Sterling" value="<?php echo htmlspecialchars($cardholdername ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="card-number" class="labels">Card Number</label><br>
                        <input type="text" name="cardnumber" id="card-number" class="input-field"
                            placeholder="0000 0000 0000 0000" value="<?php echo htmlspecialchars($cardnumber ?? ''); ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="expiry-date" class="labels">Expiry Date</label><br>
                            <input type="date" name="expirydate" id="expiry-date" class="input-field"
                                placeholder="MM / YY" value="<?php echo htmlspecialchars($expirydate ?? ''); ?>">
                        </div>

                        <div class="form-group" class="labels">
                            <label for="cvv">CVV / CVC</label><br>
                            <input type="password" name="cvv" id="cvv" class="input-field" placeholder="***"
                            value="<?php echo htmlspecialchars($cvv ?? ''); ?>">
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
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="city" class="labels">City</label><br>
                            <input type="text" name="city" id="city" class="input-field" placeholder="Los Angeles"
                            value="<?php echo htmlspecialchars($city ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="zip" class="labels">Zip Code</label><br>
                            <input type="text" name="zip" id="zip" class="input-field" placeholder="90001"
                            value="<?php echo htmlspecialchars($zip ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <!-- Save Card -->
                <div class="checkbox-group">
                    <input type="checkbox" id="save-card">
                    <label for="save-card">Save card information for future bookings</label>
                </div>

                <!-- Button -->
                <button id="pay-button" class="primary-btn">
                    CONFIRM & PAY NPR 4,250.00
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
                <img class="car-image" src="/vehicle_rental_system/assets/images/ferrari-img.jpg" alt="">
                <div class="car-image-overlay"></div>
                <h2 class="car-title">2024 Ferrari SF90</h2>
            </div>

            <div class="booking-panel">

                <!-- Reservation Dates -->
                <div class="booking-meta">
                    <div>
                        <p class="booking-meta-label">Reservation Dates</p>
                        <p class="booking-meta-value">Oct 12 — Oct 15, 2024</p>
                    </div>
                    <div style="text-align: right;">
                        <p class="booking-meta-label">Duration</p>
                        <p class="booking-meta-value">3 Days</p>
                    </div>
                </div>

                <!-- Price Breakdown -->
                <p class="price-breakdown-label">Price Breakdown</p>

                <div class="price-row">
                    <span class="price-row-name">Daily Rate ($1,250 × 3)</span>
                    <span class="price-row-amount">NPR 3,750.00</span>
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
                    <span class="total-amount">NPR 4,250.00</span>
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
                        Hybrid
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
                        of NPR 5,000 will be held during the rental period.
                    </p>
                </div>

            </div>

        </div>

    </div>

</body>

</html>