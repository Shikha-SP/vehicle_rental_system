<?php
/**
 * kharcha_portal_pay.php
 * Creates a Kharcha Payment Portal session and redirects the user to Kharcha's hosted checkout.
 * On completion Kharcha redirects back to kharcha_portal_return.php.
 */
require_once '../../config/db.php';
require_once '../../config/kharcha.php';
require_once '../../includes/functions.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../authentication/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: paymentdetail.php');
    exit;
}

// Read booking context from the portal form's hidden fields,
// falling back to session if already set (e.g., from a prior main-form POST).
$vehicle_id   = (int)   ($_POST['vehicle_id']   ?? ($_SESSION['payment_vehicle_id'] ?? 0));
$pickup_date  = trim(    $_POST['pickup_date']   ?? ($_SESSION['payment_pickup']     ?? ''));
$dropoff_date = trim(    $_POST['dropoff_date']  ?? ($_SESSION['payment_dropoff']    ?? ''));
$days         = (int)   ($_POST['days']          ?? ($_SESSION['payment_days']       ?? 0));

if (!$vehicle_id || !$pickup_date || !$dropoff_date || $days < 1) {
    $_SESSION['portal_error'] = 'Booking details are missing. Please go back and try again.';
    header('Location: paymentdetail.php');
    exit;
}

// Store in session so kharcha_portal_return.php can use them after the redirect back
$_SESSION['payment_vehicle_id'] = $vehicle_id;
$_SESSION['payment_pickup']     = $pickup_date;
$_SESSION['payment_dropoff']    = $dropoff_date;
$_SESSION['payment_days']       = $days;

// Fetch vehicle
$stmt = $conn->prepare("SELECT price_per_day, model FROM vehicles WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    $_SESSION['portal_error'] = 'Vehicle not found. Please go back and try again.';
    header('Location: paymentdetail.php');
    exit;
}

$totalprice = ((float)$vehicle['price_per_day'] * $days) + 500;

// ── Apply discount code if one was submitted from the payment form ─────────
$discount_code    = trim($_POST['applied_discount_code'] ?? '');
$discount_amount  = 0.00;
$discount_code_id = null;
$user_id          = $_SESSION['user_id'];

if (!empty($discount_code)) {
    $dc_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
    $dc_stmt->bind_param("s", $discount_code);
    $dc_stmt->execute();
    $dc_result = $dc_stmt->get_result();

    if ($dc_result->num_rows > 0) {
        $code_data = $dc_result->fetch_assoc();
        $code_id   = (int) $code_data['id'];
        $dc_stmt->close();

        $valid = true;
        if ($code_data['expires_at'] && $code_data['expires_at'] < date('Y-m-d')) $valid = false;
        if ($code_data['max_uses'] !== null && $code_data['used_count'] >= $code_data['max_uses']) $valid = false;
        if ($code_data['owner_user_id'] !== null && (int)$code_data['owner_user_id'] !== (int)$user_id) $valid = false;

        if ($valid) {
            $use_stmt = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
            $use_stmt->bind_param("ii", $user_id, $code_id);
            $use_stmt->execute();
            if ($use_stmt->get_result()->num_rows > 0) $valid = false;
            $use_stmt->close();
        }

        if ($valid) {
            if ($code_data['type'] === 'flat') {
                $discount_amount = min((float)$code_data['discount_flat'], $totalprice);
            } else {
                $discount_amount = ($totalprice * (float)$code_data['discount_percent']) / 100;
            }
            $totalprice      -= $discount_amount;
            $discount_code_id = $code_id;
        }
    } else {
        $dc_stmt->close();
    }
}

// Store discount info in session so kharcha_portal_return.php can record it
$_SESSION['kharcha_portal_discount_code']    = $discount_code;
$_SESSION['kharcha_portal_discount_amount']  = $discount_amount;
$_SESSION['kharcha_portal_discount_code_id'] = $discount_code_id;

$note = "TD Rentals – {$vehicle['model']} ({$pickup_date} to {$dropoff_date})";

// Build return URL using the actual script directory so it works regardless of deployment path
$scheme     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'];
$script_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$return_url = $scheme . '://' . $host . $script_dir . '/kharcha_portal_return.php';

// Create portal session via Kharcha API
$result = kharchaCreatePortalSession($totalprice, $note, $return_url);

if (!($result['success'] ?? false)) {
    $_SESSION['portal_error'] = $result['message'] ?? 'Failed to create Kharcha portal session. Please try again.';
    header('Location: paymentdetail.php');
    exit;
}

// Stash portal session_id so return handler can verify it
$_SESSION['kharcha_portal_session_id'] = $result['session_id'];
$_SESSION['kharcha_portal_amount']     = $totalprice;

// Redirect user to Kharcha's hosted checkout
header('Location: ' . $result['checkout_url']);
exit;