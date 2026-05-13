<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Auth check
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}

// CSRF check
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$booking_id = (int) ($_POST['booking_id'] ?? 0);
$new_end_raw = $_POST['new_end_date'] ?? '';

// Validate date format
if (!$booking_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_end_raw)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$new_end = new DateTime($new_end_raw);
$today = new DateTime('today');

if ($new_end <= $today) {
    echo json_encode(['success' => false, 'message' => 'New end date must be in the future.']);
    exit;
}

// Verify booking belongs to user and is active, get current end_date
$check_sql = "SELECT id, end_date, vehicle_id FROM bookings 
               WHERE id = ? AND user_id = ? AND status = 'confirmed' AND end_date >= CURDATE() 
               LIMIT 1";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $booking_id, $user_id);
$check_stmt->execute();
$booking = $check_stmt->get_result()->fetch_assoc();

if (!$booking) {
    echo json_encode(['success' => false, 'message' => 'Active booking not found.']);
    exit;
}

$current_end = new DateTime($booking['end_date']);
if ($new_end <= $current_end) {
    echo json_encode(['success' => false, 'message' => 'New date must be after current drop-off date.']);
    exit;
}

// Update the booking
$update_sql = "UPDATE bookings SET end_date = ? WHERE id = ? AND user_id = ?";
$update_stmt = $conn->prepare($update_sql);
$new_end_str = $new_end->format('Y-m-d');
$update_stmt->bind_param("sii", $new_end_str, $booking_id, $user_id);

if ($update_stmt->execute()) {
    echo json_encode([
        'success' => true,
        'new_end_date' => $new_end->format('M d, Y'),  // display format
        'raw_end_date' => $new_end_str,                 // for JS min/date update
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
?>