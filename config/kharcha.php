<?php
// config/kharcha.php
// Kharcha Payment Gateway Configuration
//
// HOW TO GET YOUR API KEY:
//   1. Log in to your Kharcha merchant/organization account.
//   2. Go to Settings → API Keys → Generate New Key.
//   3. Copy the key (format: kh_live_XXXXXXXXXXXX) and paste it below.
//   4. Keep this file out of version control (add it to .gitignore).
//
// Kharcha Card BIN: cards starting with 733333 are Kharcha cards.

define('KHARCHA_API_BASE_URL', 'https://kharcha-production.up.railway.app'); // Change to your Kharcha backend URL
define('KHARCHA_API_KEY',      getenv('KHARCHA_API_KEY') ?: 'kh_live_6c23e4013f930ebd66b42064a342d0cb1d5b2bc169dd971d');
define('KHARCHA_CARD_BIN',     '733333'); // Kharcha card number prefix (after leading 7)

/**
 * Detect whether a card number belongs to Kharcha.
 * Kharcha card numbers: 16 digits starting with 733333.
 */
function isKharchaCard(string $cardNumber): bool {
    $clean = preg_replace('/\s+/', '', $cardNumber);
    return str_starts_with($clean, '7' . KHARCHA_CARD_BIN);
}

/**
 * Call the Kharcha /api/payment/charge endpoint.
 *
 * Returns an array:
 *   ['success' => true,  'transaction' => [...]]
 *   ['success' => false, 'error_code' => '...', 'message' => '...']
 */
function kharchaChargeCard(string $cardNumber, string $cvv, float $amount, string $remarks = ''): array {
    $url     = rtrim(KHARCHA_API_BASE_URL, '/') . '/api/payment/charge';
    $payload = json_encode([
        'card_number' => preg_replace('/\s+/', '', $cardNumber),
        'cvv'         => $cvv,
        'amount'      => $amount,
        'currency'    => 'NPR',
        'remarks'     => $remarks ?: 'Vehicle rental payment – TD Rentals',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-API-Key: ' . KHARCHA_API_KEY,
        ],
    ]);

    $raw      = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[Kharcha] cURL error: $curlErr");
        return ['success' => false, 'error_code' => 'GATEWAY_ERROR', 'message' => 'Could not reach Kharcha payment gateway. Please try another payment method.'];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        error_log("[Kharcha] Non-JSON response ($httpCode): $raw");
        return ['success' => false, 'error_code' => 'GATEWAY_ERROR', 'message' => 'Unexpected response from Kharcha gateway.'];
    }

    return $data;
}

/**
 * Create a Kharcha Payment Portal session (hosted checkout).
 *
 * Returns: ['success' => true,  'session_id' => '...', 'checkout_url' => '...', 'expires_at' => '...']
 *       or ['success' => false, 'message' => '...']
 */
function kharchaCreatePortalSession(float $amount, string $note, string $return_url, string $callback_url = ''): array {
    $url     = rtrim(KHARCHA_API_BASE_URL, '/') . '/api/pay-portal/sessions/create';
    $body    = ['amount' => $amount, 'note' => $note ?: 'TD Rentals vehicle booking',
                'return_url' => $return_url, 'expires_in_minutes' => 30];
    if ($callback_url) $body['callback_url'] = $callback_url;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . KHARCHA_API_KEY],
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) { error_log("[Kharcha Portal] cURL: $err"); return ['success' => false, 'message' => 'Could not reach Kharcha gateway.']; }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['success' => false, 'message' => 'Unexpected response from Kharcha gateway.'];
}

/**
 * Get Kharcha Payment Portal session status (requires API key).
 */
function kharchaGetPortalSessionStatus(string $session_id): array {
    $url = rtrim(KHARCHA_API_BASE_URL, '/') . '/api/pay-portal/sessions/' . urlencode($session_id) . '/status';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
                            CURLOPT_HTTPHEADER => [
                                'Accept: application/json',
                                'X-API-Key: ' . KHARCHA_API_KEY,
                            ]]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['success' => false, 'message' => 'Could not reach Kharcha gateway.'];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['success' => false, 'message' => 'Unexpected response.'];
}

/**
 * Create a Kharcha Dynamic QR payment session.
 *
 * Returns: ['success' => true, 'session_id' => '...', 'qr_payload' => '{"kharcha_qr_id":"..."}']
 *       or ['success' => false, 'message' => '...']
 */
function kharchaCreateQRSession(float $amount, string $note): array {
    $url = rtrim(KHARCHA_API_BASE_URL, '/') . '/api/org/qr-codes/payments/create';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['amount' => $amount, 'note' => $note ?: 'TD Rentals vehicle booking']),
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-API-Key: ' . KHARCHA_API_KEY],
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) { error_log("[Kharcha QR] cURL: $err"); return ['success' => false, 'message' => 'Could not reach Kharcha gateway.']; }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['success' => false, 'message' => 'Unexpected response from Kharcha gateway.'];
}

/**
 * Poll a Kharcha QR payment session status.
 *
 * Returns: ['success' => true, 'status' => 'pending|success|expired']
 *       or ['success' => false, 'message' => '...']
 */
function kharchaGetQRSessionStatus(string $session_id): array {
    $url = rtrim(KHARCHA_API_BASE_URL, '/') . '/api/org/qr-codes/payments/status/' . urlencode($session_id);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['X-API-Key: ' . KHARCHA_API_KEY],
    ]);
    $raw = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
    if ($err) return ['success' => false, 'message' => 'Could not reach Kharcha gateway.'];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['success' => false, 'message' => 'Unexpected response.'];
}