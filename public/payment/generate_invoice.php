<?php
require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

function generateInvoicePDF($data) {
    $pdf = new TCPDF();
    $pdf->AddPage();

    $invoice_no = "INV-" . str_pad($data['booking_id'], 5, "0", STR_PAD_LEFT);

    $discount_html = "";
    if (isset($data['discount_amount']) && $data['discount_amount'] > 0) {
        $discount_html = "<br><span style=\"color: #2ecc71;\">Discount (" . htmlspecialchars($data['discount_code']) . "): - NPR " . number_format($data['discount_amount'], 2) . "</span>";
    }

    $html = "
    <style>
        h1 { color: #C0392B; }
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
    Basic Fee: NPR 500{$discount_html}
    </p>

    <hr>
    <p><strong>Total: NPR " . number_format($data['total_price'], 2) . "</strong></p>

    <hr>
    <p>Thank you for choosing TD Rentals </p>
    ";

    $pdf->writeHTML($html);

    return $pdf->Output("invoice_{$data['booking_id']}.pdf", 'S');
}