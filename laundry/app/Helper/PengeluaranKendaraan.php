<?php

/**
 * Opsi kendaraan untuk jenis pengeluaran Minyak Kendaraan.
 */
class PengeluaranKendaraan
{
    public static function isMinyakKendaraan(string $jenis): bool
    {
        $j = mb_strtoupper(trim($jenis), 'UTF-8');
        if ($j === '') {
            return false;
        }

        return str_contains($j, 'MINYAK') && str_contains($j, 'KENDARAAN');
    }

    /**
     * @param array<string,mixed> $post
     * @return array{ok:bool,note?:string,message?:string,id_kendaraan?:int}
     */
    public static function resolveKeteranganFromPost(array $post, string $jenisNama, $db): array
    {
        $jenisNama = trim($jenisNama);
        if (!self::isMinyakKendaraan($jenisNama)) {
            return ['ok' => true, 'note' => trim((string) ($post['f1'] ?? ''))];
        }

        $idKendaraan = (int) ($post['f1_kendaraan'] ?? 0);
        if ($idKendaraan <= 0) {
            return ['ok' => false, 'message' => 'Pilih kendaraan terlebih dahulu.'];
        }

        $row = $db->get_where_row('pengeluaran_kendaraan', 'id_kendaraan = ' . $idKendaraan . ' AND aktif = 1');
        if (!is_array($row) || empty($row['id_kendaraan'])) {
            return ['ok' => false, 'message' => 'Kendaraan tidak valid.'];
        }

        $isLainnya = (int) ($row['is_lainnya'] ?? 0) === 1;
        if ($isLainnya) {
            $lain = trim((string) ($post['f1_lainnya'] ?? ''));
            if ($lain === '') {
                return ['ok' => false, 'message' => 'Keterangan wajib diisi untuk opsi Lainnya.'];
            }
            if (mb_strlen($lain) > 80) {
                return ['ok' => false, 'message' => 'Keterangan maksimal 80 karakter.'];
            }

            return ['ok' => true, 'note' => $lain, 'id_kendaraan' => $idKendaraan];
        }

        $nama = trim((string) ($row['nama_kendaraan'] ?? ''));
        if ($nama === '') {
            return ['ok' => false, 'message' => 'Data kendaraan tidak lengkap.'];
        }

        return ['ok' => true, 'note' => $nama, 'id_kendaraan' => $idKendaraan];
    }

    public static function bumpFreq($db, int $idKendaraan): void
    {
        if ($idKendaraan <= 0) {
            return;
        }

        $db->update('pengeluaran_kendaraan', 'freq = freq + 1', 'id_kendaraan = ' . $idKendaraan);
    }

    /** @return list<array<string,mixed>> */
    public static function refreshSessionList($db): array
    {
        $rows = $db->get_where_order('pengeluaran_kendaraan', 'aktif = 1', 'freq DESC, sort_order ASC, id_kendaraan ASC');
        return is_array($rows) ? $rows : [];
    }
}
