<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Suppress specific Symfony PropertyInfo deprecation warnings during development
if ($_ENV['APP_ENV'] === 'dev') {
    // Filter out PropertyInfo getTypes() deprecation warnings
    set_error_handler(function ($severity, $message, $file, $line) {
        // Suppress PropertyInfo deprecation warnings specifically
        if (strpos($message, 'PropertyInfo') !== false && 
            strpos($message, 'getTypes()') !== false && 
            strpos($message, 'deprecated') !== false) {
            return true; // Suppress this specific warning
        }
        
        // Let other deprecation warnings through
        return false;
    }, E_USER_DEPRECATED);
}
