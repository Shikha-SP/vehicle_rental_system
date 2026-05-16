<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$owner_id = (int) $_SESSION['user_id'];
$review_id = (int) ($_POST['review_id'] ?? 0);
$reply = trim($_POST['reply_text'] ?? '');

if (!$review_id || !$reply) {
    echo json_encode(['success' => false, 'message' => 'Missing fields']);
    exit;
}

// Verify the logged-in user actually owns the vehicle this review is for
$check_sql = "SELECT r.id FROM reviews r 
              JOIN vehicles v ON r.vehicle_id = v.id 
              WHERE r.id = ? AND v.user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $review_id, $owner_id);
$check_stmt->execute();
if (!$check_stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Upsert — one reply per review
$upsert_sql = "INSERT INTO review_replies (review_id, owner_id, reply_text) 
               VALUES (?, ?, ?) 
               ON DUPLICATE KEY UPDATE reply_text = VALUES(reply_text), created_at = NOW()";

// Add UNIQUE constraint if not already: ALTER TABLE review_replies ADD UNIQUE (review_id);

$upsert_stmt = $conn->prepare($upsert_sql);
$upsert_stmt->bind_param("iis", $review_id, $owner_id, $reply);

if ($upsert_stmt->execute()) {
    echo json_encode(['success' => true, 'reply_text' => htmlspecialchars($reply)]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>