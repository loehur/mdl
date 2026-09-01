<?php

namespace App\Helpers\Laundry;

use App\Config\Fonnte;
use App\Helpers\CRM\FonnteService;

class KasUnmatchedNotification
{
    public static function send($db, array $row): array
    {
        $method = strtoupper(trim((string) ($row['method'] ?? '')));
        $refFinance = trim((string) ($row['ref_finance'] ?? ''));
        $userId = (int) ($row['id_user'] ?? 0);

        if ($method === '' || $refFinance === '' || $userId === 0) {
            return ['ok' => false, 'skipped' => true, 'error' => 'invalid_notification_row'];
        }

        try {
            $name = self::resolveName($db, $row);
            $groupId = self::resolveGroup($db, (int) ($row['id_cabang'] ?? 0));
            $amount = number_format((float) ($row['total'] ?? 0), 0, ',', '.');
            $message = implode("\n", ['*' . $method . '*', mb_strtoupper($name, 'UTF-8'), 'Rp ' . $amount, 'TIDAK DITEMUKAN']);

            $result = (new FonnteService())->sendToGroup($groupId, $message, ['delay' => '0']);
            if (empty($result['success'])) {
                return ['ok' => false, 'skipped' => false, 'error' => (string) ($result['error'] ?? 'send_failed')];
            }

            return ['ok' => true, 'skipped' => false, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'skipped' => false, 'error' => $e->getMessage()];
        }
    }

    private static function resolveName($db, array $row): string
    {
        $transactionType = (int) ($row['jenis_transaksi'] ?? 0);
        $userId = (int) ($row['id_user'] ?? 0);
        $clientId = (int) ($row['id_client'] ?? 0);

        if ($transactionType === 2 && $userId > 0) {
            $employee = $db->query(
                'SELECT nama_user FROM user WHERE id_user = ? LIMIT 1',
                [$userId]
            )->row_array();
            return trim((string) ($employee['nama_user'] ?? '')) ?: 'Kasir';
        }

        if ($transactionType === 5 && $clientId > 0) {
            $employee = $db->query(
                'SELECT nama_user FROM user WHERE id_user = ? LIMIT 1',
                [$clientId]
            )->row_array();
            return trim((string) ($employee['nama_user'] ?? '')) ?: 'Karyawan';
        }

        if ($transactionType === 7 && $clientId <= 0) {
            return 'Umum';
        }

        if ($clientId > 0) {
            $customer = $db->query(
                'SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$clientId]
            )->row_array();
            return trim((string) ($customer['nama_pelanggan'] ?? '')) ?: 'Pelanggan';
        }

        return 'Pelanggan';
    }

    private static function resolveGroup($db, int $branchId): string
    {
        if ($branchId > 0) {
            $branch = $db->query(
                'SELECT id_group_fonnte FROM cabang WHERE id_cabang = ? LIMIT 1',
                [$branchId]
            )->row_array();
            $groupId = trim((string) ($branch['id_group_fonnte'] ?? ''));
            if ($groupId !== '' && preg_match('/@g\.us$/i', $groupId)) {
                return $groupId;
            }
        }

        return Fonnte::getEstimasiGroupId();
    }
}
