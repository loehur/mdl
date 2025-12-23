<?php
// Test sendImage endpoint
// URL: https://api.nalju.com/test_upload.php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$result = [
    'status' => true,
    'message' => 'Test upload endpoint',
    'files_received' => $_FILES,
    'post_data' => $_POST,
    'server' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
    ]
];

echo json_encode($result, JSON_PRETTY_PRINT);
