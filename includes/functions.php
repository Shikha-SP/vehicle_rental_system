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
function isValidLuhn($number)
{
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

function getCardType($number)
{
    if (preg_match('/^4/', $number))
        return 'Visa';
    if (preg_match('/^5[1-5]/', $number))
        return 'Mastercard';
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
 * Send an instant reminder email based on time until pickup
 */
function sendReminderEmail($conn, $bookingId, $userEmail, $firstName, $vehicleModel, $pickupDatetime, $totalPrice)
{
    require_once __DIR__ . '/../config/mailer.php';

    $pickupTimestamp = strtotime($pickupDatetime);
    $currentTimestamp = time();
    $hoursLeft = ($pickupTimestamp - $currentTimestamp) / 3600;

    if ($hoursLeft < 0)
        return false; // Already passed

    if ($hoursLeft < 0.5) {
        $reminderType = 'email_30min';
        $subject = 'Final Alert: Pickup in 30 Minutes';
    } elseif ($hoursLeft <= 2) {
        $reminderType = 'email_2h';
        $subject = 'Urgent: Your Pickup is in 2 Hours';
    } else {
        $reminderType = 'email_24h';
        $subject = 'Reminder: Pickup Tomorrow';
    }

    // Check if already sent
    $checkStmt = $conn->prepare("SELECT id FROM reminder_log WHERE booking_id = ? AND reminder_type = ?");
    $checkStmt->bind_param("is", $bookingId, $reminderType);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        return false; // Already sent this type
    }

    try {
        $mail = createMailer();
        $mail->addAddress($userEmail, $firstName);
        $mail->Subject = $subject;

        $startDate = date('Y-m-d', $pickupTimestamp);
        $pickupTime = date('H:i:s', $pickupTimestamp);
        $totalPriceFmt = number_format((float) $totalPrice, 2);

        $htmlBody = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Hi {$firstName},</h2>
                <p>This is a reminder regarding your upcoming vehicle rental.</p>
                <table style='width: 100%; max-width: 400px; text-align: left; margin-bottom: 20px;'>
                    <tr><th>Vehicle:</th><td>{$vehicleModel}</td></tr>
                    <tr><th>Pickup:</th><td>{$startDate} at {$pickupTime}</td></tr>
                    <tr><th>Total:</th><td>NPR {$totalPriceFmt}</td></tr>
                </table>
                <p>Please arrive on time. If you need to cancel or reschedule, visit your bookings page.</p>
                <p>— TDRentals Team</p>
            </div>
        ";

        $mail->isHTML(true);
        $mail->Body = $htmlBody;

        if ($mail->send()) {
            $logStmt = $conn->prepare("INSERT INTO reminder_log (booking_id, reminder_type) VALUES (?, ?)");
            $logStmt->bind_param('is', $bookingId, $reminderType);
            $logStmt->execute();
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to send instant reminder email: " . $e->getMessage());
    }
    return false;
}

function isNotificationEnabled($conn, $user_id)
{

    $stmt = $conn->prepare("
        SELECT enabled
        FROM notification_preference
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    return $result && $result['enabled'] == 1;
}
/**
 * Validates if an email is genuine and properly formatted
 */
function is_email_genuine($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $disposable_domains = ['mailinator.com', '10minutemail.com', 'guerrillamail.com'];
    $domain = substr(strrchr($email, "@"), 1);
    return !in_array($domain, $disposable_domains);
}

/**
 * Generates a numeric One-Time Password (OTP)
 */
function generateOTP($length = 6) {
    $otp = "";
    for ($i = 0; $i < $length; $i++) {
        $otp .= random_int(0, 9);
    }
    return $otp;
}
?>