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
        // Tanya estimasi siap hari ini/besok → jangan lanjut session kurir (hindari minta shareloc)
        if ($this->messageLooksLikeEstimasiSelesai($text)
            || $this->parseEstimasiRequestedRelativeDay($text) !== null) {
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

    /**
     * @return bool true = pesan sudah ditangani kurir; false = unrelated → lanjut routing intent lain
     */
    private function handleMinta_Jemput_Antar($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->isOperatingHours()) {
            $this->handleJam_tutup($phoneIn, $waNumber, $textBody);
            return true;
        }

        $msg = trim((string) $textBody);
        $session = $this->getKurirSession($waNumber);
        $idPelanggan = $session['id_pelanggan'] ?? null;
        if (!$idPelanggan) {
            $idPelanggan = $this->resolveIdPelangganForKurirLink($phoneIn, $waNumber);
        }
        if (!$idPelanggan) {
            $this->sendKurirUnregisteredFallback($waNumber);
            return true;
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
                return true;
            }
        }

        return $this->routeKurirStep($phoneIn, $waNumber, $msg, $session);
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

    /**
     * @return bool true = ditangani; false = AI unrelated → biarkan process() lanjut intent lain
     */
    private function routeKurirStep(string $phoneIn, string $waNumber, string $msg, array $session): bool
    {
        $step = (string) ($session['step'] ?? 'ask_jenis');
        $sapaan = $this->getSapaanForGreeting($waNumber);

        // Hard: batal selalu prioritas
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelAndReply($waNumber, $sapaan, $session);
            return true;
        }

        // Hard: pin/Maps coords (terutama ask_shareloc, atau customer kirim pin saat confirm/pick)
        $coords = $this->kurirExtractCoords($msg);
        if ($coords !== null && in_array($step, ['ask_shareloc', 'confirm_lokasi', 'pick_lokasi', 'lokasi_check'], true)) {
            $this->kurirHandleShareloc($waNumber, $sapaan, $session, $msg);
            $this->kurirAppendSummary($waNumber, $session, 'shareloc_coords');
            return true;
        }

        // Hard: pilih angka di pick_lokasi / instant_pick
        if ($step === 'pick_lokasi' && preg_match('/^\s*(\d{1,2})\s*$/u', trim($msg))) {
            $this->kurirHandlePickLokasi($waNumber, $sapaan, $session, $msg);
            return true;
        }
        if ($step === 'instant_pick' && preg_match('/^\s*(\d{1,2})\s*$/u', trim($msg))) {
            $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // ask_jenis: tetap deteksi jemput/antar (bisa AI jika ambigu)
        if ($step === 'ask_jenis') {
            $jenis = $this->detectKurirJenis($msg);
            if (!$jenis && preg_match('/\bjemput\b/iu', $msg)) {
                $jenis = 'jemput';
            }
            if (!$jenis && preg_match('/\bantar\b/iu', $msg)) {
                $jenis = 'antar';
            }
            if ($jenis) {
                $this->saveKurirSession($waNumber, ['jenis' => $jenis, 'step' => 'lokasi_check']);
                $session = $this->getKurirSession($waNumber) ?: $session;
                $session['jenis'] = $jenis;
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                $this->kurirAppendSummary($waNumber, $session, 'jenis=' . $jenis);
                return true;
            }
            // Ambigu → AI
        }

        // lokasi_check tanpa input bermakna: jalankan check (biasanya dipanggil internal)
        if ($step === 'lokasi_check' && trim($msg) === '') {
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return true;
        }

        // AI decide (summary + konteks)
        $decision = $this->kurirAiDecide($waNumber, $session, $msg);
        if ($decision !== null) {
            if (($decision['action'] ?? '') === 'unrelated') {
                // Lepas session WA kurir supaya intent lain (estimasi/bill/harga) bisa jalan
                $this->clearKurirSession($waNumber);
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'unrelated→continue_routing');
                return false;
            }
            $this->kurirDispatchAiAction($waNumber, $sapaan, $session, $msg, $decision);
            return true;
        }

        // Fallback regex/route lama
        $this->kurirRouteStepRegexFallback($phoneIn, $waNumber, $msg, $session, $step, $sapaan);
        return true;
    }

    private function kurirRouteStepRegexFallback(
        string $phoneIn,
        string $waNumber,
        string $msg,
        array $session,
        string $step,
        string $sapaan
    ): void {
        if ($step === 'ask_jenis') {
            $this->sendAutoreplyText($waNumber, "Mohon pilih ya {$sapaan}: *jemput* atau *antar*?");
            return;
        }

        if ($this->kurirLooksWantFast($msg) && in_array($step, ['confirm_lokasi', 'request_aktif', 'lokasi_check', 'pick_lokasi'], true)) {
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
            case 'request_aktif':
                $this->kurirHandleRequestAktif($waNumber, $sapaan, $session, $msg);
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
        if ($this->kurirLooksRefuse($msg)
            || $this->kurirLooksWantOtherLokasi($msg)
            || $this->kurirLooksWantJam($msg)
            || $this->kurirLooksWantFast($msg)
        ) {
            return false;
        }
        return (bool) preg_match(
            '/\b(ya|iya|iyo|yoi|ok|oke|baik|setuju|boleh|sip|siap|lanjut|gas|bener|benar|betul|yuk|yo)\b/u',
            $t
        );
    }

    private function kurirLooksRefuse(string $msg): bool
    {
        if ($this->kurirLooksCancel($msg)) {
            return true;
        }
        return (bool) preg_match(
            '/\b(tidak|tdk|ga|gak|ngga|nggak|engga|enggak|bukan|jangan|nanti\s*aja|no)\b/iu',
            $msg
        );
    }

    /** Customer menolak lokasi yang ditawarkan / minta alamat lain. */
    private function kurirLooksWantOtherLokasi(string $msg): bool
    {
        return (bool) preg_match(
            '/\b('
            . 'beda(\s*lokasi|\s*tempat|\s*alamat)?'
            . '|lokasi\s*(lain|beda|baru|salah)'
            . '|alamat\s*(lain|beda|baru|salah)'
            . '|tempat\s*(lain|beda|baru|salah)'
            . '|bukan\s*(itu|yang\s*itu|disitu|di\s*situ|sini|sana)?'
            . '|salah\s*(lokasi|tempat|alamat)?'
            . '|ganti\s*(lokasi|alamat|tempat)'
            . '|pindah\s*(lokasi|alamat|tempat)?'
            . '|bukan\s*rumah'
            . '|lokasi\s*lain'
            . ')\b/iu',
            $msg
        );
    }

    /**
     * Customer minta batalkan request antar/jemput (bukan sekadar "tidak" di konfirmasi lokasi).
     * Contoh: batal, batalin, gak jadi, cancel, gak usah, udahan, …
     */
    private function kurirLooksCancel(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }

        // Kata kunci tunggal / bentuk infleksi
        if (preg_match(
            '/\b('
            . 'batal(in|kan|kan\s*aja|in\s*aja)?'
            . '|di\s*batal(in|kan)?'
            . '|cancel(l?ed|lation|ing)?'
            . '|urung(kan)?'
            . '|abort'
            . '|udahan'
            . '|cabut'
            . ')\b/iu',
            $t
        )) {
            return true;
        }

        // Frasa “tidak/gak … jadi/usah/perlu/lanjut”
        if (preg_match(
            '/\b('
            . '(gak|ga|ngga|nggak|engga|enggak|ndak|ndk|tidak|tdk|tak)\s*'
            . '(jadi|usah|perlu|jadi\s*deh|jadi\s*aja|jadi\s*dulu)'
            . '|jangan\s*(jadi|lanjut(kan)?|dulu|diantar|dijemput)'
            . '|gak\s*usah\s*(antar|jemput|dulu)?'
            . '|ga\s*usah\s*(antar|jemput|dulu)?'
            . '|sudah(i)?\s*aja'
            . '|stop\s*(aja|dulu)?'
            . '|mundur\s*aja'
            . '|skip\s*(aja|dulu)?'
            . ')\b/iu',
            $t
        )) {
            return true;
        }

        return false;
    }

    private function kurirLooksWantJam(string $msg): bool
    {
        if ($this->kurirLooksCancel($msg)) {
            return false;
        }
        if ($this->parseEstimasiRequestWaktu($msg) !== null) {
            return true;
        }
        return (bool) preg_match(
            '/\b(jam\s*\d{1,2}|pukul\s*\d{1,2}|jam\s*(brp|berapa|bisa|bs|gak|ga|nggak|ngga)|'
            . 'kapan\s*(dijemput|diantar|jemput|antar)|siang\s*(ini)?|sore\s*(ini)?|'
            . 'mau\s*(jam|pukul)|minta\s*(jam|pukul))\b/iu',
            $msg
        );
    }

    private function kurirLooksWantFast(string $msg): bool
    {
        if ($this->kurirLooksCancel($msg)) {
            return false;
        }
        // "sekarang/skrg" saja terlalu ambigu (sering ikut kalimat batal/pulang)
        return (bool) preg_match(
            '/\b(segera|cepat|cepet|instant|instan|gojek|grab|kilat|buru(-?buru)?|langsung\s*aja)\b/iu',
            $msg
        ) || (bool) preg_match(
            '/\b(sekarang|skrg)\b.*\b(antar|jemput|kurir|kirim|ambil)\b|\b(antar|jemput|kurir|kirim|ambil)\b.*\b(sekarang|skrg)\b/iu',
            $msg
        );
    }

    private function kurirCancelAndReply(string $waNumber, string $sapaan, array $session): void
    {
        $this->kurirCancelDeliveryRequest($session);
        $id = (int) ($session['id_pelanggan'] ?? 0);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik, maaf ya {$sapaan}, permintaan dibatalkan. Untuk pemesanan antar/jemput bisa juga via link berikut:\n"
            . "https://ml.nalju.com/J/kurir/{$id}"
        );
        $this->clearKurirSession($waNumber);
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
        // Langsung dari pasangan lat,lng (YCloud caption / Fonnte location / teks)
        if (preg_match('/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/', $msg, $m)
            || preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $msg, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
        // URL sudah jelas: maps?q=lat,lng atau @lat,lng — parse lokal, tanpa maps_server
        if (preg_match('/[?&]q=(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/i', $msg, $m)
            || preg_match('/@(-?\d+\.?\d+),(-?\d+\.?\d+)/', $msg, $m)
            || preg_match('/maps\/place\/(-?\d+\.?\d+),(-?\d+\.?\d+)/i', $msg, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
        // Short link / URL Maps tanpa koordinat eksplisit → maps_server
        if (preg_match('/https?:\/\/(?:maps\.app\.goo\.gl|goo\.gl\/maps|[^\s]*google\.[^\s]*\/maps)[^\s]*/i', $msg)) {
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
        if ($this->kurirLooksWantOtherLokasi($msg)
            || preg_match('/\b(baru|lain|share\s*loc|shareloc|maps|pin)\b/iu', $msg)
        ) {
            $this->kurirStartShareloc($waNumber, $sapaan, $session, $msg);
            return;
        }

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
            $this->sendAutoreplyText(
                $waNumber,
                "Pilih nomor lokasi yang tersedia ya {$sapaan}, atau balas *baru* untuk kirim shareloc."
            );
            return;
        }
        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $picked);
    }

    private function kurirHandleConfirmLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksWantJam($msg)) {
            $waktu = $this->parseEstimasiRequestWaktu($msg);
            if ($waktu === null) {
                if (!$this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session)) {
                    return;
                }
                $this->sendAutoreplyText(
                    $waNumber,
                    "Jam berapa {$sapaan} ingin " . $this->kurirJenisLabel($session) . "? (contoh: jam 14)"
                );
                return;
            }
            if (!$this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session)) {
                return;
            }
            $session = $this->getKurirSession($waNumber) ?: $session;
            $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, $msg, $waktu);
            return;
        }
        if ($this->kurirLooksAgree($msg)) {
            $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
            return;
        }
        // Tolak / beda lokasi → jangan ulang prefer lokasi yang sama
        if ($this->kurirLooksRefuse($msg) || $this->kurirLooksWantOtherLokasi($msg)) {
            $this->kurirAskOtherLokasi($waNumber, $sapaan, $session, $msg);
            return;
        }
        $this->sendAutoreplyText(
            $waNumber,
            "Lokasinya sudah benar {$sapaan}? Balas *ya* untuk lanjut, atau bilang *beda lokasi* / kirim shareloc."
        );
    }

    /**
     * Customer menolak lokasi konfirmasi: tawarkan lokasi tersimpan lain, atau minta shareloc.
     * (Tidak kembali ke prefer last-delivery yang sama.)
     */
    private function kurirAskOtherLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $excludeId = (int) ($session['id_lokasi'] ?? 0);
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $list = $this->kurirListLokasi($idPelanggan);
        $filtered = [];
        foreach ($list as $lok) {
            if ((int) ($lok['id_lokasi'] ?? 0) !== $excludeId) {
                $filtered[] = $lok;
            }
        }

        $summary = trim((string) ($session['summary'] ?? ''));
        $note = 'tolak_lokasi id=' . $excludeId . ' "' . mb_substr(trim($msg), 0, 80) . '"';
        $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 500);

        if (empty($filtered)) {
            $this->kurirStartShareloc($waNumber, $sapaan, $session, $msg, $summary);
            return;
        }

        $lines = ["Baik {$sapaan}, pilih lokasi lain:"];
        foreach ($filtered as $i => $lok) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$lok['nama']}* — {$lok['detail']}";
        }
        $lines[] = "Atau balas *baru* lalu kirim *shareloc* / pin Google Maps.";
        $this->saveKurirSession($waNumber, [
            'step' => 'pick_lokasi',
            'id_lokasi' => null,
            'lokasi_nama' => null,
            'lokasi_detail' => null,
            'latt' => null,
            'longt' => null,
            'tarif' => null,
            'rates_json' => json_encode(['lokasi_list' => $filtered], JSON_UNESCAPED_UNICODE),
            'summary' => $summary,
        ]);
        $this->sendAutoreplyText($waNumber, implode("\n", $lines));
    }

    private function kurirStartShareloc(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        ?string $summary = null
    ): void {
        if ($summary === null) {
            $summary = trim((string) ($session['summary'] ?? ''));
            $note = 'minta_shareloc "' . mb_substr(trim($msg), 0, 80) . '"';
            $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 500);
        }
        $this->saveKurirSession($waNumber, [
            'step' => 'ask_shareloc',
            'id_lokasi' => null,
            'lokasi_nama' => null,
            'lokasi_detail' => null,
            'latt' => null,
            'longt' => null,
            'tarif' => null,
            'summary' => $summary,
        ]);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}, kirimkan *shareloc* / pin lokasi WhatsApp atau link Google Maps ya, biar kami catat titik yang dimaksud."
        );
    }

    /**
     * Setelah lokasi+ongkir dikonfirmasi: insert delivery_request + info (tanpa tanya setuju lagi).
     * Session tetap aktif (request_aktif) untuk jam khusus / batal.
     */
    private function kurirAcceptLokasiAndCreateRequest(string $waNumber, string $sapaan, array $session): bool
    {
        $ok = $this->kurirInsertSamedayRequest($waNumber, $session, null);
        if (!$ok) {
            return false;
        }
        $noun = $this->kurirJenisNoun($session);
        $text = "Baik {$sapaan}, permintaan diterima, jam kerja driver pukul 08.00 - 17.00, "
            . "waktu {$noun} tergantung pada posisi dan rute driver. "
            . "Pastikan selalu ada orang (satpam/saudara/teman) di lokasi. Terima kasih 😊";
        $this->saveKurirSession($waNumber, ['step' => 'request_aktif']);
        $this->sendAutoreplyText($waNumber, $text);
        return true;
    }

    /**
     * Request sudah jalan — siap jam khusus, instant, atau batal.
     */
    private function kurirHandleRequestAktif(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelAndReply($waNumber, $sapaan, $session);
            return;
        }

        if ($this->kurirLooksWantFast($msg)) {
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

        $wantJam = $this->kurirLooksWantJam($msg);
        $waktu = $this->parseEstimasiRequestWaktu($msg);
        if ($wantJam && $waktu === null) {
            $this->sendAutoreplyText($waNumber, "Tunggu ya {$sapaan}, kami tanyakan dulu ke driver.");
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

        $this->sendAutoreplyText(
            $waNumber,
            "Permintaan " . $this->kurirJenisLabel($session) . " sudah kami terima {$sapaan}. "
            . "Kalau ada jam tertentu atau ingin batalkan, tinggal bilang saja ya."
        );
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
            // Request biasanya sudah ada — update catatan saja
            if (!empty($session['id_request'])) {
                $this->kurirUpdateRequestCatatanJam($session, $alt);
            } else {
                $this->kurirInsertSamedayRequest($waNumber, $session, $alt);
            }
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, permintaan " . $this->kurirJenisLabel($session)
                . " kami lanjutkan sesuai jam alternatif driver. Terima kasih 😊"
            );
            $this->saveKurirSession($waNumber, [
                'step' => 'request_aktif',
                'request_granted' => 1,
            ]);
            return;
        }
        if ($this->kurirLooksRefuse($msg)) {
            $this->kurirCancelDeliveryRequest($session);
            $id = (int) ($session['id_pelanggan'] ?? 0);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik, maaf ya {$sapaan}, permintaan dibatalkan. Untuk pemesanan antar/jemput bisa juga via link berikut:\n"
                . "https://ml.nalju.com/J/kurir/{$id}"
            );
            $this->clearKurirSession($waNumber);
            return;
        }
        $this->sendAutoreplyText($waNumber, "Apakah permintaan tetap dilanjutkan {$sapaan}?");
    }

    private function kurirCancelDeliveryRequest(array $session): void
    {
        $idRequest = (int) ($session['id_request'] ?? 0);
        if ($idRequest <= 0) {
            return;
        }
        try {
            DB::getInstance(1)->update(
                'delivery_request',
                [
                    'delivery_status' => 'batal',
                    'catatan_batal' => 'Dibatalkan customer via WA AI',
                    'selesaiTime' => date('Y-m-d H:i:s'),
                ],
                ['id_request' => $idRequest]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirCancelDeliveryRequest: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function kurirUpdateRequestCatatanJam(array $session, array $jamMeta): void
    {
        $idRequest = (int) ($session['id_request'] ?? 0);
        if ($idRequest <= 0 || empty($jamMeta['jam'])) {
            return;
        }
        $tgl = $jamMeta['tanggal'] ?? date('Y-m-d');
        $catatan = mb_substr(
            'Minta jam ' . $this->formatKurirJamLabel((float) $jamMeta['jam']) . " tanggal {$tgl}",
            0,
            150
        );
        try {
            DB::getInstance(1)->update(
                'delivery_request',
                ['catatan_kurir' => $catatan],
                ['id_request' => $idRequest]
            );
        } catch (\Throwable $e) {
            // ignore
        }
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
            // Sudah ada request dari konfirmasi lokasi — update catatan saja
            $existingId = (int) ($session['id_request'] ?? 0);
            if ($existingId > 0) {
                if ($catatan !== '') {
                    $db->update(
                        'delivery_request',
                        ['catatan_kurir' => mb_substr($catatan, 0, 150)],
                        ['id_request' => $existingId]
                    );
                }
                $this->saveKurirSession($waNumber, ['id_request' => $existingId, 'step' => 'request_aktif']);
                return true;
            }

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
            $this->saveKurirSession($waNumber, ['id_request' => $idRequest, 'step' => 'request_aktif']);
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
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelAndReply($waNumber, $sapaan, $session);
            return;
        }

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
                // Kembali ke sameday: jika request sudah ada, tetap di request_aktif
                $stepBack = !empty($session['id_request']) ? 'request_aktif' : 'confirm_lokasi';
                $this->saveKurirSession($waNumber, ['layanan' => 'sameday', 'step' => $stepBack]);
                if ($stepBack === 'request_aktif') {
                    $this->sendAutoreplyText(
                        $waNumber,
                        "Baik {$sapaan}, kita lanjutkan permintaan sameday yang sudah diterima."
                    );
                } else {
                    $this->sendAutoreplyText(
                        $waNumber,
                        "Baik {$sapaan}, kita kembali ke sameday. Balas *ya* untuk konfirmasi lokasi/ongkir."
                    );
                }
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

    // -------------------------------------------------------------------------
    // Kurir AI: summary + chat context → action + optional reply
    // -------------------------------------------------------------------------

    /** @return list<string> */
    private function kurirAllowedActionsForStep(string $step): array
    {
        $common = ['cancel', 'clarify', 'unrelated'];
        switch ($step) {
            case 'ask_jenis':
                return array_merge($common, ['confirm']); // confirm = pilih jenis via slots later → treat as clarify if no jenis
            case 'lokasi_check':
                return array_merge($common, ['other_lokasi', 'ask_shareloc', 'want_instant', 'want_jam']);
            case 'ask_shareloc':
                return array_merge($common, ['ask_shareloc']);
            case 'ask_lokasi_nama':
            case 'ask_lokasi_detail':
                return array_merge($common, ['confirm']);
            case 'pick_lokasi':
                return array_merge($common, ['pick_lokasi', 'other_lokasi', 'ask_shareloc', 'want_instant']);
            case 'confirm_lokasi':
                return array_merge($common, ['confirm', 'other_lokasi', 'ask_shareloc', 'want_jam', 'want_instant']);
            case 'terms_setuju':
            case 'request_aktif':
                return array_merge($common, ['want_jam', 'want_instant', 'noop_ack']);
            case 'wait_driver_jam':
                return array_merge($common, ['noop_ack']);
            case 'wait_continue_alt':
                return array_merge($common, ['agree_alt', 'refuse_alt']);
            case 'instant_confirm':
                return array_merge($common, ['confirm', 'refuse_alt', 'other_lokasi']);
            case 'instant_pick':
                return array_merge($common, ['pick_lokasi', 'other_lokasi']);
            default:
                return array_merge($common, ['noop_ack', 'other_lokasi', 'want_jam', 'want_instant', 'confirm']);
        }
    }

    private function kurirBuildAiContext(string $waNumber, array $session, string $msg): string
    {
        $step = (string) ($session['step'] ?? '');
        $lines = [];
        $lines[] = 'SESSION:';
        $lines[] = '- step: ' . $step;
        $lines[] = '- jenis: ' . ($session['jenis'] ?? '-');
        $lines[] = '- layanan: ' . ($session['layanan'] ?? 'sameday');
        $lines[] = '- id_lokasi: ' . ($session['id_lokasi'] ?? '-');
        $lines[] = '- lokasi_nama: ' . ($session['lokasi_nama'] ?? '-');
        $lines[] = '- lokasi_detail: ' . ($session['lokasi_detail'] ?? '-');
        $lines[] = '- tarif: ' . ($session['tarif'] ?? '-');
        $lines[] = '- id_request: ' . ($session['id_request'] ?? '-');
        $lines[] = '- request_jam: ' . ($session['request_jam'] ?? '-');
        $lines[] = '- request_tanggal: ' . ($session['request_tanggal'] ?? '-');
        $lines[] = '- driver_alt_jam: ' . ($session['driver_alt_jam'] ?? '-');
        $lines[] = '- driver_alt_tanggal: ' . ($session['driver_alt_tanggal'] ?? '-');
        $sumTxt = trim((string) ($session['summary'] ?? ''));
        $lines[] = '- summary: ' . ($sumTxt !== '' ? $sumTxt : '(kosong)');

        $allowed = $this->kurirAllowedActionsForStep($step);
        $lines[] = 'ALLOWED_ACTIONS: ' . implode(', ', $allowed);

        $chat = $this->kurirFetchRecentChatTurns($waNumber, 8);
        $lines[] = 'RECENT_CHAT (lama→baru):';
        if (empty($chat)) {
            $lines[] = '(tidak ada riwayat)';
        } else {
            foreach ($chat as $t) {
                $dir = $t['dir'] === 'out' ? 'BOT' : 'CUST';
                $lines[] = "{$dir}: " . $t['body'];
            }
        }
        $lines[] = 'PESAN_BARU_CUSTOMER: ' . mb_substr(trim($msg), 0, 400);

        return implode("\n", $lines);
    }

    /**
     * @return list<array{dir:string,body:string,at:string}>
     */
    private function kurirFetchRecentChatTurns(string $waNumber, int $limit = 8): array
    {
        $phones = $this->waMessagesOutPhoneVariants($waNumber);
        if (empty($phones)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $rows = [];
        try {
            $db = DB::getInstance(0);
            $in = $db->query(
                "SELECT created_at AS at, 'in' AS dir, text AS body
                 FROM wa_messages_in
                 WHERE phone IN ($placeholders)
                 ORDER BY created_at DESC LIMIT 10",
                $phones
            );
            if ($in) {
                foreach ($in->result_array() as $r) {
                    $body = trim((string) ($r['body'] ?? ''));
                    if ($body === '') {
                        continue;
                    }
                    $rows[] = [
                        'dir' => 'in',
                        'body' => mb_substr($body, 0, 160),
                        'at' => (string) ($r['at'] ?? ''),
                    ];
                }
            }
            $out = $db->query(
                "SELECT created_at AS at, 'out' AS dir, content AS body
                 FROM wa_messages_out
                 WHERE phone IN ($placeholders)
                 ORDER BY created_at DESC LIMIT 10",
                $phones
            );
            if ($out) {
                foreach ($out->result_array() as $r) {
                    $body = trim((string) ($r['body'] ?? ''));
                    if ($body === '') {
                        continue;
                    }
                    $rows[] = [
                        'dir' => 'out',
                        'body' => mb_substr($body, 0, 160),
                        'at' => (string) ($r['at'] ?? ''),
                    ];
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirFetchRecentChatTurns: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return [];
        }

        usort($rows, static function ($a, $b) {
            return strcmp((string) $a['at'], (string) $b['at']);
        });
        if (count($rows) > $limit) {
            $rows = array_slice($rows, -$limit);
        }
        return $rows;
    }

    /**
     * @return array{action:string,reply:?string,slots:array,summary_note:?string,reason:?string}|null
     */
    private function kurirAiDecide(string $waNumber, array $session, string $msg): ?array
    {
        try {
            if (!class_exists('\\App\\Config\\AI')) {
                $configFile = __DIR__ . '/../Config/AI.php';
                if (!file_exists($configFile)) {
                    return null;
                }
                require_once $configFile;
            }
            if (!\App\Config\AI::isEnabled()) {
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'skip_disabled');
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        $step = (string) ($session['step'] ?? '');
        $allowed = $this->kurirAllowedActionsForStep($step);
        $context = $this->kurirBuildAiContext($waNumber, $session, $msg);

        $system = "Kamu asisten session kurir Madinah Laundry (jemput/antar). "
            . "Baca SESSION.summary + RECENT_CHAT + PESAN_BARU. "
            . "Tentukan apakah customer MASIH di intent kurir, typo/ambigu, atau SUDAH pindah topik. "
            . "Pilih SATU action dari ALLOWED_ACTIONS saja. "
            . "Jangan mengarang action di luar daftar. "
            . "Jika customer menolak lokasi / beda alamat / bukan itu → other_lokasi atau ask_shareloc. "
            . "Jika setuju lokasi/ongkir → confirm. "
            . "Jika batal/gak jadi/cancel → cancel. "
            . "Jika minta jam tertentu → want_jam (isi slots.jam/tanggal jika ada). "
            . "Jika minta cepat/gojek/grab/instant → want_instant. "
            . "Jika typo/kurang jelas → clarify + suggested_text (contoh: 'jemput laundry ke rumah kak'). "
            . "Jika topik lain (estimasi siap, bill, harga, status, salam penutup, dll) → unrelated (jangan balas sebagai kurir). "
            . "Jangan minta shareloc jika pesan jelas tentang estimasi siap/hari ini. "
            . "Field reply: kalimat WhatsApp singkat (boleh kosong). "
            . "Jawab HANYA JSON valid tanpa markdown.";

        $user = $context . "\n\nFORMAT:\n"
            . '{"action":"...", "reply":"...", "suggested_text":"...", '
            . '"slots":{"jam":null,"tanggal":null,"pick_index":null,"jenis":null}, '
            . '"summary_note":"ringkas 1 kalimat", "reason":"singkat"}';

        try {
            $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'request step=' . $step);
            $raw = $this->executeOpenAIRequestWithMessages(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                220,
                $waNumber
            );
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'error ' . mb_substr($e->getMessage(), 0, 200));
            return null;
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json) && preg_match('/\{.*\}/s', (string) $raw, $m)) {
            $json = json_decode($m[0], true);
        }
        if (!is_array($json)) {
            $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'bad_json ' . mb_substr((string) $raw, 0, 180));
            return null;
        }

        $action = strtolower(trim((string) ($json['action'] ?? '')));
        if ($action === '' || !in_array($action, $allowed, true)) {
            // soft remap
            if ($action === 'refuse_alt' && in_array('cancel', $allowed, true)) {
                $action = 'cancel';
            } elseif (in_array('clarify', $allowed, true)) {
                $action = 'clarify';
            } else {
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'action_not_allowed=' . ($json['action'] ?? ''));
                return null;
            }
        }

        $slots = $json['slots'] ?? [];
        if (!is_array($slots)) {
            $slots = [];
        }

        $decision = [
            'action' => $action,
            'reply' => isset($json['reply']) ? trim((string) $json['reply']) : null,
            'suggested_text' => isset($json['suggested_text']) ? trim((string) $json['suggested_text']) : null,
            'slots' => $slots,
            'summary_note' => isset($json['summary_note']) ? trim((string) $json['summary_note']) : null,
            'reason' => isset($json['reason']) ? trim((string) $json['reason']) : null,
        ];
        if ($decision['reply'] === '') {
            $decision['reply'] = null;
        }
        if ($decision['suggested_text'] === '') {
            $decision['suggested_text'] = null;
        }

        $this->logAutoreplyTrace(
            $waNumber,
            'KURIR_AI',
            'action=' . $action . ' reason=' . mb_substr((string) ($decision['reason'] ?? ''), 0, 120)
        );

        return $decision;
    }

    /**
     * @param array{action:string,reply:?string,slots:array,summary_note:?string,reason:?string} $decision
     */
    private function kurirDispatchAiAction(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        array $decision
    ): void {
        $action = $decision['action'];
        $aiReply = $decision['reply'];
        $slots = $decision['slots'] ?? [];
        $note = $decision['summary_note'] ?: ($action . ': ' . mb_substr($msg, 0, 60));

        // Side-effects + reply policy
        switch ($action) {
            case 'cancel':
                $this->kurirCancelAndReply($waNumber, $sapaan, $session);
                // template wajib; abaikan AI reply
                return;

            case 'confirm':
                $step = (string) ($session['step'] ?? '');
                if ($step === 'ask_jenis') {
                    $jenis = null;
                    $slotJenis = strtolower((string) ($slots['jenis'] ?? ''));
                    if (in_array($slotJenis, ['antar', 'jemput'], true)) {
                        $jenis = $slotJenis;
                    } else {
                        $jenis = $this->detectKurirJenis($msg);
                    }
                    if ($jenis) {
                        $this->saveKurirSession($waNumber, ['jenis' => $jenis, 'step' => 'lokasi_check']);
                        $session = $this->getKurirSession($waNumber) ?: $session;
                        $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                        $this->kurirAppendSummary($waNumber, $session, $note);
                        return;
                    }
                    $this->sendAutoreplyText(
                        $waNumber,
                        $aiReply ?: "Mohon pilih ya {$sapaan}: *jemput* atau *antar*?"
                    );
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if ($step === 'instant_confirm') {
                    $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, 'ya');
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if (in_array($step, ['ask_lokasi_nama', 'ask_lokasi_detail'], true)) {
                    // Biarkan handler regex isi nama/detail dari pesan mentah
                    if ($step === 'ask_lokasi_nama') {
                        $this->kurirHandleLokasiNama($waNumber, $sapaan, $session, $msg);
                    } else {
                        $this->kurirHandleLokasiDetail($waNumber, $sapaan, $session, $msg);
                    }
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                // confirm_lokasi → insert + template jam kerja (abaikan AI reply)
                $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'other_lokasi':
                // Selalu pakai list/shareloc template (agar daftar lokasi tampil)
                $this->kurirAskOtherLokasi($waNumber, $sapaan, $session, $msg);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'ask_shareloc':
                if ($aiReply) {
                    $summary = trim((string) ($session['summary'] ?? ''));
                    $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 800);
                    $this->saveKurirSession($waNumber, [
                        'step' => 'ask_shareloc',
                        'id_lokasi' => null,
                        'lokasi_nama' => null,
                        'lokasi_detail' => null,
                        'latt' => null,
                        'longt' => null,
                        'tarif' => null,
                        'summary' => $summary,
                    ]);
                    $this->sendAutoreplyText($waNumber, $aiReply);
                } else {
                    $this->kurirStartShareloc($waNumber, $sapaan, $session, $msg);
                }
                return;

            case 'pick_lokasi':
                $pickMsg = $msg;
                if (isset($slots['pick_index']) && $slots['pick_index'] !== null && $slots['pick_index'] !== '') {
                    $pickMsg = (string) ((int) $slots['pick_index']);
                }
                $step = (string) ($session['step'] ?? '');
                if ($step === 'instant_pick') {
                    $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, $pickMsg);
                } else {
                    $this->kurirHandlePickLokasi($waNumber, $sapaan, $session, $pickMsg);
                }
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'want_jam':
                $waktu = null;
                if (!empty($slots['jam'])) {
                    $jamVal = (float) $slots['jam'];
                    $tgl = !empty($slots['tanggal']) ? (string) $slots['tanggal'] : date('Y-m-d');
                    $waktu = ['jam' => $jamVal, 'tanggal' => $tgl];
                }
                if ($waktu === null) {
                    $waktu = $this->parseEstimasiRequestWaktu($msg);
                }
                $step = (string) ($session['step'] ?? '');
                // Pastikan request sudah ada jika masih di confirm
                if ($step === 'confirm_lokasi') {
                    if (!$this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session)) {
                        return;
                    }
                    $session = $this->getKurirSession($waNumber) ?: $session;
                }
                if ($waktu === null) {
                    $this->sendAutoreplyText(
                        $waNumber,
                        $aiReply ?: ("Jam berapa {$sapaan} ingin " . $this->kurirJenisLabel($session) . "? (contoh: jam 14)")
                    );
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if ($aiReply) {
                    $this->sendAutoreplyText($waNumber, $aiReply);
                }
                $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, $msg, $waktu);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'want_instant':
                $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'agree_alt':
                $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, 'ya');
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'refuse_alt':
                // Di wait_continue_alt = batal; di instant = kembali sameday
                $step = (string) ($session['step'] ?? '');
                if ($step === 'wait_continue_alt') {
                    $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, 'tidak');
                } elseif (in_array($step, ['instant_confirm', 'instant_pick'], true)) {
                    $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, 'tidak');
                } else {
                    $this->kurirCancelAndReply($waNumber, $sapaan, $session);
                    return;
                }
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'noop_ack':
                $this->sendAutoreplyText(
                    $waNumber,
                    $aiReply ?: (
                        "Permintaan " . $this->kurirJenisLabel($session) . " sudah kami terima {$sapaan}. "
                        . "Kalau ada jam tertentu atau ingin batalkan, tinggal bilang saja ya."
                    )
                );
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'clarify':
                $suggested = trim((string) ($decision['suggested_text'] ?? ''));
                if ($suggested !== '') {
                    $this->sendClarifyConfirmation($waNumber, $suggested);
                } else {
                    $this->sendAutoreplyText(
                        $waNumber,
                        $aiReply ?: ("Maaf {$sapaan}, textnya kurang dapat saya pahami, boleh ketik ulang lebih jelas?")
                    );
                }
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            default:
                $this->sendAutoreplyText(
                    $waNumber,
                    $aiReply ?: ("Mohon diperjelas ya {$sapaan}, biar kami bantu lanjutkan.")
                );
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;
        }
    }

    private function kurirAppendSummary(string $waNumber, array $session, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            return;
        }
        $summary = trim((string) ($session['summary'] ?? ''));
        $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 800);
        $this->saveKurirSession($waNumber, ['summary' => $summary]);
    }
}
