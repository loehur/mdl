<!DOCTYPE html>
<html>
<head>
    <title>Flip API Test - Bank List</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .success { color: green; }
        .error { color: red; }
        h2 { margin-top: 0; color: #333; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        .test-btn { padding: 10px 20px; margin: 10px 5px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 5px; }
        .test-btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🧪 Flip API Test - Bank List</h2>
        <button class="test-btn" onclick="testModel()">Test Model Langsung</button>
        <button class="test-btn" onclick="testEndpoint()">Test Endpoint /Flip/banks</button>
        <button class="test-btn" onclick="testFlipAPI()">Test Flip API Langsung</button>
    </div>
    
    <div id="result"></div>

    <script>
    function testModel() {
        document.getElementById('result').innerHTML = '<div class="box">Loading...</div>';
        fetch('?action=model')
            .then(r => r.json())
            .then(data => {
                document.getElementById('result').innerHTML = 
                    '<div class="box"><h2>Model Test Result</h2><pre>' + 
                    JSON.stringify(data, null, 2) + '</pre></div>';
            });
    }

    function testEndpoint() {
        document.getElementById('result').innerHTML = '<div class="box">Loading...</div>';
        fetch('/mdl/api/Flip/banks')
            .then(r => r.json())
            .then(data => {
                document.getElementById('result').innerHTML = 
                    '<div class="box"><h2>Endpoint Test Result</h2><pre>' + 
                    JSON.stringify(data, null, 2) + '</pre></div>';
            })
            .catch(err => {
                document.getElementById('result').innerHTML = 
                    '<div class="box error"><h2>Endpoint Test Error</h2><pre>' + 
                    err.toString() + '</pre></div>';
            });
    }

    function testFlipAPI() {
        document.getElementById('result').innerHTML = '<div class="box">Loading...</div>';
        fetch('?action=direct')
            .then(r => r.json())
            .then(data => {
                document.getElementById('result').innerHTML = 
                    '<div class="box"><h2>Direct Flip API Test</h2><pre>' + 
                    JSON.stringify(data, null, 2) + '</pre></div>';
            });
    }

    // Auto-run model test on load
    window.onload = () => testModel();
    </script>
</body>
</html>

<?php
if (!isset($_GET['action'])) {
    exit;
}

require_once 'app/init.php';
use App\Models\Flip;

header('Content-Type: application/json');

$flip = new Flip();
$action = $_GET['action'] ?? 'model';

if ($action === 'model') {
    // Test using Model
    $result = [
        'test' => 'Flip Model Test',
        'environment' => $flip->getEnvironment(),
        'is_sandbox' => $flip->isSandbox(),
    ];
    
    $apiResult = $flip->getBankList();
    $result['api_call'] = $apiResult;
    
    if (isset($apiResult['success']) && $apiResult['success']) {
        $cleanResult = $apiResult;
        unset($cleanResult['success']);
        unset($cleanResult['http_code']);
        $result['bank_count'] = is_array($cleanResult) ? count($cleanResult) : 0;
        $result['status'] = 'SUCCESS';
    } else {
        $result['status'] = 'FAILED';
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} elseif ($action === 'direct') {
    // Test direct call to Flip API
    $url = "https://bigflip.id/big_sandbox_api/v2/general/banks";
    $secretKey = defined('\\Env::FLIP_SECRET_KEY') ? \Env::FLIP_SECRET_KEY : '';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($secretKey . ':'),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $result = [
        'test' => 'Direct Flip API Call',
        'url' => $url,
        'http_code' => $httpCode,
        'curl_error' => $curlError ?: null,
        'response' => json_decode($response, true) ?: $response
    ];
    
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>
