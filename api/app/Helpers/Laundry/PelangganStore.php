<?php

namespace App\Helpers\Laundry;

use App\Core\DB;
use App\Helpers\CRM\WaSenderContext;
use App\Helpers\Jaggu_School\AiClient;

/**
 * Sumber tunggal CRUD pelanggan laundry (tabel pelanggan di mdl_laundry).
 * Dipakai API (Laundry/Pelanggan) — Laundry & CRM konsumen lewat endpoint ini.
 *
 * Kontrak response mengikuti kontrak lama Laundry (PelangganDaftar):
 * - cekHp: {ok:1, exists:bool, items:[{id,nama,hp}]}
 * - tambah: {ok:1, id, nama, hp} | {ok:0, msg}
 * - pilih: {ok:1, id, nama, hp} | {ok:0, msg}
 * - update/updateCell: {ok:1} | {ok:0, msg}
 * - cekEdit: {ok:1, nama_dup, ada_konflik, hasil}
 */
class PelangganStore
{
    private static function db(): DB
    {
        return DB::getInstance(1);
    }

    /** @return list<array{id:int,nama:string,hp:string}> */
    public static function cekHp(string $hp, int $idCabang): array
    {
        $hp = preg_replace('/\D/', '', $hp);
        if ($hp === '' || strlen($hp) < 8) {
            return ['ok' => 0, 'msg' => 'Nomor HP belum lengkap'];
        }

        $n = WaSenderContext::toNomorNasional($hp);
        if ($n === null || strlen($n) < 8) {
            $n = $hp;
        }

        $db = self::db();
        $esc = $db->escape($n);
        $rows = $db->query_array(
            'SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan
             FROM pelanggan
             WHERE id_cabang = ' . (int) $idCabang . ' AND ('
                . WaSenderContext::likeSql($esc, 'nomor_pelanggan')
                . ' OR ' . WaSenderContext::likeSql($esc, 'nomor_pelanggan_2') . ')
             ORDER BY id_pelanggan DESC'
        );

        $items = [];
        $seen = [];
        foreach ((array) $rows as $r) {
            $id = (int) ($r['id_pelanggan'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $items[] = [
                'id' => $id,
                'nama' => strtoupper(trim((string) ($r['nama_pelanggan'] ?? ''))),
                'hp' => (string) ($r['nomor_pelanggan'] ?? ''),
            ];
        }

        return [
            'ok' => 1,
            'exists' => $items !== [],
            'items' => $items,
        ];
    }

    /** @return array{ok:int,msg?:string,id?:int,nama?:string,hp?:string} */
    public static function tambah(array $input, int $idCabang): array
    {
        $nama = strtoupper(trim((string) ($input['nama'] ?? $input['f1'] ?? '')));
        $hp = preg_replace('/\D/', '', (string) ($input['hp'] ?? $input['f2'] ?? ''));
        $hp2 = preg_replace('/\D/', '', (string) ($input['hp2'] ?? $input['f3'] ?? ''));
        $cekMirip = (string) ($input['cek_mirip'] ?? '') === '1';

        if ($nama === '' || $hp === '') {
            return ['ok' => 0, 'msg' => 'Nama dan nomor HP wajib diisi'];
        }

        $db = self::db();
        $namaEsc = $db->escape($nama);
        $where = 'id_cabang = ' . (int) $idCabang . " AND nama_pelanggan = '" . $namaEsc . "'";
        if ($db->count_where('pelanggan', $where) > 0) {
            return ['ok' => 0, 'msg' => 'Gagal! nama ' . $nama . ' sudah digunakan'];
        }

        $existing = self::itemsByHpRaw($hp, $idCabang);
        if ($existing !== []) {
            if (!$cekMirip) {
                return ['ok' => 0, 'msg' => 'Nomor sudah terdaftar. Cek dulu, lalu pilih yang ada atau simpan nama baru.'];
            }
            $mirip = self::cekNamaMiripAi($nama, $existing);
            if ($mirip === null) {
                return ['ok' => 0, 'msg' => 'Gagal memeriksa kemiripan nama. Coba lagi.'];
            }
            if (!empty($mirip['similar'])) {
                $match = trim((string) ($mirip['match'] ?? ''));
                $msg = $match !== ''
                    ? 'Nama terlalu mirip dengan "' . $match . '". Pakai data yang sudah ada, atau ganti nama yang benar-benar berbeda.'
                    : 'Nama terlalu mirip dengan yang sudah terdaftar. Pakai data yang sudah ada, atau ganti nama yang benar-benar berbeda.';
                return ['ok' => 0, 'msg' => $msg];
            }
        }

        $do = $db->insert('pelanggan', [
            'id_cabang' => (int) $idCabang,
            'nama_pelanggan' => $nama,
            'nomor_pelanggan' => $hp,
            'nomor_pelanggan_2' => $hp2 !== '' ? $hp2 : null,
        ]);
        if (($do['errno'] ?? 1) != 0) {
            self::log('[PelangganStore::tambah] ' . ($do['error'] ?? ''));
            return ['ok' => 0, 'msg' => 'Gagal menyimpan pelanggan'];
        }

        $row = $db->get_where_order(
            'pelanggan',
            'id_cabang = ' . (int) $idCabang . " AND nama_pelanggan = '" . $namaEsc . "'",
            'id_pelanggan DESC'
        );
        $new = is_array($row) && isset($row[0]) ? $row[0] : null;
        if (!$new) {
            return ['ok' => 0, 'msg' => 'Tersimpan, tetapi ID tidak ditemukan. Refresh halaman.'];
        }

        return [
            'ok' => 1,
            'id' => (int) $new['id_pelanggan'],
            'nama' => strtoupper((string) $new['nama_pelanggan']),
            'hp' => (string) $new['nomor_pelanggan'],
        ];
    }

    /** @return array{ok:int,msg?:string,id?:int,nama?:string,hp?:string} */
    public static function pilih(int $id, string $nama, int $idCabang): array
    {
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }

        $db = self::db();
        $row = $db->get_where_row(
            'pelanggan',
            'id_cabang = ' . (int) $idCabang . ' AND id_pelanggan = ' . $id
        );
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak ditemukan'];
        }

        $namaLama = trim((string) ($row['nama_pelanggan'] ?? ''));
        $nama = strtoupper(trim($nama));
        if ($nama !== '' && strcasecmp($nama, $namaLama) !== 0) {
            $namaEsc = $db->escape($nama);
            $dup = $db->count_where(
                'pelanggan',
                'id_cabang = ' . (int) $idCabang . " AND nama_pelanggan = '" . $namaEsc . "' AND id_pelanggan <> " . $id
            );
            if ($dup > 0) {
                return ['ok' => 0, 'msg' => 'Gagal! nama ' . $nama . ' sudah digunakan'];
            }
            $up = $db->update(
                'pelanggan',
                ['nama_pelanggan' => $nama],
                'id_cabang = ' . (int) $idCabang . ' AND id_pelanggan = ' . $id
            );
            if (!empty($up['errno'])) {
                return ['ok' => 0, 'msg' => $up['error'] ?? 'Gagal mengubah nama'];
            }
            $namaLama = $nama;
        }

        return [
            'ok' => 1,
            'id' => $id,
            'nama' => strtoupper($namaLama),
            'hp' => (string) ($row['nomor_pelanggan'] ?? ''),
        ];
    }

    /** @return array{ok:int,msg?:string} */
    public static function update(array $input, int $idCabang, bool $canEditDisc): array
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }

        $nama = strtoupper(trim((string) ($input['nama_pelanggan'] ?? '')));
        $nomor = preg_replace('/\D/', '', (string) ($input['nomor_pelanggan'] ?? ''));
        $nomor2 = preg_replace('/\D/', '', (string) ($input['nomor_pelanggan_2'] ?? ''));
        $disc = (float) ($input['disc'] ?? 0);
        if ($disc > 100) {
            $disc = 100;
        }
        if ($disc < 0) {
            $disc = 0;
        }

        if ($nama === '' || $nomor === '') {
            return ['ok' => 0, 'msg' => 'Nama dan nomor HP tidak boleh kosong'];
        }

        $db = self::db();
        $namaEsc = $db->escape($nama);
        $dup = $db->count_where(
            'pelanggan',
            'id_cabang = ' . (int) $idCabang . " AND nama_pelanggan = '" . $namaEsc . "' AND id_pelanggan <> " . $id
        );
        if ($dup > 0) {
            return ['ok' => 0, 'msg' => 'Gagal! nama ' . $nama . ' sudah digunakan'];
        }

        $set = [
            'nama_pelanggan' => $nama,
            'nomor_pelanggan' => $nomor,
            'nomor_pelanggan_2' => $nomor2 !== '' ? $nomor2 : null,
        ];
        if ($canEditDisc) {
            $set['disc'] = $disc;
        }
        $up = $db->update(
            'pelanggan',
            $set,
            'id_cabang = ' . (int) $idCabang . ' AND id_pelanggan = ' . $id
        );
        if (($up['errno'] ?? 1) != 0) {
            return ['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update'];
        }

        return ['ok' => 1];
    }

    /** @return array{ok:int,msg?:string} */
    public static function updateCell(int $id, string $mode, $value, int $idCabang, bool $canEditDisc): array
    {
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }

        $col = null;
        if ($mode === '1') {
            $col = 'nama_pelanggan';
            $value = is_string($value) ? strtoupper(trim($value)) : $value;
        } elseif ($mode === '2') {
            $col = 'nomor_pelanggan';
            $value = preg_replace('/\D/', '', (string) $value);
        } elseif ($mode === '6') {
            $col = 'nomor_pelanggan_2';
            $value = preg_replace('/\D/', '', (string) $value);
        } elseif ($mode === '5') {
            if (!$canEditDisc) {
                return ['ok' => 0, 'msg' => 'Hanya admin yang dapat mengubah diskon'];
            }
            $col = 'disc';
            $value = (float) $value;
            if ($value > 100) {
                $value = 100;
            }
        }
        if ($col === null) {
            return ['ok' => 0, 'msg' => 'Mode tidak valid'];
        }

        $db = self::db();
        $up = $db->update(
            'pelanggan',
            [$col => $value],
            'id_cabang = ' . (int) $idCabang . ' AND id_pelanggan = ' . $id
        );
        if (($up['errno'] ?? 1) != 0) {
            return ['ok' => 0, 'msg' => $up['error'] ?? 'Gagal update'];
        }

        return ['ok' => 1];
    }

    /** @return array{ok:int,nama_dup:?array,ada_konflik:bool,hasil:list<array>} */
    public static function cekEdit(array $input, int $idCabang): array
    {
        $id = (int) ($input['id'] ?? 0);
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }
        $nama = strtoupper(trim((string) ($input['nama_pelanggan'] ?? '')));
        $nomor = preg_replace('/\D/', '', (string) ($input['nomor_pelanggan'] ?? ''));
        $nomor2 = preg_replace('/\D/', '', (string) ($input['nomor_pelanggan_2'] ?? ''));

        $db = self::db();

        // Nama harus unik di cabang sama — langsung tolak.
        $namaDup = null;
        if ($nama !== '') {
            $namaEsc = $db->escape($nama);
            $rows = $db->query_array(
                'SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan
                 FROM pelanggan
                 WHERE id_cabang = ' . (int) $idCabang . " AND nama_pelanggan = '" . $namaEsc . "' AND id_pelanggan <> " . $id
                    . ' ORDER BY id_pelanggan DESC'
            );
            if (!empty($rows[0])) {
                $namaDup = [
                    'id' => (int) ($rows[0]['id_pelanggan'] ?? 0),
                    'nama' => strtoupper((string) ($rows[0]['nama_pelanggan'] ?? '')),
                    'nomor' => (string) ($rows[0]['nomor_pelanggan'] ?? ''),
                ];
            }
        }

        $hasil = [];
        $nomorCek = [['nomor' => $nomor, 'label' => 'Nomor HP']];
        if ($nomor2 !== '') {
            $nomorCek[] = ['nomor' => $nomor2, 'label' => 'Nomor HP Alternatif'];
        }
        $seen = [];
        foreach ($nomorCek as $nc) {
            $n = WaSenderContext::toNomorNasional($nc['nomor']);
            if ($n === null || strlen($n) < 8) {
                continue;
            }
            $kunci = $n;
            if (isset($seen[$kunci])) {
                $hasil[] = [
                    'label' => $nc['label'],
                    'nomor' => $n,
                    'bentrok' => true,
                    'msg' => $nc['label'] . ' sama dengan ' . $seen[$kunci] . ' — gunakan nomor berbeda',
                ];
                continue;
            }
            $seen[$kunci] = $nc['label'];

            $esc = $db->escape($n);
            $rows = $db->query_array(
                'SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan, nomor_pelanggan_2
                 FROM pelanggan
                 WHERE id_cabang = ' . (int) $idCabang . ' AND id_pelanggan <> ' . $id
                    . ' AND (' . WaSenderContext::likeSql($esc, 'nomor_pelanggan')
                    . ' OR ' . WaSenderContext::likeSql($esc, 'nomor_pelanggan_2') . ')'
                    . ' ORDER BY id_pelanggan DESC'
            );
            $items = [];
            foreach ((array) $rows as $r) {
                $items[] = [
                    'id' => (int) ($r['id_pelanggan'] ?? 0),
                    'nama' => strtoupper((string) ($r['nama_pelanggan'] ?? '')),
                    'nomor' => (string) ($r['nomor_pelanggan'] ?? ''),
                    'nomor2' => (string) ($r['nomor_pelanggan_2'] ?? ''),
                ];
            }
            $hasil[] = [
                'label' => $nc['label'],
                'nomor' => $n,
                'bentrok' => $items !== [],
                'items' => $items,
            ];
        }

        $adaKonflik = false;
        foreach ($hasil as $h) {
            if (!empty($h['bentrok'])) {
                $adaKonflik = true;
                break;
            }
        }

        return [
            'ok' => 1,
            'nama_dup' => $namaDup,
            'ada_konflik' => $adaKonflik,
            'hasil' => $hasil,
        ];
    }

    /** @return array{ok:int,msg?:string} */
    public static function setNomorAlternatif(int $id, string $nomor2, int $idCabang): array
    {
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }
        $nomor2 = preg_replace('/\D/', '', $nomor2);

        $db = self::db();
        $row = $db->query(
            'SELECT id_cabang, nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
            [$id]
        )->row_array();
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak ditemukan'];
        }

        if ($idCabang <= 0) {
            $idCabang = (int) ($row['id_cabang'] ?? 0);
        }

        $nomorUtama = preg_replace('/\D/', '', (string) ($row['nomor_pelanggan'] ?? ''));

        if ($nomor2 !== '') {
            if (strlen($nomor2) < 8) {
                return ['ok' => 0, 'msg' => 'Nomor alternatif minimal 8 digit'];
            }
            if ($nomorUtama !== '' && $nomor2 === $nomorUtama) {
                return ['ok' => 0, 'msg' => 'Nomor alternatif tidak boleh sama dengan nomor utama'];
            }
            $n = WaSenderContext::toNomorNasional($nomor2);
            if ($n !== null && strlen($n) >= 8) {
                $esc = $db->escape($n);
                $dup = $db->query(
                    'SELECT id_pelanggan FROM pelanggan
                     WHERE id_cabang = ' . (int) $idCabang
                        . ' AND id_pelanggan <> ' . $id
                        . ' AND (' . WaSenderContext::likeSql($esc, 'nomor_pelanggan')
                        . ' OR ' . WaSenderContext::likeSql($esc, 'nomor_pelanggan_2') . ')
                     LIMIT 1'
                )->row_array();
                if (!empty($dup['id_pelanggan'])) {
                    return ['ok' => 0, 'msg' => 'Nomor alternatif sudah digunakan pelanggan lain di cabang yang sama'];
                }
            }
        }

        $up = $db->update(
            'pelanggan',
            ['nomor_pelanggan_2' => $nomor2 !== '' ? $nomor2 : null],
            'id_pelanggan = ' . $id
        );
        if (!empty($up['errno'])) {
            return ['ok' => 0, 'msg' => 'Gagal menyimpan nomor alternatif: ' . ($up['error'] ?? 'error')];
        }

        return ['ok' => 1];
    }

    /** @return list<array{id:int,nama:string,hp:string}> */
    private static function itemsByHpRaw(string $hp, int $idCabang): array
    {
        $res = self::cekHp($hp, $idCabang);
        return $res['items'] ?? [];
    }

    /**
     * @param list<array{id:int,nama:string,hp:string}> $existing
     * @return array{similar:bool,match:string,reason:string}|null
     */
    private static function cekNamaMiripAi(string $namaBaru, array $existing): ?array
    {
        $baruNorm = self::normalizeNamaBanding($namaBaru);
        if ($baruNorm === '') {
            return ['similar' => true, 'match' => '', 'reason' => 'nama kosong'];
        }
        $lamaList = [];
        foreach ($existing as $it) {
            $lama = trim((string) ($it['nama'] ?? ''));
            if ($lama === '') {
                continue;
            }
            $lamaList[] = $lama;
            if (self::normalizeNamaBanding($lama) === $baruNorm) {
                return ['similar' => true, 'match' => $lama, 'reason' => 'nama sama'];
            }
        }
        if ($lamaList === []) {
            return ['similar' => false, 'match' => '', 'reason' => ''];
        }

        $system = "Kamu penilai identitas pelanggan laundry Indonesia.\n"
            . "Tugas: apakah NAMA BARU merujuk orang/tempat/sebutan yang SAMA dengan salah satu NAMA LAMA (hanya mirip), atau identitas yang benar-benar berbeda.\n"
            . "similar=true jika: typo/ejaan, singkatan/inisial, nama pendek vs lengkap orang sama, sapaan+nama (Bang X vs X), kapital/spasi/#NEW#, panggilan wajar orang yang sama.\n"
            . "similar=false HANYA jika jelas identitas lain (orang berbeda, toko/kantor/sebutan berbeda).\n"
            . "Output JSON saja: {\"similar\":true|false,\"match\":\"nama lama atau kosong\",\"reason\":\"singkat\"}";
        $user = "NAMA BARU: " . $namaBaru . "\nNAMA LAMA: " . json_encode(array_values($lamaList), JSON_UNESCAPED_UNICODE);

        try {
            $raw = AiClient::chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 120, 0.0)['content'];
        } catch (\Throwable $e) {
            self::log('[PelangganStore::cekNamaMiripAi] ' . $e->getMessage());
            return null;
        }

        $json = $raw;
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            $json = $m[0];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !array_key_exists('similar', $decoded)) {
            return null;
        }
        $flag = $decoded['similar'];
        $similar = $flag === true || $flag === 1 || $flag === '1' || $flag === 'true';
        $match = trim((string) ($decoded['match'] ?? ''));
        if ($similar && $match === '' && isset($lamaList[0])) {
            $match = $lamaList[0];
        }

        return [
            'similar' => $similar,
            'match' => $match,
            'reason' => trim((string) ($decoded['reason'] ?? '')),
        ];
    }

    private static function normalizeNamaBanding(string $nama): string
    {
        $nama = strtoupper(trim($nama));
        $nama = preg_replace('/\s*#NEW#\s*$/u', '', $nama) ?? $nama;
        $nama = preg_replace('/[^A-Z0-9]+/u', ' ', $nama) ?? $nama;

        return trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama);
    }

    private static function log(string $text): void
    {
        if (class_exists('\\Log', false)) {
            \Log::write($text, 'api', 'PelangganStore');
        }
    }
}
