<?php
// Samakan umur session PHP dengan cookie auth (24 jam) agar idle tidak langsung kehilangan session
ini_set('session.gc_maxlifetime', '86400');
if (session_status() === PHP_SESSION_NONE) {
     session_set_cookie_params([
          'lifetime' => 86400,
          'path' => '/',
          'httponly' => true,
          'samesite' => 'Lax',
     ]);
     session_start();
}
date_default_timezone_set("Asia/Jakarta");
$GLOBALS['now'] = date("Y-m-d H:i:s");

spl_autoload_register(function ($class) {
     $basePath = __DIR__ . '/';
     $corePath = $basePath . 'Core/' . $class . '.php';
     $modelPath = $basePath . 'Models/' . $class . '.php';
     $helperPath = $basePath . 'Helper/' . $class . '.php';
     
     if (file_exists($corePath)) {
          require_once $corePath;
     } elseif (file_exists($modelPath)) {
          require_once $modelPath;
     } elseif (file_exists($helperPath)) {
          require_once $helperPath;
     }
});
