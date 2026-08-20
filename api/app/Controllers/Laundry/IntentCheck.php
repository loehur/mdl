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
            'usage' => [
                'POST /Laundry/IntentCheck/check JSON { "text": "..." }',
                'POST /Laundry/IntentCheck/proposeTeach JSON { "text": "...", "intent": "PENUTUP" }',
                'POST /Laundry/IntentCheck/proposeUntouch JSON { "text": "...", "intent": "PENUTUP" }',
            ],
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

        $data = $this->readJsonOrPost();
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
            $forceReload = !empty($data['reload']) || !empty($data['force_reload']);
            if ($forceReload && class_exists('\\App\\Config\\AutoReplyKeywordsLoader')) {
                \App\Config\AutoReplyKeywordsLoader::clearCache();
            }

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

    /**
     * POST /Laundry/IntentCheck/proposeTeach
     * Body: { text, intent } — AI usulkan pattern + potongan ai_prompt.
     */
    public function proposeTeach()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->verifyTeachAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->readJsonOrPost();
        $text = trim((string) ($data['text'] ?? ''));
        $intent = strtoupper(trim((string) ($data['intent'] ?? '')));

        try {
            $out = \App\Helpers\Laundry\IntentTeachHelper::propose($text, $intent);
            if (empty($out['ok'])) {
                http_response_code(400);
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('IntentCheck proposeTeach: ' . $e->getMessage(), 'api', 'IntentCheck');
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal usulan teach',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /Laundry/IntentCheck/proposeUntouch
     * Body: { text, intent } — AI usulkan keluarkan dari intent (pattern match + pengecualian prompt).
     */
    public function proposeUntouch()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->verifyTeachAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = $this->readJsonOrPost();
        $text = trim((string) ($data['text'] ?? ''));
        $intent = strtoupper(trim((string) ($data['intent'] ?? '')));

        try {
            $out = \App\Helpers\Laundry\IntentTeachHelper::proposeUntouch($text, $intent);
            if (empty($out['ok'])) {
                http_response_code(400);
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('IntentCheck proposeUntouch: ' . $e->getMessage(), 'api', 'IntentCheck');
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal usulan keluarkan',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return array<string,mixed> */
    private function readJsonOrPost(): array
    {
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
        return $data;
    }

    private function verifyTeachAccess(): bool
    {
        $allowedIps = ['194.233.94.47'];
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }
        if ($clientIp !== '' && in_array($clientIp, $allowedIps, true)) {
            return true;
        }

        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = trim((string) \Env::CRON_SECRET);
        }
        if ($expected === '') {
            $expected = trim((string) (getenv('CRON_SECRET') ?: ''));
        }
        if ($expected === '') {
            return true; // compat / local
        }
        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }
        return $provided !== '' && hash_equals($expected, $provided);
    }
}
