<?php
require '/www/wwwroot/mdl/api/app/Config/Env.php';
$token = defined('Env::FONNTE_TOKEN') ? (string) Env::FONNTE_TOKEN : '';
echo 'token_len=' . strlen($token) . PHP_EOL;
$ch = curl_init('https://api.fonnte.com/device');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: ' . $token],
    CURLOPT_TIMEOUT => 20,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "http=$code\n";
$data = json_decode((string) $raw, true);
if (!is_array($data)) {
    echo "raw=" . substr((string) $raw, 0, 300) . PHP_EOL;
    exit;
}
unset($data['token'], $data['webhook'], $data['device']);
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
