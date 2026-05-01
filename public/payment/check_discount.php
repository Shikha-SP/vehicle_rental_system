<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['valid' => false, 'message' => 'User not logged in']);
    exit;
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code        = strtoupper(trim($_POST['code'] ?? ''));
    $total_price = floatval($_POST['total_price'] ?? 0);
    $user_id     = $_SESSION['user_id'];

    if (empty($code)) {
        echo json_encode(['valid' => false, 'message' => 'Please enter a code']);
        exit;
    }

    // 1. Fetch the code
    $stmt = $conn->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['valid' => false, 'message' => 'Invalid or inactive discount code']);
        $stmt->close();
        exit;
    }

    $c = $result->fetch_assoc();
    $stmt->close();

    // 2. Check ownership if it's a personal code
    if ($c['owner_user_id'] !== null && (int)$c['owner_user_id'] !== (int)$user_id) {
        echo json_encode(['valid' => false, 'message' => 'This is a personal discount code and cannot be used by you.']);
        exit;
    }

    // 3. Check expiry
    if ($c['expires_at'] && $c['expires_at'] < date('Y-m-d')) {
        echo json_encode(['valid' => false, 'message' => 'This code has expired']);
        exit;
    }

    // 3. Check max uses (global)
    if ($c['max_uses'] !== null && $c['used_count'] >= $c['max_uses']) {
        echo json_encode(['valid' => false, 'message' => 'This code has reached its maximum uses']);
        exit;
    }

    // 4. Check if this user already used this code
    $stmt = $conn->prepare("SELECT id FROM discount_code_uses WHERE user_id = ? AND code_id = ?");
    $stmt->bind_param("ii", $user_id, $c['id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['valid' => false, 'message' => 'You have already used this discount code']);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // 5. Calculate discount
    if ($c['type'] === 'flat') {
        $discount_amount = min($c['discount_flat'], $total_price);
        $discount_label  = 'NPR ' . number_format($c['discount_flat'], 0) . ' off';
        $discount_percent = 0;
    } else {
        $discount_amount = ($total_price * $c['discount_percent']) / 100;
        $discount_label  = $c['discount_percent'] . '% off';
        $discount_percent = $c['discount_percent'];
    }

    $new_total = $total_price - $discount_amount;

    echo json_encode([
        'valid'            => true,
        'message'          => 'Discount applied! (' . $discount_label . ')',
        'type'             => $c['type'],
        'discount_percent' => $discount_percent,
        'discount_amount'  => round($discount_amount, 2),
        'new_total'        => round($new_total, 2),
        'discount_label'   => $discount_label,
    ]);
    exit;
}

echo json_encode(['valid' => false, 'message' => 'Invalid request']);
