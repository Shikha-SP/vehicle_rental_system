<?php

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
// Support ngrok forwarding header if it exists
$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = '/vehicle_rental_collab_project';

$website_url = $protocol . '://' . $host . $base_path . '/';
$return_url = $protocol . '://' . $host . $base_path . '/public/payment/khalti_callback.php';

return [
    'secret_key' => '05bf95cc57244045b8df5fad06748dab',
    'base_url' => 'https://dev.khalti.com/api/v2/',
    'return_url' => $return_url,
    'website_url' => $website_url,
];
