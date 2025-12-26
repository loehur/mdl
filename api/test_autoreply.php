<?php
/**
 * Auto-Reply Test Script
 * 
 * Script untuk test auto-reply tanpa perlu kirim WhatsApp asli
 * Useful untuk debugging pattern matching
 */

// Setup path
$basePath = __DIR__ . '/..';
require_once $basePath . '/app/Core/DB.php';
require_once $basePath . '/app/Models/WAReplies.php';
require_once $basePath . '/app/Helpers/Log.php';

use App\Models\WAReplies;

echo "===================================\n";
echo "AUTO-REPLY TEST SCRIPT\n";
echo "===================================\n\n";

// Test messages
$testMessages = [
    'bon',
    'nota',
    'struk',
    'cek',
    'status',
    'kapan buka',
    'jam berapa tutup',
    'p',
    'halo',
    'makasih',
    'terima kasih',
    // Natural language (untuk test AI)
    'tolong kirim bon dong',
    'laundry saya udah selesai belum?',
    'jam berapa buka hari ini?',
];

// Test phone number
$testPhone = '+628123456789';
$phoneIn = "'08123456789', '+628123456789', '628123456789'";

$replies = new WAReplies();

echo "Testing with phone: {$testPhone}\n";
echo "-----------------------------------\n\n";

foreach ($testMessages as $index => $message) {
    $testNum = $index + 1;
    echo "[TEST #{$testNum}] Message: \"{$message}\"\n";
    echo "----------------------------------------\n";
    
    // Clear previous test log (optional)
    // @unlink(__DIR__ . '/../../logs/auto_reply_process.log');
    
    try {
        $result = $replies->process($phoneIn, $message, $testPhone);
        
        if ($result) {
            echo "✅ RESULT: Auto-reply SENT\n";
        } else {
            echo "❌ RESULT: NO auto-reply (no match or cooldown)\n";
        }
    } catch (Exception $e) {
        echo "🔴 ERROR: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
    
    // Delay to avoid rate limit
    sleep(1);
}

echo "===================================\n";
echo "TEST COMPLETE\n";
echo "===================================\n\n";
echo "📊 Check logs at: logs/auto_reply_*.log\n";
echo "📖 Read guide: AUTO_REPLY_DEBUG_GUIDE.md\n";
