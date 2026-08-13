<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Config\AutoReplyKeywordsLoader;

/**
 * Seed / status AutoReply keywords (file hanya di server API).
 * Laundry memanggil via https://api.nalju.com/Laundry/AutoReplyKeywords/...
 */
class AutoReplyKeywords extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET /Laundry/AutoReplyKeywords
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'name' => 'AutoReplyKeywords',
            'usage' => [
                'POST /Laundry/AutoReplyKeywords/seed JSON { "replace": false }',
                'GET  /Laundry/AutoReplyKeywords/status',
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /Laundry/AutoReplyKeywords/status
     */
    public function status()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            $db = \App\Core\DB::getInstance(0);
            $i = $db->query('SELECT COUNT(*) AS c FROM wa_autoreply_intents')->row_array();
            $p = $db->query('SELECT COUNT(*) AS c FROM wa_autoreply_patterns')->row_array();
            $v = $db->query(
                "SELECT meta_value FROM wa_autoreply_meta WHERE meta_key = 'cache_version' LIMIT 1"
            )->row_array();
            $file = AutoReplyKeywordsLoader::filePath();
            echo json_encode([
                'ok' => true,
                'intents' => (int) ($i['c'] ?? 0),
                'patterns' => (int) ($p['c'] ?? 0),
                'cache_version' => (string) ($v['meta_value'] ?? '0'),
                'file_exists' => is_file($file),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Gagal baca status',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /Laundry/AutoReplyKeywords/seed
     * Body JSON/form: replace=0|1
     */
    public function seed()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if (!$this->verifyAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
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

        $replace = !empty($data['replace']);

        try {
            $result = AutoReplyKeywordsLoader::seedFromFile($replace);
            $ok = !empty($result['ok']);
            if (!$ok) {
                http_response_code(400);
            }
            echo json_encode([
                'ok' => $ok,
                'message' => $result['message'] ?? '',
                'intents' => (int) ($result['intents'] ?? 0),
                'patterns' => (int) ($result['patterns'] ?? 0),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('AutoReplyKeywords seed: ' . $e->getMessage(), 'api', 'AutoReplyKeywords');
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'message' => 'Seed gagal',
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Auth: IP laundry whitelist ATAU cron secret (sama pola Fonnte).
     */
    private function verifyAccess(): bool
    {
        if ($this->isAllowedIp()) {
            return true;
        }

        $expected = $this->expectedCronSecret();
        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        if ($expected === '') {
            return true;
        }
        if ($provided === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    private function isAllowedIp(): bool
    {
        $allowedIps = ['194.233.94.47'];
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }
        return $clientIp !== '' && in_array($clientIp, $allowedIps, true);
    }

    private function expectedCronSecret(): string
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = trim((string) \Env::CRON_SECRET);
        }
        if ($expected === '') {
            $expected = trim((string) (getenv('CRON_SECRET') ?: ''));
        }
        return $expected;
    }
}
