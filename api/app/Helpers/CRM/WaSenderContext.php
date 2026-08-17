<?php

namespace App\Helpers\CRM;

use App\Core\DB;

/**
 * Identitas pengirim WA (sebelum deteksi intent).
 * nomor = nasional ID tanpa 62/0, mulai 8 (62852… → 852…).
 */
class WaSenderContext
{
    /**
     * @return array{
     *   nomor: string,
     *   is_admin: bool,
     *   is_karyawan: bool,
     *   id_karyawan: int,
     *   is_pelanggan: bool,
     *   id_pelanggan: int,
     *   ids_pelanggan: list<int>,
     *   contact_name: ?string,
     *   assigned_user_id: ?int,
     *   code: ?string,
     *   cust_id: ?int
     * }
     */
    public static function resolve(string $waNumber): array
    {
        $ctx = self::empty();
        $nomor = self::toNomorNasional($waNumber);
        if ($nomor === null) {
            return $ctx;
        }
        $ctx['nomor'] = $nomor;
        $ctx['is_admin'] = self::isAdminNomor($nomor);

        try {
            $db1 = DB::getInstance(1);
        } catch (\Throwable $e) {
            return $ctx;
        }

        self::fillKaryawan($db1, $nomor, $ctx);
        self::fillPelanggan($db1, $nomor, $ctx);

        // Karyawan (meski juga pelanggan) tidak di-assign ke CSW cabang.
        if (!empty($ctx['is_karyawan'])) {
            $ctx['assigned_user_id'] = null;
        }

        return $ctx;
    }

    /** Resolve dari 08… / 62… / 852… (getUserData lama). */
    public static function resolveFromPhone($phone): array
    {
        return self::resolve((string) $phone);
    }

    /**
     * @return array{
     *   nomor: string,
     *   is_admin: bool,
     *   is_karyawan: bool,
     *   id_karyawan: int,
     *   is_pelanggan: bool,
     *   id_pelanggan: int,
     *   ids_pelanggan: list<int>,
     *   contact_name: ?string,
     *   assigned_user_id: ?int,
     *   code: ?string,
     *   cust_id: ?int
     * }
     */
    public static function empty(): array
    {
        return [
            'nomor' => '',
            'is_admin' => false,
            'is_karyawan' => false,
            'id_karyawan' => 0,
            'is_pelanggan' => false,
            'id_pelanggan' => 0,
            'ids_pelanggan' => [],
            'contact_name' => null,
            'assigned_user_id' => null,
            'code' => null,
            'cust_id' => null,
        ];
    }

    /** 628529834343 / 0852… / +62… → 8529834343 */
    public static function toNomorNasional($phone): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '62') && strlen($digits) >= 11) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 8) {
            return null;
        }

        return $digits;
    }

    /** Kunci identitas: nasional 852… (bukan N digit terakhir). */
    public static function key($phone): string
    {
        $n = self::toNomorNasional($phone);
        if ($n !== null) {
            return $n;
        }
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);

        return is_string($digits) ? $digits : '';
    }

    public static function likeSql(string $escapedNeedle, string $column): string
    {
        return self::sqlDigitsExpr($column) . " LIKE '%{$escapedNeedle}'";
    }

    /** Klausa IN untuk handler lama: 62 / 08 / +62 / 8… */
    public static function phoneInClause(string $nomor): string
    {
        $nomor = preg_replace('/[^0-9]/', '', $nomor);
        if ($nomor === '') {
            return "''";
        }
        $clean = (str_starts_with($nomor, '8') ? '62' : '') . $nomor;
        if (!str_starts_with($clean, '62')) {
            $clean = '62' . $clean;
        }
        $rest = str_starts_with($clean, '62') ? substr($clean, 2) : $nomor;
        $phone0 = '0' . $rest;
        $parts = array_unique(["'$clean'", "'$phone0'", "'+$clean'", "'$rest'"]);

        return implode(',', $parts);
    }

    public static function sqlDigitsExpr(string $column): string
    {
        $expr = "TRIM({$column})";
        foreach (['+', '-', ' ', '(', ')', '.', '/', "'", '"', ':', ';', ',', '_', '*', '#', '@', '&', "\t"] as $ch) {
            $q = $ch === "'" ? "''" : $ch;
            $expr = "REPLACE({$expr},'{$q}','')";
        }

        return $expr;
    }

    private static function isAdminNomor(string $nomor): bool
    {
        if (!class_exists('\\Env')) {
            return false;
        }
        $list = \Env::ADMIN_NUMBERS ?? [];
        if (!is_array($list)) {
            return false;
        }
        foreach ($list as $raw) {
            $adminNomor = self::toNomorNasional((string) $raw);
            if ($adminNomor !== null && $adminNomor === $nomor) {
                return true;
            }
        }

        return false;
    }

    private static function fillKaryawan($db1, string $nomor, array &$ctx): void
    {
        try {
            $expr = self::sqlDigitsExpr('no_user');
            $row = $db1->query(
                "SELECT id_user, nama_user FROM user WHERE en = 1 AND {$expr} LIKE ? ORDER BY id_user ASC LIMIT 1",
                ['%' . $nomor]
            )->row();
            if ($row && !empty($row->id_user)) {
                $ctx['is_karyawan'] = true;
                $ctx['id_karyawan'] = (int) $row->id_user;
                $nama = trim((string) ($row->nama_user ?? ''));
                if ($nama !== '') {
                    $ctx['contact_name'] = $nama;
                }
            }
        } catch (\Throwable $e) {
            // biarkan false
        }
    }

    private static function fillPelanggan($db1, string $nomor, array &$ctx): void
    {
        try {
            $expr = self::sqlDigitsExpr('nomor_pelanggan');
            $rows = $db1->query(
                "SELECT id_pelanggan, nama_pelanggan, id_cabang FROM pelanggan WHERE {$expr} LIKE ? ORDER BY id_pelanggan ASC",
                ['%' . $nomor]
            )->result_array();
        } catch (\Throwable $e) {
            return;
        }
        if (empty($rows)) {
            return;
        }

        $ids = [];
        $byId = [];
        foreach ($rows as $p) {
            $id = (int) ($p['id_pelanggan'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $byId[$id] = $p;
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return;
        }

        $ctx['is_pelanggan'] = true;
        $ctx['ids_pelanggan'] = $ids;

        $primaryId = $ids[0];
        $idCabang = isset($byId[$primaryId]['id_cabang']) ? (int) $byId[$primaryId]['id_cabang'] : 0;
        $idsIn = implode(',', $ids);
        try {
            $sale = $db1->query(
                "SELECT id_pelanggan, id_cabang FROM sale WHERE bin = 0 AND id_pelanggan IN ($idsIn) ORDER BY insertTime DESC LIMIT 1"
            )->row();
            if ($sale && !empty($sale->id_pelanggan)) {
                $primaryId = (int) $sale->id_pelanggan;
                if (!empty($sale->id_cabang)) {
                    $idCabang = (int) $sale->id_cabang;
                }
            }
        } catch (\Throwable $e) {
            // fallback id terkecil
        }

        $ctx['id_pelanggan'] = $primaryId;
        $ctx['cust_id'] = $primaryId;
        if (empty($ctx['contact_name'])) {
            $nama = trim((string) ($byId[$primaryId]['nama_pelanggan'] ?? ''));
            if ($nama === '' && isset($byId[$ids[0]])) {
                $nama = trim((string) ($byId[$ids[0]]['nama_pelanggan'] ?? ''));
            }
            $ctx['contact_name'] = $nama !== '' ? $nama : null;
        }

        if ($idCabang <= 0 && isset($byId[$primaryId]['id_cabang'])) {
            $idCabang = (int) $byId[$primaryId]['id_cabang'];
        }
        if ($idCabang > 0) {
            try {
                $cabang = $db1->query(
                    'SELECT kode_cabang FROM cabang WHERE id_cabang = ? LIMIT 1',
                    [$idCabang]
                )->row();
                if ($cabang && !empty($cabang->kode_cabang)) {
                    $ctx['code'] = (string) $cabang->kode_cabang;
                }
            } catch (\Throwable $e) {
                // ignore
            }
            // Jangan assign CSW dari cabang pelanggan jika pengirim adalah karyawan.
            if (empty($ctx['is_karyawan'])) {
                $ctx['assigned_user_id'] = $idCabang;
            }
        }
    }

    /** Assignment CSW: hanya pelanggan murni, bukan karyawan. */
    public static function cswAssignedUserId(array $ctx): ?int
    {
        if (!empty($ctx['is_karyawan'])) {
            return null;
        }
        $id = $ctx['assigned_user_id'] ?? null;
        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

    /** Cek karyawan saja (tanpa lookup pelanggan) — untuk hot-path webhook. */
    public static function isKaryawanNumber(string $waNumber): bool
    {
        $nomor = self::toNomorNasional($waNumber);
        if ($nomor === null) {
            return false;
        }
        $ctx = self::empty();
        try {
            $db1 = DB::getInstance(1);
        } catch (\Throwable $e) {
            return false;
        }
        self::fillKaryawan($db1, $nomor, $ctx);

        return !empty($ctx['is_karyawan']);
    }
}
