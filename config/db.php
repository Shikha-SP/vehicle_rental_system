<?php
/**
 * config/db.php
 * Database connection + application constants
 * Include this file once at the top of every page via:
 *   require_once __DIR__ . '/../../config/db.php';   (from public/subfolder)
 *   require_once __DIR__ . '/../config/db.php';      (from includes/)
 *   require_once __DIR__ . '/config/db.php';         (from project root)
 */

// ─── App constants ────────────────────────────────────────────────────────────
//  define('SITE_URL',  'http://localhost/vehicle_rental_db');   // ← change for production
define('SITE_URL', 'http://localhost/vehicle_rental_system111/vehicle_rental_system');
define('ROOT_PATH', dirname(__DIR__));                // absolute path to project root

// ─── Database credentials ────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'vehicle_rental_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHAR', 'utf8mb4');

// ─── PDO singleton ────────────────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHAR;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ─── Session bootstrap ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
