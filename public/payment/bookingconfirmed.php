<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

$booking_id = (int) ($_GET['id'] ?? 0);

// if (!$booking_id) {
//     die("Invalid booking.");
// }

// Fetch booking + vehicle info
$sql = "
    SELECT b.*, v.model, v.image_path
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id
    WHERE b.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();

// bug
// var_dump($booking);
// exit;

if (!$booking) {
    die("Booking not found.");
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://kit.fontawesome.com/ac1574deb1.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../../assets/css/bookingconfirmed.css">
    <link rel="stylesheet" href="/vehicle_rental_collab_project/assets/css/header.css">
    <link rel="stylesheet" href="../../assets/css/footer.css">
    <title>Booking confirmed</title>
    <style>
        /* make footer take 100% width */
        .site-footer {
            width: 100%;
            box-sizing: border-box;
        }
    </style>
</head>

<body>
    <!-- header -->
    <?php require '../../includes/paymentheader.php'; ?>

    <!-- MAIN CONTAINER -->
    <div class="container">

        <!-- HERO SECTION -->
        <section class="hero">

            <div class="hero-left">
                <p class="tagline">RESERVATION CONFIRMED</p>

                <h1 class="hero-title">
                    THE KEYS ARE <br>
                    <span class="highlight">WAITING.</span>
                </h1>

                <p class="hero-description">
                    Your <?= htmlspecialchars($booking['model']) ?> is being prepped to exacting standards.
                    Expect a masterpiece in performance and aesthetics.
                </p>

                <div class="hero-buttons">
                    <a href="/vehicle_rental_collab_project/public/user/bookings.php" class="btnn btnn-primary">MANAGE
                        BOOKING</a>
                    <a href="" class="btnn btnn-secondary">ADD TO CALENDAR</a>
                </div>
            </div>

            <div class="hero-right">
                <div class="confirmation-card">
                    <img class="car-image" src="../../<?= htmlspecialchars($booking['image_path']) ?>" alt="">
                    <div class="car-image-overlay"></div>
                    <div class="confirmation-item">
                        <p class="label">CONFIRMATION</p>
                        <p class="value">#TD-<?= htmlspecialchars($booking['id']) ?></p>
                    </div>
                    <div class="confirmation-item">
                        <p class="label">STATUS</p>
                        <p class="value-status">SECURED</p>
                    </div>
                </div>
            </div>

        </section>

        <!-- RESERVATION DETAILS -->
        <section class="reservation-details">

            <div class="details-left">
                <h2>RESERVATION DETAILS</h2>
                <p>
                    Review your itinerary below. For any modifications, please use the
                    management portal or contact your concierge.
                </p>
            </div>

            <div class="details-right">

                <!-- COLLECTION POINT -->
                <div class="card location-card">
                    <p class="card-label"><i class="fa-solid fa-location-dot icon"></i>COLLECTION POINT</p>
                    <h3 class="location-title">MIAMI INTERNATIONAL</h3>
                    <p class="location-address">
                        VIP Terminal B, Suite 104 <br>
                        Miami, FL 33122
                    </p>
                    <div class="date-time">
                        <span class="date"><?= htmlspecialchars($booking['start_date']) ?></span>
                        <span class="time">10:00 AM</span>
                    </div>
                </div>

                <!-- RETURN POINT -->
                <div class="card location-card">
                    <p class="card-label"><i class="fa-solid fa-arrow-left icon"></i>RETURN POINT</p>
                    <h3 class="location-title">MIAMI INTERNATIONAL</h3>
                    <p class="location-address">
                        VIP Terminal B, Suite 104 <br>
                        Miami, FL 33122
                    </p>
                    <div class="date-time">
                        <span class="date"><?= htmlspecialchars($booking['end_date']) ?></span>
                        <span class="time">04:00 PM</span>
                    </div>
                </div>

            </div>

        </section>

    </div>
    <?php require '../../includes/footer.php'; ?>
</body>

</html>