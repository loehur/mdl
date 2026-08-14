<?php

/**
 * Cari pelanggan dari nomor HP (WA / kasir).
 * nomor = nasional tanpa 62/0, mulai 8 (62852… → 852…).
 * Kolom DB dibersihkan dulu, lalu LIKE '%{nomor}'.
 * id_pelanggan primary = sale terbaru (bin=0), fallback id terkecil.
 *
 *   $h = $this->helper('PelangganByPhone');
 *   $h->id($hp);      // int, 0 jika tidak ada
 *   $h->ids($hp);     // semua id nomor itu
 *   $h->resolve($hp); // id, ids, nama, id_cabang, nomor
 *   $h->row($hp);     // baris primary atau null
 */
class PelangganByPhone extends Controller
{
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

    /** Kunci identitas: nasional 852… (bukan 9 digit terakhir). */
    public static function key($phone): string
    {
        $n = self::toNomorNasional($phone);
        if ($n !== null) {
            return $n;
        }
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);

        return is_string($digits) ? $digits : '';
    }

    /**
     * Match kolom phone_tail: exact nasional baru, atau ekor lama sebagai suffix nomor.
     */
    public static function phoneTailWhere(string $escapedNomor, string $column = 'phone_tail'): string
    {
        return "({$column} = '{$escapedNomor}' OR ('{$escapedNomor}' <> '' AND {$column} <> '' AND '{$escapedNomor}' LIKE CONCAT('%', {$column})))";
    }

    public static function sqlDigitsExpr(string $column = 'nomor_pelanggan'): string
    {
        $expr = "TRIM({$column})";
        foreach (['+', '-', ' ', '(', ')', '.', '/', "'", '"', ':', ';', ',', '_', '*', '#', '@', '&', "\t"] as $ch) {
            $q = $ch === "'" ? "''" : $ch;
            $expr = "REPLACE({$expr},'{$q}','')";
        }

        return $expr;
    }

    /**
     * Klausa SQL: kolom nomor sudah digit, berakhiran $needle (sudah di-escape pemanggil).
     */
    public static function likeSql(string $escapedNeedle, string $column = 'nomor_pelanggan'): string
    {
        return self::sqlDigitsExpr($column) . " LIKE '%{$escapedNeedle}'";
    }

    /**
     * @return array{nomor:string,id_pelanggan:int,ids_pelanggan:list<int>,nama_pelanggan:?string,id_cabang:?int}
     */
    public function resolve($phone): array
    {
        $empty = [
            'nomor' => '',
            'id_pelanggan' => 0,
            'ids_pelanggan' => [],
            'nama_pelanggan' => null,
            'id_cabang' => null,
        ];
        $nomor = self::toNomorNasional($phone);
        if ($nomor === null) {
            $digits = preg_replace('/[^0-9]/', '', (string) $phone);
            if ($digits === null || strlen($digits) < 8) {
                return $empty;
            }
            $nomor = $digits;
        }
        $empty['nomor'] = $nomor;

        $esc = $this->db(0)->escape($nomor);
        $sql = 'SELECT id_pelanggan, nama_pelanggan, id_cabang FROM pelanggan WHERE '
            . self::likeSql($esc)
            . ' ORDER BY id_pelanggan ASC';
        $rows = $this->db(0)->query_array($sql);
        if (!is_array($rows) || $rows === []) {
            return $empty;
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
            return $empty;
        }

        $primaryId = $ids[0];
        $idCabang = isset($byId[$primaryId]['id_cabang']) ? (int) $byId[$primaryId]['id_cabang'] : 0;
        $idsIn = implode(',', $ids);
        $sale = $this->db(0)->query_array(
            "SELECT id_pelanggan, id_cabang FROM sale WHERE bin = 0 AND id_pelanggan IN ($idsIn) ORDER BY insertTime DESC LIMIT 1"
        );
        if (!empty($sale[0]['id_pelanggan'])) {
            $primaryId = (int) $sale[0]['id_pelanggan'];
            if (!empty($sale[0]['id_cabang'])) {
                $idCabang = (int) $sale[0]['id_cabang'];
            }
        }

        $nama = trim((string) ($byId[$primaryId]['nama_pelanggan'] ?? ''));
        if ($nama === '' && isset($byId[$ids[0]])) {
            $nama = trim((string) ($byId[$ids[0]]['nama_pelanggan'] ?? ''));
        }
        if ($idCabang <= 0 && isset($byId[$primaryId]['id_cabang'])) {
            $idCabang = (int) $byId[$primaryId]['id_cabang'];
        }

        return [
            'nomor' => $nomor,
            'id_pelanggan' => $primaryId,
            'ids_pelanggan' => $ids,
            'nama_pelanggan' => $nama !== '' ? $nama : null,
            'id_cabang' => $idCabang > 0 ? $idCabang : null,
        ];
    }

    /** @return list<int> */
    public function ids($phone): array
    {
        return $this->resolve($phone)['ids_pelanggan'];
    }

    public function id($phone): int
    {
        return (int) $this->resolve($phone)['id_pelanggan'];
    }

    /** @return array<string,mixed>|null */
    public function row($phone): ?array
    {
        $id = $this->id($phone);
        if ($id <= 0) {
            return null;
        }
        $row = $this->db(0)->get_where_row('pelanggan', 'id_pelanggan = ' . $id);

        return is_array($row) && !empty($row['id_pelanggan']) ? $row : null;
    }

    /** Primary di cabang itu, atau null. */
    public function rowInCabang($phone, int $idCabang): ?array
    {
        if ($idCabang <= 0) {
            return null;
        }
        $ids = $this->ids($phone);
        if ($ids === []) {
            return null;
        }
        $idsIn = implode(',', $ids);
        $inCabang = $this->db(0)->query_array(
            "SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan, id_cabang
             FROM pelanggan
             WHERE id_cabang = " . (int) $idCabang . " AND id_pelanggan IN ($idsIn)
             ORDER BY id_pelanggan ASC"
        );
        if (!is_array($inCabang) || $inCabang === []) {
            return null;
        }
        $cabangIds = array_map('intval', array_column($inCabang, 'id_pelanggan'));
        $cabangIds = array_values(array_filter($cabangIds));
        if ($cabangIds === []) {
            return null;
        }
        $pick = $cabangIds[0];
        $idsInC = implode(',', $cabangIds);
        $sale = $this->db(0)->query_array(
            "SELECT id_pelanggan FROM sale WHERE bin = 0 AND id_pelanggan IN ($idsInC) ORDER BY insertTime DESC LIMIT 1"
        );
        if (!empty($sale[0]['id_pelanggan'])) {
            $pick = (int) $sale[0]['id_pelanggan'];
        }
        foreach ($inCabang as $r) {
            if ((int) ($r['id_pelanggan'] ?? 0) === $pick) {
                return $r;
            }
        }

        return $inCabang[0];
    }
}
