<?php
date_default_timezone_set("Asia/Jakarta");
$GLOBALS['now'] = date("Y-m-d H:i:s");

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
    // Try to load from Core directory
    if (file_exists(__DIR__ . '/Core/' . $class . '.php')) {
        require_once __DIR__ . '/Core/' . $class . '.php';
    }
    // Try to load from Helpers directory
    elseif (file_exists(__DIR__ . '/Helpers/' . $class . '.php')) {
        require_once __DIR__ . '/Helpers/' . $class . '.php';
    }
});
