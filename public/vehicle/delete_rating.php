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
$vehicle_id = (int) ($data['vehicle_id'] ?? 0);
$booking_id = (int) ($data['booking_id'] ?? 0);

if (!$vehicle_id || !$booking_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

// Delete the review (user can only have 1 review per vehicle, so delete by user and vehicle id)
$sql = "DELETE FROM reviews WHERE user_id = ? AND vehicle_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $vehicle_id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $conn->error]);
    exit;
}

// Update vehicle average rating (sub-select handles empty reviews nicely by setting avg_rating to NULL)
$avg_sql = "UPDATE vehicles SET avg_rating = (
    SELECT AVG(rating) FROM reviews WHERE vehicle_id = ?
) WHERE id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("ii", $vehicle_id, $vehicle_id);
$avg_stmt->execute();

echo json_encode(['success' => true]);
?>
