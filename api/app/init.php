<?php
// Global CORS Headers

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://admin.nalju.com', 'https://salon.nalju.com'];

// Initialize allowed origin
$allowed_origin = 'https://admin.nalju.com'; // Default fallback

// Allow any subdomain of nalju.com
if (preg_match('/^https?:\/\/([a-z0-9-]+\.)*nalju\.com$/', $origin)) {
    $allowed_origin = $origin;
}

header("Access-Control-Allow-Origin: $allowed_origin");
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
