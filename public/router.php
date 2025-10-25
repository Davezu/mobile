<?php
/**
 * Router for PHP Built-in Server
 * This handles routing for the development server
 */

// If it's a real file, serve it
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    
    // Remove query string
    $file = parse_url($file, PHP_URL_PATH);
    
    // If it's a real file (not a directory) and exists, serve it
    if (is_file($file)) {
        return false; // Let PHP serve the file
    }
}

// Otherwise, route through index.php
require_once __DIR__ . '/index.php';
?>

