<?php
// Start output buffering to prevent "headers already sent" issues
ob_start();

// In production, log errors but don't display them
if ($_SERVER['SERVER_NAME'] === 'localhost' || strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false) {
    // Development environment
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production environment
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
    
    // Create logs directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0755, true);
    }
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    $session_options = [
        'cookie_httponly' => true,     // Prevent JavaScript access to session cookie
        'use_strict_mode' => true      // Reject uninitialized session IDs
    ];
    
    // Only use secure flag on HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $session_options['cookie_secure'] = true;
    }
    
    session_start($session_options);
}

// Set timezone for consistent date/time operations
date_default_timezone_set('Asia/Karachi');

// Include required files
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth_functions.php';

// Safe redirect function is now in functions.php
?>