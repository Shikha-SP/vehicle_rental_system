<?php
// Crontab: * * * * * /usr/bin/php /path/to/vehicle_rental_collab_project/cron/send_booking_reminders.php >> /var/log/tdrentals_reminders.log 2>&1

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php';

echo "=====================================\n";
echo "Starting Multi-Tier Reminder Job at " . date('Y-m-d H:i:s') . "\n";
echo "=====================================\n";

$emails24h = 0;
$emails2h = 0;
$emails30min = 0;
$bannersMarked = 0;

// Helper to fetch bookings
function getRemindersForWindow($conn, $interval, $reminderType) {
    $query = "
        SELECT
            b.id            AS booking_id,
            b.user_id,
            b.vehicle_id,
            b.start_date,
            b.pickup_time,
            b.total_price,
            u.first_name,
            u.email,
            u.phone_number,
            v.model         AS vehicle_model,
            CONCAT(b.start_date, ' ', IFNULL(b.pickup_time, '09:00:00')) AS pickup_datetime
        FROM bookings b
        JOIN users    u ON u.id = b.user_id
        JOIN vehicles v ON v.id = b.vehicle_id
        WHERE b.status = 'confirmed'
          AND CONCAT(b.start_date, ' ', IFNULL(b.pickup_time, '09:00:00'))
                BETWEEN NOW() AND DATE_ADD(NOW(), $interval)
          AND b.id NOT IN (
                SELECT booking_id FROM reminder_log
                WHERE reminder_type = '$reminderType'
            )
    ";
    return $conn->query($query);
}

// Helper to send email and log
function sendCronEmail($conn, $row, $subject, $reminderType) {
    $bookingId = $row['booking_id'];
    $firstName = $row['first_name'];
    $email = $row['email'];
    $vehicleModel = $row['vehicle_model'];
    $startDate = $row['start_date'];
    $pickupTime = $row['pickup_time'] ?? '09:00:00';
    $totalPrice = number_format((float)$row['total_price'], 2);

    try {
        $mail = createMailer();
        $mail->addAddress($email, $firstName);
        $mail->Subject = $subject;
        
        $htmlBody = "
            <div style='font-family: Arial, sans-serif; color: #333;'>
                <h2>Hi {$firstName},</h2>
                <p>This is a reminder regarding your upcoming vehicle rental.</p>
                <table style='width: 100%; max-width: 400px; text-align: left; margin-bottom: 20px;'>
                    <tr><th>Vehicle:</th><td>{$vehicleModel}</td></tr>
                    <tr><th>Pickup:</th><td>{$startDate} at {$pickupTime}</td></tr>
                    <tr><th>Total:</th><td>NPR {$totalPrice}</td></tr>
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
            echo date('Y-m-d H:i:s') . " - [SUCCESS $reminderType] Booking {$bookingId} to {$email}\n";
            return true;
        }
    } catch (Exception $e) {
        echo date('Y-m-d H:i:s') . " - [ERROR $reminderType] Booking {$bookingId}: " . $mail->ErrorInfo . "\n";
    }
    return false;
}

// ============================================================================
// 1. 24-HOUR REMINDER (email_24h & BANNER)
// ============================================================================
$result24h = getRemindersForWindow($conn, 'INTERVAL 24 HOUR', 'email_24h');
if ($result24h && $result24h->num_rows > 0) {
    while ($row = $result24h->fetch_assoc()) {
        if (sendCronEmail($conn, $row, 'Reminder: Pickup Tomorrow', 'email_24h')) {
            $emails24h++;
        }
        // Mark Banner flag
        $bannerStmt = $conn->prepare("INSERT IGNORE INTO reminder_log (booking_id, reminder_type) VALUES (?, 'banner')");
        $bannerStmt->bind_param('i', $row['booking_id']);
        if ($bannerStmt->execute() && $bannerStmt->affected_rows > 0) {
            $bannersMarked++;
        }
    }
}

// ============================================================================
// 2. 2-HOUR REMINDER (email_2h)
// ============================================================================
$result2h = getRemindersForWindow($conn, 'INTERVAL 2 HOUR', 'email_2h');
if ($result2h && $result2h->num_rows > 0) {
    while ($row = $result2h->fetch_assoc()) {
        if (sendCronEmail($conn, $row, 'Urgent: Your Pickup is in 2 Hours', 'email_2h')) {
            $emails2h++;
        }
    }
}

// ============================================================================
// 3. 30-MINUTE REMINDER (email_30min)
// ============================================================================
$result30m = getRemindersForWindow($conn, 'INTERVAL 30 MINUTE', 'email_30min');
if ($result30m && $result30m->num_rows > 0) {
    while ($row = $result30m->fetch_assoc()) {
        if (sendCronEmail($conn, $row, 'Final Alert: Pickup in 30 Minutes', 'email_30min')) {
            $emails30min++;
        }
    }
}

echo "-------------------------------------\n";
echo "Cron complete. 24h: $emails24h | 2h: $emails2h | 30m: $emails30min | Banners: $bannersMarked\n";
echo "=====================================\n";
?>
