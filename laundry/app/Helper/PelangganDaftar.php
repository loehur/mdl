<?php

/**
 * Tambah/cek pelanggan terpusat (order + halaman Data Pelanggan).
 */
class PelangganDaftar extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    /** @return array{ok:int,msg?:string,exists?:bool,items?:list<array{id:int,nama:string,hp:string}>} */
    public function cekHpFromPost(): array
    {
        $hp = preg_replace('/\D/', '', (string) ($_POST['f2'] ?? ''));
        if ($hp === '' || strlen($hp) < 8) {
            return ['ok' => 0, 'msg' => 'Nomor HP belum lengkap'];
        }
        $items = $this->itemsByHp($hp);

        return [
            'ok' => 1,
            'exists' => $items !== [],
            'items' => $items,
        ];
    }

    /** @return array{ok:int,msg?:string,id?:int,nama?:string,hp?:string} */
    public function tambahFromPost(): array
    {
        $nama = trim((string) ($_POST['f1'] ?? ''));
        $hp = preg_replace('/\D/', '', (string) ($_POST['f2'] ?? ''));
        $cekMirip = (string) ($_POST['cek_mirip'] ?? '') === '1';

        if ($nama === '' || $hp === '') {
            return ['ok' => 0, 'msg' => 'Nama dan nomor HP wajib diisi'];
        }

        $namaEsc = addslashes($nama);
        $where = $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "'";
        if ($this->db(0)->count_where('pelanggan', $where) > 0) {
            return ['ok' => 0, 'msg' => 'Gagal! nama ' . strtoupper($nama) . ' sudah digunakan'];
        }

        $existing = $this->itemsByHp($hp);
        if ($existing !== []) {
            if (!$cekMirip) {
                return ['ok' => 0, 'msg' => 'Nomor sudah terdaftar. Cek dulu, lalu pilih yang ada atau simpan nama baru.'];
            }
            $mirip = $this->cekNamaMiripAi($nama, $existing);
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

        $do = $this->db(0)->insert('pelanggan', [
            'id_cabang' => $this->id_cabang,
            'nama_pelanggan' => $nama,
            'nomor_pelanggan' => $hp,
        ]);
        if (($do['errno'] ?? 1) != 0) {
            $this->model('Log')->write('[PelangganDaftar::tambah] ' . ($do['error'] ?? ''));

            return ['ok' => 0, 'msg' => 'Gagal menyimpan pelanggan'];
        }

        $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        $row = $this->db(0)->get_where_order(
            'pelanggan',
            $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "'",
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
    public function pilihFromPost(): array
    {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = trim((string) ($_POST['nama'] ?? ''));
        if ($id < 1) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak valid'];
        }

        $row = $this->db(0)->get_where_row(
            'pelanggan',
            $this->wCabang . ' AND id_pelanggan = ' . $id
        );
        if (!is_array($row) || empty($row['id_pelanggan'])) {
            return ['ok' => 0, 'msg' => 'Pelanggan tidak ditemukan'];
        }

        $namaLama = trim((string) ($row['nama_pelanggan'] ?? ''));
        if ($nama !== '' && strcasecmp($nama, $namaLama) !== 0) {
            $namaEsc = addslashes($nama);
            $dup = $this->db(0)->count_where(
                'pelanggan',
                $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "' AND id_pelanggan <> " . $id
            );
            if ($dup > 0) {
                return ['ok' => 0, 'msg' => 'Gagal! nama ' . strtoupper($nama) . ' sudah digunakan'];
            }
            $up = $this->db(0)->update(
                'pelanggan',
                ['nama_pelanggan' => $nama],
                $this->wCabang . ' AND id_pelanggan = ' . $id
            );
            if (!empty($up['errno'])) {
                return ['ok' => 0, 'msg' => $up['error'] ?? 'Gagal mengubah nama'];
            }
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
            $namaLama = $nama;
        }

        return [
            'ok' => 1,
            'id' => $id,
            'nama' => strtoupper($namaLama),
            'hp' => (string) ($row['nomor_pelanggan'] ?? ''),
        ];
    }

    /** @return list<array{id:int,nama:string,hp:string}> */
    public function itemsByHp(string $hp): array
    {
        $hp = preg_replace('/\D/', '', $hp);
        if ($hp === '' || strlen($hp) < 8) {
            return [];
        }
        $this->helper('PelangganByPhone');
        $nomor = PelangganByPhone::toNomorNasional($hp);
        if ($nomor === null || $nomor === '') {
            $nomor = $hp;
        }
        $esc = $this->db(0)->escape($nomor);
        $rows = $this->db(0)->query_array(
            'SELECT id_pelanggan, nama_pelanggan, nomor_pelanggan
             FROM pelanggan
             WHERE ' . $this->wCabang . ' AND ' . PelangganByPhone::likeSql($esc) . '
             ORDER BY id_pelanggan DESC'
        );
        if (!is_array($rows)) {
            return [];
        }
        $items = [];
        $seen = [];
        foreach ($rows as $r) {
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

        return $items;
    }

    /**
     * @param list<array{id:int,nama:string,hp:string}> $existing
     * @return array{similar:bool,match:string,reason:string}|null
     */
    private function cekNamaMiripAi(string $namaBaru, array $existing): ?array
    {
        $baruNorm = $this->normalizeNamaBanding($namaBaru);
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
            if ($this->normalizeNamaBanding($lama) === $baruNorm) {
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
            /** @var AiChat $ai */
            $ai = $this->helper('AiChat');
            $raw = $ai->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], 120, 0.0, 14);
        } catch (\Throwable $e) {
            $this->model('Log')->write('[PelangganDaftar::cekNamaMiripAi] ' . $e->getMessage());

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

    private function normalizeNamaBanding(string $nama): string
    {
        $nama = strtoupper(trim($nama));
        $nama = preg_replace('/\s*#NEW#\s*$/u', '', $nama) ?? $nama;
        $nama = preg_replace('/[^A-Z0-9]+/u', ' ', $nama) ?? $nama;

        return trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama);
    }
}
