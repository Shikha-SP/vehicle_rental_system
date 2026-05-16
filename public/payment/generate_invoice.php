<?php
require_once __DIR__ . '/../../vendor/tecnickcom/tcpdf/tcpdf.php';

/**
 * TD Rentals — Dark-themed Invoice Generator
 *
 * Matches the design in the screenshot:
 *   - Dark (#1A1A1A) background
 *   - Crimson (#E63946) accents
 *   - Clean card layout with booking summary & itemised table
 *
 * @param  array  $data  Keys: booking_id, first_name, email, model,
 *                       pickup_date, dropoff_date, days,
 *                       price_per_day, basic_fee, total_price,
 *                       discount_code (opt), discount_amount (opt)
 * @return string  Raw PDF bytes (suitable for streaming or Storage::put)
 */
function generateInvoicePDF(array $data): string
{
    // ── Palette ──────────────────────────────────────────────────────────────
    $BG        = [26,  26,  26];   // #1A1A1A  page background
    $CARD      = [36,  36,  36];   // #242424  card fill
    $CARD_BDR  = [60,  60,  60];   // #3C3C3C  card border
    $RED       = [230, 57,  70];   // #E63946  accent
    $WHITE     = [255, 255, 255];
    $MUTED     = [180, 180, 180];  // labels / secondary text
    $DIVIDER   = [55,  55,  55];   // #373737  hairlines

    // ── Derived invoice values ───────────────────────────────────────────────
    $invoiceNo   = '#INV-' . str_pad($data['booking_id'], 5, '0', STR_PAD_LEFT);
    $invoiceDate = date('Y-m-d');

    $dailyTotal  = $data['price_per_day'] * $data['days'];
    $basicFee    = $data['basic_fee'] ?? 500;
    $subtotal    = $dailyTotal + $basicFee;
    $discount    = $data['discount_amount'] ?? 0;
    $total       = $data['total_price'];

    // ── TCPDF setup ──────────────────────────────────────────────────────────
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(0, 0, 0);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->AddPage();

    $W = $pdf->getPageWidth();   // 210
    $H = $pdf->getPageHeight();  // 297

    // ── Helper closures ──────────────────────────────────────────────────────

    /** Fill the entire page with the dark background */
    $drawPageBg = function () use ($pdf, $BG, $W, $H) {
        $pdf->SetFillColor(...$BG);
        $pdf->Rect(0, 0, $W, $H, 'F');
    };

    /** Rounded filled rectangle */
    $roundRect = function (
        float $x, float $y, float $w, float $h,
        array $fill, array $border = [], float $r = 3, float $lw = 0.3
    ) use ($pdf) {
        $pdf->SetFillColor(...$fill);
        if ($border) {
            $pdf->SetDrawColor(...$border);
            $pdf->SetLineWidth($lw);
            $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', 'DF');
        } else {
            $pdf->SetDrawColor(...$fill);
            $pdf->RoundedRect($x, $y, $w, $h, $r, '1111', 'F');
        }
    };

    /** Hairline divider */
    $divider = function (float $x, float $y, float $w) use ($pdf, $DIVIDER) {
        $pdf->SetDrawColor(...$DIVIDER);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($x, $y, $x + $w, $y);
    };

    /** Single-line text cell (no border) */
    $text = function (
        float $x, float $y, float $w, float $h,
        string $txt, int $size, array $color,
        string $align = 'L', string $style = '', float $lh = 1.15
    ) use ($pdf) {
        $pdf->SetXY($x, $y);
        $pdf->SetFont('helvetica', $style, $size);
        $pdf->SetTextColor(...$color);
        $pdf->MultiCell($w, $h, $txt, 0, $align, false, 1,
                         '', '', true, 0, false, true, $h * $lh, 'M');
    };

    // ═════════════════════════════════════════════════════════════════════════
    // RENDER
    // ═════════════════════════════════════════════════════════════════════════
    $drawPageBg();

    // ── Left margin / content width ──────────────────────────────────────────
    $mx  = 14;          // left margin
    $cw  = $W - $mx*2;  // content width  (182)
    $cy  = 18;          // current y cursor

    // ── TITLE ROW ────────────────────────────────────────────────────────────
    // "Invoice" in red
    $pdf->SetXY($mx, $cy);
    $pdf->SetFont('helvetica', 'B', 22);
    $pdf->SetTextColor(...$RED);
    $pdf->Cell(60, 10, 'Invoice', 0, 0, 'L');

    // Invoice # and date (right-aligned)
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(...$MUTED);
    $metaStr = "$invoiceNo  •  $invoiceDate";
    $pdf->SetXY($mx, $cy + 12);
    $pdf->Cell($cw, 5, $metaStr, 0, 0, 'L');

    // Red top accent line
    $pdf->SetDrawColor(...$RED);
    $pdf->SetLineWidth(0.6);
    $pdf->Line($mx, $cy + 19, $mx + $cw, $cy + 19);

    $cy += 24;

    // ── MAIN CARD ────────────────────────────────────────────────────────────
    $cardH = 218;   // total card height (will be trimmed to content)
    $roundRect($mx, $cy, $cw, $cardH, $CARD, $CARD_BDR, 4);

    $px  = $mx + 8;   // padding-x inside card
    $pcw = $cw - 16;  // padded content width
    $iy  = $cy + 8;   // inner y

    // ── BILL TO / FROM ───────────────────────────────────────────────────────
    $text($px,       $iy, 60, 4, 'BILL TO', 7, $MUTED, 'L', '');
    $text($px + 95,  $iy, 60, 4, 'FROM',    7, $MUTED, 'R', '');

    $iy += 7;
    $text($px,       $iy, 70, 6, $data['first_name'], 11, $WHITE,  'L', 'B');
    $text($px + 60,  $iy, $cw - 16 - 60, 6, 'TD RENTALS', 11, $RED, 'R', 'B');

    $iy += 7;
    $text($px,       $iy, 90, 5, $data['email'],            8, $MUTED, 'L', '');
    $text($px + 60,  $iy, $cw - 16 - 60, 5, 'Executive Logistics Div.', 8, $MUTED, 'R', '');

    $iy += 10;
    $divider($px, $iy, $pcw);
    $iy += 4;

    // ── BOOKING SUMMARY INNER BOX ─────────────────────────────────────────────
    $text($px, $iy, $pcw, 4, 'BOOKING SUMMARY', 7, $MUTED, 'L', '');
    $iy += 6;

    // Inner dashed card
    $pdf->SetFillColor(42, 42, 42);
    $pdf->SetDrawColor(...$CARD_BDR);
    $pdf->SetLineWidth(0.3);
    $pdf->RoundedRect($px, $iy, $pcw, 26, 2, '1111', 'DF');

    // Vehicle name & dates inside summary card
    $bx = $px + 5;
    $by = $iy + 4;
    $text($bx, $by, 60, 5, $data['model'], 10, $WHITE, 'L', 'B');

    // PICKUP
    $text($bx,      $by + 7, 30, 4, 'PICKUP', 6, $MUTED, 'L', '');
    $text($bx,      $by + 11, 30, 5, $data['pickup_date'], 8, $WHITE, 'L', '');

    // DROPOFF
    $text($bx + 38, $by + 7, 35, 4, 'DROPOFF', 6, $MUTED, 'L', '');
    $text($bx + 38, $by + 11, 35, 5, $data['dropoff_date'], 8, $WHITE, 'L', '');

    // DURATION (right side)
    $durX = $px + $pcw - 40;
    $text($durX, $by + 4, 35, 4, 'DURATION', 6, $MUTED, 'R', '');
    $text($durX, $by + 9, 35, 7, $data['days'] . ' Days', 14, $WHITE, 'R', 'B');

    $iy += 30;

    // ── LINE ITEMS TABLE ──────────────────────────────────────────────────────
    // Header row
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($px, $iy);

    $col1 = 85;  // Description column
    $col2 = 40;  // Rate/Unit
    $col3 = $pcw - $col1 - $col2;  // Total

    $pdf->Cell($col1, 5, 'DESCRIPTION', 0, 0, 'L');
    $pdf->Cell($col2, 5, 'RATE/UNIT',   0, 0, 'R');
    $pdf->Cell($col3, 5, 'TOTAL',       0, 0, 'R');

    $iy += 6;
    $divider($px, $iy, $pcw);
    $iy += 3;

    // Row helper
    $itemRow = function (
        float &$y, string $label, string $sub,
        string $rate, string $total
    ) use ($pdf, $px, $col1, $col2, $col3, $WHITE, $MUTED, $DIVIDER, $pcw, $divider) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(...$WHITE);
        $pdf->SetXY($px, $y);
        $pdf->Cell($col1, 5, $label, 0, 0, 'L');

        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($px + $col1, $y);
        $pdf->Cell($col2, 5, $rate, 0, 0, 'R');
        $pdf->SetXY($px + $col1 + $col2, $y);
        $pdf->Cell($col3, 5, $total, 0, 0, 'R');

        $y += 6;
        if ($sub) {
            $pdf->SetFont('helvetica', '', 7.5);
            $pdf->SetTextColor(...$MUTED);
            $pdf->SetXY($px, $y);
            $pdf->Cell($col1, 4, $sub, 0, 0, 'L');
            $y += 5;
        }
        $divider($px, $y, $pcw);
        $y += 3;
    };

    $fmt = fn($n) => 'NPR ' . number_format($n, 2);

    $itemRow(
        $iy,
        'Daily Vehicle Rental Rate',
        $data['model'] . ' - ' . $data['days'] . ' Days',
        $fmt($data['price_per_day']),
        $fmt($dailyTotal)
    );

    $itemRow(
        $iy,
        'Basic Booking Fee',
        'Administrative processing',
        $fmt($basicFee),
        $fmt($basicFee)
    );

    // Optional discount row
    if ($discount > 0) {
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetTextColor(46, 204, 113);   // green
        $pdf->SetXY($px, $iy);
        $code = $data['discount_code'] ?? 'DISCOUNT';
        $pdf->Cell($col1, 5, "Discount ($code)", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetXY($px + $col1, $iy);
        $pdf->Cell($col2, 5, '', 0, 0, 'R');
        $pdf->SetXY($px + $col1 + $col2, $iy);
        $pdf->Cell($col3, 5, '- ' . $fmt($discount), 0, 0, 'R');
        $iy += 6;
        $divider($px, $iy, $pcw);
        $iy += 3;
    }

    // ── SUBTOTAL / TAX ────────────────────────────────────────────────────────
    $summaryRow = function (
        float &$y, string $label, string $value,
        array $labelColor, array $valueColor, int $size = 8
    ) use ($pdf, $px, $pcw) {
        $pdf->SetFont('helvetica', '', $size);
        $pdf->SetTextColor(...$labelColor);
        $pdf->SetXY($px, $y);
        $pdf->Cell($pcw - 40, 5, $label, 0, 0, 'R');
        $pdf->SetFont('helvetica', '', $size);
        $pdf->SetTextColor(...$valueColor);
        $pdf->SetXY($px + $pcw - 40, $y);
        $pdf->Cell(40, 5, $value, 0, 0, 'R');
        $y += 6;
    };

    $summaryRow($iy, 'SUBTOTAL', $fmt($subtotal), $MUTED, $WHITE);

    $divider($px, $iy, $pcw);
    $iy += 4;

    // TOTAL — big red
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(...$MUTED);
    $pdf->SetXY($px, $iy);
    $pdf->Cell($pcw - 60, 7, 'TOTAL', 0, 0, 'R');

    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetTextColor(...$RED);
    $pdf->SetXY($px + $pcw - 60, $iy - 1);
    $pdf->Cell(60, 9, $fmt($total), 0, 0, 'R');
    $iy += 12;

    $divider($px, $iy, $pcw);
    $iy += 6;

    // ── THANK YOU FOOTER (inside card) ────────────────────────────────────────
    $text($px, $iy, $pcw, 5, '"Thank you for choosing TD Rentals"', 9, $WHITE, 'C', 'I');
    $iy += 7;
    $text($px, $iy, $pcw, 4, 'Executive Automotive Services  •  Precision Driven', 7, $MUTED, 'C', '');
    $iy += 8;

    // ── INVOICE NOTES (below card) ────────────────────────────────────────────
    $notesY = $cy + $cardH + 6;

    $text($mx, $notesY, $cw, 5, 'INVOICE NOTES', 8, $WHITE, 'C', 'B');
    $notesY += 8;

    $notesBody =
        "Please ensure payment is processed within 48 hours of invoice generation " .
        "to maintain booking priority. Rates are inclusive of basic insurance " .
        "coverage for the duration specified.";

    $pdf->SetXY($mx + 10, $notesY);
    $pdf->SetFont('helvetica', '', 8.5);
    $pdf->SetTextColor(...$MUTED);
    $pdf->MultiCell($cw - 20, 5, $notesBody, 0, 'C', false, 1);

    // ── BOTTOM BRAND LINE ─────────────────────────────────────────────────────
    $footY = $H - 18;
    $divider($mx, $footY, $cw);
    $text($mx, $footY + 3, $cw, 5, 'TD RENTALS', 9, $RED, 'C', 'B');
    $text($mx, $footY + 9, $cw, 4,
          '© ' . date('Y') . ' TD RENTALS. EXECUTIVE LOGISTICS DIVISION.',
          7, $MUTED, 'C', '');

    // ── OUTPUT ────────────────────────────────────────────────────────────────
    return $pdf->Output("invoice_{$data['booking_id']}.pdf", 'S');
}