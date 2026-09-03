<?php
/**
 * Dev router for PHP's built-in web server (`php -S`).
 *
 * Reproduces the Apache `.htaccess` rewrite used in production: existing files
 * are served as-is, everything else is routed to the app's `index.php` with the
 * request path exposed as `$_GET['url']` (the convention the App\Core\Route /
 * Route classes expect).
 *
 * Launch example:
 *   php -S 0.0.0.0:8080 -t api .cursor/php-router.php
 */

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? getcwd(), '/');
$uriPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$candidate = $docRoot . $uriPath;

// Serve real static files (assets, uploads, etc.) directly.
if ($uriPath !== '/' && $uriPath !== '' && is_file($candidate)) {
    return false;
}

// Route everything else through index.php, emulating `index.php?url=<path>`.
chdir($docRoot);
$_GET['url'] = ltrim($uriPath, '/');
$_REQUEST['url'] = $_GET['url'];
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $docRoot . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $docRoot . '/index.php';
