<?php
/**
 * public/authentication/logout.php  —  Destroy session and redirect
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';

session_destroy();
header('Location: ' . SITE_URL . '/public/user/index.php');
exit;
