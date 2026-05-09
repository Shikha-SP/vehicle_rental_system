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
function getImageUrl($path)
{
    if (empty($path) || $path === '0') {
        return null;
    }

    // If already a full URL, return as-is
    if (strpos($path, 'http') === 0) {
        return $path;
    }

    // Strip any leading slash from stored path so we don't double-slash
    $path = ltrim($path, '/');

    // If the path doesn't start with Uploads/ or assets/, assume it's a legacy vehicle image
    // Check both lowercase and uppercase for robustness
    $lowPath = strtolower($path);
    if (strpos($lowPath, 'uploads/') !== 0 && strpos($lowPath, 'assets/') !== 0) {
        $path = 'Uploads/Vehicles/' . $path;
    }

    $scriptParts = explode('/', trim($_SERVER['SCRIPT_NAME'], '/'));
    $projectRoot = isset($scriptParts[0]) ? '/' . $scriptParts[0] : '';

    return $projectRoot . '/' . $path;
}

/**
 * Check if an email is genuine by validating format and domain MX records.
 * 
 * @param string $email
 * @return bool
 */
function is_email_genuine($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = substr(strrchr($email, "@"), 1);
    
    // Check if domain has any MX records. 
    // This confirms the domain is configured to receive emails.
    // Note: This might fail in environments without internet access.
    if (!checkdnsrr($domain, "MX")) {
        // If MX fails, check for A record as a fallback (some domains handle mail without MX)
        return checkdnsrr($domain, "A");
    }

    return true;
}

/**
 * Generate a random numeric OTP.
 * 
 * @param int $length
 * @return string
 */
function generateOTP($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}
?>