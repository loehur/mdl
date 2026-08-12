<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;

/**
 * Dry-run klasifikasi intent WA (Intent Lab).
 * URL: POST /Laundry/IntentCheck
 * Body: { "text": "..." }  — hanya teks, tanpa phone.
 */
class IntentCheck extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET — info endpoint
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($this->isPost()) {
            $this->check();
            return;
        }
        echo json_encode([
            'ok' => true,
            'name' => 'IntentCheck',
            'usage' => 'POST JSON { "text": "pesan customer" }',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /Laundry/IntentCheck/check  (atau POST /Laundry/IntentCheck)
     */
    public function check()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$this->isPost() && !$this->isGet()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if ($data === []) {
            $data = $_POST;
        }

        $text = trim((string) ($data['text'] ?? $_GET['text'] ?? ''));
        if ($text === '') {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Field text wajib diisi',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (mb_strlen($text) > 2000) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'Teks maksimal 2000 karakter',
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (!class_exists('\\App\\Models\\WAReplies')) {
                require_once __DIR__ . '/../../Models/WAReplies.php';
            }
            $replies = new \App\Models\WAReplies();
            $out = $replies->classifyIntentLab($text);
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('IntentCheck: ' . $e->getMessage(), 'api', 'IntentCheck');
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal klasifikasi intent',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
