<?php
require_once '../../config/db.php';
require_once '../../includes/tcpdf/tcpdf.php';

$booking_id = (int)($_GET['id'] ?? 0);
if (!$booking_id) die("Invalid booking");

// Fetch booking + vehicle + user
$stmt = $conn->prepare("
    SELECT b.*, v.model, v.price_per_day, u.first_name, u.email
    FROM bookings b
    JOIN vehicles v ON b.vehicle_id = v.id  
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) die("Booking not found");

// Create PDF
$pdf = new TCPDF();
$pdf->AddPage();

// Content
$html = "
<h1>TD Rentals Invoice</h1>
<hr>

<h3>Customer Info</h3>
<p>Name: {$data['first_name']}<br>
Email: {$data['email']}</p>

<h3>Booking Details</h3>
<p>Vehicle: {$data['model']}<br>
Pickup: {$data['pickup_date']}<br>
Dropoff: {$data['dropoff_date']}<br>
Days: {$data['days']}</p>

<h3>Payment</h3>
<p>Daily Rate: NPR {$data['price_per_day']}<br>
Total Paid: NPR {$data['total_price']}</p>

<hr>
<p>Thank you for choosing TD Rentals 🚗</p>
";

$pdf->writeHTML($html);

// Output
$pdf->Output("invoice_$booking_id.pdf", "I"); // I = open in browser
?>