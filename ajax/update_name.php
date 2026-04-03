<?php
// 1. Start session and enable error reporting for debugging
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 2. Set JSON header early
header('Content-Type: application/json');

// 3. Robust pathing for the database connection
// Using __DIR__ ensures the path is relative to THIS file regardless of server config
$db_path = __DIR__ . '/../config/db.php';

if (file_exists($db_path)) {
    require_once $db_path;
} else {
    echo json_encode(['success' => false, 'message' => 'Database configuration file missing at: ' . $db_path]);
    exit;
}

// 4. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

// Check for admin restriction (matching your original logic)
if (!empty($_SESSION['is_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Admin accounts cannot be modified via this endpoint.']);
    exit;
}

$user_id = $_SESSION['user_id'];

// 5. Get and decode JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input received.']);
    exit;
}

$first_name = trim($data['first_name'] ?? '');
$last_name = trim($data['last_name'] ?? '');
$current_password = $data['current_password'] ?? '';

// 6. Validation
if (empty($first_name) || empty($last_name)) {
    echo json_encode(['success' => false, 'message' => 'First and last names are required.']);
    exit;
}

if (strlen($first_name) < 2 || strlen($last_name) < 2) {
    echo json_encode(['success' => false, 'message' => 'Names must be at least 2 characters long.']);
    exit;
}

if (empty($current_password)) {
    echo json_encode(['success' => false, 'message' => 'Current password is required for security.']);
    exit;
}

// 7. Verify current password against database
// Using a prepared statement for security
$query = "SELECT password FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !password_verify($current_password, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Verification failed: The current password you entered is incorrect.']);
    exit;
}

// 8. Execute the update
$update_query = "UPDATE users SET first_name = ?, last_name = ? WHERE id = ?";
$update_stmt = mysqli_prepare($conn, $update_query);

if (!$update_stmt) {
    echo json_encode(['success' => false, 'message' => 'Update preparation failed: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($update_stmt, "ssi", $first_name, $last_name, $user_id);

if (mysqli_stmt_execute($update_stmt)) {
    // Sync the session with the new name
    $_SESSION['username'] = $first_name . ' ' . $last_name;
    
    echo json_encode([
        'success' => true, 
        'message' => 'Success! Your name has been updated.',
        'full_name' => $_SESSION['username']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: Could not update user information.']);
}

// 9. Clean up
mysqli_close($conn);
?>