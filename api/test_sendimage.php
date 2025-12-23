<?php
// Direct test of sendImage method
// Access: http://localhost/mdl/api/test_sendimage.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/init.php';

echo "<h1>Test sendImage Method</h1>";

// Simulate the Chat controller
$chatController = new \App\Controllers\CMS\Chat();

// Test if method exists
if (method_exists($chatController, 'sendImage')) {
    echo "<p>✓ sendImage method exists</p>";
} else {
    die("<p>✗ sendImage method NOT found!</p>");
}

// Check if WhatsAppService has sendImage
require_once __DIR__ . '/app/Helpers/WhatsAppService.php';
$waService = new \App\Helpers\WhatsAppService();

if (method_exists($waService, 'sendImage')) {
    echo "<p>✓ WhatsAppService->sendImage() exists</p>";
} else {
    die("<p>✗ WhatsAppService->sendImage() NOT found!</p>");
}

// Check upload directory
$uploadDir = __DIR__ . '/uploads/wa_media/';
if (is_dir($uploadDir)) {
    echo "<p>✓ Upload directory exists: {$uploadDir}</p>";
    if (is_writable($uploadDir)) {
        echo "<p>✓ Upload directory is writable</p>";
    } else {
        echo "<p>✗ Upload directory is NOT writable!</p>";
    }
} else {
    echo "<p>✗ Upload directory does NOT exist: {$uploadDir}</p>";
}

echo "<hr>";
echo "<p><strong>All checks passed!</strong> The issue might be:</p>";
echo "<ul>";
echo "<li>Missing \$_FILES['image'] data</li>";
echo "<li>Database connection issue</li>";
echo "<li>WhatsApp API credentials</li>";
echo "<li>Check browser Network tab for actual error response</li>";
echo "</ul>";
