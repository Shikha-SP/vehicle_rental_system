<?php
session_start();
require_once '../../config/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'send') {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    if (!$booking_id || !$message) {
        echo json_encode(['success' => false, 'message' => 'Missing data']);
        exit;
    }

    // Verify booking and get parties
    $stmt = $conn->prepare("SELECT user_id FROM bookings WHERE id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit;
    }

    // Who is the receiver? 
    // If sender is user, receiver is admin. 
    // If sender is admin, receiver is user.
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();

    $receiver_id = null;
    if ($me['is_admin']) {
        $receiver_id = $booking['user_id'];
    } else {
        // Find an admin. For simplicity, we'll send to the first admin found or a specific admin ID if defined.
        $res = $conn->query("SELECT id FROM users WHERE is_admin = 1 LIMIT 1");
        $admin = $res->fetch_assoc();
        $receiver_id = $admin['id'];
    }

    if (!$receiver_id) {
        echo json_encode(['success' => false, 'message' => 'No admin found to receive message']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO messages (booking_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $booking_id, $user_id, $receiver_id, $message);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'DB Error']);
    }

} elseif ($action === 'get') {
    $booking_id = (int)($_GET['booking_id'] ?? 0);
    if (!$booking_id) {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id']);
        exit;
    }

    // Mark as read if I am the receiver
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE booking_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();

    $stmt = $conn->prepare("
        SELECT m.*, u.first_name, u.is_admin as sender_is_admin 
        FROM messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.booking_id = ? 
        ORDER BY m.created_at ASC
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'messages' => $messages]);

} elseif ($action === 'counts') {
    // Admin only: get unread counts per booking
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc();
    
    if (!$me['is_admin']) {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $sql = "
        SELECT 
            b.id as booking_id, 
            u.first_name as user_name, 
            u.last_name as user_last_name,
            v.model as vehicle_model,
            COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = ? THEN 1 END) as unread_count,
            MAX(m.created_at) as last_message_time
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN vehicles v ON b.vehicle_id = v.id
        LEFT JOIN messages m ON b.id = m.booking_id
        GROUP BY b.id
        HAVING unread_count > 0 OR last_message_time IS NOT NULL
        ORDER BY last_message_time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'conversations' => $counts]);
}
