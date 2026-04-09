<?php
// ajax/map_proxy.php

// Disable error output (important for binary image responses)
ini_set('display_errors', 0);
error_reporting(0);

// Store API key securely on backend
$apiKey = 'ee4b8e9049f44c1387225e75fa5397c7';

// Validate parameters exist
if (!isset($_GET['z'], $_GET['x'], $_GET['y'])) {
    http_response_code(400);
    exit;
}

// Sanitize inputs
$z = intval($_GET['z']);
$x = intval($_GET['x']);
$y = intval($_GET['y']);

// Extra validation (prevent abuse / invalid zoom levels)
if ($z < 0 || $z > 20 || $x < 0 || $y < 0) {
    http_response_code(400);
    exit;
}

// Geoapify tile URL
$url = "https://maps.geoapify.com/v1/tile/carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}";

// Initialize cURL
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => false,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);

// Handle curl errors safely
if ($response === false) {
    http_response_code(500);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// PHP 8.5+: curl_close() deprecated → not needed anymore
unset($ch);

// Return tile if successful
if ($httpCode === 200) {

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=86400');
    header('Access-Control-Allow-Origin: *');

    echo $response;

} else {

    http_response_code($httpCode ?: 500);

}
?>