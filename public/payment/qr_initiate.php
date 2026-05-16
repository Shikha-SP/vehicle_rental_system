<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/db.php';
require_once '../../includes/functions.php';
$khalti_config = require_once '../../config/khalti.php';

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

// Fetch vehicle and user details
$sql = "SELECT v.model, v.price_per_day, u.email, u.first_name, u.phone_number 
        FROM vehicles v 
        JOIN users u ON u.id = ? 
        WHERE v.id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $vehicle_id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("Vehicle or User not found.");
}

$stmt->close();

$basicprice = 500; // From paymentdetail.php
$total_price_npr = ($data['price_per_day'] * $days) + $basicprice;

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

            $_SESSION['khalti_discount_code'] = $discount_code;
            $_SESSION['khalti_discount_amount'] = $discount_amount;
            $_SESSION['khalti_discount_code_id'] = $code_id;
        }
    }
    $dc_stmt->close();
} else {
    unset($_SESSION['khalti_discount_code'], $_SESSION['khalti_discount_amount'], $_SESSION['khalti_discount_code_id']);
}

$amount_paisa = $total_price_npr * 100; // Khalti expects amount in paisa

// Generate a unique purchase order ID
$purchase_order_id = "BOOK-" . $user_id . "-" . $vehicle_id . "-" . time();

$admin_sql = "SELECT first_name, email, phone_number FROM users WHERE is_admin = 1 LIMIT 1";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->execute();
$admin_data = $admin_stmt->get_result()->fetch_assoc();

$post_data = [
    'return_url' => $khalti_config['return_url'],
    'website_url' => $khalti_config['website_url'],
    'amount' => (int) $amount_paisa,
    'purchase_order_id' => $purchase_order_id,
    'purchase_order_name' => "Rental: " . $data['model'],
    'customer_info' => [
        'name' => $data['first_name'] ?? 'User',
        'email' => $data['email'] ?? 'user@tdrentals.com',
        'phone' => $data['phone_number'] ?? '9800000000'
    ]
];

// Pre-create the booking as pending so the callback knows about it without needing the session
$insert_sql = "INSERT INTO bookings (user_id, vehicle_id, start_date, end_date, total_price, status, payment_status, purchase_order_id, discount_code, discount_amount, created_at)
               VALUES (?, ?, ?, ?, ?, 'confirmed', 'pending', ?, ?, ?, NOW())";
$insert_stmt = $conn->prepare($insert_sql);
$insert_stmt->bind_param("iissdssd", $user_id, $vehicle_id, $pickup_date, $dropoff_date, $total_price_npr, $purchase_order_id, $discount_code, $discount_amount);
if (!$insert_stmt->execute()) {
    die("Failed to create pending booking.");
}
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $khalti_config['base_url'] . 'epayment/initiate/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($post_data),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER => array(
        'Authorization: Key ' . $khalti_config['secret_key'],
        'Content-Type: application/json',
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

if ($err) {
    die("cURL Error #:" . $err);
}

$response_data = json_decode($response, true);

if (isset($response_data['payment_url'])) {
    $_SESSION['khalti_purchase_order_id'] = $purchase_order_id;
    $_SESSION['khalti_amount'] = $total_price_npr;
    // Store pidx so check_payment.php can verify with Khalti API during polling
    // This is required because the phone browser can't reach localhost to call khalti_callback.php
    $_SESSION['khalti_pidx'] = $response_data['pidx'] ?? '';
    $payment_url = $response_data['payment_url'];
} else {
    echo "Khalti Error: " . ($response_data['detail'] ?? 'Unknown error');
    echo "<pre>";
    print_r($response_data);
    echo "</pre>";
    exit;
}
?>

<?php require_once '../../includes/header.php'; ?>
<style>
    .qr-container {
        text-align: center;
        padding: 60px 20px;
        min-height: 60vh;
        background-color: #f9f9f9;
        border-radius: 10px;
        margin: 40px auto;
        max-width: 600px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .qr-title {
        color: #2c3e50;
        margin-bottom: 10px;
        font-family: 'Inter', sans-serif;
    }

    .qr-subtitle {
        color: #666;
        margin-bottom: 30px;
    }

    #qrcode {
        display: flex;
        justify-content: center;
        margin: 30px 0;
        padding: 20px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        display: inline-block;
    }

    .back-btn {
        display: inline-block;
        margin-top: 30px;
        padding: 12px 25px;
        background-color: #333;
        color: #fff;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        transition: background-color 0.3s;
    }

    .back-btn:hover {
        background-color: #555;
        color: white;
    }

    .status-text {
        color: #27ae60;
        font-weight: 600;
        margin-top: 20px;
    }
</style>

<div class="qr-container">
    <h2 class="qr-title">Scan QR to Pay</h2>
    <p class="qr-subtitle">Please scan the QR code below using your mobile device or Khalti app.</p>

    <div id="qrcode"></div>

    <p class="status-text"><i class="fa-solid fa-circle-notch fa-spin"></i> Waiting for payment...</p>
    <p style="color: #888; font-size: 14px; margin-top: 15px;">
        Once you complete the payment on your phone, you will receive a confirmation email.<br>
        If your browser does not redirect automatically, please check your bookings.
    </p>

    <a href="paymentdetail.php" class="back-btn">Cancel & Go Back</a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    var paymentUrl = "<?php echo htmlspecialchars($payment_url); ?>";
    var orderId = "<?php echo htmlspecialchars($purchase_order_id); ?>";

    new QRCode(document.getElementById("qrcode"), {
        text: paymentUrl,
        width: 280,
        height: 280,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    // Poll every 3 seconds to check if payment is done
    var pollInterval = setInterval(function () {
        fetch('check_payment.php?order_id=' + orderId + '&_=' + new Date().getTime(), { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'paid') {
                    clearInterval(pollInterval);
                    window.location.href = 'bookingconfirmed.php?id=' + data.booking_id;
                } else if (data.status === 'failed') {
                    clearInterval(pollInterval);
                    window.location.href = 'paymentdetail.php?error=payment_failed';
                }
            })
            .catch(err => console.error("Polling error:", err));
    }, 3000);
</script>

<?php require '../../includes/footer.php'; ?>