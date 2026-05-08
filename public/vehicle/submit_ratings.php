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
$review = trim($data['review'] ?? '');

$sql = "INSERT INTO reviews (booking_id, user_id, vehicle_id, rating, review) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiis", $booking_id, $user_id, $vehicle_id, $rating, $review);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error']);
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