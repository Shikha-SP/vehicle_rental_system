<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../includes/functions.php';
$esewa_config = require_once '../../config/esewa.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    die("User not logged in.");
}

$user_id = $_SESSION['user_id'];
$vehicle_id = $_SESSION['payment_vehicle_id'] ?? 0;
$pickup_date = $_SESSION['payment_pickup'] ?? '';
$dropoff_date = $_SESSION['payment_dropoff'] ?? '';
$days = $_SESSION['payment_days'] ?? 0;

if (!$vehicle_id) {
    header('Location: ../vehicle/vehicles.php');
    exit;
}

// Fetch vehicle price
$sql = "SELECT price_per_day FROM vehicles WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $vehicle_id);
$stmt->execute();
$vehicle = $stmt->get_result()->fetch_assoc();

if (!$vehicle) {
    die("Vehicle not found.");
}

$stmt->close();

$basicprice = 500; // From paymentdetail.php
$total_price_npr = ($vehicle['price_per_day'] * $days) + $basicprice;

$discount_code = strtoupper(trim((string) ($_GET['discount_code'] ?? '')));
$discount_amount = 0;

if ($discount_code !== '') {
    $dc_stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
    $dc_stmt->bind_param("s", $discount_code);
    $dc_stmt->execute();
    $result = $dc_stmt->get_result();

    if ($result->num_rows > 0) {
        $code_data = $result->fetch_assoc();
        $code_id = (int) $code_data['id'];

        $valid = true;
        if ($code_data['expires_at'] && $code_data['expires_at'] < date('Y-m-d')) {
            $valid = false;
        }
        if ($code_data['max_uses'] !== null && $code_data['used_count'] >= $code_data['max_uses']) {
            $valid = false;
        }
        if ($code_data['owner_user_id'] !== null && (int) $code_data['owner_user_id'] !== (int) $user_id) {
            $valid = false;
        }

        if ($valid) {
            $check_stmt = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
            $check_stmt->bind_param("ii", $user_id, $code_id);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                $valid = false;
            }
            $check_stmt->close();
        }

        if ($valid) {
            if ($code_data['type'] === 'flat') {
                $discount_amount = min((float) $code_data['discount_flat'], $total_price_npr);
            } else {
                $discount_amount = ($total_price_npr * (float) $code_data['discount_percent']) / 100;
            }
            $total_price_npr -= $discount_amount;

            $_SESSION['esewa_discount_code'] = $discount_code;
            $_SESSION['esewa_discount_amount'] = $discount_amount;
            $_SESSION['esewa_discount_code_id'] = $code_id;
        }
    }
    $dc_stmt->close();
} else {
    unset($_SESSION['esewa_discount_code'], $_SESSION['esewa_discount_amount'], $_SESSION['esewa_discount_code_id']);
}

// Generate a unique transaction UUID
$transaction_uuid = "ESEWA-" . $user_id . "-" . $vehicle_id . "-" . time();

// Store in session for callback verification
$_SESSION['esewa_transaction_uuid'] = $transaction_uuid;
$_SESSION['esewa_amount'] = $total_price_npr;

// eSewa Signature Generation
$message = "total_amount=$total_price_npr,transaction_uuid=$transaction_uuid,product_code=" . $esewa_config['merchant_code'];
$secret_key = $esewa_config['secret_key'];
$signature = base64_encode(hash_hmac('sha256', $message, $secret_key, true));

// Prepare parameters for automatic form submission
$params = [
    'amount' => $total_price_npr,
    'tax_amount' => 0,
    'total_amount' => $total_price_npr,
    'transaction_uuid' => $transaction_uuid,
    'product_code' => $esewa_config['merchant_code'],
    'product_service_charge' => 0,
    'product_delivery_charge' => 0,
    'success_url' => $esewa_config['return_url'],
    'failure_url' => $esewa_config['failure_url'],
    'signed_field_names' => 'total_amount,transaction_uuid,product_code',
    'signature' => $signature,
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Redirecting to eSewa...</title>
</head>
<body onload="document.forms['esewa_form'].submit();">
    <p>Please wait, redirecting to eSewa...</p>
    <form name="esewa_form" action="<?= $esewa_config['base_url'] ?>api/epay/main/v2/form" method="POST">
        <?php foreach ($params as $key => $value): ?>
            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
        <?php endforeach; ?>
    </form>
</body>
</html>
