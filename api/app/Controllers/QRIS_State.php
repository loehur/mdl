<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Tampilan status/log QRIS per ref_finance.
 * URL: /QRIS_State/{ref_finance}
 */
class QRIS_State extends Controller
{
    public function index($ref_finance = null)
    {
        $refFinance = trim((string) $ref_finance);
        if ($refFinance === '') {
            echo 'Invalid ref_finance';
            exit;
        }

        $db = $this->db(1);
        if (!$db) {
            echo 'Database connection failed';
            exit;
        }

        $result = $db->get_where('kas_qris_cleanup_log', ['ref_finance' => $refFinance], 1);
        $log = $result->row_array();

        if (!$log) {
            echo 'Log QRIS tidak ditemukan untuk ref: ' . htmlspecialchars($refFinance);
            exit;
        }

        $rawDecoded = [];
        if (!empty($log['raw_json'])) {
            $decoded = json_decode($log['raw_json'], true);
            if (is_array($decoded)) {
                $rawDecoded = $decoded;
            }
        }

        $namaPelanggan = '';
        if (!empty($log['id_client'])) {
            $pel = $db->get_where('pelanggan', ['id_pelanggan' => (int) $log['id_client']], 1)->row_array();
            $namaPelanggan = $pel['nama_pelanggan'] ?? '';
        }

        $state = strtolower(trim($log['state'] ?? ''));
        $statusTheme = 'is-warning';
        $statusBadge = 'warning';
        $statusIcon = '⚠️';
        $statusLabel = strtoupper($log['state'] ?? '-');

        if (in_array($state, \Env::QRIS_STATUS_SUCCESS, true) || $state === 'paid') {
            $statusTheme = 'is-success';
            $statusBadge = 'success';
            $statusIcon = '✅';
        } elseif ($state === 'error' || in_array($state, \Env::QRIS_STATUS_EXPIRED, true) || $state === 'failed') {
            $statusTheme = 'is-danger';
            $statusBadge = 'danger';
            $statusIcon = '⏰';
        } elseif ($state === 'unpaid') {
            $statusTheme = 'is-warning';
            $statusBadge = 'warning';
            $statusIcon = '⚠️';
        } elseif ($state === 'pending') {
            $statusTheme = 'is-warning';
            $statusBadge = 'warning';
            $statusIcon = '⏳';
        }

        $this->renderView('qris_state', [
            'log' => $log,
            'raw_decoded' => $rawDecoded,
            'nama_pelanggan' => $namaPelanggan,
            'status_theme' => $statusTheme,
            'status_badge' => $statusBadge,
            'status_icon' => $statusIcon,
            'status_label' => $statusLabel,
            'raw_json_pretty' => json_encode(
                $rawDecoded ?: json_decode($log['raw_json'] ?? '{}', true),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
        ]);
    }

    private function renderView($view, $data = [])
    {
        extract($data);
        include __DIR__ . '/../Views/' . $view . '.php';
    }
}
