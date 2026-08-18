<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Config\Fonnte as FonnteConfig;
use App\Helpers\CRM\FonnteService;

/**
 * Fonnte proxy untuk laundry (VPS laundry tidak share folder dengan api).
 * URL: /Laundry/Fonnte/{method}
 *
 * Token: Env::FONNTE_TOKEN (server API saja).
 */
class Fonnte extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET /Laundry/Fonnte — info singkat
     */
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'name' => 'Fonnte API',
            'endpoints' => ['sendGroup', 'groups', 'status', 'qr', 'logout'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /Laundry/Fonnte/groups
     * ID group driver + fallback cabang (bukan secret).
     */
    public function groups()
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'driver' => FonnteConfig::getDriverGroupId(),
            'estimasi' => FonnteConfig::getEstimasiGroupId(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /Laundry/Fonnte/sendGroup
     * Body JSON: { group_id, message }
     * Auth: X-Cron-Secret / ?secret= (sama CRON_SECRET) bila diset di Env.
     */
    public function sendGroup()
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            if (!$this->isPost()) {
                http_response_code(405);
                echo json_encode(['ok' => false, 'success' => false, 'message' => 'Method not allowed']);
                return;
            }

            if (!$this->verifyAccess()) {
                http_response_code(403);
                echo json_encode(['ok' => false, 'success' => false, 'message' => 'Access denied']);
                return;
            }

            $body = $this->getBody();
            if (!is_array($body)) {
                $body = [];
            }

            $groupId = trim((string) ($body['group_id'] ?? $body['target'] ?? ''));
            $message = (string) ($body['message'] ?? '');

            if ($groupId === '' || !preg_match('/@g\.us$/i', $groupId)) {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'success' => false,
                    'message' => 'group_id tidak valid (wajib …@g.us)',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (trim($message) === '') {
                http_response_code(400);
                echo json_encode([
                    'ok' => false,
                    'success' => false,
                    'message' => 'message wajib diisi',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte', false)) {
                require_once __DIR__ . '/../../Config/Fonnte.php';
            }

            $fonnte = new FonnteService();
            $res = $fonnte->sendToGroup($groupId, $message);
            $ok = !empty($res['success']);

            if (!$ok) {
                http_response_code(502);
            }

            echo json_encode([
                'ok' => $ok,
                'success' => $ok,
                'message' => $ok ? 'Terkirim' : (string) ($res['error'] ?? 'Gagal kirim Fonnte'),
                'error' => $ok ? null : (string) ($res['error'] ?? 'Gagal kirim Fonnte'),
                'data' => $res['data'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('Laundry/Fonnte sendGroup: ' . $e->getMessage(), 'api', 'Fonnte');
            }
            http_response_code(500);
            echo json_encode([
                'ok' => false,
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /Laundry/Fonnte/status — health + device fonnte_server
     */
    public function status()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte', false)) {
                require_once __DIR__ . '/../../Config/Fonnte.php';
            }

            $fonnte = new FonnteService();
            $health = $fonnte->getGatewayHealth();
            $device = $fonnte->getGatewayDevice();

            $wa = is_array($health['data']['whatsapp'] ?? null) ? $health['data']['whatsapp'] : [];
            $dev = is_array($device['data'] ?? null) ? $device['data'] : [];
            $connected = !empty($wa['connected']) || !empty($dev['connected']) || !empty($dev['status']);

            $qr = null;
            if (!empty($health['data']['qr'])) {
                $qr = (string) $health['data']['qr'];
            }
            if ($qr === null || $qr === '') {
                $qrRes = $fonnte->getGatewayQr();
                $qrData = is_array($qrRes['data'] ?? null) ? $qrRes['data'] : [];
                if (!empty($qrData['qr'])) {
                    $qr = (string) $qrData['qr'];
                }
            }

            $state = $wa['state'] ?? ($dev['state'] ?? 'unknown');
            $qrHint = null;
            if (!$connected && ($qr === null || $qr === '')) {
                if ($state === 'connecting') {
                    $qrHint = 'Sedang menghubungkan sesi lama. Jika QR tidak muncul dalam 30 detik, klik Logout / Scan ulang.';
                } else {
                    $qrHint = 'QR belum tersedia. Pastikan fonnte_server jalan, lalu klik Logout / Scan ulang.';
                }
            }

            echo json_encode([
                'ok' => $health['success'] || $device['success'],
                'connected' => $connected,
                'state' => $state,
                'device' => $dev['device'] ?? ($health['data']['device'] ?? ''),
                'package' => $dev['package'] ?? 'self-hosted-baileys',
                'webhook' => !empty($health['data']['webhook']),
                'gateway' => \App\Config\Fonnte::getBaseUrl(),
                'qr' => ($qr !== null && $qr !== '') ? $qr : null,
                'has_qr' => ($qr !== null && $qr !== ''),
                'qr_hint' => $qrHint,
                'health' => $health['data'] ?? null,
                'device_profile' => $dev ?: null,
                'error' => $health['error'] ?? $device['error'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /Laundry/Fonnte/qr
     */
    public function qr()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->verifyAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
            }

            $fonnte = new FonnteService();
            $res = $fonnte->getGatewayQr();
            $data = is_array($res['data'] ?? null) ? $res['data'] : [];

            if (!empty($data['connected'])) {
                echo json_encode([
                    'ok' => true,
                    'connected' => true,
                    'device' => $data['device'] ?? '',
                    'message' => 'Sudah terhubung',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            if (empty($data['qr'])) {
                http_response_code(404);
                echo json_encode([
                    'ok' => false,
                    'message' => $data['message'] ?? ($res['error'] ?? 'QR belum tersedia'),
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode([
                'ok' => true,
                'qr' => $data['qr'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /Laundry/Fonnte/logout — reset sesi Baileys
     */
    public function logout()
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!$this->verifyAccess()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Access denied'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService', false)) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
            }

            $fonnte = new FonnteService();
            $res = $fonnte->gatewayLogout();
            $ok = !empty($res['success']);

            if (!$ok) {
                http_response_code(502);
            }

            echo json_encode([
                'ok' => $ok,
                'message' => $ok
                    ? ($res['data']['message'] ?? 'Sesi direset — scan QR baru')
                    : ($res['error'] ?? 'Gagal reset sesi'),
                'data' => $res['data'] ?? null,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Auth: cron secret cocok, ATAU IP laundry whitelist (sama WhatsApp).
     * Jika Env::CRON_SECRET kosong dan IP tidak whitelist → izinkan (dev/compat).
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
