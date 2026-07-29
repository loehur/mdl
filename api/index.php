<?php

// Serve /uploads/* as static files when the web server routes everything to index.php
// (e.g. nginx catch-all). Also covers legacy files saved under api/app/uploads/.
$urlPath = isset($_GET['url']) ? trim((string) $_GET['url'], '/') : '';
if ($urlPath === '' && !empty($_SERVER['REQUEST_URI'])) {
    $urlPath = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '', '/');
}
if ($urlPath !== '' && preg_match('#^uploads/(.+)$#i', $urlPath, $m)) {
    $rel = str_replace(['\\', "\0"], ['/', ''], $m[1]);
    if (strpos($rel, '..') === false) {
        $candidates = [
            __DIR__ . '/uploads/' . $rel,
            __DIR__ . '/app/uploads/' . $rel, // legacy misplaced path (Helpers used ../../)
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                $mime = function_exists('mime_content_type') ? @mime_content_type($file) : null;
                if (!$mime) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $map = [
                        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                        'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4',
                        'ogg' => 'audio/ogg', 'mp3' => 'audio/mpeg', 'pdf' => 'application/pdf',
                    ];
                    $mime = $map[$ext] ?? 'application/octet-stream';
                }
                header('Content-Type: ' . $mime);
                header('Content-Length: ' . filesize($file));
                header('Cache-Control: public, max-age=31536000');
                readfile($file);
                exit;
            }
        }
    }
}

require_once 'app/init.php';

$app = new App\Core\Route();
