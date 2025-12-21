<?php
ob_start();
// Global CORS Headers

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

// Initialize allowed origin
$allowed_origin = 'https://nalju.com'; // Default fallback

// Parse origin to check host
$parsed_origin = parse_url($origin);
$origin_host = $parsed_origin['host'] ?? '';

// Allow nalju.com and any subdomain *.nalju.com
if ($origin_host === 'nalju.com' || str_ends_with(strtolower($origin_host), '.nalju.com')) {
    $allowed_origin = $origin;
}

header("Access-Control-Allow-Origin: $allowed_origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin, User-Agent');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Clear output buffer to ensure no whitespace is sent
    ob_clean();
    http_response_code(200);
    exit;
}

// Error handler to output errors as JSON (for debugging)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'PHP Error',
        'error' => $errstr,
        'file' => basename($errfile),
        'line' => $errline
    ]);
    exit;
});

set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => false,
        'message' => 'Exception',
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
});

date_default_timezone_set("Asia/Jakarta");
$GLOBALS['now'] = date("Y-m-d H:i:s");

// Start session for all API requests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Environment Configuration
require_once __DIR__ . '/Config/Env.php';

if (Env::isDev()) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

spl_autoload_register(function ($class) {
    // PSR-4 autoloader for App namespace
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // Try old non-namespaced classes (backward compatibility)
        if (file_exists(__DIR__ . '/Core/' . $class . '.php')) {
            require_once __DIR__ . '/Core/' . $class . '.php';
            return;
        }
        if (file_exists(__DIR__ . '/Helpers/' . $class . '.php')) {
            require_once __DIR__ . '/Helpers/' . $class . '.php';
            return;
        }
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace namespace separators with directory separators, add .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require_once $file;
    }
});
