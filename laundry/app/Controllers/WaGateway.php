<?php

/**
 * Admin Tools — WhatsApp gateway (fonnte_server / Baileys)
 * Menu: Tools → WhatsApp
 */
class WaGateway extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'WhatsApp Gateway'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/whatsapp', [
            'test_targets' => $this->buildTestTargets(),
        ]);
    }

    /**
     * Kirim pesan tes "TES" ke group Fonnte (delivery atau cabang).
     * POST: target = delivery | cabang_{id_cabang}
     */
    public function sendTest()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $targetKey = trim((string) ($_POST['target'] ?? ''));
        $resolved = $this->resolveTestTarget($targetKey);
        if ($resolved === null) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Target tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->helper('FonnteService');
        $send = FonnteService::sendToGroup($resolved['group_id'], 'TES');
        if (empty($send['success'])) {
            http_response_code(502);
            echo json_encode([
                'ok' => false,
                'message' => $send['error'] ?? 'Gagal kirim ke group',
                'target' => $resolved['label'],
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode([
            'ok' => true,
            'message' => 'Pesan TES terkirim ke ' . $resolved['label'],
            'target' => $resolved['label'],
            'group_id' => $resolved['group_id'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return list<array{key:string,label:string,group_id:string,kode?:string,id_cabang?:int}>
     */
    private function buildTestTargets(): array
    {
        $this->helper('FonnteService');

        $targets = [[
            'key' => 'delivery',
            'label' => 'Group Delivery',
            'group_id' => FonnteService::driverGroupId(),
        ]];

        $cabangs = $this->db(0)->get('cabang');
        if (!is_array($cabangs)) {
            $cabangs = [];
        }

        usort($cabangs, static function ($a, $b) {
            $na = strtolower(trim((string) ($a['nama'] ?? '')));
            $nb = strtolower(trim((string) ($b['nama'] ?? '')));
            return $na <=> $nb;
        });

        foreach ($cabangs as $cab) {
            if (!is_array($cab)) {
                continue;
            }
            $idCabang = (int) ($cab['id_cabang'] ?? 0);
            if ($idCabang <= 0) {
                continue;
            }
            $nama = trim((string) ($cab['nama'] ?? ''));
            $kode = trim((string) ($cab['kode_cabang'] ?? ''));
            $label = $nama !== '' ? $nama : ('Cabang #' . $idCabang);
            if ($kode !== '') {
                $label .= ' (' . $kode . ')';
            }

            $targets[] = [
                'key' => 'cabang_' . $idCabang,
                'label' => $label,
                'kode' => $kode,
                'id_cabang' => $idCabang,
                'group_id' => FonnteService::cabangGroupId($cab),
            ];
        }

        return $targets;
    }

    /**
     * @return array{key:string,label:string,group_id:string}|null
     */
    private function resolveTestTarget(string $targetKey): ?array
    {
        if ($targetKey === '') {
            return null;
        }

        foreach ($this->buildTestTargets() as $target) {
            if (($target['key'] ?? '') === $targetKey) {
                return $target;
            }
        }

        return null;
    }

    public function status()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::status();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    public function qr()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::qr();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    public function logout()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $api = $this->helper('FonnteGatewayApi');
        $res = $api::logout();
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }
}
