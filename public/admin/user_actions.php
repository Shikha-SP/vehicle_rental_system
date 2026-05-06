<?php
require_once 'admin_functions.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$user_id = (int)($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$days = (int)($_POST['days'] ?? 0);

if (!$user_id || !in_array($action, ['ban', 'timeout', 'unban'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

try {
    if ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET status = 'active', ban_expires_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        $status = $action === 'ban' ? 'banned' : 'timeout';
        $expires = date('Y-m-d H:i:s', strtotime("+$days days"));
        
        $stmt = $conn->prepare("UPDATE users SET status = ?, ban_expires_at = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $expires, $user_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
