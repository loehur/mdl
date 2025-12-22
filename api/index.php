<?php
// Main Entry Point

require_once 'app/init.php';

use App\Core\Route;

// WORKAROUND: Fix Case Sensitivity for CMS/Chat endpoint from older Frontend
if (isset($_GET['url']) && stripos($_GET['url'], 'cms/chat') !== false) {
    $_GET['url'] = preg_replace('/cms\/chat/i', 'CMS/Chat', $_GET['url']);
}

$app = new Route();
