<?php
// ============================================================
// TD RENTALS — Database Configuration
// ============================================================

define('DB_HOST',   getenv('DB_HOST')   ?: 'localhost');
define('DB_NAME',   getenv('DB_NAME')   ?: 'td_rentals');
define('DB_USER',   getenv('DB_USER')   ?: 'root');
define('DB_PASS',   getenv('DB_PASS')   ?: '');
define('DB_CHARSET','utf8mb4');

// Site settings
define('SITE_NAME', 'TD RENTALS');

// Auto-detect SITE_URL from the current request so redirects always work
// regardless of what folder the project is in (td-rentals, td_rentals, htdocs root, etc.)
if (!defined('SITE_URL')) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Derive the base path from the script's location relative to the document root
    $docRoot  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $dir      = rtrim(dirname($_SERVER['SCRIPT_FILENAME'] ?? __FILE__), '/');
    $basePath = str_replace($docRoot, '', $dir);
    // Remove any trailing slash
    $basePath = rtrim($basePath, '/');
    define('SITE_URL', $scheme . '://' . $host . $basePath);
}

// Session secret (change this!)
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: 'change_me_to_a_random_string_32chars');

// ============================================================
// PDO singleton
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ============================================================
// Session bootstrap
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ============================================================
// Auth helpers
// ============================================================
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void {
    if (!currentUser()) {
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void {
    $u = currentUser();
    if (!$u || $u['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
}

// ============================================================
// JSON response helper (for API endpoints)
// ============================================================
function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// CSRF helpers
// ============================================================
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

// ============================================================
// Audit log helper
// ============================================================
function auditLog(string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void {
    $u = currentUser();
    try {
        $stmt = db()->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, target_type, target_id, detail) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $u['id']   ?? null,
            $u['name'] ?? 'System',
            $action, $targetType, $targetId ?: null, $detail ?: null
        ]);
    } catch (Exception $e) {}
}
