<?php
/**
 * ajax/calculator.php  —  Booking price breakdown + availability JSON endpoint
 *
 * Called by assets/js/app.js via fetch():
 *   /ajax/calculator.php?car_id=N&pickup_date=YYYY-MM-DD&dropoff_date=YYYY-MM-DD
 *
 * Returns JSON with availability flag, cost breakdown, and booked date ranges.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$carId   = (int)($_GET['car_id']      ?? 0);
$pickup  = $_GET['pickup_date']  ?? '';
$dropoff = $_GET['dropoff_date'] ?? '';

if (!$carId) {
    jsonResponse(['error' => 'Missing car_id parameter'], 400);
}

$car = db()->prepare("SELECT price_per_day FROM cars WHERE id = ? AND available = 1 LIMIT 1");
$car->execute([$carId]);
$car = $car->fetch();

if (!$car) {
    jsonResponse(['error' => 'Car not found or unavailable'], 404);
}

// Fetch all booked date ranges (for front-end calendar blocking)
$bookedStmt = db()->prepare("
    SELECT pickup_date, dropoff_date FROM bookings
    WHERE car_id = ? AND status NOT IN ('cancelled')
    ORDER BY pickup_date ASC
");
$bookedStmt->execute([$carId]);
$bookedRanges = $bookedStmt->fetchAll(PDO::FETCH_ASSOC);

// If dates were supplied, check availability and calculate cost
if ($pickup && $dropoff) {
    $p = strtotime($pickup);
    $d = strtotime($dropoff);

    if (!$p || !$d || $d <= $p) {
        jsonResponse(['error' => 'Invalid date range', 'booked_ranges' => $bookedRanges], 422);
    }

    $isAvailable = isCarAvailable($carId, $pickup, $dropoff);
    $costs       = calcBooking((float)$car['price_per_day'], $pickup, $dropoff);

    jsonResponse([
        'available'     => $isAvailable,
        'days'          => $costs['days'],
        'price_per_day' => (float)$car['price_per_day'],
        'rental_total'  => $costs['rental_total'],
        'insurance_fee' => $costs['insurance_fee'],
        'grand_total'   => $costs['grand_total'],
        'booked_ranges' => $bookedRanges,
    ]);
}

// No dates — just return booked ranges
jsonResponse(['booked_ranges' => $bookedRanges]);
