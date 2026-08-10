<?php

namespace App\Models;

use App\Core\DB;
use App\Helpers\Laundry\AntarTarif;

/**
 * Multi-turn MINTA_JEMPUT_ANTAR — dipakai oleh WAReplies via `use`.
 */
trait WARepliesKurirTrait
{
    private const KURIR_SESSION_TTL_MINUTES = 60;

    private function getKurirSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_kurir_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
                $this->clearKurirSession($waNumber);
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getKurirSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return null;
        }
    }

    private function clearKurirSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        try {
            DB::getInstance(0)->query('DELETE FROM wa_kurir_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function saveKurirSession(string $waNumber, array $data): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $existing = $this->getKurirSessionRaw($phone);
        $merge = function (string $key, $default = null) use ($data, $existing) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
            return $existing[$key] ?? $default;
        };

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::KURIR_SESSION_TTL_MINUTES * 60));
        $vals = [
            $phone,
            $merge('id_pelanggan'),
            $merge('id_cabang'),
            $merge('jenis'),
            $merge('layanan', 'sameday'),
            $merge('step', 'ask_jenis'),
            $merge('id_lokasi'),
            $merge('lokasi_nama'),
            $merge('lokasi_detail'),
            $merge('latt'),
            $merge('longt'),
            $merge('tarif'),
            $merge('request_text'),
            $merge('request_tanggal'),
            $merge('request_jam'),
            $merge('request_granted'),
            $merge('driver_alt_tanggal'),
            $merge('driver_alt_jam'),
            $merge('courier_company'),
            $merge('courier_type'),
            $merge('courier_name'),
            $merge('ongkir'),
            $merge('rates_json'),
            $merge('id_request'),
            $merge('summary'),
            $now,
            $expires,
        ];

        try {
            DB::getInstance(0)->query(
                'INSERT INTO wa_kurir_session
                  (phone, id_pelanggan, id_cabang, jenis, layanan, step, id_lokasi, lokasi_nama, lokasi_detail,
                   latt, longt, tarif, request_text, request_tanggal, request_jam, request_granted,
                   driver_alt_tanggal, driver_alt_jam, courier_company, courier_type, courier_name,
                   ongkir, rates_json, id_request, summary, updated_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   id_pelanggan=VALUES(id_pelanggan), id_cabang=VALUES(id_cabang), jenis=VALUES(jenis),
                   layanan=VALUES(layanan), step=VALUES(step), id_lokasi=VALUES(id_lokasi),
                   lokasi_nama=VALUES(lokasi_nama), lokasi_detail=VALUES(lokasi_detail),
                   latt=VALUES(latt), longt=VALUES(longt), tarif=VALUES(tarif),
                   request_text=VALUES(request_text), request_tanggal=VALUES(request_tanggal),
                   request_jam=VALUES(request_jam), request_granted=VALUES(request_granted),
                   driver_alt_tanggal=VALUES(driver_alt_tanggal), driver_alt_jam=VALUES(driver_alt_jam),
                   courier_company=VALUES(courier_company), courier_type=VALUES(courier_type),
                   courier_name=VALUES(courier_name), ongkir=VALUES(ongkir), rates_json=VALUES(rates_json),
                   id_request=VALUES(id_request), summary=VALUES(summary),
                   updated_at=VALUES(updated_at), expires_at=VALUES(expires_at)',
                $vals
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('saveKurirSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function getKurirSessionRaw(string $phone): ?array
    {
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_kurir_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if ($res && $res->num_rows() > 0) {
                return (array) $res->row();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    private function messageBreaksKurirSession(string $text, array $keywordConfig): bool
    {
        if (preg_match('/\b(bon|bill|bil{1,}|tagihan|nota|invoice|pricelist|price\s*list)\b/iu', $text)) {
            return true;
        }
        $breakout = ['TAGIHAN', 'NOTA', 'STATUS', 'HARGA', 'HARGA_PAKET', 'HARGA_PAKET_D', 'PEMBUKA', 'PENUTUP', 'ESTIMASI_SELESAI'];
        foreach ($breakout as $handler) {
            foreach ($keywordConfig[$handler]['patterns'] ?? [] as $pattern) {
                if (@preg_match($pattern, $text)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function handleMinta_Jemput_Antar($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->isOperatingHours()) {
            $this->handleJam_tutup($phoneIn, $waNumber, $textBody);
            return;
        }

        $msg = trim((string) $textBody);
        $session = $this->getKurirSession($waNumber);
        $idPelanggan = $session['id_pelanggan'] ?? null;
        if (!$idPelanggan) {
            $idPelanggan = $this->resolveIdPelangganForKurirLink($phoneIn, $waNumber);
        }
        if (!$idPelanggan) {
            $this->sendKurirUnregisteredFallback($waNumber);
            return;
        }
        $idPelanggan = (int) $idPelanggan;
        $idCabang = $this->resolveKurirIdCabang($idPelanggan, $session);

        if ($session === null) {
            $jenis = $this->detectKurirJenis($msg);
            $this->saveKurirSession($waNumber, [
                'id_pelanggan' => $idPelanggan,
                'id_cabang' => $idCabang,
                'jenis' => $jenis,
                'layanan' => 'sameday',
                'step' => $jenis ? 'lokasi_check' : 'ask_jenis',
                'summary' => '[pesan] ' . mb_substr($msg, 0, 200),
            ]);
            $session = $this->getKurirSession($waNumber) ?: [];
            if (!$jenis) {
                $sapaan = $this->getSapaanForGreeting($waNumber);
                $this->sendAutoreplyText(
                    $waNumber,
                    "Baik {$sapaan}, mau *jemput* laundry dari lokasi Anda, atau *antar* laundry ke lokasi Anda?"
                );
                return;
            }
        }

        $this->routeKurirStep($phoneIn, $waNumber, $msg, $session);
    }

    private function sendKurirUnregisteredFallback(string $waNumber): void
    {
        $mid = "Untuk balasan otomatis, silahkan ketik:\n"
            . "- *BON* untuk info nota\n"
            . "- *CEK* untuk info status\n"
            . "- *BILL* untuk info tagihan";
        if ($this->autoReplyProvider === 'B') {
            $text = "Maaf, mohon menunggu. Admin sedang melayani customer lain.\n\n{$mid}\n\n"
                . "Untuk informasi lainnya, kirimkan pesan ke *Madinah Laundry (CS)*\n💬 wa.me/6281170706611";
        } else {
            $text = "Maaf, mohon menunggu. CS sedang melayani customer lain.\n\n{$mid}\n\n"
                . "Untuk pengaduan, kirimkan pesan ke *Madinah Laundry (Admin)*\n💬 wa.me/628117686252";
        }
        $this->sendAutoreplyText($waNumber, $text);
    }

    private function resolveKurirIdCabang(int $idPelanggan, ?array $session): ?int
    {
        if (!empty($session['id_cabang'])) {
            return (int) $session['id_cabang'];
        }
        try {
            $row = DB::getInstance(1)->query(
                'SELECT id_cabang FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            )->row();
            if ($row && !empty($row->id_cabang)) {
                return (int) $row->id_cabang;
            }
            $sale = DB::getInstance(1)->query(
                'SELECT id_cabang FROM sale WHERE id_pelanggan = ? AND bin = 0 ORDER BY insertTime DESC LIMIT 1',
                [$idPelanggan]
            )->row();
            if ($sale && !empty($sale->id_cabang)) {
                return (int) $sale->id_cabang;
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    private function detectKurirJenis(string $msg): ?string
    {
        $t = mb_strtolower($msg);
        $hasJemput = (bool) preg_match('/\b(jemput|jmpt|dijemput|penjemputan)\b/u', $t);
        $hasAntar = (bool) preg_match('/\b(antar|diantar|pengantaran|kirim\s*(ke|ke\s+rumah)?)\b/u', $t);
        if ($hasJemput && !$hasAntar) {
            return 'jemput';
        }
        if ($hasAntar && !$hasJemput) {
            return 'antar';
        }
        return null;
    }

    private function routeKurirStep(string $phoneIn, string $waNumber, string $msg, array $session): void
    {
        $step = (string) ($session['step'] ?? 'ask_jenis');
        $sapaan = $this->getSapaanForGreeting($waNumber);

        if ($step === 'ask_jenis') {
            $jenis = $this->detectKurirJenis($msg);
            if (!$jenis && preg_match('/\bjemput\b/iu', $msg)) {
                $jenis = 'jemput';
            }
            if (!$jenis && preg_match('/\bantar\b/iu', $msg)) {
                $jenis = 'antar';
            }
            if (!$jenis) {
                $this->sendAutoreplyText($waNumber, "Mohon pilih ya {$sapaan}: *jemput* atau *antar*?");
                return;
            }
            $this->saveKurirSession($waNumber, ['jenis' => $jenis, 'step' => 'lokasi_check']);
            $session = $this->getKurirSession($waNumber) ?: $session;
            $session['jenis'] = $jenis;
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }

        if ($this->kurirLooksWantFast($msg) && in_array($step, ['confirm_lokasi', 'terms_setuju', 'lokasi_check', 'pick_lokasi'], true)) {
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

        switch ($step) {
            case 'lokasi_check':
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                break;
            case 'ask_shareloc':
                $this->kurirHandleShareloc($waNumber, $sapaan, $session, $msg);
                break;
            case 'ask_lokasi_nama':
                $this->kurirHandleLokasiNama($waNumber, $sapaan, $session, $msg);
                break;
            case 'ask_lokasi_detail':
                $this->kurirHandleLokasiDetail($waNumber, $sapaan, $session, $msg);
                break;
            case 'pick_lokasi':
                $this->kurirHandlePickLokasi($waNumber, $sapaan, $session, $msg);
                break;
            case 'confirm_lokasi':
                $this->kurirHandleConfirmLokasi($waNumber, $sapaan, $session, $msg);
                break;
            case 'terms_setuju':
                $this->kurirHandleTerms($waNumber, $sapaan, $session, $msg);
                break;
            case 'wait_driver_jam':
                $this->sendAutoreplyText(
                    $waNumber,
                    "Sebentar ya {$sapaan}, kami masih menunggu konfirmasi driver."
                );
                break;
            case 'wait_continue_alt':
                $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, $msg);
                break;
            case 'instant_confirm':
            case 'instant_pick':
                $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, $msg);
                break;
            default:
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                break;
        }
    }

    private function kurirJenisLabel(array $session): string
    {
        return (($session['jenis'] ?? '') === 'antar') ? 'antar' : 'jemput';
    }

    private function kurirJenisNoun(array $session): string
    {
        return (($session['jenis'] ?? '') === 'antar') ? 'pengantaran' : 'penjemputan';
    }

    private function kurirLooksAgree(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }
        if ($this->kurirLooksRefuse($msg) || $this->kurirLooksWantJam($msg) || $this->kurirLooksWantFast($msg)) {
            return false;
        }
        return (bool) preg_match(
            '/\b(ya|iya|iyo|yoi|ok|oke|baik|setuju|boleh|sip|siap|lanjut|gas|bener|benar|betul|yuk|yo)\b/u',
            $t
        );
    }

    private function kurirLooksRefuse(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(tidak|tdk|ga|gak|ngga|engga|bukan|batal|cancel|jangan|nanti\s*aja)\b/iu',
            $msg
        );
    }

    private function kurirLooksWantJam(string $msg): bool
    {
        if ($this->parseEstimasiRequestWaktu($msg) !== null) {
            return true;
        }
        return (bool) preg_match('/\b(jam\s*\d{1,2}|pukul\s*\d{1,2}|jam\s*(brp|berapa)|kapan\s*(dijemput|diantar|jemput|antar))\b/iu', $msg);
    }

    private function kurirLooksWantFast(string $msg): bool
    {
        return (bool) preg_match(
            '/\b(segera|sekarang|cepat|cepet|instant|instan|gojek|grab|kilat|buru|skrg|langsung\s*aja)\b/iu',
            $msg
        );
    }

    private function kurirLokasiCheck(string $waNumber, string $sapaan, array $session): void
    {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $list = $this->kurirListLokasi($idPelanggan);
        if (empty($list)) {
            $this->saveKurirSession($waNumber, ['step' => 'ask_shareloc']);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, kirimkan *shareloc* / pin lokasi WhatsApp atau link Google Maps ya, biar kami catat titik jemput/antar."
            );
            return;
        }

        $last = $this->kurirLastDeliveryRequest($idPelanggan);
        $preferId = $last ? (int) ($last['id_lokasi'] ?? 0) : 0;
        $prefer = null;
        foreach ($list as $lok) {
            if ((int) $lok['id_lokasi'] === $preferId) {
                $prefer = $lok;
                break;
            }
        }
        if ($prefer === null && count($list) === 1) {
            $prefer = $list[0];
        }

        if ($prefer !== null) {
            $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $prefer);
            return;
        }

        $lines = ["Baik {$sapaan}, pilih lokasi " . $this->kurirJenisLabel($session) . ":"];
        foreach ($list as $i => $lok) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$lok['nama']}* — {$lok['detail']}";
        }
        $lines[] = "Balas angka pilihan ya {$sapaan}.";
        $this->saveKurirSession($waNumber, [
            'step' => 'pick_lokasi',
            'rates_json' => json_encode(['lokasi_list' => $list], JSON_UNESCAPED_UNICODE),
        ]);
        $this->sendAutoreplyText($waNumber, implode("\n", $lines));
    }

    private function kurirListLokasi(int $idPelanggan): array
    {
        try {
            $rows = DB::getInstance(1)->query(
                'SELECT id_lokasi, nama, detail, latt, longt FROM pelanggan_lokasi WHERE id_pelanggan = ? ORDER BY id_lokasi ASC',
                [$idPelanggan]
            )->result_array();
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function kurirLastDeliveryRequest(int $idPelanggan): ?array
    {
        try {
            $row = DB::getInstance(1)->query(
                "SELECT * FROM delivery_request
                 WHERE id_pelanggan = ? AND delivery_status <> 'batal'
                 ORDER BY insertTime DESC LIMIT 1",
                [$idPelanggan]
            )->row();
            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function kurirPrepareConfirm(string $waNumber, string $sapaan, array $session, array $lok): void
    {
        $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
        $latt = (float) ($lok['latt'] ?? 0);
        $longt = (float) ($lok['longt'] ?? 0);
        $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
        $jenis = $this->kurirJenisLabel($session);
        $nama = (string) ($lok['nama'] ?? '');
        $detail = (string) ($lok['detail'] ?? '');
        $tarifRp = AntarTarif::formatRp((int) $calc['tarif']);

        $this->saveKurirSession($waNumber, [
            'step' => 'confirm_lokasi',
            'id_lokasi' => (int) $lok['id_lokasi'],
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
            'tarif' => (int) $calc['tarif'],
        ]);

        $this->sendAutoreplyText(
            $waNumber,
            "Konfirmasi {$jenis} ke *{$nama}* ({$detail}) ya {$sapaan}?\n"
            . "Estimasi ongkir sameday {$tarifRp} (jarak ~{$calc['km']} km).\n"
            . "Balas *ya* untuk lanjut, atau sebut lokasi lain."
        );
    }

    private function kurirCabangCoords(int $idCabang): array
    {
        $out = ['latt' => 0.0, 'long' => 0.0, 'nama' => ''];
        if ($idCabang <= 0) {
            return $out;
        }
        try {
            $row = DB::getInstance(1)->query(
                'SELECT latt, `long`, nama, id_group_fonnte FROM cabang WHERE id_cabang = ? LIMIT 1',
                [$idCabang]
            )->row();
            if ($row) {
                $out['latt'] = (float) ($row->latt ?? 0);
                $out['long'] = (float) ($row->long ?? 0);
                $out['nama'] = (string) ($row->nama ?? '');
                $out['id_group_fonnte'] = (string) ($row->id_group_fonnte ?? '');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return $out;
    }

    private function kurirHandleShareloc(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $coords = $this->kurirExtractCoords($msg);
        if ($coords === null) {
            $this->sendAutoreplyText(
                $waNumber,
                "Belum ketemu koordinatnya {$sapaan}. Kirim pin lokasi WhatsApp atau link Google Maps ya."
            );
            return;
        }
        $this->saveKurirSession($waNumber, [
            'latt' => $coords['lat'],
            'longt' => $coords['lng'],
            'step' => 'ask_lokasi_nama',
        ]);
        $this->sendAutoreplyText(
            $waNumber,
            "Lokasi diterima {$sapaan}. Ini *rumah / kos / kantor / penginapan*?"
        );
    }

    private function kurirExtractCoords(string $msg): ?array
    {
        if (preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $msg, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
        if (preg_match('/https?:\/\/[^\s]+/i', $msg)) {
            if (!class_exists('\\App\\Helpers\\CRM\\MapsServer')) {
                require_once __DIR__ . '/../Helpers/CRM/MapsServer.php';
            }
            $res = \App\Helpers\CRM\MapsServer::resolve($msg);
            if (is_array($res) && !empty($res['ok'])) {
                return ['lat' => (float) $res['lat'], 'lng' => (float) $res['lng']];
            }
        }
        return null;
    }

    private function kurirHandleLokasiNama(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $nama = null;
        $t = mb_strtolower($msg);
        foreach (['rumah', 'kos', 'kost', 'kantor', 'penginapan', 'hotel', 'apartemen', 'kontrakan'] as $k) {
            if (mb_strpos($t, $k) !== false) {
                $nama = ($k === 'kost') ? 'Kos' : ucfirst($k);
                if ($nama === 'Hotel' || $nama === 'Apartemen' || $nama === 'Kontrakan') {
                    $nama = 'Penginapan';
                }
                break;
            }
        }
        if ($nama === null) {
            $clean = trim(preg_replace('/\s+/', ' ', $msg));
            if (mb_strlen($clean) >= 2 && mb_strlen($clean) <= 50) {
                $nama = mb_substr($clean, 0, 50);
            }
        }
        if ($nama === null) {
            $this->sendAutoreplyText($waNumber, "Pilih ya {$sapaan}: *rumah*, *kos*, *kantor*, atau *penginapan*?");
            return;
        }
        $this->saveKurirSession($waNumber, ['lokasi_nama' => $nama, 'step' => 'ask_lokasi_detail']);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}. Tuliskan detailnya (nama jalan, no rumah/kamar, atau merek toko)."
        );
    }

    private function kurirHandleLokasiDetail(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $detail = trim(preg_replace("/[\r\n]+/", ' ', $msg));
        $detail = trim(preg_replace('/\s+/u', ' ', $detail));
        if (mb_strlen($detail) < 3) {
            $this->sendAutoreplyText($waNumber, "Detail terlalu singkat {$sapaan}. Contoh: Jl Melati no 12 kamar 3.");
            return;
        }
        if (mb_strlen($detail) > 255) {
            $detail = mb_substr($detail, 0, 255);
        }
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $latt = (float) ($session['latt'] ?? 0);
        $longt = (float) ($session['longt'] ?? 0);
        $nama = (string) ($session['lokasi_nama'] ?? 'Rumah');
        $idLokasi = $this->kurirInsertLokasi($idPelanggan, $nama, $detail, $latt, $longt);
        if ($idLokasi <= 0) {
            $this->sendAutoreplyText($waNumber, "Maaf {$sapaan}, gagal menyimpan lokasi. Coba kirim detail lagi.");
            return;
        }
        $lok = [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
        ];
        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok);
    }

    private function kurirInsertLokasi(int $idPelanggan, string $nama, string $detail, float $latt, float $longt): int
    {
        try {
            $db = DB::getInstance(1);
            $id = $db->insert('pelanggan_lokasi', [
                'id_pelanggan' => $idPelanggan,
                'nama' => $nama,
                'detail' => $detail,
                'latt' => round($latt, 7),
                'longt' => round($longt, 7),
                'insertTime' => date('Y-m-d H:i:s'),
            ]);
            return $id ? (int) $id : 0;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertLokasi: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return 0;
        }
    }

    private function kurirHandlePickLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $list = [];
        $raw = $session['rates_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            $list = $j['lokasi_list'] ?? [];
        }
        if (empty($list)) {
            $list = $this->kurirListLokasi((int) ($session['id_pelanggan'] ?? 0));
        }
        $idx = null;
        if (preg_match('/\b(\d{1,2})\b/', $msg, $m)) {
            $idx = (int) $m[1] - 1;
        }
        $picked = null;
        if ($idx !== null && isset($list[$idx])) {
            $picked = $list[$idx];
        } else {
            foreach ($list as $lok) {
                if (mb_stripos($msg, (string) $lok['nama']) !== false) {
                    $picked = $lok;
                    break;
                }
            }
        }
        if ($picked === null) {
            $this->sendAutoreplyText($waNumber, "Pilih nomor lokasi yang tersedia ya {$sapaan}.");
            return;
        }
        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $picked);
    }

    private function kurirHandleConfirmLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksWantJam($msg)) {
            $this->kurirSendTermsThenMaybeJam($waNumber, $sapaan, $session, $msg, true);
            return;
        }
        if ($this->kurirLooksAgree($msg)) {
            $this->kurirSendTerms($waNumber, $sapaan, $session);
            return;
        }
        if ($this->kurirLooksRefuse($msg)) {
            $this->saveKurirSession($waNumber, ['step' => 'lokasi_check', 'id_lokasi' => null]);
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }
        $this->sendAutoreplyText($waNumber, "Lokasinya sudah benar {$sapaan}? Balas *ya* untuk lanjut.");
    }

    private function kurirSendTerms(string $waNumber, string $sapaan, array $session): void
    {
        $jenis = $this->kurirJenisLabel($session);
        $noun = $this->kurirJenisNoun($session);
        $text = "Baik permintaan diterima, namun kami informasikan bahwa jam kerja driver pukul 08.00 - 17.00, "
            . "jam {$noun} belum dapat dipastikan, tergantung pada posisi dan rute driver laundry.";
        $hour = (int) date('G');
        if ($hour >= 12) {
            $text .= " Pesanan {$jenis} melewati jam 12 paling lama {$noun} besok ya {$sapaan}.";
        }
        $text .= " Pastikan selalu ada orang (satpam/saudara/teman) di rumah, apakah setuju?";
        $this->saveKurirSession($waNumber, ['step' => 'terms_setuju']);
        $this->sendAutoreplyText($waNumber, $text);
    }

    private function kurirSendTermsThenMaybeJam(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        bool $forceJam
    ): void {
        $this->kurirSendTerms($waNumber, $sapaan, $session);
        if ($forceJam) {
            // stay on terms; next message or same — handle jam after agree path via terms
            $this->saveKurirSession($waNumber, [
                'step' => 'terms_setuju',
                'request_text' => $msg,
            ]);
        }
    }

    private function kurirHandleTerms(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksWantFast($msg)) {
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

        $wantJam = $this->kurirLooksWantJam($msg);
        $waktu = $this->parseEstimasiRequestWaktu($msg);
        if ($wantJam && $waktu === null && preg_match('/\bjam\s*(brp|berapa)|kapan\b/iu', $msg)) {
            $this->sendAutoreplyText(
                $waNumber,
                "Tunggu ya {$sapaan}, kami tanyakan dulu ke driver."
            );
            // still need a concrete jam — ask
            $this->sendAutoreplyText(
                $waNumber,
                "Jam berapa {$sapaan} ingin " . $this->kurirJenisLabel($session) . "? (contoh: jam 14)"
            );
            return;
        }

        if ($wantJam && $waktu !== null) {
            $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, $msg, $waktu);
            return;
        }

        // Pending jam text from earlier confirm
        if (!empty($session['request_text']) && empty($session['request_jam'])) {
            $prevWaktu = $this->parseEstimasiRequestWaktu((string) $session['request_text']);
            if ($prevWaktu && $this->kurirLooksAgree($msg)) {
                $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, (string) $session['request_text'], $prevWaktu);
                return;
            }
        }

        if ($this->kurirLooksAgree($msg)) {
            $ok = $this->kurirInsertSamedayRequest($waNumber, $session, null);
            if ($ok) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Terima kasih {$sapaan}, permintaan " . $this->kurirJenisLabel($session)
                    . " sameday sudah kami terima. Driver akan memproses ya 😊"
                );
                $this->clearKurirSession($waNumber);
            }
            return;
        }

        if ($this->kurirLooksRefuse($msg)) {
            $id = (int) ($session['id_pelanggan'] ?? 0);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik, maaf ya {$sapaan}, untuk pemesanan antar/jemput bisa juga via link berikut:\n"
                . "https://ml.nalju.com/J/kurir/{$id}"
            );
            $this->clearKurirSession($waNumber);
            return;
        }

        $this->sendAutoreplyText($waNumber, "Apakah setuju dengan ketentuan di atas {$sapaan}?");
    }

    private function kurirEscalateJamRequest(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        array $waktu
    ): void {
        $tgl = $waktu['tanggal'] ?? date('Y-m-d');
        $jam = $waktu['jam'];
        $this->saveKurirSession($waNumber, [
            'step' => 'wait_driver_jam',
            'request_text' => $msg,
            'request_tanggal' => $tgl,
            'request_jam' => $jam,
            'request_granted' => null,
            'driver_alt_tanggal' => null,
            'driver_alt_jam' => null,
        ]);
        $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami tanyakan driver dulu ya {$sapaan}.");
        $this->kurirForwardJamToGroups($waNumber, $session, $msg, $tgl, (float) $jam);
    }

    private function kurirForwardJamToGroups(
        string $waNumber,
        array $session,
        string $msg,
        string $tgl,
        float $jam
    ): void {
        $nama = trim($this->getContactNameForGreeting($waNumber)) ?: 'Pelanggan';
        $jenis = $this->kurirJenisLabel($session);
        $jamLabel = $this->formatKurirJamLabel($jam);
        $groupText = "{$nama} minta {$jenis} jam {$jamLabel} ({$tgl}). \"{$msg}\". (AI Agent)";

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte')) {
                require_once __DIR__ . '/../Config/Fonnte.php';
            }
            $fonnte = new \App\Helpers\CRM\FonnteService();
            $driverG = \App\Config\Fonnte::getDriverGroupId();
            $fonnte->sendToGroup($driverG, $groupText);

            $cabangG = $this->resolveEstimasiFonnteGroupId(
                isset($session['id_cabang']) ? (int) $session['id_cabang'] : null
            );
            if ($cabangG !== '' && $cabangG !== $driverG) {
                $fonnte->sendToGroup($cabangG, $groupText);
            }
            $this->logAutoreplyTrace($waNumber, 'MINTA_JEMPUT_ANTAR', 'forward_jam_groups ok');
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirForwardJamToGroups: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function formatKurirJamLabel(float $jam): string
    {
        $h = (int) floor($jam);
        $frac = $jam - $h;
        $m = (int) round($frac * 100);
        if ($m > 59) {
            $m = (int) round(($frac * 60));
        }
        return sprintf('%02d:%02d', $h, $m);
    }

    private function kurirHandleContinueAlt(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksAgree($msg)) {
            $alt = [
                'tanggal' => $session['driver_alt_tanggal'] ?? date('Y-m-d'),
                'jam' => $session['driver_alt_jam'] ?? null,
            ];
            $ok = $this->kurirInsertSamedayRequest($waNumber, $session, $alt);
            if ($ok) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Baik {$sapaan}, permintaan " . $this->kurirJenisLabel($session)
                    . " kami lanjutkan sesuai jam alternatif driver. Terima kasih 😊"
                );
                $this->clearKurirSession($waNumber);
            }
            return;
        }
        if ($this->kurirLooksRefuse($msg)) {
            $id = (int) ($session['id_pelanggan'] ?? 0);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik, maaf ya {$sapaan}, untuk pemesanan antar/jemput bisa juga via link berikut:\n"
                . "https://ml.nalju.com/J/kurir/{$id}"
            );
            $this->clearKurirSession($waNumber);
            return;
        }
        $this->sendAutoreplyText($waNumber, "Apakah permintaan tetap dilanjutkan {$sapaan}?");
    }

    /**
     * @param array|null $jamMeta ['tanggal'=>,'jam'=>] for catatan
     */
    private function kurirInsertSamedayRequest(string $waNumber, array $session, ?array $jamMeta): bool
    {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idCabang = (int) ($session['id_cabang'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $jenis = $this->kurirJenisLabel($session);
        if ($idPelanggan <= 0 || $idCabang <= 0 || $idLokasi <= 0) {
            $this->sendAutoreplyText($waNumber, 'Maaf, data lokasi/cabang belum lengkap. Silakan ulangi permintaan.');
            return false;
        }

        $phoneTail = $this->kurirPhoneTail($idPelanggan, $waNumber);
        if (strlen($phoneTail) < 8) {
            $this->sendAutoreplyText($waNumber, 'Maaf, nomor pelanggan belum lengkap di sistem.');
            return false;
        }

        $eligibleIds = [];
        if ($jenis === 'antar') {
            $eligibleIds = $this->kurirEligibleSaleIds($idPelanggan, false);
            if (empty($eligibleIds)) {
                $this->sendAutoreplyText(
                    $waNumber,
                    'Maaf, belum ada item laundry yang bisa diantar saat ini. '
                    . "Bisa cek juga via https://ml.nalju.com/J/kurir/{$idPelanggan}"
                );
                return false;
            }
        }

        $catatan = '';
        if ($jamMeta && !empty($jamMeta['jam'])) {
            $tgl = $jamMeta['tanggal'] ?? date('Y-m-d');
            $catatan = 'Minta jam ' . $this->formatKurirJamLabel((float) $jamMeta['jam']) . " tanggal {$tgl}";
        } elseif (!empty($session['request_jam']) && (int) ($session['request_granted'] ?? 0) === 1) {
            $tgl = $session['request_tanggal'] ?? date('Y-m-d');
            $catatan = 'Minta jam ' . $this->formatKurirJamLabel((float) $session['request_jam']) . " tanggal {$tgl}";
        }

        $tarif = (int) ($session['tarif'] ?? 0);
        if ($tarif <= 0) {
            $cab = $this->kurirCabangCoords($idCabang);
            $calc = AntarTarif::tarifFromCoords(
                $cab['latt'],
                $cab['long'],
                (float) ($session['latt'] ?? 0),
                (float) ($session['longt'] ?? 0)
            );
            $tarif = (int) $calc['tarif'];
        }

        $now = date('Y-m-d H:i:s');
        $db = DB::getInstance(1);
        try {
            $insData = [
                'sumber' => 'customer',
                'jenis' => $jenis,
                'layanan' => 'sameday',
                'delivery_status' => 'berjalan',
                'id_pelanggan' => $idPelanggan,
                'phone_tail' => $phoneTail,
                'id_cabang' => $idCabang,
                'id_lokasi' => $idLokasi,
                'lokasi_nama' => (string) ($session['lokasi_nama'] ?? ''),
                'lokasi_detail' => (string) ($session['lokasi_detail'] ?? ''),
                'lokasi_latt' => (float) ($session['latt'] ?? 0),
                'lokasi_longt' => (float) ($session['longt'] ?? 0),
                'insertTime' => $now,
                'tarif_surcas' => $tarif,
            ];
            if ($catatan !== '') {
                $insData['catatan_kurir'] = mb_substr($catatan, 0, 150);
            }
            $idRequest = $db->insert('delivery_request', $insData);
            $idRequest = $idRequest ? (int) $idRequest : 0;
            if ($idRequest <= 0) {
                throw new \RuntimeException('insert_id empty');
            }
            if ($jenis === 'antar') {
                foreach ($eligibleIds as $idSale) {
                    $sale = $db->query(
                        'SELECT no_ref FROM sale WHERE id_penjualan = ? LIMIT 1',
                        [$idSale]
                    )->row();
                    $db->insert('delivery_request_item', [
                        'id_request' => $idRequest,
                        'id_penjualan' => $idSale,
                        'no_ref' => (string) ($sale->no_ref ?? ''),
                    ]);
                }
            }
            $this->saveKurirSession($waNumber, ['id_request' => $idRequest, 'step' => 'done']);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertSamedayRequest: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->sendAutoreplyText($waNumber, 'Maaf, gagal membuat permintaan. Coba lagi atau pakai link portal.');
            return false;
        }
    }

    private function kurirPhoneTail(int $idPelanggan, string $waNumber): string
    {
        try {
            $row = DB::getInstance(1)->query(
                'SELECT nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            )->row();
            $digits = preg_replace('/\D+/', '', (string) ($row->nomor_pelanggan ?? ''));
            if (strlen($digits) >= 8) {
                return substr($digits, -9);
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $digits = preg_replace('/\D+/', '', $waNumber);
        return strlen($digits) >= 8 ? substr($digits, -9) : $digits;
    }

    private function kurirEligibleSaleIds(int $idPelanggan, bool $requireSelesai): array
    {
        $selesaiClause = '';
        if ($requireSelesai) {
            $selesaiClause = "
            AND EXISTS (
              SELECT 1 FROM notif n
              WHERE n.tipe = 2
                AND n.no_ref = CAST(s.id_penjualan AS CHAR)
            )";
        }
        try {
            $rows = DB::getInstance(1)->query(
                "SELECT s.id_penjualan
                 FROM sale s
                 WHERE s.bin = 0 AND s.id_pelanggan = ?
                   AND (
                     s.tuntas = 0
                     OR (s.tuntas = 1 AND s.tuntasTime IS NOT NULL AND s.tuntasTime >= (NOW() - INTERVAL 2 DAY))
                   )
                   AND NOT EXISTS (
                     SELECT 1 FROM delivery_riwayat dr
                     WHERE dr.id_penjualan = s.id_penjualan AND dr.jenis = 'antar'
                   )
                   AND NOT EXISTS (
                     SELECT 1 FROM delivery_request_item dri
                     INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
                     WHERE dri.id_penjualan = s.id_penjualan
                       AND drq.jenis = 'antar'
                       AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')
                   )
                   {$selesaiClause}
                 ORDER BY s.insertTime DESC
                 LIMIT 50",
                [$idPelanggan]
            )->result_array();
            return array_map(static function ($r) {
                return (int) $r['id_penjualan'];
            }, is_array($rows) ? $rows : []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function kurirStartInstant(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $jenis = $this->kurirJenisLabel($session);
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        if ($jenis === 'antar') {
            $ids = $this->kurirEligibleSaleIds($idPelanggan, true);
            if (empty($ids)) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Mohon maaf {$sapaan}, laundry belum selesai, order kurir instan belum dapat dilakukan."
                );
                return;
            }
        }
        if (empty($session['id_lokasi']) || empty($session['latt'])) {
            $this->sendAutoreplyText($waNumber, "Sebentar {$sapaan}, pilih/konfirmasi lokasi dulu sebelum Instant.");
            $this->saveKurirSession($waNumber, ['step' => 'lokasi_check', 'layanan' => 'instant']);
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }

        $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
        try {
            if (!class_exists('\\App\\Models\\BiteshipClient')) {
                require_once __DIR__ . '/BiteshipClient.php';
            }
            if (!class_exists('\\App\\Helpers\\Laundry\\InstantKurir')) {
                require_once __DIR__ . '/../Helpers/Laundry/InstantKurir.php';
            }
            $client = new \App\Models\BiteshipClient();
            $res = $client->getRates([
                'origin_latitude' => $cab['latt'],
                'origin_longitude' => $cab['long'],
                'destination_latitude' => (float) $session['latt'],
                'destination_longitude' => (float) $session['longt'],
                'couriers' => 'grab,gojek,paxel,lalamove,borzo,maxim,deliveree',
                'items' => [[
                    'name' => 'Laundry',
                    'description' => 'Paket laundry',
                    'value' => 50000,
                    'quantity' => 1,
                    'weight' => 1000,
                ]],
            ]);
            $rates = \App\Helpers\Laundry\InstantKurir::filterInstantPricing($res['pricing'] ?? []);
        } catch (\Throwable $e) {
            $rates = [];
            if (class_exists('\Log')) {
                \Log::write('kurirStartInstant rates: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        if (empty($rates)) {
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, tarif Instant sementara tidak tersedia. Lanjut sameday saja atau coba lagi nanti."
            );
            return;
        }

        $this->saveKurirSession($waNumber, [
            'layanan' => 'instant',
            'rates_json' => json_encode(['rates' => $rates], JSON_UNESCAPED_UNICODE),
            'step' => count($rates) === 1 ? 'instant_confirm' : 'instant_pick',
            'courier_company' => $rates[0]['courier_company'] ?? null,
            'courier_type' => $rates[0]['courier_type'] ?? null,
            'courier_name' => $rates[0]['courier_name'] ?? null,
            'ongkir' => isset($rates[0]['price']) ? (int) $rates[0]['price'] : null,
        ]);

        if (count($rates) === 1) {
            $r = $rates[0];
            $rp = AntarTarif::formatRp((int) ($r['price'] ?? 0));
            $name = $r['courier_name'] ?? ($r['courier_company'] . ' ' . $r['courier_type']);
            $this->sendAutoreplyText(
                $waNumber,
                "Ada opsi Instant *{$name}* {$rp}. Lanjut pesan {$jenis} Instant {$sapaan}?"
            );
            return;
        }

        $lines = ["Pilih kurir Instant ya {$sapaan}:"];
        foreach ($rates as $i => $r) {
            $n = $i + 1;
            $rp = AntarTarif::formatRp((int) ($r['price'] ?? 0));
            $name = $r['courier_name'] ?? ($r['courier_company'] . ' ' . $r['courier_type']);
            $lines[] = "{$n}. {$name} — {$rp}";
        }
        $this->sendAutoreplyText($waNumber, implode("\n", $lines));
    }

    private function kurirHandleInstantChoice(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $j = json_decode((string) ($session['rates_json'] ?? ''), true);
        $rates = $j['rates'] ?? [];
        if (empty($rates)) {
            $this->sendAutoreplyText($waNumber, "Data tarif hilang {$sapaan}, ulangi permintaan Instant.");
            return;
        }

        $step = $session['step'] ?? '';
        if ($step === 'instant_pick') {
            $idx = null;
            if (preg_match('/\b(\d{1,2})\b/', $msg, $m)) {
                $idx = (int) $m[1] - 1;
            }
            if ($idx === null || !isset($rates[$idx])) {
                $this->sendAutoreplyText($waNumber, "Pilih nomor kurir ya {$sapaan}.");
                return;
            }
            $r = $rates[$idx];
            $this->saveKurirSession($waNumber, [
                'courier_company' => $r['courier_company'] ?? null,
                'courier_type' => $r['courier_type'] ?? null,
                'courier_name' => $r['courier_name'] ?? null,
                'ongkir' => (int) ($r['price'] ?? 0),
                'step' => 'instant_confirm',
            ]);
            $session = array_merge($session, [
                'courier_company' => $r['courier_company'] ?? null,
                'courier_type' => $r['courier_type'] ?? null,
                'courier_name' => $r['courier_name'] ?? null,
                'ongkir' => (int) ($r['price'] ?? 0),
            ]);
            $rp = AntarTarif::formatRp((int) ($r['price'] ?? 0));
            $name = $r['courier_name'] ?? 'kurir';
            $this->sendAutoreplyText($waNumber, "Lanjut pesan *{$name}* {$rp} {$sapaan}?");
            return;
        }

        if (!$this->kurirLooksAgree($msg)) {
            if ($this->kurirLooksRefuse($msg)) {
                $this->saveKurirSession($waNumber, ['layanan' => 'sameday', 'step' => 'terms_setuju']);
                $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kita kembali ke sameday. Apakah setuju ketentuan driver?");
                return;
            }
            $this->sendAutoreplyText($waNumber, "Lanjut pesan Instant {$sapaan}? Balas ya/tidak.");
            return;
        }

        $ok = $this->kurirInsertInstantRequest($waNumber, $session);
        if ($ok) {
            $id = (int) ($session['id_pelanggan'] ?? 0);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, order Instant sudah dibuat. Silakan lakukan pembayaran lewat link berikut:\n"
                . "https://ml.nalju.com/J/kurir/{$id}\n\n"
                . "Setelah pembayaran sukses, kurir terdekat akan ditugaskan ya 😊"
            );
            $this->clearKurirSession($waNumber);
        }
    }

    private function kurirInsertInstantRequest(string $waNumber, array $session): bool
    {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idCabang = (int) ($session['id_cabang'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $jenis = $this->kurirJenisLabel($session);
        $ongkir = (int) ($session['ongkir'] ?? 0);
        $phoneTail = $this->kurirPhoneTail($idPelanggan, $waNumber);
        if ($idPelanggan <= 0 || $idCabang <= 0 || $idLokasi <= 0 || $ongkir <= 0) {
            $this->sendAutoreplyText($waNumber, 'Maaf, data Instant belum lengkap.');
            return false;
        }

        $itemIds = [];
        if ($jenis === 'antar') {
            $itemIds = $this->kurirEligibleSaleIds($idPelanggan, true);
            if (empty($itemIds)) {
                $this->sendAutoreplyText($waNumber, 'Maaf, laundry belum selesai untuk Instant antar.');
                return false;
            }
        }

        $now = date('Y-m-d H:i:s');
        $db = DB::getInstance(1);
        try {
            $idRequest = $db->insert('delivery_request', [
                'sumber' => 'customer',
                'jenis' => $jenis,
                'layanan' => 'instant',
                'delivery_status' => 'menunggu_pembayaran',
                'id_pelanggan' => $idPelanggan,
                'phone_tail' => $phoneTail,
                'id_cabang' => $idCabang,
                'id_lokasi' => $idLokasi,
                'lokasi_nama' => (string) ($session['lokasi_nama'] ?? ''),
                'lokasi_detail' => (string) ($session['lokasi_detail'] ?? ''),
                'lokasi_latt' => (float) ($session['latt'] ?? 0),
                'lokasi_longt' => (float) ($session['longt'] ?? 0),
                'insertTime' => $now,
                'courier_company' => (string) ($session['courier_company'] ?? ''),
                'courier_type' => (string) ($session['courier_type'] ?? ''),
                'courier_name' => (string) ($session['courier_name'] ?? ''),
            ]);
            $idRequest = $idRequest ? (int) $idRequest : 0;
            if ($idRequest <= 0) {
                throw new \RuntimeException('no id_request');
            }
            foreach ($itemIds as $idSale) {
                $sale = $db->query('SELECT no_ref FROM sale WHERE id_penjualan = ? LIMIT 1', [$idSale])->row();
                $db->insert('delivery_request_item', [
                    'id_request' => $idRequest,
                    'id_penjualan' => $idSale,
                    'no_ref' => (string) ($sale->no_ref ?? ''),
                ]);
            }

            $idKas = $this->kurirNewIdKas($db);
            $refFinance = 'I' . $idKas;
            $kasOk = $db->insert('kas', [
                'id_kas' => $idKas,
                'id_cabang' => $idCabang,
                'jenis_transaksi' => 10,
                'jumlah' => $ongkir,
                'id_user' => 0,
                'id_client' => $idPelanggan,
                'ref_transaksi' => $idRequest,
                'ref_finance' => $refFinance,
                'jenis_mutasi' => 1,
                'metode_mutasi' => 2,
                'note' => 'QRIS',
                'status_mutasi' => 2,
                'insertTime' => $now,
            ]);
            if ($kasOk === false) {
                throw new \RuntimeException('kas insert failed');
            }
            $db->update(
                'delivery_request',
                ['payment_ref_finance' => $refFinance],
                ['id_request' => $idRequest]
            );
            return true;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertInstantRequest: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->sendAutoreplyText($waNumber, 'Maaf, gagal membuat order Instant. Coba via portal.');
            return false;
        }
    }

    private function kurirNewIdKas($db): int
    {
        try {
            $row = $db->query('SELECT COALESCE(MAX(id_kas),0)+1 AS n FROM kas')->row();
            return (int) ($row->n ?? 1);
        } catch (\Throwable $e) {
            return (int) (time() % 100000000);
        }
    }
}
