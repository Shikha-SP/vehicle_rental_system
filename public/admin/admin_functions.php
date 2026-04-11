<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';

function requireAdmin() {
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        header("Location: ../../index.php");
        exit;
    }
}

function currentUser() {
    global $conn;
    if (isset($_SESSION['user_id'])) {
        $id = (int)$_SESSION['user_id'];
        $res = $conn->query("SELECT * FROM users WHERE id = $id");
        return $res->fetch_assoc();
    }
    return null;
}

function getFlash() {
    $flash = ['type' => $_SESSION['flash_type'] ?? '', 'msg' => $_SESSION['flash_msg'] ?? ''];
    unset($_SESSION['flash_type'], $_SESSION['flash_msg']);
    return $flash;
}

function setFlash($type, $msg) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_msg'] = $msg;
}

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF token");
    }
}

function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auditLog($action, $target_type, $target_id, $detail) {
    global $conn;
    $admin_id = $_SESSION['user_id'] ?? 0;
    
    // In this project `username` is `first_name` and `last_name`
    $user = currentUser();
    $admin_name = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Admin';
    
    $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, target_type, target_id, detail) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isssis", $admin_id, $admin_name, $action, $target_type, $target_id, $detail);
        $stmt->execute();
    }
}

// Ensure audit_logs table exists
$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED,
    admin_name VARCHAR(100),
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40),
    target_id INT UNSIGNED,
    detail TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
)");

function uploadVehicleImage($fileArray) {
    if (!$fileArray || !isset($fileArray['tmp_name']) || empty($fileArray['tmp_name'])) {
        return null;
    }
    $targetDir = __DIR__ . '/../../assets/images/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $filename = time() . '_' . basename($fileArray['name']);
    $targetFile = $targetDir . $filename;
    
    if (move_uploaded_file($fileArray['tmp_name'], $targetFile)) {
        return $filename; // Returns path relative to assets/images/
    }
    return null;
}
?>
