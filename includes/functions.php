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
    $errorPage = $_SERVER['DOCUMENT_ROOT'] . '/vehicle_rental_collab_project/public/user/404.php';
    if (file_exists($errorPage)) {
        include($errorPage);
    } else {
        echo "<h1>404 - Page Not Found</h1>";
    }
    exit();
}
// Luhn algorithm for credit card validation
function isValidLuhn($number) {
    $number = preg_replace('/\D/', '', $number); // remove spaces
    $sum = 0;
    $alternate = false;
    
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = intval($number[$i]);
        
        if ($alternate) {
            $n *= 2;
            if ($n > 9) {
                $n -= 9;
            }
        }
        
        $sum += $n;
        $alternate = !$alternate;
    }
    
    return ($sum % 10 == 0);
}

function getCardType($number) {
    if (preg_match('/^4/', $number)) return 'Visa';
    if (preg_match('/^5[1-5]/', $number)) return 'Mastercard';
    return 'Unknown';
}

?>