<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$rating = (int) ($data['rating'] ?? 0);
$vehicle_id = (int) ($data['vehicle_id'] ?? 0);
$booking_id = (int) ($data['booking_id'] ?? 0);

if ($rating < 1 || $rating > 5 || !$vehicle_id || !$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}
$review = isset($data['review']) ? trim($data['review']) : null;

// Enforce one rating/review per user per vehicle: check if they already rated this vehicle
$check_sql = "SELECT booking_id FROM reviews WHERE user_id = ? AND vehicle_id = ? LIMIT 1";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $user_id, $vehicle_id);
$check_stmt->execute();
$existing_review = $check_stmt->get_result()->fetch_assoc();
if ($existing_review) {
    // If they already have a review, update it under its original booking_id
    $booking_id = $existing_review['booking_id'];
}

$sql = "INSERT INTO reviews (booking_id, user_id, vehicle_id, rating, review) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            rating = VALUES(rating), 
            review = CASE 
                WHEN VALUES(review) IS NOT NULL THEN VALUES(review)
                ELSE review 
            END";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiis", $booking_id, $user_id, $vehicle_id, $rating, $review);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $conn->error]);
    exit;
}

// Update vehicle average
$avg_sql = "UPDATE vehicles SET avg_rating = (
    SELECT AVG(rating) FROM reviews WHERE vehicle_id = ?
) WHERE id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("ii", $vehicle_id, $vehicle_id);
$avg_stmt->execute();

$affected = $stmt->affected_rows;
// MySQL: INSERT = 1 row affected, ON DUPLICATE KEY UPDATE = 2 rows affected
$wasUpdate = $affected === 2;

echo json_encode(['success' => true, 'updated' => $wasUpdate]);

// echo json_encode(['success' => true]);