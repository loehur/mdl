<?php

namespace App\Helpers\Laundry;

use App\Config\Fonnte;
use App\Helpers\CRM\FonnteService;

class KasMismatchNotify
{
    public static function send($dbLaundry, array $row, string $method): bool
    {
        try {
            $clientId = (int) ($row['id_client'] ?? 0);
            $userId = (int) ($row['id_user'] ?? 0);
            if ($userId === 0) {
                return false;
            }
            $branchId = (int) ($row['id_cabang'] ?? 0);
            $transactionType = (int) ($row['jenis_transaksi'] ?? 0);
            $name = $transactionType === 2 ? 'Karyawan' : 'Pelanggan';
            $groupId = Fonnte::getEstimasiGroupId();

            if ($transactionType === 2 && $userId > 0) {
                $employee = $dbLaundry->query(
                    'SELECT nama_user FROM user WHERE id_user = ? LIMIT 1',
                    [$userId]
                )->row_array();
                $name = trim((string) ($employee['nama_user'] ?? '')) ?: $name;
            } elseif ($clientId > 0) {
                $customer = $dbLaundry->query(
                    'SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                    [$clientId]
                )->row_array();
                $name = trim((string) ($customer['nama_pelanggan'] ?? '')) ?: $name;
            }

            if ($branchId > 0) {
                $branch = $dbLaundry->query(
                    'SELECT id_group_fonnte FROM cabang WHERE id_cabang = ? LIMIT 1',
                    [$branchId]
                )->row_array();
                $branchGroup = trim((string) ($branch['id_group_fonnte'] ?? ''));
                if ($branchGroup !== '' && preg_match('/@g\.us$/i', $branchGroup)) {
                    $groupId = $branchGroup;
                }
            }

            $amount = number_format((float) ($row['total'] ?? 0), 0, ',', '.');
            $message = implode("\n", [
                strtoupper(trim($method)),
                $name,
                'Rp ' . $amount,
                'TIDAK DITEMUKAN',
            ]);

            if (!class_exists(FonnteService::class, false)) {
                require_once __DIR__ . '/../CRM/FonnteService.php';
            }

            $result = (new FonnteService())->sendToGroup($groupId, $message, ['delay' => '0']);
            if (empty($result['success']) && class_exists('\\Log')) {
                \Log::write(
                    'KasMismatchNotify gagal method=' . $method
                    . ' ref=' . (string) ($row['ref_finance'] ?? '')
                    . ' cabang=' . $branchId
                    . ' error=' . (string) ($result['error'] ?? 'unknown'),
                    'wa_error',
                    'KasMismatch'
                );
            }
            return !empty($result['success']);
        } catch (\Throwable $e) {
            if (class_exists('\\Log')) {
                \Log::write(
                    'KasMismatchNotify exception method=' . $method
                    . ' ref=' . (string) ($row['ref_finance'] ?? '')
                    . ' error=' . $e->getMessage(),
                    'wa_error',
                    'KasMismatch'
                );
            }
            return false;
        }
    }
}
