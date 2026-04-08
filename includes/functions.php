<?php
/**
 * includes/functions.php
 * All shared helper / utility functions for TD Rentals.
 * Requires config/db.php to be loaded first.
 */

// ─── Authentication helpers ───────────────────────────────────────────────────

/**
 * Return the current logged-in user array, or null.
 */
function currentUser(): ?array {
    return $_SESSION['user'] ?? null;
}

/**
 * Redirect to login page if not logged in.
 */
function requireLogin(): void {
    if (!currentUser()) {
        header('Location: ' . SITE_URL . '/public/authentication/login.php?msg=login_required');
        exit;
    }
}

/**
 * Redirect to home page if not an admin.
 */
function requireAdmin(): void {
    $user = currentUser();
    if (!$user || $user['role'] !== 'admin') {
        header('Location: ' . SITE_URL . '/public/user/index.php');
        exit;
    }
}

// ─── CSRF protection ──────────────────────────────────────────────────────────

/**
 * Return (and lazily create) a CSRF token stored in the session.
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Abort with 403 if the POST CSRF token doesn't match the session token.
 */
function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

// ─── JSON response helper (used by ajax/) ─────────────────────────────────────

/**
 * Emit a JSON response and exit.
 *
 * @param array $data
 * @param int   $status HTTP status code
 */
function jsonResponse(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// ─── Flash message helpers ────────────────────────────────────────────────────

/**
 * Store a flash message in the session.
 *
 * @param string $type  'success' | 'error'
 * @param string $msg
 */
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

/**
 * Retrieve and clear the flash message.
 *
 * @return array{type:string, msg:string}
 */
function getFlash(): array {
    $flash = $_SESSION['flash'] ?? ['type' => '', 'msg' => ''];
    unset($_SESSION['flash']);
    return $flash;
}

// ─── Booking helpers ──────────────────────────────────────────────────────────

/**
 * Calculate rental cost breakdown.
 *
 * @param float  $pricePerDay
 * @param string $pickup      Y-m-d
 * @param string $dropoff     Y-m-d
 * @return array{days:int, rental_total:float, insurance_fee:float, grand_total:float}
 */
function calcBooking(float $pricePerDay, string $pickup, string $dropoff): array {
    $days        = (int)((strtotime($dropoff) - strtotime($pickup)) / 86400);
    $rentalTotal = $days * $pricePerDay;
    $insurance   = $days * 150;
    $grandTotal  = $rentalTotal + $insurance;

    return [
        'days'          => $days,
        'rental_total'  => $rentalTotal,
        'insurance_fee' => $insurance,
        'grand_total'   => $grandTotal,
    ];
}

/**
 * Check whether a car is available for a date range.
 *
 * @param int    $carId
 * @param string $pickup   Y-m-d
 * @param string $dropoff  Y-m-d
 * @param int    $excludeBookingId  Ignore this booking ID (for edits)
 * @return bool
 */
function isCarAvailable(int $carId, string $pickup, string $dropoff, int $excludeBookingId = 0): bool {
    $sql = "SELECT id FROM bookings
            WHERE car_id = ? AND status NOT IN ('cancelled')
              AND pickup_date < ? AND dropoff_date > ?";
    $params = [$carId, $dropoff, $pickup];

    if ($excludeBookingId) {
        $sql    .= ' AND id != ?';
        $params[] = $excludeBookingId;
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return !$stmt->fetch();
}

// ─── Admin audit log ──────────────────────────────────────────────────────────

/**
 * Write an entry to the audit_logs table (silently ignores DB errors).
 */
function auditLog(string $action, string $targetType = '', int $targetId = 0, string $detail = ''): void {
    $u = currentUser();
    try {
        db()->prepare("INSERT INTO audit_logs (admin_id, admin_name, action, target_type, target_id, detail)
                       VALUES (?, ?, ?, ?, ?, ?)")
           ->execute([
               $u['id']   ?? null,
               $u['name'] ?? 'System',
               $action,
               $targetType,
               $targetId ?: null,
               $detail   ?: null,
           ]);
    } catch (Exception $e) {
        // Silently fail — audit logging must never break the main request
    }
}

// ─── Image upload helper ──────────────────────────────────────────────────────

/**
 * Handle a vehicle image upload.
 * Returns the filename saved inside assets/images/, or null on failure.
 */
function uploadVehicleImage(array $file): ?string {
    if (empty($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return null;
    }
    $filename = 'car_' . time() . '.' . $ext;
    $dest     = ROOT_PATH . '/assets/images/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return $filename;
    }
    return null;
}
