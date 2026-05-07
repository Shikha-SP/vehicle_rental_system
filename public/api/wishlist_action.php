<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to use wishlist.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$vehicle_id || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

if ($action === 'add') {
    $sql = "INSERT IGNORE INTO wishlist (user_id, vehicle_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $vehicle_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to wishlist.', 'in_wishlist' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to wishlist.']);
    }
} else {
    $sql = "DELETE FROM wishlist WHERE user_id = ? AND vehicle_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $vehicle_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Removed from wishlist.', 'in_wishlist' => false]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove from wishlist.']);
    }
}
