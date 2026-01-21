<!DOCTYPE html>
<html>
<head>
    <title>WhatsApp API Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; }
        .error { color: red; }
        h2 { margin-top: 0; color: #333; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        input { padding: 8px; margin: 5px; width: 200px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🧪 WhatsApp API Test</h2>
        <p>Test pengiriman pesan WhatsApp menggunakan API</p>
        
        <div>
            <label>Nomor WhatsApp (tanpa +62):</label><br>
            <input type="text" id="phone" placeholder="81234567890" value="81234567890">
        </div>
        
        <div>
            <label>Pesan Test:</label><br>
            <input type="text" id="message" placeholder="Test message" value="Test dari API" style="width: 400px;">
        </div>
        
        <button onclick="testConfig()">1. Test Config</button>
        <button onclick="testSend()">2. Test Send Message</button>
        <button onclick="testOTP()">3. Test OTP Format</button>
    </div>
    
    <div id="result"></div>

    <script>
    function testConfig() {
        showLoading();
        fetch('?action=config')
            .then(r => r.json())
            .then(data => showResult('Configuration Test', data));
    }

    function testSend() {
        const phone = document.getElementById('phone').value;
        const message = document.getElementById('message').value;
        
        if (!phone || !message) {
            alert('Isi nomor WA dan pesan!');
            return;
        }
        
        showLoading();
        fetch('?action=send&phone=' + encodeURIComponent(phone) + '&message=' + encodeURIComponent(message))
            .then(r => r.json())
            .then(data => showResult('Send Message Test', data));
    }

    function testOTP() {
        const phone = document.getElementById('phone').value;
        
        if (!phone) {
            alert('Isi nomor WA!');
            return;
        }
        
        showLoading();
        fetch('?action=otp&phone=' + encodeURIComponent(phone))
            .then(r => r.json())
            .then(data => showResult('OTP Format Test', data));
    }

    function showLoading() {
        document.getElementById('result').innerHTML = '<div class="box">Loading...</div>';
    }

    function showResult(title, data) {
        const statusClass = data.success ? 'success' : 'error';
        document.getElementById('result').innerHTML = 
            '<div class="box ' + statusClass + '"><h2>' + title + '</h2><pre>' + 
            JSON.stringify(data, null, 2) + '</pre></div>';
    }
    </script>
</body>
</html>

<?php
if (!isset($_GET['action'])) {
    exit;
}

require_once 'app/init.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    // Load WhatsApp Service
    require_once __DIR__ . '/app/Helpers/WhatsAppService.php';
    require_once __DIR__ . '/app/Config/WhatsApp.php';
    
    $wa = new \App\Helpers\WhatsAppService();
    
    if ($action === 'config') {
        // Test configuration
        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp Service loaded successfully',
            'config' => [
                'api_key_prefix' => $wa->getApiKeyPrefix(),
                'service_loaded' => true,
                'php_version' => PHP_VERSION
            ]
        ]);
        
    } elseif ($action === 'send') {
        // Test send message
        $phone = $_GET['phone'] ?? '';
        $message = $_GET['message'] ?? 'Test message';
        
        if (empty($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Phone number required'
            ]);
            exit;
        }
        
        // Format phone
        if (substr($phone, 0, 2) !== '62') {
            $phone = '62' . $phone;
        }
        
        $result = $wa->sendFreeText($phone, $message);
        echo json_encode($result);
        
    } elseif ($action === 'otp') {
        // Test OTP format
        $phone = $_GET['phone'] ?? '';
        
        if (empty($phone)) {
            echo json_encode([
                'success' => false,
                'error' => 'Phone number required'
            ]);
            exit;
        }
        
        // Format phone
        if (substr($phone, 0, 2) !== '62') {
            $phone = '62' . $phone;
        }
        
        // Generate OTP
        $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Format message like in Karyawan controller
        $message = "🔐 *Kode OTP Verifikasi*\n\n";
        $message .= "Kode OTP Anda: *{$otp}*\n\n";
        $message .= "Kode ini berlaku selama 5 menit.\n";
        $message .= "⚠️ Jangan bagikan kode ini kepada siapapun.";
        
        $result = $wa->sendFreeText($phone, $message);
        $result['otp_used'] = $otp; // Include OTP for reference
        
        echo json_encode($result);
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
