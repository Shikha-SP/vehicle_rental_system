<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Fix the path: Go up ONE level from /ajax/ to find /config/
$db_path = __DIR__ . '/../config/db.php';

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    echo json_encode(['success' => false, 'message' => 'Critical: Database connection failed.']);
    exit;
}

// Check if user is logged in and is NOT admin
if (!isset($_SESSION['user_id']) || (!empty($_SESSION['is_admin']))) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$password = $data['password'] ?? '';
$confirm_text = $data['confirm_text'] ?? '';

// 1. Validation
if (empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required to delete account']);
    exit;
}

if ($confirm_text !== 'DELETE MY ACCOUNT') {
    echo json_encode(['success' => false, 'message' => 'Please type DELETE MY ACCOUNT exactly as shown']);
    exit;
}

// 2. Verify password before allowing deletion
$query = "SELECT password FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password. Deletion cancelled.']);
    exit;
}

// 3. Delete the user
// (Your DB schema has ON DELETE CASCADE on the bookings table, so this is safe)
$delete_query = "DELETE FROM users WHERE id = ?";
$delete_stmt = mysqli_prepare($conn, $delete_query);

if (!$delete_stmt) {
    echo json_encode(['success' => false, 'message' => 'Deletion query preparation failed.']);
    exit;
}

mysqli_stmt_bind_param($delete_stmt, "i", $user_id);

if (mysqli_stmt_execute($delete_stmt)) {
    // 4. Cleanup: Clear all session data and destroy it
    $_SESSION = array(); 
    session_destroy();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Your account has been permanently deleted.', 
        'redirect' => '../landing_page.php'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: Failed to delete account.']);
}

mysqli_close($conn);
?>