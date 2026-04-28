<?php
require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

function generateInvoicePDF($data) {
    $pdf = new TCPDF();
    $pdf->AddPage();

    $invoice_no = "INV-" . str_pad($data['booking_id'], 5, "0", STR_PAD_LEFT);

    $html = "
    <style>
        h1 { color: #e63946; }
        h3 { margin-top: 20px; }
        p { font-size: 12px; }
    </style>

    <h1>TD Rentals Invoice</h1>
    <hr>

    <p><strong>  Invoice #:</strong> {$invoice_no}<br>
    <strong>Date:</strong> " . date('Y-m-d') . "</p>

    <h3>Customer Information</h3>
    <hr>
    <p>
    Name: " . htmlspecialchars($data['first_name']) . "<br>
    Email: " . htmlspecialchars($data['email']) . "
    </p>

    <h3>Booking Details</h3>
    <hr>
    <p>
    Vehicle: " . htmlspecialchars($data['model']) . "<br>
    Pickup: {$data['pickup_date']}<br>
    Dropoff: {$data['dropoff_date']}<br>
    Duration: {$data['days']} days
    </p>

    <h3>Price Breakdown</h3>
    <hr>
    <p>
    Daily Rate: NPR {$data['price_per_day']}<br>
    Basic Fee: NPR 500
    </p>

    <hr>
    <p><strong>Total: NPR {$data['total_price']}</strong></p>

    <hr>
    <p>Thank you for choosing TD Rentals </p>
    ";

    $pdf->writeHTML($html);

    return $pdf->Output("invoice_{$data['booking_id']}.pdf", 'S');
}