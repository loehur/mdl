<?php
// Global CORS Headers - Handle this before anything else to ensure headers are sent
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
$allowed_origins = ['https://admin.nalju.com'];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://nalju.com");
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("CORS Preflight (OPTIONS) received for: " . $_SERVER['REQUEST_URI']);
    http_response_code(200);
    exit;
} else {
    error_log("API Request (" . $_SERVER['REQUEST_METHOD'] . ") received for: " . $_SERVER['REQUEST_URI']);
}

// Temporary .htaccess fix - will run once and can be removed
$htaccess_path = realpath(__DIR__ . '/../.htaccess');
$htaccess_content = "Options -MultiViews -Indexes\nRewriteEngine On\n\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^(.+)$ index.php?url=\$1 [L,QSA]";
if (file_exists($htaccess_path)) {
    file_put_contents($htaccess_path, $htaccess_content);
}

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
