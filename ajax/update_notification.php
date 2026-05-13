<?php
/**
 * AJAX Endpoint: Update Notification Preference
 *
 * Accepts a JSON POST body with { "enabled": true|false }
 * Upserts the user's row in notification_preference.
 */
session_start();

header('Content-Type: application/json');

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Parse JSON body
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'Missing enabled field']);
    exit;
}

$enabled = $data['enabled'] ? 1 : 0;
$user_id = (int) $_SESSION['user_id'];

require_once '../config/db.php';

// Use INSERT ... ON DUPLICATE KEY UPDATE so the row is created if it
// doesn't exist yet (first time a user visits settings).
$sql = "UPDATE notification_preference
    SET enabled = ?
    WHERE user_id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ii', $enabled, $user_id);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => $enabled ? 'Notifications enabled' : 'Notifications disabled',
        'enabled' => (bool) $enabled
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update preference']);
}

$stmt->close();
$conn->close();
?>