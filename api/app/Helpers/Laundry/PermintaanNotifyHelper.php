<?php

namespace App\Helpers\Laundry;

use App\Config\Fonnte;
use App\Core\DB;
use App\Helpers\CRM\CrmChatMergeHelper;
use App\Helpers\CRM\FonnteService;

/**
 * Notifikasi permintaan ke group Fonnte cabang (sama format autoreply PERMINTAAN).
 */
class PermintaanNotifyHelper
{
    public static function forwardToCabangGroup(
        string $phoneStorage,
        ?int $idCabang,
        string $summary,
        bool $isUpdate = false
    ): bool {
        $nama = self::formatGroupNama($phoneStorage);
        $ringkas = PermintaanSummaryHelper::finalize($summary, 280);
        if ($ringkas === '') {
            $ringkas = 'Permintaan atau pertanyaan pelanggan.';
        }

        $lines = ["*{$nama}*", $ringkas];
        if ($isUpdate) {
            $lines[] = '(update)';
        }
        $groupText = implode("\n", $lines);

        try {
            $groupId = self::resolveFonnteGroupId($idCabang);
            if ($groupId === '') {
                if (class_exists('\Log')) {
                    \Log::write(
                        'PermintaanNotify skip_no_group cabang=' . ($idCabang ?? 0) . ' phone=' . $phoneStorage,
                        'wa_error',
                        'Permintaan'
                    );
                }
                return false;
            }

            $fonnte = new FonnteService();
            $res = $fonnte->sendToGroup($groupId, $groupText, ['delay' => '0']);
            $ok = !empty($res['success']);
            if (!$ok && class_exists('\Log')) {
                \Log::write(
                    'PermintaanNotify fail cabang=' . ($idCabang ?? 0)
                    . ' target=' . $groupId
                    . ' err=' . ($res['error'] ?? 'unknown'),
                    'wa_error',
                    'Permintaan'
                );
            }

            return $ok;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('PermintaanNotify exception: ' . $e->getMessage(), 'wa_error', 'Permintaan');
            }
            return false;
        }
    }

    public static function resolveFonnteGroupId(?int $idCabang): string
    {
        if ($idCabang !== null && $idCabang > 0) {
            try {
                $rows = DB::getInstance(1)->query(
                    'SELECT id_group_fonnte FROM cabang WHERE id_cabang = ? LIMIT 1',
                    [$idCabang]
                )->result_array();
                $fromCabang = trim((string) ($rows[0]['id_group_fonnte'] ?? ''));
                if ($fromCabang !== '' && preg_match('/@g\.us$/i', $fromCabang)) {
                    return $fromCabang;
                }
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write('resolveFonnteGroupId: ' . $e->getMessage(), 'wa_error', 'Permintaan');
                }
            }
        }

        return Fonnte::getEstimasiGroupId();
    }

    private static function formatGroupNama(string $phoneStorage): string
    {
        $nama = '';

        try {
            [, $variants] = CrmChatMergeHelper::phoneInClause($phoneStorage);
            if ($variants !== []) {
                $placeholders = implode(',', array_fill(0, count($variants), '?'));
                $row = DB::getInstance(0)->query(
                    "SELECT contact_name FROM wa_conversations
                     WHERE wa_number IN ({$placeholders})
                     ORDER BY last_message_at DESC
                     LIMIT 1",
                    $variants
                )->row_array();
                $nama = trim((string) ($row['contact_name'] ?? ''));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        if ($nama === '') {
            try {
                $phoneKey = preg_replace('/[^0-9]/', '', $phoneStorage) ?: '';
                if ($phoneKey !== '') {
                    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(nomor_pelanggan,'+',''),'-',''),' ',''),'.','')";
                    $row = DB::getInstance(1)->query(
                        "SELECT nama_pelanggan FROM pelanggan
                         WHERE {$expr} LIKE ?
                         ORDER BY id_pelanggan DESC
                         LIMIT 1",
                        ['%' . $phoneKey]
                    )->row_array();
                    $nama = trim((string) ($row['nama_pelanggan'] ?? ''));
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($nama === '') {
            $nama = 'Pelanggan';
        }

        return mb_strtoupper($nama, 'UTF-8');
    }
}
