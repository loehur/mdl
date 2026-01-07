<?php
session_start();
date_default_timezone_set("Asia/Jakarta");
$GLOBALS['now'] = date("Y-m-d H:i:s");

spl_autoload_register(function ($class) {
    $paths = ['Core/', 'Models/'];
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists(__DIR__ . '/' . $file)) {
            require_once $file;
            return;
        }
    }
});
