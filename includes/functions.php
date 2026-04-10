<?php
// includes/functions.php
// Reusable helper functions for Vehicle Rental System

/**
 * Escape output for safe HTML display (prevents XSS attacks)
 *
 * @param string $string The string to escape
 * @return string Escaped string safe for HTML output
 */
function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a different page and exit immediately
 *
 * @param string $url The URL to redirect to
 */
function redirect($url)
{
    header("Location: $url");
    exit;
}

/**
 * Check if a user is logged in
 *
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

/**
 * Check if logged-in user is an admin
 *
 * @return bool True if user has admin privileges, false otherwise
 */
function isAdmin()
{
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Generate a new CSRF token and store it in session
 *
 * @return string The generated CSRF token
 */
function generateCsrfToken()
{
    if (!isset($_SESSION)) {
        session_start();
    }
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verify a CSRF token from form submission
 *
 * @param string $token The token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCsrfToken($token)
{
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;

}
function show404()
{
    http_response_code(404);
    $errorPage = $_SERVER['DOCUMENT_ROOT'] . '/vehicle_rental_system/public/user/404.php';
    if (file_exists($errorPage)) {
        include($errorPage);
    } else {
        echo "<h1>404 - Page Not Found</h1>";
    }
    exit();
}
?>