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
    echo json_encode(['success' => false, 'message' => 'Database connection failed. Path error.']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

// 1. Validation
if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
    exit;
}

// Check for Uppercase, Lowercase, and Number
if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
    echo json_encode(['success' => false, 'message' => 'Password must contain uppercase, lowercase, and a number']);
    exit;
}

// 2. Verify current password from database
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

if (!$user || !password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// 3. Update to new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
$update_query = "UPDATE users SET password = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Update preparation failed']);
    exit;
}

mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update password in database']);
}

mysqli_close($conn);
?>