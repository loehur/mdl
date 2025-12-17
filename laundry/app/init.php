<?php
session_start();
date_default_timezone_set("Asia/Jakarta");
$GLOBALS['now'] = date("Y-m-d H:i:s");

spl_autoload_register(function ($class) {
     $basePath = __DIR__ . '/';
     $corePath = $basePath . 'Core/' . $class . '.php';
     $modelPath = $basePath . 'Models/' . $class . '.php';
     
     if (file_exists($corePath)) {
          require_once $corePath;
     } elseif (file_exists($modelPath)) {
          require_once $modelPath;
     }
});
