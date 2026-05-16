<?php
require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

function generateInvoicePDF($data) {
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');

    $pdf->SetCreator('TD Rentals');
    $pdf->SetAuthor('TD Rentals');
    $pdf->SetTitle('Invoice');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->AddPage();

    // Force dark background
    $pdf->SetFillColor(13, 13, 13);
    $pdf->Rect(0, 0, 210, 297, 'F');

    $invoice_no   = "INV-" . str_pad($data['booking_id'], 5, "0", STR_PAD_LEFT);
    $invoice_date = date('Y-m-d');
    $daily_total  = $data['price_per_day'] * $data['days'];
    $booking_fee  = 500;
    $subtotal     = $daily_total + $booking_fee;
    $discount     = isset($data['discount_amount']) ? $data['discount_amount'] : 0;
    $tax          = 0;
    $total        = $subtotal - $discount + $tax;

    $discount_row = "";
    if ($discount > 0) {
        $discount_row = "
        <tr>
            <td style='padding:10px 0; border-bottom:1px solid #2a2a2a; color:#ffffff; font-size:11px;'>
                Discount (" . htmlspecialchars($data['discount_code']) . ")<br>
                <span style='color:#888888; font-size:10px;'>Promotional discount applied</span>
            </td>
            <td style='padding:10px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#2ecc71; font-size:11px;'>-</td>
            <td style='padding:10px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#2ecc71; font-size:11px;'>- NPR " . number_format($discount, 2) . "</td>
        </tr>";
    }

    $html = '
    <style>
        * { font-family: helvetica, sans-serif; }
    </style>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#0d0d0d; margin:0; padding:0;">
    <tr><td style="padding:24px 32px 16px;">

        <!-- HEADER -->
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <span style="font-size:26px; font-weight:bold; color:#e53935;">Invoice</span><br>
                <span style="font-size:11px; color:#888888;">#' . $invoice_no . ' &bull; ' . $invoice_date . '</span>
            </td>
        </tr>
        </table>

        <!-- Red divider -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px; margin-bottom:20px;">
        <tr><td style="height:2px; background-color:#e53935;"></td></tr>
        </table>

        <!-- MAIN CARD -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#1a1a1a;">
        <tr><td style="padding:24px;">

            <!-- BILL TO / FROM -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td width="50%" style="vertical-align:top;">
                    <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">BILL TO</span><br><br>
                    <span style="font-size:15px; font-weight:bold; color:#ffffff;">' . htmlspecialchars($data['first_name']) . '</span><br>
                    <span style="font-size:11px; color:#888888;">' . htmlspecialchars($data['email']) . '</span>
                </td>
                <td width="50%" style="vertical-align:top; text-align:right;">
                    <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">FROM</span><br><br>
                    <span style="font-size:15px; font-weight:bold; color:#e53935;">TD RENTALS</span><br>
                    <span style="font-size:11px; color:#888888;">Executive Logistics Div.</span>
                </td>
            </tr>
            </table>

            <!-- BOOKING SUMMARY BOX -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#222222; margin-bottom:20px;">
            <tr><td style="padding:16px;">
                <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">BOOKING SUMMARY</span><br><br>
                <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="60%" style="vertical-align:middle;">
                        <span style="font-size:14px; font-weight:bold; color:#ffffff;">' . htmlspecialchars($data['model']) . '</span><br><br>
                        <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="padding-right:24px;">
                                <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">PICKUP</span><br>
                                <span style="font-size:12px; color:#ffffff;">' . $data['pickup_date'] . '</span>
                            </td>
                            <td>
                                <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">DROPOFF</span><br>
                                <span style="font-size:12px; color:#ffffff;">' . $data['dropoff_date'] . '</span>
                            </td>
                        </tr>
                        </table>
                    </td>
                    <td width="40%" style="text-align:right; vertical-align:middle;">
                        <span style="font-size:9px; color:#888888; font-weight:bold; letter-spacing:1px;">DURATION</span><br>
                        <span style="font-size:18px; font-weight:bold; color:#ffffff;">' . $data['days'] . ' Days</span>
                    </td>
                </tr>
                </table>
            </td></tr>
            </table>

            <!-- LINE ITEMS -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
            <tr>
                <td style="padding-bottom:8px; border-bottom:1px solid #2a2a2a; font-size:9px; font-weight:bold; color:#888888; letter-spacing:1px;">DESCRIPTION</td>
                <td style="padding-bottom:8px; border-bottom:1px solid #2a2a2a; font-size:9px; font-weight:bold; color:#888888; letter-spacing:1px; text-align:right;">RATE/UNIT</td>
                <td style="padding-bottom:8px; border-bottom:1px solid #2a2a2a; font-size:9px; font-weight:bold; color:#888888; letter-spacing:1px; text-align:right;">TOTAL</td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; color:#ffffff; font-size:11px;">
                    Daily Vehicle Rental Rate<br>
                    <span style="color:#888888; font-size:10px;">' . htmlspecialchars($data['model']) . ' &bull; ' . $data['days'] . ' Days</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#ffffff; font-size:11px;">NPR ' . number_format($data['price_per_day'], 2) . '</td>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#ffffff; font-size:11px;">NPR ' . number_format($daily_total, 2) . '</td>
            </tr>
            <tr>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; color:#ffffff; font-size:11px;">
                    Basic Booking Fee<br>
                    <span style="color:#888888; font-size:10px;">Administrative processing</span>
                </td>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#ffffff; font-size:11px;">NPR 500.00</td>
                <td style="padding:12px 0; border-bottom:1px solid #2a2a2a; text-align:right; color:#ffffff; font-size:11px;">NPR 500.00</td>
            </tr>
            ' . $discount_row . '
            </table>

            <!-- SUBTOTAL / TAX -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
            <tr>
                <td width="60%"></td>
                <td width="20%" style="padding:4px 0; font-size:10px; color:#888888; letter-spacing:1px;">SUBTOTAL</td>
                <td width="20%" style="padding:4px 0; text-align:right; font-size:11px; color:#ffffff;">NPR ' . number_format($subtotal, 2) . '</td>
            </tr>
            <tr>
                <td width="60%"></td>
                <td width="20%" style="padding:4px 0; font-size:10px; color:#888888; letter-spacing:1px;">TAX (0%)</td>
                <td width="20%" style="padding:4px 0; text-align:right; font-size:11px; color:#ffffff;">NPR 0.00</td>
            </tr>
            </table>

            <!-- DIVIDER -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
            <tr><td style="height:1px; background-color:#2a2a2a;"></td></tr>
            </table>

            <!-- GRAND TOTAL -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">
            <tr>
                <td width="60%"></td>
                <td width="15%" style="font-size:11px; color:#888888; vertical-align:bottom; padding-bottom:4px;">TOTAL</td>
                <td width="25%" style="text-align:right;">
                    <span style="font-size:24px; font-weight:bold; color:#e53935;">NPR ' . number_format($total, 2) . '</span>
                </td>
            </tr>
            </table>

            <!-- DIVIDER -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
            <tr><td style="height:1px; background-color:#2a2a2a;"></td></tr>
            </table>

            <!-- THANK YOU -->
            <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="text-align:center; padding:8px 0;">
                    <span style="font-size:13px; font-style:italic; color:#cccccc;">&quot;Thank you for choosing TD Rentals&quot;</span><br>
                    <span style="font-size:10px; color:#666666;">Executive Automotive Services &bull; Precision Driven</span>
                </td>
            </tr>
            </table>

        </td></tr>
        </table>

        <!-- INVOICE NOTES -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:20px; margin-bottom:20px;">
        <tr>
            <td style="text-align:center; padding-bottom:10px;">
                <span style="font-size:9px; font-weight:bold; color:#888888; letter-spacing:2px;">INVOICE NOTES</span>
            </td>
        </tr>
        <tr>
            <td style="text-align:center; font-size:11px; color:#888888; line-height:1.6;">
                Please ensure payment is processed within 48 hours of invoice generation<br>
                to maintain booking priority. Rates are inclusive of basic insurance<br>
                coverage for the duration specified.
            </td>
        </tr>
        </table>

        <!-- FOOTER DIVIDER -->
        <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
        <tr><td style="height:1px; background-color:#2a2a2a;"></td></tr>
        </table>

        <!-- FOOTER -->
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="text-align:center; padding-bottom:6px;">
                <span style="font-size:13px; font-weight:bold; color:#e53935;">TD RENTALS</span>
            </td>
        </tr>
        <tr>
            <td style="text-align:center;">
                <span style="font-size:9px; color:#555555;">&copy; 2026 TD RENTALS. EXECUTIVE LOGISTICS DIVISION.</span>
            </td>
        </tr>
        </table>

    </td></tr>
    </table>';

    $pdf->writeHTML($html, true, false, true, false, '');

    return $pdf->Output("invoice_{$data['booking_id']}.pdf", 'S');
}