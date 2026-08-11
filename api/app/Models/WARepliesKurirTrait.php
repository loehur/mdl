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
    /** Session belum lengkap nama/detail lokasi: tahan lebih lama agar bisa dilanjutkan hari berikutnya */
    private const KURIR_INCOMPLETE_LOKASI_TTL_MINUTES = 10080; // 7 hari

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
        $step = (string) $merge('step', 'ask_jenis');
        $ttlMin = self::KURIR_SESSION_TTL_MINUTES;
        $hasCoords = $merge('latt') !== null && $merge('latt') !== ''
            && $merge('longt') !== null && $merge('longt') !== '';
        $detailIncomplete = trim((string) ($merge('lokasi_detail') ?? '')) === '';
        if (in_array($step, ['ask_lokasi_nama', 'ask_lokasi_detail', 'ask_shareloc', 'new_ask_nama', 'new_ask_shareloc'], true)
            || ($hasCoords && $detailIncomplete && in_array($step, ['ask_lokasi_nama', 'ask_lokasi_detail'], true))) {
            $ttlMin = self::KURIR_INCOMPLETE_LOKASI_TTL_MINUTES;
        }
        $expires = date('Y-m-d H:i:s', time() + ($ttlMin * 60));
        $vals = [
            $phone,
            $merge('id_pelanggan'),
            $merge('id_cabang'),
            $merge('jenis'),
            $merge('layanan', 'sameday'),
            $step,
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
            $merge('butuh_estimasi', 0),
            $merge('estimasi_tanggal'),
            $merge('estimasi_jam'),
            $merge('butuh_update_nama', 0),
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
                   butuh_estimasi, estimasi_tanggal, estimasi_jam, butuh_update_nama,
                   driver_alt_tanggal, driver_alt_jam, courier_company, courier_type, courier_name,
                   ongkir, rates_json, id_request, summary, updated_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   id_pelanggan=VALUES(id_pelanggan), id_cabang=VALUES(id_cabang), jenis=VALUES(jenis),
                   layanan=VALUES(layanan), step=VALUES(step), id_lokasi=VALUES(id_lokasi),
                   lokasi_nama=VALUES(lokasi_nama), lokasi_detail=VALUES(lokasi_detail),
                   latt=VALUES(latt), longt=VALUES(longt), tarif=VALUES(tarif),
                   request_text=VALUES(request_text), request_tanggal=VALUES(request_tanggal),
                   request_jam=VALUES(request_jam), request_granted=VALUES(request_granted),
                   butuh_estimasi=VALUES(butuh_estimasi), estimasi_tanggal=VALUES(estimasi_tanggal),
                   estimasi_jam=VALUES(estimasi_jam), butuh_update_nama=VALUES(butuh_update_nama),
                   driver_alt_tanggal=VALUES(driver_alt_tanggal), driver_alt_jam=VALUES(driver_alt_jam),
                   courier_company=VALUES(courier_company), courier_type=VALUES(courier_type),
                   courier_name=VALUES(courier_name), ongkir=VALUES(ongkir), rates_json=VALUES(rates_json),
                   id_request=VALUES(id_request), summary=VALUES(summary),
                   updated_at=VALUES(updated_at), expires_at=VALUES(expires_at)',
                $vals
            );
        } catch (\Throwable $e) {
            // Fallback jika kolom butuh_update_nama belum dimigrasi
            if (class_exists('\Log')) {
                \Log::write('saveKurirSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->saveKurirSessionLegacyWithoutUpdateNama($phone, $data, $existing);
        }
    }

    /** Fallback INSERT tanpa butuh_update_nama (DB belum migrasi 009). */
    private function saveKurirSessionLegacyWithoutUpdateNama(string $phone, array $data, ?array $existing): void
    {
        $merge = function (string $key, $default = null) use ($data, $existing) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
            return $existing[$key] ?? $default;
        };
        $now = date('Y-m-d H:i:s');
        $step = (string) $merge('step', 'ask_jenis');
        $ttlMin = self::KURIR_SESSION_TTL_MINUTES;
        if (in_array($step, ['ask_lokasi_nama', 'ask_lokasi_detail', 'ask_shareloc', 'new_ask_nama', 'new_ask_shareloc'], true)) {
            $ttlMin = self::KURIR_INCOMPLETE_LOKASI_TTL_MINUTES;
        }
        $expires = date('Y-m-d H:i:s', time() + ($ttlMin * 60));
        $vals = [
            $phone,
            $merge('id_pelanggan'),
            $merge('id_cabang'),
            $merge('jenis'),
            $merge('layanan', 'sameday'),
            $step,
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
            $merge('butuh_estimasi', 0),
            $merge('estimasi_tanggal'),
            $merge('estimasi_jam'),
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
                   butuh_estimasi, estimasi_tanggal, estimasi_jam,
                   driver_alt_tanggal, driver_alt_jam, courier_company, courier_type, courier_name,
                   ongkir, rates_json, id_request, summary, updated_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   id_pelanggan=VALUES(id_pelanggan), id_cabang=VALUES(id_cabang), jenis=VALUES(jenis),
                   layanan=VALUES(layanan), step=VALUES(step), id_lokasi=VALUES(id_lokasi),
                   lokasi_nama=VALUES(lokasi_nama), lokasi_detail=VALUES(lokasi_detail),
                   latt=VALUES(latt), longt=VALUES(longt), tarif=VALUES(tarif),
                   request_text=VALUES(request_text), request_tanggal=VALUES(request_tanggal),
                   request_jam=VALUES(request_jam), request_granted=VALUES(request_granted),
                   butuh_estimasi=VALUES(butuh_estimasi), estimasi_tanggal=VALUES(estimasi_tanggal),
                   estimasi_jam=VALUES(estimasi_jam),
                   driver_alt_tanggal=VALUES(driver_alt_tanggal), driver_alt_jam=VALUES(driver_alt_jam),
                   courier_company=VALUES(courier_company), courier_type=VALUES(courier_type),
                   courier_name=VALUES(courier_name), ongkir=VALUES(ongkir), rates_json=VALUES(rates_json),
                   id_request=VALUES(id_request), summary=VALUES(summary),
                   updated_at=VALUES(updated_at), expires_at=VALUES(expires_at)',
                $vals
            );
        } catch (\Throwable $e2) {
            if (class_exists('\Log')) {
                \Log::write('saveKurirSessionLegacy: ' . $e2->getMessage(), 'wa_error', 'Autoreply');
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

    private function messageBreaksKurirSession(
        string $text,
        array $keywordConfig,
        bool $hasActiveSaleForEstimasi = true
    ): bool {
        if (preg_match('/\b(bon|bill|bil{1,}|tagihan|nota|invoice|pricelist|price\s*list)\b/iu', $text)) {
            return true;
        }
        // Reminder / ingat — keyword jelas, jangan tahan di session kurir/lokasi
        if (preg_match('/^\s*(reminder|remind|ingatkan|ingat|pengingat)\s*$/iu', $text)) {
            return true;
        }
        // Tanya estimasi siap → break kurir hanya jika ada order aktif
        if ($hasActiveSaleForEstimasi
            && (
                $this->messageLooksLikeEstimasiSelesai($text)
                || $this->parseEstimasiRequestedRelativeDay($text) !== null
            )
        ) {
            return true;
        }
        // Jangan include PEMBUKA/PENUTUP: "ya"/"ok"/"iya" saat konfirmasi lokasi/layanan
        // harus tetap di session kurir, bukan dibalas sapaan/ack penutup.
        $breakout = [
            'TAGIHAN', 'NOTA', 'STATUS', 'HARGA', 'HARGA_PAKET', 'HARGA_PAKET_D',
            'REMINDER', 'KEY', 'JAM_OPERASIONAL',
            'AMBIL_LEWAT_TUTUP', 'KARYAWAN', 'KAS_LAUNDRY', 'CEK_TOKEN',
            'CEK_QRIS', 'SALDO_YCLOUD', 'INFO_FONNTE', 'TARIK_TOKOPAY',
            'SLIP_GAJI', 'GAJI_CASH', 'GAJI_TF',
        ];
        if ($hasActiveSaleForEstimasi) {
            $breakout[] = 'ESTIMASI_SELESAI';
        }
        foreach ($breakout as $handler) {
            foreach ($keywordConfig[$handler]['patterns'] ?? [] as $pattern) {
                if (@preg_match($pattern, $text)) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Intent jelas lain → lepaskan follow-up session LOKASI (sama daftar breakout kurir). */
    private function messageBreaksLokasiSession(string $text, array $keywordConfig): bool
    {
        return $this->messageBreaksKurirSession($text, $keywordConfig, true);
    }

    /**
     * @return bool true = pesan sudah ditangani kurir; false = unrelated → lanjut routing intent lain
     */
    private function handleMinta_Jemput_Antar($phoneIn, $waNumber, $textBody = '')
    {
        // Di luar jam operasional: flow sameday tetap jalan; instant ditolak / tidak ditawarkan
        $msg = trim((string) $textBody);
        $session = $this->getKurirSession($waNumber);
        $idPelanggan = $session['id_pelanggan'] ?? null;
        if (!$idPelanggan) {
            $idPelanggan = $this->resolveIdPelangganForKurirLink($phoneIn, $waNumber);
        }
        if (!$idPelanggan) {
            return $this->handleKurirUnregisteredFlow($phoneIn, $waNumber, $msg, $session);
        }
        $idPelanggan = (int) $idPelanggan;
        $idCabang = $this->resolveKurirIdCabang($idPelanggan, $session);
        $outsideHours = !$this->isOperatingHours();

        if ($session === null) {
            $jenis = $this->detectKurirJenis($msg, $session);
            // Ambigu: ada order aktif → antar; tanpa order / baru → jemput (jangan tanya)
            if (!$jenis) {
                $jenis = $this->kurirInferJenisWhenAmbiguous($phoneIn, $waNumber);
            }
            $layananPref = $this->detectKurirLayanan($msg);
            // Luar jam: jangan simpan prefer instant — anggap sameday; chat grab/gosend ditolak di route
            if ($outsideHours && $layananPref === 'instant') {
                $layananPref = null;
            }
            $summary = '[pesan] ' . mb_substr($msg, 0, 200);
            if ($layananPref) {
                $summary .= ' | prefer_layanan=' . $layananPref;
            }
            $summary .= ' | jenis_infer=' . $jenis;
            $this->saveKurirSession($waNumber, [
                'id_pelanggan' => $idPelanggan,
                'id_cabang' => $idCabang,
                'jenis' => $jenis,
                'layanan' => $layananPref ?: 'sameday',
                'step' => 'lokasi_check',
                'summary' => $summary,
            ]);
            if ($layananPref === 'instant') {
                $this->saveKurirSession($waNumber, ['layanan' => 'instant']);
            } elseif ($layananPref === 'sameday') {
                $this->saveKurirSession($waNumber, ['layanan' => 'sameday']);
            }
            $session = $this->getKurirSession($waNumber) ?: [];

            // Chat langsung minta grab/gosend di luar jam → tolak sekali, lanjut sameday
            if ($outsideHours && $this->kurirLooksWantFast($msg)) {
                $sapaan = $this->getSapaanForGreeting($waNumber);
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return true;
            }

            $sapaan = $this->getSapaanForGreeting($waNumber);
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return true;
        }

        return $this->routeKurirStep($phoneIn, $waNumber, $msg, $session);
    }

    /**
     * Jenis kurir jika pesan ambigu: ada sale aktif → antar; selain itu jemput.
     */
    private function kurirInferJenisWhenAmbiguous(string $phoneIn, string $waNumber): string
    {
        return $this->pelangganHasActiveSale($phoneIn, $waNumber) ? 'antar' : 'jemput';
    }

    /** Pertanyaan singkat hanya untuk edge case step ask_jenis yang masih macet. */
    private function kurirAskJenisPrompt(string $sapaan): string
    {
        return "Maaf {$sapaan}, mau order jemput atau antar ya?";
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

    /**
     * Pelanggan belum terdaftar: Antar → tolak (noRegister);
     * Jemput → onboarding nama → shareloc → cabang terdekat → insert #NEW# + bell.
     */
    private function handleKurirUnregisteredFlow(
        string $phoneIn,
        string $waNumber,
        string $msg,
        ?array $session
    ): bool {
        $sapaan = $this->getSapaanForGreeting($waNumber);
        $step = (string) ($session['step'] ?? '');

        if ($session !== null && empty($session['id_pelanggan'])
            && in_array($step, ['ask_jenis', 'new_ask_nama', 'new_ask_shareloc'], true)
        ) {
            $this->routeKurirUnregisteredStep($waNumber, $sapaan, $session, $msg);
            return true;
        }

        $jenis = $this->detectKurirJenis($msg, $session);
        if ($jenis === 'antar') {
            $this->sendAutoreplyText($waNumber, $this->getNoRegisterText());
            return true;
        }
        // Unregistered + ambigu / jemput → selalu jemput (belum ada order untuk diantar)
        $this->saveKurirSession($waNumber, [
            'id_pelanggan' => null,
            'id_cabang' => null,
            'jenis' => 'jemput',
            'layanan' => 'sameday',
            'step' => 'new_ask_nama',
            'summary' => '[pesan] ' . mb_substr($msg, 0, 200) . ' | onboarding_new=1 | jenis_infer=jemput',
        ]);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}, boleh sebut *nama lengkap* Anda ya?"
        );
        return true;
    }

    private function routeKurirUnregisteredStep(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $step = (string) ($session['step'] ?? '');

        if ($this->kurirLooksCancel($msg)) {
            $this->clearKurirSession($waNumber);
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, permintaan dibatalkan. Terima kasih.");
            return;
        }

        switch ($step) {
            case 'ask_jenis':
                $jenis = $this->detectKurirJenis($msg, $session);
                if ($jenis === null && $this->kurirLooksAddingOtherJenis($msg)) {
                    $jenis = 'antar';
                }
                if ($jenis === 'antar') {
                    $this->clearKurirSession($waNumber);
                    $this->sendAutoreplyText($waNumber, $this->getNoRegisterText());
                    return;
                }
                if ($jenis === 'jemput' || $jenis === null) {
                    // Ambigu unregistered → jemput
                    $this->saveKurirSession($waNumber, [
                        'jenis' => 'jemput',
                        'layanan' => 'sameday',
                        'step' => 'new_ask_nama',
                    ]);
                    $this->sendAutoreplyText(
                        $waNumber,
                        "Baik {$sapaan}, boleh sebut *nama lengkap* Anda ya?"
                    );
                    return;
                }
                $this->sendAutoreplyText($waNumber, $this->kurirAskJenisPrompt($sapaan));
                return;

            case 'new_ask_nama':
                $this->kurirHandleNewAskNama($waNumber, $sapaan, $session, $msg);
                return;

            case 'new_ask_shareloc':
                $this->kurirHandleNewAskShareloc($waNumber, $sapaan, $session, $msg);
                return;

            default:
                $this->sendAutoreplyText($waNumber, $this->kurirAskJenisPrompt($sapaan));
                $this->saveKurirSession($waNumber, ['step' => 'ask_jenis']);
        }
    }

    private function kurirHandleNewAskNama(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $nama = trim(preg_replace("/[\r\n]+/", ' ', $msg));
        $nama = trim(preg_replace('/\s+/u', ' ', $nama));
        // Buang sapaan pendek murni
        if ($nama === '' || mb_strlen($nama) < 2
            || preg_match('/^(kak|kk|bang|pak|bu|mbak|ya|iya|ok|oke|baik)\s*$/iu', $nama)
        ) {
            $this->sendAutoreplyText(
                $waNumber,
                "Nama belum jelas {$sapaan}. Boleh ketik *nama lengkap* Anda ya?"
            );
            return;
        }
        if (mb_strlen($nama) > 80) {
            $nama = mb_substr($nama, 0, 80);
        }

        $summary = preg_replace('/\s*\|\s*new_nama_asli=[^|]*/', '', (string) ($session['summary'] ?? ''));
        $summary = trim($summary . ($summary !== '' ? ' | ' : '') . 'new_nama_asli=' . $nama);

        $this->saveKurirSession($waNumber, [
            'step' => 'new_ask_shareloc',
            'summary' => mb_substr($summary, 0, 800),
        ]);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}, kirimkan *shareloc* / pin lokasi WhatsApp atau link Google Maps ya."
        );
    }

    private function kurirHandleNewAskShareloc(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $coords = $this->kurirExtractCoords($msg);
        if ($coords === null) {
            $this->sendAutoreplyText(
                $waNumber,
                "Belum ketemu koordinatnya {$sapaan}. Kirim pin lokasi WhatsApp atau link Google Maps ya."
            );
            return;
        }

        $latt = (float) $coords['lat'];
        $longt = (float) $coords['lng'];
        $nearest = $this->kurirFindNearestCabang($latt, $longt);
        if ($nearest === null) {
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, belum bisa menentukan cabang terdekat. Coba kirim ulang lokasi ya."
            );
            return;
        }

        $namaAsli = '';
        if (preg_match('/new_nama_asli=([^|]+)/', (string) ($session['summary'] ?? ''), $m)) {
            $namaAsli = trim($m[1]);
        }
        if ($namaAsli === '') {
            $this->saveKurirSession($waNumber, ['step' => 'new_ask_nama', 'latt' => $latt, 'longt' => $longt]);
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, boleh sebut *nama lengkap* Anda dulu ya?");
            return;
        }

        $namaDb = $this->kurirFormatNewPelangganNama($namaAsli);
        $nomor = $this->kurirNomorPelangganFromWa($waNumber);
        $idCabang = (int) $nearest['id_cabang'];
        $idPelanggan = $this->kurirInsertNewPelanggan($namaDb, $nomor, $idCabang);
        if ($idPelanggan <= 0) {
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, gagal menyimpan data. Coba kirim ulang lokasi sebentar lagi ya."
            );
            return;
        }

        $idLokasi = $this->kurirInsertLokasi($idPelanggan, '', '', $latt, $longt);
        if ($idLokasi <= 0) {
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, gagal menyimpan titik lokasi. Coba kirim shareloc lagi ya."
            );
            return;
        }

        $summary = preg_replace('/\s*\|\s*onboarding_new=1/', '', (string) ($session['summary'] ?? ''));
        $summary = trim($summary . ' | pelanggan_new=1');

        $this->saveKurirSession($waNumber, [
            'id_pelanggan' => $idPelanggan,
            'id_cabang' => $idCabang,
            'jenis' => 'jemput',
            'layanan' => 'sameday',
            'step' => 'ask_lokasi_detail',
            'id_lokasi' => $idLokasi,
            'latt' => $latt,
            'longt' => $longt,
            'lokasi_nama' => null,
            'lokasi_detail' => null,
            'butuh_update_nama' => 1,
            'summary' => mb_substr($summary, 0, 800),
        ]);

        // Bell petugas segera — tanpa balasan ke customer soal update nama
        $this->logAutoreplyTrace(
            $waNumber,
            'KURIR',
            "pelanggan_new id={$idPelanggan} nama={$namaDb} cabang={$idCabang} butuh_update_nama=1"
        );

        $this->sendAutoreplyText(
            $waNumber,
            $this->lokasiAskDetailPrompt($sapaan)
        );
    }

    /**
     * "Andi pratama jaya" → "ANDI PJ #NEW#"
     */
    private function kurirFormatNewPelangganNama(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName));
        $parts = preg_split('/\s+/u', $fullName) ?: [];
        $parts = array_values(array_filter($parts, static function ($p) {
            return trim((string) $p) !== '';
        }));
        if ($parts === []) {
            return 'BARU #NEW#';
        }

        $firstLetters = preg_replace('/[^\p{L}]/u', '', $parts[0]);
        $first = mb_strtoupper(mb_substr((string) $firstLetters, 0, 10));
        if ($first === '') {
            $first = 'BARU';
        }

        $initials = '';
        $n = count($parts);
        for ($i = 1; $i < $n; $i++) {
            $w = preg_replace('/[^\p{L}]/u', '', $parts[$i]);
            if ($w !== null && $w !== '') {
                $initials .= mb_strtoupper(mb_substr($w, 0, 1));
            }
        }

        $name = $first;
        if ($initials !== '') {
            $name .= ' ' . $initials;
        }
        return $name . ' #NEW#';
    }

    private function kurirNomorPelangganFromWa(string $waNumber): string
    {
        $d = $this->normalizePhoneDigitsOnlyPhp($waNumber);
        if ($d === '') {
            return '';
        }
        if (strpos($d, '62') === 0 && strlen($d) > 2) {
            return '0' . substr($d, 2);
        }
        if ($d[0] !== '0') {
            return '0' . $d;
        }
        return $d;
    }

    /**
     * @return array{id_cabang:int,nama:string,latt:float,long:float}|null
     */
    private function kurirFindNearestCabang(float $latt, float $longt): ?array
    {
        $rows = null;
        try {
            $rows = DB::getInstance(1)->query(
                'SELECT id_cabang, nama, latt, `long`, is_training
                 FROM cabang
                 WHERE latt IS NOT NULL AND `long` IS NOT NULL'
            )->result_array();
        } catch (\Throwable $e) {
            // Fallback jika kolom is_training belum ada
            try {
                $rows = DB::getInstance(1)->query(
                    'SELECT id_cabang, nama, latt, `long`
                     FROM cabang
                     WHERE latt IS NOT NULL AND `long` IS NOT NULL'
                )->result_array();
            } catch (\Throwable $e2) {
                return null;
            }
        }
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $best = null;
        $bestKm = null;
        foreach ($rows as $r) {
            if (!empty($r['is_training'])) {
                continue;
            }
            $clat = (float) ($r['latt'] ?? 0);
            $clon = (float) ($r['long'] ?? 0);
            if ($clat == 0.0 && $clon == 0.0) {
                continue;
            }
            $km = AntarTarif::distanceKm($latt, $longt, $clat, $clon);
            if ($bestKm === null || $km < $bestKm) {
                $bestKm = $km;
                $best = [
                    'id_cabang' => (int) ($r['id_cabang'] ?? 0),
                    'nama' => (string) ($r['nama'] ?? ''),
                    'latt' => $clat,
                    'long' => $clon,
                ];
            }
        }
        if ($best === null || $best['id_cabang'] <= 0) {
            return null;
        }
        return $best;
    }

    private function kurirInsertNewPelanggan(string $nama, string $nomor, int $idCabang): int
    {
        if ($nama === '' || $nomor === '' || $idCabang <= 0) {
            return 0;
        }
        try {
            $id = DB::getInstance(1)->insert('pelanggan', [
                'id_cabang' => $idCabang,
                'nama_pelanggan' => $nama,
                'nomor_pelanggan' => $nomor,
            ]);
            return $id ? (int) $id : 0;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertNewPelanggan: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return 0;
        }
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

    /**
     * Deteksi jemput/antar. Typo "anter" = antar.
     * Implicit: "ambil kain kotor" → jemput; "bawakan kain yang siap" → antar.
     * Jika keduanya disebut (pesan + summary session) → antar.
     *
     * @return 'jemput'|'antar'|null
     */
    private function detectKurirJenis(string $msg, ?array $session = null): ?string
    {
        $blob = mb_strtolower($msg);
        if ($session !== null) {
            $blob .= ' ' . mb_strtolower((string) ($session['summary'] ?? ''));
            $prev = (string) ($session['jenis'] ?? '');
            if ($prev === 'jemput' || $prev === 'antar') {
                $blob .= ' ' . $prev;
            }
        }

        $hasJemput = (bool) preg_match('/\b(jemput|jmpt|dijemput|penjemputan)\b/u', $blob)
            || $this->kurirMsgLooksAmbilKotorJemput($blob);
        $hasAntar = (bool) preg_match(
            '/\b(antar|anter|diantar|dianter|pengantaran|kirim\s*(ke|ke\s+rumah)?)\b/u',
            $blob
        ) || $this->kurirMsgLooksBawakanSiapAntar($blob);

        // Antar sekaligus jemput (atau sebaliknya) → antar
        if ($hasJemput && $hasAntar) {
            return 'antar';
        }
        if ($hasJemput) {
            return 'jemput';
        }
        if ($hasAntar) {
            return 'antar';
        }
        return null;
    }

    /**
     * "bisa ambil kain kotor" / "ambil baju kotor" = minta kurir jemput (bukan customer ambil sendiri).
     */
    private function kurirMsgLooksAmbilKotorJemput(string $blob): bool
    {
        // Customer ambil sendiri → bukan jemput kurir
        if (preg_match('/\b(saya|aku|sy|gue|awak)\s+(ambil|ngambil|jemput)\b/u', $blob)) {
            return false;
        }
        // Status "sudah/udah bisa diambil?" tanpa kain kotor
        if (preg_match('/\b(udah|sudah|udh|sdh|dah|dh)\s+bisa\s*(di\s*)?ambil\b/u', $blob)
            && !preg_match('/\bkotor\b/u', $blob)
        ) {
            return false;
        }

        return (bool) preg_match(
            '/\b(ambil|ngambil|mengambil|jemput|jmpt)\b.{0,80}\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,40}\bkotor\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,30}\bkotor\b.{0,60}\b(ambil|ngambil|jemput|jmpt|dijemput)\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(bisa|boleh|tolong|minta|mau)\s+(ambil|ngambil|jemput|jmpt)\b.{0,60}\b(kain|baju|cucian|laundry|londry|londri).{0,30}\bkotor\b/u',
            $blob
        );
    }

    /**
     * "sekalian bawakan kain yang udah siap" / "bawak kan kain yg siap" = minta antar.
     */
    private function kurirMsgLooksBawakanSiapAntar(string $blob): bool
    {
        return (bool) preg_match(
            '/\b(bawak|bawakan|bawa\s*kan|bawa\s*in|bawa\s*kan|anter|antar|kirim(kan)?)\b.{0,100}\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,80}\b(yg|yang)?\s*(udah|sudah|udh|dah|sdh)?\s*(siap|selesai|kelar)\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,40}\b(yg|yang)?\s*(udah|sudah|udh|dah|sdh)?\s*(siap|selesai|kelar)\b.{0,60}\b(bawak|bawakan|bawa\s*kan|bawa\s*in|antar|anter|kirim(kan)?)\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(sekalian|sekaligus|juga)\s+(bawak|bawakan|bawa\s*kan|bawa\s*in|antar|anter)\b.{0,80}\b(kain|baju|cucian|laundry|siap|selesai)\b/u',
            $blob
        );
    }

    /** "jemput juga" / "antar sekalian" / "sekalian bawakan" — menambah jenis lain di tengah chat. */
    private function kurirLooksAddingOtherJenis(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }
        if ($this->kurirMsgLooksBawakanSiapAntar($t) || $this->kurirMsgLooksAmbilKotorJemput($t)) {
            return true;
        }
        return (bool) preg_match(
            '/\b(jemput|jmpt|antar|anter)\s*(juga|jg|jga|sekalian|sekaligus|bareng|sama)\b/iu',
            $t
        ) || (bool) preg_match(
            '/\b(juga|jg|jga|sekalian|sekaligus)\s*(di)?(jemput|jmpt|antar|anter|bawak|bawakan|bawa)\b/iu',
            $t
        );
    }

    /**
     * Deteksi pilihan kurir dari teks bebas (bukan hanya 1/2).
     * @return 'sameday'|'instant'|null
     */
    private function detectKurirLayanan(string $msg): ?string
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return null;
        }
        // Angka pilihan
        if (preg_match('/^\s*1\s*[.)]?\s*$/u', $t) || preg_match('/^\s*satu\s*$/iu', $t)) {
            return 'sameday';
        }
        if (preg_match('/^\s*2\s*[.)]?\s*$/u', $t) || preg_match('/^\s*dua\s*$/iu', $t)) {
            return 'instant';
        }
        // Instant dulu (lebih spesifik)
        if ($this->kurirLooksWantFast($msg)
            || preg_match(
                '/\b(gosend|go\s*send|grab|gojek|gofood|grabexpress|instant|instan|kilat|maxim|paxel|lalamove|borzo|deliveree)\b/iu',
                $t
            )
        ) {
            return 'instant';
        }
        // Sameday
        if (preg_match(
            '/\b(sameday|same\s*day|same\-day|kurir\s*(laundry|toko|mdl|biasa)|yang\s*biasa|driver\s*(laundry|toko)|besok|bsk)\b/iu',
            $t
        )) {
            return 'sameday';
        }
        // "1 sameday" / "pilih 2"
        if (preg_match('/\b(pilih\s*)?1\b/u', $t) && !preg_match('/\b2\b/u', $t)
            && preg_match('/\b(sameday|same|hari|besok|biasa|kurir)\b/iu', $t)) {
            return 'sameday';
        }
        if (preg_match('/\b(pilih\s*)?2\b/u', $t) && preg_match('/\b(instant|instan|grab|gojek|gosend|cepat)\b/iu', $t)) {
            return 'instant';
        }
        return null;
    }

    private function kurirAskLayananPrompt(string $sapaan): string
    {
        return "Pilih jenis kurir ya {$sapaan}:\n"
            . "1. Kurir *sameday* (hari ini/besok)\n"
            . "2. Kurir *instant* (Grab/Gojek)\n"
            . "Balas *1* atau *2* (boleh juga ketik sameday / grab).";
    }

    /**
     * @return bool true = pesan sudah ditangani kurir; false = AI unrelated → lanjut routing intent lain
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

        // Hard: klarifikasi pagi/malam untuk jam 7–9
        if ($step === 'ask_jam_ampm') {
            $this->kurirHandleAskJamAmpm($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // Hard: pilih angka di pick_lokasi / instant_pick / ask_layanan / delete_lokasi
        if ($step === 'pick_lokasi' && preg_match('/^\s*(\d{1,2})\s*$/u', trim($msg))) {
            $this->kurirHandlePickLokasi($waNumber, $sapaan, $session, $msg);
            return true;
        }
        if ($step === 'instant_pick' && preg_match('/^\s*(\d{1,2})\s*$/u', trim($msg))) {
            $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, $msg);
            return true;
        }
        if ($step === 'delete_lokasi') {
            $this->kurirHandleDeleteLokasiPick($waNumber, $sapaan, $session, $msg);
            return true;
        }
        if ($step === 'ask_layanan') {
            $this->kurirHandleAskLayanan($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // Hard: ubah/hapus alamat → hapus lokasi (1 langsung, >1 tanya nomor)
        if ($this->kurirLooksWantDeleteLokasi($msg)
            && in_array($step, [
                'lokasi_check', 'pick_lokasi', 'confirm_lokasi', 'ask_layanan',
                'request_aktif', 'instant_confirm', 'instant_pick',
            ], true)
        ) {
            $this->kurirStartDeleteLokasi($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // ask_jenis (session lama): deteksi dari pesan, atau infer dari order aktif
        if ($step === 'ask_jenis') {
            $jenis = $this->detectKurirJenis($msg, $session);
            if (!$jenis && preg_match('/\b(jemput|jmpt)\b/iu', $msg)) {
                $jenis = 'jemput';
            }
            if (!$jenis && preg_match('/\b(antar|anter)\b/iu', $msg)) {
                $jenis = 'antar';
            }
            if (!$jenis) {
                $jenis = $this->kurirInferJenisWhenAmbiguous($phoneIn, $waNumber);
            }
            $layananPref = $this->detectKurirLayanan($msg);
            $set = ['jenis' => $jenis, 'step' => 'lokasi_check'];
            $summary = trim((string) ($session['summary'] ?? ''));
            if ($layananPref) {
                $set['layanan'] = $layananPref;
                $summary = preg_replace('/\s*\|\s*prefer_layanan=(instant|sameday)/', '', $summary);
                $summary = trim($summary . ($summary !== '' ? ' | ' : '') . 'prefer_layanan=' . $layananPref);
                $set['summary'] = mb_substr($summary, 0, 800);
            }
            $this->saveKurirSession($waNumber, $set);
            $session = $this->getKurirSession($waNumber) ?: $session;
            $session['jenis'] = $jenis;
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            $this->kurirAppendSummary($waNumber, $session, 'jenis=' . $jenis);
            return true;
        }

        // "jemput juga" / "antar juga": gabung dengan summary → antar bila keduanya
        if ($this->kurirLooksAddingOtherJenis($msg)) {
            $resolved = $this->detectKurirJenis($msg, $session) ?: 'antar';
            $this->saveKurirSession($waNumber, ['jenis' => $resolved]);
            $session['jenis'] = $resolved;
            $this->kurirAppendSummary($waNumber, $session, 'jenis_merge=' . $resolved);
            if ($step === 'ask_jenis' || empty($session['id_lokasi'])) {
                $this->saveKurirSession($waNumber, ['step' => 'lokasi_check']);
                $session['step'] = 'lokasi_check';
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return true;
            }
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, kami catat sebagai *{$resolved}* ya."
            );
            return true;
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
            $this->sendAutoreplyText($waNumber, $this->kurirAskJenisPrompt($sapaan));
            return;
        }

        if ($step === 'ask_layanan') {
            $this->kurirHandleAskLayanan($waNumber, $sapaan, $session, $msg);
            return;
        }

        if ($this->kurirLooksWantFast($msg) && in_array($step, ['confirm_lokasi', 'request_aktif', 'lokasi_check', 'pick_lokasi', 'ask_layanan', 'wait_continue_alt'], true)) {
            if (!$this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                if ($step === 'request_aktif') {
                    return;
                }
                if ($step === 'wait_continue_alt') {
                    $this->sendAutoreplyText($waNumber, $this->kurirContinueAltReprompt($sapaan, $session));
                    return;
                }
                if (!empty($session['id_lokasi']) && in_array($step, ['confirm_lokasi', 'ask_layanan'], true)) {
                    $lok = [
                        'id_lokasi' => (int) $session['id_lokasi'],
                        'nama' => (string) ($session['lokasi_nama'] ?? ''),
                        'detail' => (string) ($session['lokasi_detail'] ?? ''),
                        'latt' => (float) ($session['latt'] ?? 0),
                        'longt' => (float) ($session['longt'] ?? 0),
                    ];
                    $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok);
                    return;
                }
                // lokasi_check / pick_lokasi: lanjut switch di bawah (sameday)
            } else {
                $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
                return;
            }
        }

        switch ($step) {
            case 'wait_lokasi':
                if ($this->getLokasiSession($waNumber) !== null) {
                    $this->handleLokasi($phoneIn, $waNumber, $msg);
                    return;
                }
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                break;
            case 'lokasi_check':
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                break;
            case 'ask_shareloc':
                $this->kurirHandleShareloc($waNumber, $sapaan, $session, $msg);
                break;
            case 'ask_lokasi_nama':
            case 'ask_lokasi_detail':
                // Session lama: alihkan ke LOKASI tanpa prompt dobel, lalu proses pesan ini
                $this->saveKurirSession($waNumber, ['step' => 'wait_lokasi']);
                $this->saveLokasiSession($waNumber, [
                    'id_pelanggan' => (int) ($session['id_pelanggan'] ?? 0),
                    'id_lokasi' => (int) ($session['id_lokasi'] ?? 0) ?: null,
                    'latt' => (float) ($session['latt'] ?? 0) ?: null,
                    'longt' => (float) ($session['longt'] ?? 0) ?: null,
                    'lokasi_nama' => trim((string) ($session['lokasi_nama'] ?? '')) ?: null,
                    'step' => 'ask_detail',
                    'last_ask_at' => date('Y-m-d H:i:s'),
                ]);
                $this->handleLokasi($phoneIn, $waNumber, $msg);
                return;
            case 'pick_lokasi':
                $this->kurirHandlePickLokasi($waNumber, $sapaan, $session, $msg);
                break;
            case 'delete_lokasi':
                $this->kurirHandleDeleteLokasiPick($waNumber, $sapaan, $session, $msg);
                break;
            case 'ask_jam_ampm':
                $this->kurirHandleAskJamAmpm($waNumber, $sapaan, $session, $msg);
                break;
            case 'ask_layanan':
                $this->kurirHandleAskLayanan($waNumber, $sapaan, $session, $msg);
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
            || $this->kurirLooksWantDeleteLokasi($msg)
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

    /** Customer menolak lokasi yang ditawarkan / minta alamat lain (bukan hapus/ubah). */
    private function kurirLooksWantOtherLokasi(string $msg): bool
    {
        if ($this->kurirLooksWantDeleteLokasi($msg)) {
            return false;
        }
        return (bool) preg_match(
            '/\b('
            . 'beda(\s*lokasi|\s*tempat|\s*alamat)?'
            . '|lokasi\s*(lain|beda|baru|salah)'
            . '|alamat\s*(lain|beda|baru|salah)'
            . '|tempat\s*(lain|beda|baru|salah)'
            . '|bukan\s*(itu|yang\s*itu|disitu|di\s*situ|sini|sana)?'
            . '|salah\s*(lokasi|tempat|alamat)?'
            . '|pindah\s*(lokasi|alamat|tempat)?'
            . '|bukan\s*rumah'
            . '|lokasi\s*lain'
            . ')\b/iu',
            $msg
        );
    }

    /**
     * Ubah alamat / hapus lokasi → keduanya diperlakukan sebagai hapus.
     * (Tidak ada alur edit in-place.)
     */
    private function kurirLooksWantDeleteLokasi(string $msg): bool
    {
        return (bool) preg_match(
            '/\b('
            . 'hapus(\s*(lokasi|alamat|tempat|ini|aja|saja|dulu))?'
            . '|delete(\s*(lokasi|alamat|tempat))?'
            . '|buang(\s*(lokasi|alamat|tempat))?'
            . '|hilangkan(\s*(lokasi|alamat))?'
            . '|ubah(\s*(lokasi|alamat|tempat|pin))'
            . '|ganti(\s*(lokasi|alamat|tempat))'
            . '|edit(\s*(lokasi|alamat|tempat))'
            . '|update(\s*(lokasi|alamat))'
            . '|ganti\s*alamat'
            . '|ubah\s*alamat'
            . '|hapus\s*alamat'
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
            '/\b(segera|cepat|cepet|instant|instan|gojek|grab|gosend|go\s*send|kilat|buru(-?buru)?|langsung\s*aja)\b/iu',
            $msg
        ) || (bool) preg_match(
            '/\b(sekarang|skrg)\b.*\b(antar|anter|jemput|kurir|kirim|ambil)\b|\b(antar|anter|jemput|kurir|kirim|ambil)\b.*\b(sekarang|skrg)\b/iu',
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

        // Lanjutkan lokasi draft (shareloc sudah ada, nama/detail belum lengkap)
        $incomplete = $this->kurirFindIncompleteLokasi($idPelanggan);
        if ($incomplete !== null) {
            $this->kurirResumeIncompleteLokasi($waNumber, $sapaan, $session, $incomplete);
            return;
        }

        // Session masih punya coords tapi belum selesai isi
        $sessLatt = (float) ($session['latt'] ?? 0);
        $sessLongt = (float) ($session['longt'] ?? 0);
        $sessNama = trim((string) ($session['lokasi_nama'] ?? ''));
        $sessDetail = trim((string) ($session['lokasi_detail'] ?? ''));
        if (abs($sessLatt) > 0.0001 && abs($sessLongt) > 0.0001 && $sessDetail === '') {
            if ($sessNama === '') {
                $this->saveKurirSession($waNumber, ['step' => 'ask_lokasi_detail']);
                $this->sendAutoreplyText(
                    $waNumber,
                    $this->lokasiAskDetailPrompt($sapaan)
                );
            } else {
                $this->saveKurirSession($waNumber, ['step' => 'ask_lokasi_detail']);
                $this->sendAutoreplyText(
                    $waNumber,
                    $this->kurirAskLokasiDetailPrompt($sessNama, $sapaan)
                );
            }
            return;
        }

        $list = $this->kurirListLokasi($idPelanggan);
        // Sembunyikan draft belum lengkap dari daftar pilih
        $list = array_values(array_filter($list, static function ($lok) {
            $n = trim((string) ($lok['nama'] ?? ''));
            $d = trim((string) ($lok['detail'] ?? ''));
            return $n !== '' && $d !== '';
        }));
        if (empty($list)) {
            $this->saveKurirSession($waNumber, ['step' => 'ask_shareloc']);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, kirimkan *shareloc* / pin lokasi WhatsApp atau link Google Maps ya, biar kami catat titik jemput/antar."
            );
            return;
        }

        // 1 lokasi → langsung; >1 → paling sering dipakai (status selesai); imbang → tanya hanya yang imbang
        $candidates = $this->kurirPickLokasiCandidatesByUsage($idPelanggan, $list);
        if (count($candidates) === 1) {
            $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $candidates[0]);
            return;
        }

        $lines = ["Baik {$sapaan}, pilih lokasi " . $this->kurirJenisLabel($session) . ":"];
        foreach ($candidates as $i => $lok) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$lok['nama']}* — {$lok['detail']}";
        }
        $lines[] = "Balas angka pilihan ya {$sapaan}.";
        $this->saveKurirSession($waNumber, [
            'step' => 'pick_lokasi',
            'rates_json' => json_encode(['lokasi_list' => $candidates], JSON_UNESCAPED_UNICODE),
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

    /**
     * Frekuensi id_lokasi dari delivery_request status selesai.
     * @return array<int,int> id_lokasi => count
     */
    private function kurirLokasiUsageCountsSelesai(int $idPelanggan): array
    {
        if ($idPelanggan <= 0) {
            return [];
        }
        try {
            $rows = DB::getInstance(1)->query(
                "SELECT id_lokasi, COUNT(*) AS cnt
                 FROM delivery_request
                 WHERE id_pelanggan = ?
                   AND delivery_status = 'selesai'
                   AND id_lokasi IS NOT NULL
                   AND id_lokasi > 0
                 GROUP BY id_lokasi",
                [$idPelanggan]
            )->result_array();
            $out = [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                $id = (int) ($r['id_lokasi'] ?? 0);
                if ($id > 0) {
                    $out[$id] = (int) ($r['cnt'] ?? 0);
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Pilih kandidat lokasi: pemenang frekuensi sukses, atau semua yang imbang di puncak.
     * @param list<array> $list lokasi lengkap pelanggan
     * @return list<array>
     */
    private function kurirPickLokasiCandidatesByUsage(int $idPelanggan, array $list): array
    {
        if (count($list) <= 1) {
            return array_values($list);
        }

        $counts = $this->kurirLokasiUsageCountsSelesai($idPelanggan);
        $max = 0;
        foreach ($list as $lok) {
            $id = (int) ($lok['id_lokasi'] ?? 0);
            $c = $counts[$id] ?? 0;
            if ($c > $max) {
                $max = $c;
            }
        }

        $tied = [];
        foreach ($list as $lok) {
            $id = (int) ($lok['id_lokasi'] ?? 0);
            if (($counts[$id] ?? 0) === $max) {
                $tied[] = $lok;
            }
        }

        return $tied !== [] ? $tied : array_values($list);
    }

    /**
     * Request sameday terakhir yang sukses (delivery_status=selesai).
     */
    private function kurirLastSuccessfulSamedayRequest(int $idPelanggan): ?array
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        try {
            $row = DB::getInstance(1)->query(
                "SELECT * FROM delivery_request
                 WHERE id_pelanggan = ?
                   AND delivery_status = 'selesai'
                   AND layanan = 'sameday'
                 ORDER BY COALESCE(selesaiTime, insertTime) DESC, id_request DESC
                 LIMIT 1",
                [$idPelanggan]
            )->row();
            return $row ? (array) $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Preferensi layanan dari pesan / summary (bukan default DB sameday).
     * @return 'sameday'|'instant'|null
     */
    private function kurirResolvePreferredLayanan(array $session, string $hintMsg = ''): ?string
    {
        $fromHint = $this->detectKurirLayanan($hintMsg);
        if ($fromHint !== null) {
            return $fromHint;
        }
        $sum = (string) ($session['summary'] ?? '');
        if (preg_match('/prefer_layanan=(instant|sameday)/', $sum, $m)) {
            return $m[1];
        }
        // Hanya percaya layanan=instant (eksplisit); sameday default DB tidak cukup
        if (($session['layanan'] ?? '') === 'instant') {
            return 'instant';
        }
        return null;
    }

    /**
     * Setelah lokasi lengkap: default sameday (tanpa tanya 1/2).
     * Instant hanya jika customer eksplisit minta cepat/grab/gosend/sekarang.
     */
    private function kurirAfterLokasiReady(
        string $waNumber,
        string $sapaan,
        array $session,
        array $lok,
        string $hintMsg = ''
    ): void {
        $latt = (float) ($lok['latt'] ?? 0);
        $longt = (float) ($lok['longt'] ?? 0);
        $nama = (string) ($lok['nama'] ?? '');
        $detail = (string) ($lok['detail'] ?? '');
        $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
        $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);

        $this->saveKurirSession($waNumber, [
            'id_lokasi' => (int) ($lok['id_lokasi'] ?? 0),
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
            'tarif' => (int) $calc['tarif'],
        ]);
        $session = $this->getKurirSession($waNumber) ?: array_merge($session, [
            'id_lokasi' => (int) ($lok['id_lokasi'] ?? 0),
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
            'tarif' => (int) $calc['tarif'],
        ]);

        $pref = $this->kurirResolvePreferredLayanan($session, $hintMsg);
        $wantInstant = ($pref === 'instant') || $this->kurirLooksWantFast($hintMsg);

        // Di luar jam: tidak tawarkan instant → langsung sameday
        if (!$this->isOperatingHours()) {
            if ($wantInstant) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
            }
            $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok);
            return;
        }

        if ($wantInstant) {
            $this->saveKurirSession($waNumber, ['layanan' => 'instant']);
            $session['layanan'] = 'instant';
            $this->kurirStartInstant($waNumber, $sapaan, $session, $hintMsg);
            return;
        }

        // Default sameday — tanpa ask_layanan
        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok);
    }

    private function kurirHandleAskLayanan(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $layanan = $this->detectKurirLayanan($msg);
        if ($layanan === null) {
            if (!$this->isOperatingHours()) {
                // Di luar jam tidak tawarkan pilihan → sameday
                $layanan = 'sameday';
            } else {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Belum jelas {$sapaan}. " . $this->kurirAskLayananPrompt($sapaan)
                );
                return;
            }
        }

        if ($layanan === 'instant' && !$this->isOperatingHours()) {
            $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
            $layanan = 'sameday';
        }

        $summary = preg_replace('/\s*\|\s*prefer_layanan=(instant|sameday)/', '', (string) ($session['summary'] ?? ''));
        $summary = trim($summary . ($summary !== '' ? ' | ' : '') . 'prefer_layanan=' . $layanan);

        if ($layanan === 'instant') {
            $this->saveKurirSession($waNumber, [
                'layanan' => 'instant',
                'summary' => mb_substr($summary, 0, 800),
            ]);
            $session = $this->getKurirSession($waNumber) ?: $session;
            $session['layanan'] = 'instant';
            if (empty($session['id_lokasi']) || empty($session['latt'])) {
                $this->saveKurirSession($waNumber, ['step' => 'lokasi_check']);
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return;
            }
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

        // Sameday
        $this->saveKurirSession($waNumber, [
            'layanan' => 'sameday',
            'summary' => mb_substr($summary, 0, 800),
        ]);
        $session = $this->getKurirSession($waNumber) ?: $session;
        if (empty($session['id_lokasi'])) {
            $this->saveKurirSession($waNumber, ['step' => 'lokasi_check']);
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }
        $lok = [
            'id_lokasi' => (int) $session['id_lokasi'],
            'nama' => (string) ($session['lokasi_nama'] ?? ''),
            'detail' => (string) ($session['lokasi_detail'] ?? ''),
            'latt' => (float) ($session['latt'] ?? 0),
            'longt' => (float) ($session['longt'] ?? 0),
        ];
        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok);
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
        $tarif = (int) $calc['tarif'];
        $idLokasi = (int) ($lok['id_lokasi'] ?? 0);
        $tarifRp = AntarTarif::formatRp($tarif);

        $this->saveKurirSession($waNumber, [
            'step' => 'confirm_lokasi',
            'layanan' => 'sameday',
            'id_lokasi' => $idLokasi,
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
            'tarif' => $tarif,
        ]);
        $session = $this->getKurirSession($waNumber) ?: array_merge($session, [
            'id_lokasi' => $idLokasi,
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
            'tarif' => $tarif,
            'layanan' => 'sameday',
        ]);

        // Riwayat sameday sukses terakhir: lokasi + tarif sama → skip konfirmasi, langsung create
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $lastOk = $this->kurirLastSuccessfulSamedayRequest($idPelanggan);
        if ($lastOk !== null
            && (int) ($lastOk['id_lokasi'] ?? 0) === $idLokasi
            && (int) ($lastOk['tarif_surcas'] ?? 0) === $tarif
        ) {
            $this->logAutoreplyTrace(
                $waNumber,
                'KURIR',
                "skip_confirm same_lokasi_tarif id_lokasi={$idLokasi} tarif={$tarif}"
            );
            $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
            return;
        }

        $this->sendAutoreplyText(
            $waNumber,
            "Konfirmasi {$jenis} ke *{$nama}* ({$detail}) ya {$sapaan}?\n"
            . "Ongkir sameday {$tarifRp}. Balas *ya* untuk lanjut."
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

        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $latt = (float) $coords['lat'];
        $longt = (float) $coords['lng'];

        // Progressive save: langsung simpan lat/long (nama & detail masih kosong)
        if ($idLokasi > 0) {
            $near = $this->lokasiFindNear($idPelanggan, $latt, $longt);
            if ($near !== null && (int) $near['id_lokasi'] !== $idLokasi) {
                // Koordinat hampir sama dengan lokasi lain — pakai yang existing
                $idLokasi = (int) $near['id_lokasi'];
            } else {
                $this->kurirUpdateLokasi($idLokasi, $idPelanggan, [
                    'latt' => $latt,
                    'longt' => $longt,
                ]);
            }
        } else {
            $saved = $this->lokasiUpsertCoords($idPelanggan, $latt, $longt);
            if ($saved === null) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Maaf {$sapaan}, gagal menyimpan titik lokasi. Coba kirim shareloc lagi ya."
                );
                return;
            }
            $idLokasi = (int) $saved['id_lokasi'];
            $incomplete = [
                'id_lokasi' => $idLokasi,
                'nama' => $saved['nama'],
                'detail' => $saved['detail'],
                'latt' => $saved['latt'],
                'longt' => $saved['longt'],
            ];
            if (!$this->lokasiRowIncomplete($incomplete)) {
                $this->saveKurirSession($waNumber, [
                    'id_lokasi' => $idLokasi,
                    'latt' => $saved['latt'],
                    'longt' => $saved['longt'],
                    'lokasi_nama' => $saved['nama'],
                    'lokasi_detail' => $saved['detail'],
                    'step' => 'lokasi_check',
                ]);
                $session = $this->getKurirSession($waNumber) ?: $session;
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return;
            }
            $this->saveKurirSession($waNumber, [
                'id_lokasi' => $idLokasi,
                'latt' => $latt,
                'longt' => $longt,
            ]);
            $session = $this->getKurirSession($waNumber) ?: array_merge($session, [
                'id_lokasi' => $idLokasi,
                'latt' => $latt,
                'longt' => $longt,
            ]);
            $this->lokasiHandOffFromKurir($waNumber, $sapaan, $session, $incomplete);
            return;
        }

        $this->saveKurirSession($waNumber, [
            'id_lokasi' => $idLokasi,
            'latt' => $latt,
            'longt' => $longt,
        ]);
        $session = $this->getKurirSession($waNumber) ?: $session;
        $dbRow = null;
        try {
            $rows = DB::getInstance(1)->query(
                'SELECT id_lokasi, nama, detail, latt, longt FROM pelanggan_lokasi WHERE id_lokasi = ? AND id_pelanggan = ? LIMIT 1',
                [$idLokasi, $idPelanggan]
            )->result_array();
            $dbRow = $rows[0] ?? null;
        } catch (\Throwable $e) {
            $dbRow = null;
        }
        $incomplete = $dbRow ?: [
            'id_lokasi' => $idLokasi,
            'nama' => '',
            'detail' => '',
            'latt' => $latt,
            'longt' => $longt,
        ];
        if (!$this->lokasiRowIncomplete($incomplete)) {
            $this->saveKurirSession($waNumber, [
                'lokasi_nama' => $incomplete['nama'],
                'lokasi_detail' => $incomplete['detail'],
                'step' => 'lokasi_check',
            ]);
            $session = $this->getKurirSession($waNumber) ?: $session;
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }
        $this->lokasiHandOffFromKurir($waNumber, $sapaan, $session, $incomplete);
    }

    private function kurirExtractCoords(string $msg): ?array
    {
        return $this->lokasiExtractCoords($msg);
    }

    private function kurirAskLokasiJenisPrompt(string $sapaan): string
    {
        // LOKASI disingkat: langsung minta detail; nama (Rumah/Kos/Toko/…) diinfer dari jawaban
        return $this->lokasiAskDetailPrompt($sapaan);
    }

    /** Prompt tunggal setelah shareloc — tidak tanya kategori rumah/kos dulu. */
    private function lokasiAskDetailPrompt(string $sapaan): string
    {
        return "Lokasi diterima {$sapaan}. Boleh jelaskan *detailnya* ya?\n"
            . "Contoh: kos Azzahra kamar 2 / rumah pagar kuning / mess BPK / toko sebelah Indomaret";
    }

    /**
     * Pertanyaan detail sesuai jenis lokasi (hasil pilihan kategori) — legacy / fallback.
     */
    private function kurirAskLokasiDetailPrompt(string $nama, string $sapaan): string
    {
        switch (mb_strtolower(trim($nama))) {
            case 'rumah':
                return "Baik {$sapaan}. Boleh sebut *nomor rumah* atau *ciri-ciri rumahnya* ya?";
            case 'kos':
                return "Baik {$sapaan}. *Nama kosnya* apa?";
            case 'mess':
                return "Baik {$sapaan}. Sebut *nama mess* atau *nomor mess* ya (salah satu cukup).";
            case 'asrama':
                return "Baik {$sapaan}. *Nama asramanya* apa?";
            case 'penginapan':
                return "Baik {$sapaan}. Sebut *nama penginapan* dan *nomor kamar*, atau titip di *lobby*?";
            case 'kantor':
                return "Baik {$sapaan}. *Nama kantornya* apa?";
            case 'toko':
                return "Baik {$sapaan}. Sebut *nama toko/warung* atau *ciri-cirinya* ya?";
            default:
                return "Baik {$sapaan}. Boleh jelaskan *detail titiknya* ya?";
        }
    }

    /**
     * @return 'Rumah'|'Kos'|'Mess'|'Asrama'|'Kantor'|'Penginapan'|'Toko'|null
     */
    private function kurirParseLokasiJenis(string $msg): ?string
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return null;
        }
        if (preg_match('/\b(rumah|rmh)\b/iu', $t)) {
            return 'Rumah';
        }
        if (preg_match('/\b(mess|mes)\b/iu', $t)) {
            return 'Mess';
        }
        if (preg_match('/\b(asrama|asrma)\b/iu', $t)) {
            return 'Asrama';
        }
        if (preg_match('/\b(kos|kost|kosan)\b/iu', $t)) {
            return 'Kos';
        }
        if (preg_match('/\b(kantor|office|perusahaan)\b/iu', $t)) {
            return 'Kantor';
        }
        if (preg_match('/\b(penginapan|hotel|apartemen|apartment|kontrakan|inn|homestay)\b/iu', $t)) {
            return 'Penginapan';
        }
        // Toko / tempat usaha (studio, warung, swalayan, kedai, sejenisnya)
        if (preg_match(
            '/\b(toko|studio|warung|swalayan|kedai|minimarket|minimark|mini\s*market|supermarket|kios|outlet|counter|kafe|cafe|coffee\s*shop|restoran|rumah\s*makan|\brm\b|bakery|salon|barbershop|bengkel|apotik|apotek)\b/iu',
            $t
        )) {
            return 'Toko';
        }
        return null;
    }

    /**
     * Alamat "jalan … nomor …" tanpa embel toko/kantor/kos/dll → dianggap Rumah.
     * Contoh: "Jlan pinang, no 45", "Jl. Melati 12A"
     */
    private function lokasiLooksLikeAlamatJalanNomor(string $msg): bool
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', preg_replace("/[\r\n]+/", ' ', $msg))));
        if ($t === '') {
            return false;
        }
        // Kata jalan (termasuk typo jlan / singkatan jl/jln)
        if (!preg_match('/\b(jalan|jln|jlan|jl\.?)\b/iu', $t)) {
            return false;
        }
        // Ada nomor (no/nomor/# + angka, atau angka alamat di akhir/fragmen)
        if (preg_match('/\b(no\.?|nomor|nom\.?|#)\s*\d{1,5}[a-z]?\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b\d{1,5}[a-z]?\b/iu', $t)) {
            return true;
        }
        return false;
    }

    /**
     * Infer nama + detail dari satu jawaban bebas.
     *
     * @return array{nama:string,detail:?string}
     */
    private function lokasiInferNamaDetailFromReply(string $msg): array
    {
        $nama = $this->kurirParseLokasiJenis($msg);
        // Tanpa kategori eksplisit: sebut jalan + nomor → Rumah; gagal deteksi → default Rumah
        if ($nama === null && $this->lokasiLooksLikeAlamatJalanNomor($msg)) {
            $nama = 'Rumah';
        }
        $nama = $nama ?: 'Rumah';

        $detail = $this->kurirExtractLokasiDetailFromJenisReply($msg, $nama);
        if ($detail !== null) {
            return ['nama' => $nama, 'detail' => $detail];
        }
        // Jawaban hanya kata jenis ("kos") atau Rumah tanpa sisa strip → pakai teks utuh
        $t = trim(preg_replace('/\s+/u', ' ', preg_replace("/[\r\n]+/", ' ', $msg)));
        if ($nama === 'Rumah' && mb_strlen($t) >= 2
            && !preg_match('/^(kak|kk|bang|pak|bu|ya|iya|ok|oke|baik|dong|deh|aja|aj)\s*$/iu', $t)
        ) {
            return ['nama' => 'Rumah', 'detail' => mb_substr($t, 0, 255)];
        }
        return ['nama' => $nama, 'detail' => null];
    }

    /**
     * Sisa teks setelah kata jenis — jika ada, dianggap detail sudah lengkap.
     * Contoh: "kos azzahra" → "azzahra"; "rumah pagar kuning" → "pagar kuning".
     */
    private function kurirExtractLokasiDetailFromJenisReply(string $msg, string $nama): ?string
    {
        $t = trim(preg_replace("/[\r\n]+/", ' ', $msg));
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        if ($t === '') {
            return null;
        }

        $strip = '';
        switch (mb_strtolower(trim($nama))) {
            case 'rumah':
                $strip = 'rumah|rmh';
                break;
            case 'kos':
                $strip = 'kos|kost|kosan';
                break;
            case 'mess':
                $strip = 'mess|mes';
                break;
            case 'asrama':
                $strip = 'asrama|asrma';
                break;
            case 'kantor':
                $strip = 'kantor|office|perusahaan';
                break;
            case 'penginapan':
                $strip = 'penginapan|hotel|apartemen|apartment|kontrakan|inn|homestay';
                break;
            case 'toko':
                $strip = 'toko|studio|warung|swalayan|kedai|minimarket|minimark|mini\s*market|supermarket|kios|outlet|counter|kafe|cafe|coffee\s*shop|restoran|rumah\s*makan|\brm\b|bakery|salon|barbershop|bengkel|apotik|apotek';
                break;
            default:
                return null;
        }

        $detail = preg_replace('/\b(?:' . $strip . ')\b/iu', ' ', $t);
        $detail = trim(preg_replace('/\s+/u', ' ', (string) $detail));
        $detail = trim($detail, " \t,.;:-");
        if ($detail === '' || mb_strlen($detail) < 2) {
            return null;
        }
        // Hanya kata sapaan / filler → belum lengkap
        if (preg_match('/^(kak|kk|bang|pak|bu|ya|iya|ok|oke|baik|dong|deh|aja|aj)\s*$/iu', $detail)) {
            return null;
        }
        return mb_substr($detail, 0, 255);
    }

    private function kurirHandleLokasiNama(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        // Legacy: sama seperti LOKASI — infer nama+detail dari satu jawaban (termasuk Toko)
        $inferred = $this->lokasiInferNamaDetailFromReply($msg);
        $nama = $inferred['nama'];
        $detailInline = $inferred['detail'];

        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);

        if ($detailInline !== null && $detailInline !== '') {
            if ($idLokasi > 0) {
                $this->kurirUpdateLokasi($idLokasi, $idPelanggan, ['nama' => $nama]);
            }
            $this->saveKurirSession($waNumber, [
                'lokasi_nama' => $nama,
                'id_lokasi' => $idLokasi > 0 ? $idLokasi : ($session['id_lokasi'] ?? null),
            ]);
            $session = $this->getKurirSession($waNumber) ?: array_merge($session, ['lokasi_nama' => $nama]);
            $this->kurirHandleLokasiDetail($waNumber, $sapaan, $session, $detailInline);
            return;
        }

        if ($this->kurirParseLokasiJenis($msg) !== null) {
            // Hanya kata kategori ("toko"/"kos") tanpa detail → minta detail sesuai jenis
            if ($idLokasi > 0) {
                $this->kurirUpdateLokasi($idLokasi, $idPelanggan, ['nama' => $nama]);
            }
            $this->saveKurirSession($waNumber, [
                'lokasi_nama' => $nama,
                'step' => 'ask_lokasi_detail',
                'id_lokasi' => $idLokasi > 0 ? $idLokasi : ($session['id_lokasi'] ?? null),
            ]);
            $this->sendAutoreplyText(
                $waNumber,
                $this->kurirAskLokasiDetailPrompt($nama, $sapaan)
            );
            return;
        }

        $this->saveKurirSession($waNumber, ['step' => 'ask_lokasi_detail']);
        $this->sendAutoreplyText(
            $waNumber,
            "Detail masih kurang jelas {$sapaan}. "
            . "Contoh: *kos Azzahra kamar 2* / *rumah pagar kuning* / *toko sebelah Indomaret*."
        );
    }

    private function kurirHandleLokasiDetail(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        $detail = trim(preg_replace("/[\r\n]+/", ' ', $msg));
        $detail = trim(preg_replace('/\s+/u', ' ', $detail));
        if (mb_strlen($detail) < 2) {
            $nama = (string) ($session['lokasi_nama'] ?? 'Rumah');
            $this->sendAutoreplyText(
                $waNumber,
                "Detail terlalu singkat {$sapaan}. " . $this->kurirAskLokasiDetailPrompt($nama, $sapaan)
            );
            return;
        }
        if (mb_strlen($detail) > 255) {
            $detail = mb_substr($detail, 0, 255);
        }
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $latt = (float) ($session['latt'] ?? 0);
        $longt = (float) ($session['longt'] ?? 0);
        $nama = (string) ($session['lokasi_nama'] ?? 'Rumah');
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);

        if ($idLokasi > 0) {
            $ok = $this->kurirUpdateLokasi($idLokasi, $idPelanggan, [
                'nama' => $nama,
                'detail' => $detail,
                'latt' => $latt,
                'longt' => $longt,
            ]);
            if (!$ok) {
                $this->sendAutoreplyText($waNumber, "Maaf {$sapaan}, gagal menyimpan detail lokasi. Coba kirim lagi.");
                return;
            }
        } else {
            $idLokasi = $this->kurirInsertLokasi($idPelanggan, $nama, $detail, $latt, $longt);
            if ($idLokasi <= 0) {
                $this->sendAutoreplyText($waNumber, "Maaf {$sapaan}, gagal menyimpan lokasi. Coba kirim detail lagi.");
                return;
            }
        }

        $lok = [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
        ];
        $this->saveKurirSession($waNumber, [
            'id_lokasi' => $idLokasi,
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
        ]);
        $session = $this->getKurirSession($waNumber) ?: $session;
        $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $lok);
    }

    /**
     * Lokasi draft: punya koordinat, tapi nama atau detail masih kosong.
     */
    private function kurirFindIncompleteLokasi(int $idPelanggan): ?array
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        try {
            $rows = DB::getInstance(1)->query(
                "SELECT id_lokasi, nama, detail, latt, longt
                 FROM pelanggan_lokasi
                 WHERE id_pelanggan = ?
                   AND latt IS NOT NULL AND longt IS NOT NULL
                   AND ABS(latt) > 0.0001 AND ABS(longt) > 0.0001
                   AND (
                     nama IS NULL OR TRIM(nama) = ''
                     OR detail IS NULL OR TRIM(detail) = ''
                   )
                 ORDER BY id_lokasi DESC
                 LIMIT 1",
                [$idPelanggan]
            )->result_array();
            if (!empty($rows[0])) {
                return $rows[0];
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    private function kurirResumeIncompleteLokasi(
        string $waNumber,
        string $sapaan,
        array $session,
        array $incomplete
    ): void {
        $nama = trim((string) ($incomplete['nama'] ?? ''));
        $detail = trim((string) ($incomplete['detail'] ?? ''));

        if ($nama !== '' && strcasecmp($nama, 'Shareloc') !== 0 && $detail !== '') {
            $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $incomplete);
            return;
        }

        // Serahkan lengkapi nama/detail ke intent LOKASI (state terpisah)
        $this->lokasiHandOffFromKurir($waNumber, $sapaan, $session, $incomplete);
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

    /**
     * @param array{nama?:string,detail?:string,latt?:float,longt?:float} $fields
     */
    private function kurirUpdateLokasi(int $idLokasi, int $idPelanggan, array $fields): bool
    {
        if ($idLokasi <= 0 || $idPelanggan <= 0) {
            return false;
        }
        $set = [];
        if (array_key_exists('nama', $fields)) {
            $set['nama'] = (string) $fields['nama'];
        }
        if (array_key_exists('detail', $fields)) {
            $set['detail'] = (string) $fields['detail'];
        }
        if (array_key_exists('latt', $fields)) {
            $set['latt'] = round((float) $fields['latt'], 7);
        }
        if (array_key_exists('longt', $fields)) {
            $set['longt'] = round((float) $fields['longt'], 7);
        }
        if (empty($set)) {
            return true;
        }
        try {
            $ok = DB::getInstance(1)->update(
                'pelanggan_lokasi',
                $set,
                ['id_lokasi' => $idLokasi, 'id_pelanggan' => $idPelanggan]
            );
            return $ok !== false;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirUpdateLokasi: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return false;
        }
    }

    private function kurirHandlePickLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksWantDeleteLokasi($msg)) {
            $this->kurirStartDeleteLokasi($waNumber, $sapaan, $session, $msg);
            return;
        }
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
        $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $picked, $msg);
    }

    private function kurirHandleConfirmLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksWantJam($msg)) {
            if (!$this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session)) {
                return;
            }
            $session = $this->getKurirSession($waNumber) ?: $session;
            $this->kurirProcessJamIntent($waNumber, $sapaan, $session, $msg);
            return;
        }
        if ($this->kurirLooksAgree($msg)) {
            $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
            return;
        }
        // Tolak / beda lokasi → jangan ulang prefer lokasi yang sama
        if ($this->kurirLooksWantDeleteLokasi($msg)) {
            $this->kurirStartDeleteLokasi($waNumber, $sapaan, $session, $msg);
            return;
        }
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

    /**
     * Lokasi tersimpan yang sudah lengkap (nama+detail) — untuk pilih/hapus.
     * @return list<array>
     */
    private function kurirCompleteLokasiList(int $idPelanggan): array
    {
        $list = $this->kurirListLokasi($idPelanggan);
        return array_values(array_filter($list, static function ($lok) {
            $n = trim((string) ($lok['nama'] ?? ''));
            $d = trim((string) ($lok['detail'] ?? ''));
            return $n !== '' && $d !== '';
        }));
    }

    /**
     * Ubah/hapus alamat: selalu hapus. 1 lokasi → langsung hapus; >1 → tanya nomor.
     */
    private function kurirStartDeleteLokasi(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $list = $this->kurirCompleteLokasiList($idPelanggan);
        if (empty($list)) {
            $this->sendAutoreplyText(
                $waNumber,
                "Belum ada lokasi tersimpan untuk dihapus {$sapaan}."
            );
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return;
        }

        if (count($list) === 1) {
            $this->kurirPerformDeleteLokasi($waNumber, $sapaan, $session, $list[0]);
            return;
        }

        $lines = ["Baik {$sapaan}, lokasi mana yang ingin dihapus?"];
        foreach ($list as $i => $lok) {
            $n = $i + 1;
            $lines[] = "{$n}. *{$lok['nama']}* — {$lok['detail']}";
        }
        $lines[] = "Balas angka pilihan ya {$sapaan}.";
        $summary = trim((string) ($session['summary'] ?? ''));
        $note = 'minta_hapus_lokasi "' . mb_substr(trim($msg), 0, 80) . '"';
        $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 800);
        $this->saveKurirSession($waNumber, [
            'step' => 'delete_lokasi',
            'rates_json' => json_encode(['lokasi_list' => $list], JSON_UNESCAPED_UNICODE),
            'summary' => $summary,
        ]);
        $this->sendAutoreplyText($waNumber, implode("\n", $lines));
    }

    private function kurirHandleDeleteLokasiPick(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelAndReply($waNumber, $sapaan, $session);
            return;
        }

        $list = [];
        $raw = $session['rates_json'] ?? '';
        if (is_string($raw) && $raw !== '') {
            $j = json_decode($raw, true);
            $list = is_array($j['lokasi_list'] ?? null) ? $j['lokasi_list'] : [];
        }
        if (empty($list)) {
            $list = $this->kurirCompleteLokasiList((int) ($session['id_pelanggan'] ?? 0));
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
                $nama = trim((string) ($lok['nama'] ?? ''));
                if ($nama !== '' && mb_stripos($msg, $nama) !== false) {
                    $picked = $lok;
                    break;
                }
            }
        }

        if ($picked === null) {
            $this->sendAutoreplyText(
                $waNumber,
                "Pilih nomor lokasi yang ingin dihapus ya {$sapaan}."
            );
            return;
        }

        $this->kurirPerformDeleteLokasi($waNumber, $sapaan, $session, $picked);
    }

    /**
     * Hapus 1 baris pelanggan_lokasi lalu lanjut cek lokasi untuk session kurir.
     */
    private function kurirPerformDeleteLokasi(
        string $waNumber,
        string $sapaan,
        array $session,
        array $lok
    ): void {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($lok['id_lokasi'] ?? 0);
        $label = trim((string) ($lok['nama'] ?? ''));
        if ($label === '') {
            $label = 'tersimpan';
        }

        if ($idPelanggan <= 0 || $idLokasi <= 0) {
            $this->sendAutoreplyText($waNumber, "Maaf {$sapaan}, lokasi tidak valid.");
            return;
        }

        try {
            $db = DB::getInstance(1);
            $aktif = 0;
            try {
                $row = $db->query(
                    "SELECT COUNT(*) AS n FROM delivery_request
                     WHERE id_pelanggan = ? AND id_lokasi = ?
                       AND delivery_status IN ('berjalan','menunggu_pembayaran')",
                    [$idPelanggan, $idLokasi]
                )->row();
                $aktif = (int) ($row->n ?? 0);
            } catch (\Throwable $e) {
                $aktif = 0;
            }
            if ($aktif > 0) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Lokasi *{$label}* belum bisa dihapus {$sapaan} karena masih ada permintaan kurir aktif. "
                    . "Balas *batal* dulu jika ingin batalkan permintaan, atau pilih lokasi lain."
                );
                return;
            }

            $ok = $db->delete_limit(
                'pelanggan_lokasi',
                ['id_lokasi' => $idLokasi, 'id_pelanggan' => $idPelanggan],
                1
            );
            if ($ok === false) {
                throw new \RuntimeException('delete_lokasi failed');
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirPerformDeleteLokasi: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, gagal menghapus lokasi. Coba lagi ya."
            );
            return;
        }

        $summary = trim((string) ($session['summary'] ?? ''));
        $note = 'hapus_lokasi id=' . $idLokasi . ' "' . mb_substr($label, 0, 60) . '"';
        $summary = mb_substr(($summary !== '' ? $summary . ' | ' : '') . $note, 0, 800);

        $hadRequest = !empty($session['id_request']);
        $clear = [
            'summary' => $summary,
            'rates_json' => null,
        ];
        if ((int) ($session['id_lokasi'] ?? 0) === $idLokasi) {
            $clear['id_lokasi'] = null;
            $clear['lokasi_nama'] = null;
            $clear['lokasi_detail'] = null;
            $clear['latt'] = null;
            $clear['longt'] = null;
            $clear['tarif'] = null;
        }
        $this->saveKurirSession($waNumber, $clear);

        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}, lokasi *{$label}* telah dihapus."
        );

        // Request sudah aktif: cukup konfirmasi hapus, jangan reset alur
        if ($hadRequest) {
            $this->saveKurirSession($waNumber, ['step' => 'request_aktif']);
            return;
        }

        $session = $this->getKurirSession($waNumber) ?: $session;
        $this->saveKurirSession($waNumber, ['step' => 'lokasi_check']);
        $session['step'] = 'lokasi_check';
        $this->kurirLokasiCheck($waNumber, $sapaan, $session);
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
        // Buang draft lokasi lama agar resume tidak nyangkut ke titik sebelumnya
        $this->kurirClearIncompleteLokasi((int) ($session['id_pelanggan'] ?? 0));
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

    /** Hapus draft lokasi (coords ada, nama/detail kosong) — saat minta shareloc baru. */
    private function kurirClearIncompleteLokasi(int $idPelanggan): void
    {
        if ($idPelanggan <= 0) {
            return;
        }
        try {
            DB::getInstance(1)->query(
                "DELETE FROM pelanggan_lokasi
                 WHERE id_pelanggan = ?
                   AND (
                     nama IS NULL OR TRIM(nama) = ''
                     OR detail IS NULL OR TRIM(detail) = ''
                   )",
                [$idPelanggan]
            );
        } catch (\Throwable $e) {
            // ignore
        }
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
            if (!$this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                return;
            }
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

        $wantJam = $this->kurirLooksWantJam($msg);
        if ($wantJam) {
            $this->kurirProcessJamIntent($waNumber, $sapaan, $session, $msg);
            return;
        }

        $this->sendAutoreplyText(
            $waNumber,
            "Permintaan " . $this->kurirJenisLabel($session) . " sudah kami terima {$sapaan}. "
            . "Kalau ada jam tertentu atau ingin batalkan, tinggal bilang saja ya."
        );
    }

    /**
     * Customer minta jam: spesifik → grant (atau cutoff), "jam berapa" → butuh estimasi petugas.
     */
    private function kurirProcessJamIntent(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        ?array $waktuOverride = null
    ): void {
        $waktu = $waktuOverride;
        if ($waktu === null) {
            $waktu = $this->parseEstimasiRequestWaktu($msg);
        }

        // "hari ini/besok jam berapa?" → petugas isi perkiraan
        if ($waktu === null || (!$this->estimasiWaktuIsResolved($waktu) && empty($waktu['ask_ampm']))) {
            if ($this->kurirLooksAskJamBerapa($msg) || $this->kurirLooksWantJam($msg)) {
                $preferTgl = $this->kurirPreferTanggalFromMsg($msg);
                $this->kurirEscalateJamEstimasi($waNumber, $sapaan, $session, $msg, $preferTgl);
                return;
            }
        }

        if (!empty($waktu['ask_ampm'])) {
            $rawH = (int) ($waktu['raw_hour'] ?? 0);
            $tgl = $waktu['tanggal'] ?? date('Y-m-d');
            $this->saveKurirSession($waNumber, [
                'step' => 'ask_jam_ampm',
                'request_text' => $msg,
                'request_tanggal' => $tgl,
                'request_jam' => null,
                'request_granted' => null,
                'summary' => mb_substr(
                    trim((string) ($session['summary'] ?? '') . ' | ask_ampm hour=' . $rawH),
                    0,
                    800
                ),
            ]);
            $this->replyAskJamPagiMalam($waNumber, $sapaan, $rawH);
            return;
        }

        if ($this->estimasiWaktuIsResolved($waktu)) {
            $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, $msg, $waktu);
            return;
        }

        // Fallback: tanya estimasi
        $this->kurirEscalateJamEstimasi(
            $waNumber,
            $sapaan,
            $session,
            $msg,
            $this->kurirPreferTanggalFromMsg($msg)
        );
    }

    private function kurirLooksAskJamBerapa(string $msg): bool
    {
        return (bool) preg_match('/\bjam\s*(brp|brpa|berapa)\b/iu', $msg)
            || (bool) preg_match('/\b(kapan|kira[\s\-]*kira)\b.{0,40}\b(dijemput|diantar|jemput|antar)\b/iu', $msg)
            || (bool) preg_match('/\b(dijemput|diantar|jemput|antar)\b.{0,40}\b(jam\s*)?(brp|berapa|kapan)\b/iu', $msg);
    }

    private function kurirPreferTanggalFromMsg(string $msg): ?string
    {
        if (preg_match('/\b(besok|bsk)\b/iu', $msg)) {
            return date('Y-m-d', strtotime('+1 day'));
        }
        if (preg_match('/\blusa\b/iu', $msg)) {
            return date('Y-m-d', strtotime('+2 day'));
        }
        if (preg_match('/\b(hari\s*ini|hr\s*ini)\b/iu', $msg)) {
            return date('Y-m-d');
        }
        return null;
    }

    /** ≥16:00: soft-ack "petugas alternatif" untuk request jam HARI INI (tetap escalate). */
    private function kurirIsPastSamedayJamCutoff(): bool
    {
        return ((int) date('G')) >= 16;
    }

    private function kurirHandleAskJamAmpm(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelAndReply($waNumber, $sapaan, $session);
            return;
        }
        if (!preg_match('/\b(pagi|malam)\b/iu', $msg)) {
            $rawH = 8;
            if (preg_match('/ask_ampm hour=(\d{1,2})/', (string) ($session['summary'] ?? ''), $m)) {
                $rawH = (int) $m[1];
            }
            $this->replyAskJamPagiMalam($waNumber, $sapaan, $rawH);
            return;
        }
        $rawH = 8;
        if (preg_match('/ask_ampm hour=(\d{1,2})/', (string) ($session['summary'] ?? ''), $m)) {
            $rawH = (int) $m[1];
        } elseif (preg_match('/\bjam\s*(\d{1,2})\b/iu', (string) ($session['request_text'] ?? ''), $m2)) {
            $rawH = (int) $m2[1];
        }
        $ampm = preg_match('/\bmalam\b/iu', $msg) ? 'malam' : 'pagi';
        $synthetic = "jam {$rawH} {$ampm}";
        $prev = (string) ($session['request_text'] ?? '');
        if (preg_match('/\b(besok|bsk|hari\s*ini|lusa)\b/iu', $prev, $dayM)) {
            $synthetic .= ' ' . $dayM[0];
        }
        $waktu = $this->parseEstimasiRequestWaktu($synthetic);
        if (!$this->estimasiWaktuIsResolved($waktu)) {
            $this->replyAskJamPagiMalam($waNumber, $sapaan, $rawH);
            return;
        }
        $this->kurirEscalateJamRequest($waNumber, $sapaan, $session, $prev !== '' ? $prev : $synthetic, $waktu);
    }

    /**
     * "Jam berapa jemput/antar?" → bell kurir_estimasi (petugas isi tanggal+jam).
     */
    private function kurirEscalateJamEstimasi(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        ?string $preferTgl
    ): void {
        $tglHint = $preferTgl;
        $today = date('Y-m-d');
        if ($tglHint === null || $tglHint === '') {
            $tglHint = $today;
        }

        $pastCutoffToday = ($tglHint === $today && $this->kurirIsPastSamedayJamCutoff());

        $this->saveKurirSession($waNumber, [
            'step' => 'wait_driver_jam',
            'request_text' => $msg,
            'request_tanggal' => $tglHint,
            'request_jam' => null,
            'request_granted' => null,
            'butuh_estimasi' => 1,
            'estimasi_tanggal' => null,
            'estimasi_jam' => null,
            'driver_alt_tanggal' => null,
            'driver_alt_jam' => null,
        ]);
        if ($pastCutoffToday) {
            // Soft "petugas alternatif" HANYA di jam operasional; luar jam → escalate ack next hours
            if ($this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirPastCutoffAlternatifAck($sapaan));
            } else {
                $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
            }
        } elseif (!$this->isOperatingHours()) {
            $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
        } else {
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami tanyakan dulu ke driver ya.");
        }
        $this->kurirForwardJamEstimasiToGroups($waNumber, $session, $msg, $tglHint);
    }

    /**
     * Soft-ack ≥16:00 (hari ini, jam permintaan ≤20) — HANYA saat jam operasional.
     * <17:00 = dekat jam pulang; ≥17:00 = sudah pulang.
     */
    private function kurirPastCutoffAlternatifAck(string $sapaan): string
    {
        if (((int) date('G')) >= 17) {
            return "Maaf {$sapaan}, Abang driver sudah pulang. "
                . "Kami coba tanyakan dulu ke petugas alternatif ya {$sapaan}.";
        }

        return "Maaf {$sapaan}, Abang driver sudah tinggal menyelesaikan sisa rute terakhir dan dekat jam pulang. "
            . "Kami coba tanyakan dulu ke petugas alternatif ya {$sapaan}.";
    }

    /** Ack escalate di luar jam operasional (task tetap pending untuk petugas). */
    private function kurirEscalateOutsideHoursAck(string $sapaan): string
    {
        return "Baik {$sapaan}, saat ini di luar jam operasional. "
            . "Kami catat dulu dan tanyakan ke petugas di jam operasional berikutnya ya {$sapaan}.";
    }

    /** Tolak Grab/Gojek/instant di luar jam operasional. */
    private function kurirRejectInstantOutsideHoursAck(string $sapaan): string
    {
        return "Maaf {$sapaan}, layanan *Grab/Gojek/instant* tidak tersedia di luar jam operasional. "
            . "Silakan gunakan kurir *sameday* atau hubungi kami lagi saat jam buka ya {$sapaan}.";
    }

    /** Jam permintaan hari ini > 20:00 → hard-cut, jadwalkan besok (tanpa escalate). */
    private function kurirIsJamAfterNightCutoff(?float $jam): bool
    {
        return $jam !== null && (float) $jam > 20.0;
    }

    /**
     * Hard-cut: tidak escalate, balas jadwalkan besok.
     * Teks ikut jam sekarang (≥17 = sudah pulang, else dekat jam pulang).
     */
    private function kurirReplyHardCutScheduleTomorrow(
        string $waNumber,
        string $sapaan,
        array $session
    ): void {
        $noun = $this->kurirJenisNoun($session);
        $this->saveKurirSession($waNumber, [
            'step' => 'request_aktif',
            'butuh_estimasi' => 0,
            'request_jam' => null,
            'request_granted' => null,
            'estimasi_tanggal' => null,
            'estimasi_jam' => null,
        ]);
        if (((int) date('G')) >= 17) {
            $text = "Maaf {$sapaan}, Abang driver sudah pulang. "
                . "Kami jadwalkan {$noun} *besok* ya {$sapaan}.";
        } else {
            $text = "Maaf {$sapaan}, Abang driver sudah tinggal menyelesaikan sisa rute terakhir dan dekat jam pulang. "
                . "Kami jadwalkan {$noun} *besok* ya {$sapaan}.";
        }
        $this->sendAutoreplyText($waNumber, $text);
    }

    private function kurirForwardJamEstimasiToGroups(
        string $waNumber,
        array $session,
        string $msg,
        string $tglHint
    ): void {
        $nama = trim($this->getContactNameForGreeting($waNumber)) ?: 'Pelanggan';
        $jenis = $this->kurirJenisLabel($session);
        $hari = ($tglHint === date('Y-m-d')) ? 'hari ini' : (($tglHint === date('Y-m-d', strtotime('+1 day'))) ? 'besok' : $tglHint);
        $groupText = "{$nama} tanya {$jenis} {$hari} jam berapa. \"{$msg}\". (AI Agent — isi estimasi)";

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
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirForwardJamEstimasiToGroups: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function kurirEscalateJamRequest(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        array $waktu
    ): void {
        if (!$this->estimasiWaktuIsResolved($waktu)) {
            $this->kurirProcessJamIntent($waNumber, $sapaan, $session, $msg, $waktu);
            return;
        }
        $tgl = $waktu['tanggal'] ?? date('Y-m-d');
        $jam = $waktu['jam'];
        $today = date('Y-m-d');

        // Hari ini + jam permintaan > 20:00 → hard-cut jadwalkan besok (tanpa escalate)
        if ($tgl === $today && $this->kurirIsJamAfterNightCutoff(isset($jam) ? (float) $jam : null)) {
            $this->kurirReplyHardCutScheduleTomorrow($waNumber, $sapaan, $session);
            return;
        }

        $pastCutoffToday = ($tgl === $today && $this->kurirIsPastSamedayJamCutoff());

        $this->saveKurirSession($waNumber, [
            'step' => 'wait_driver_jam',
            'request_text' => $msg,
            'request_tanggal' => $tgl,
            'request_jam' => $jam,
            'request_granted' => null,
            'butuh_estimasi' => 0,
            'estimasi_tanggal' => null,
            'estimasi_jam' => null,
            'driver_alt_tanggal' => null,
            'driver_alt_jam' => null,
        ]);
        if ($pastCutoffToday) {
            if ($this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirPastCutoffAlternatifAck($sapaan));
            } else {
                $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
            }
        } elseif (!$this->isOperatingHours()) {
            $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
        } else {
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami tanyakan driver dulu ya {$sapaan}.");
        }
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
        // Driver tolak jam → customer boleh pilih instant (grab/gosend/sekarang)
        if ($this->kurirLooksWantFast($msg) || $this->detectKurirLayanan($msg) === 'instant') {
            if (!$this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                $this->sendAutoreplyText($waNumber, $this->kurirContinueAltReprompt($sapaan, $session));
                return;
            }
            $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
            return;
        }

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
        $this->sendAutoreplyText($waNumber, $this->kurirContinueAltReprompt($sapaan, $session));
    }

    private function kurirContinueAltReprompt(string $sapaan, array $session): string
    {
        $jenis = $this->kurirJenisLabel($session);
        $altTgl = (string) ($session['driver_alt_tanggal'] ?? '');
        $altJam = $session['driver_alt_jam'] ?? null;
        $altPart = '';
        if ($altJam !== null && $altJam !== '') {
            $altPart = ' jam alternatif ' . $this->formatKurirJamLabel((float) $altJam);
            if ($altTgl !== '') {
                $altPart .= " ({$altTgl})";
            }
        }
        $text = "Apakah permintaan {$jenis} tetap dilanjutkan{$altPart} {$sapaan}?";
        if ($this->isOperatingHours()) {
            $text .= " Atau mau pakai *instant* (Grab/Gojek)?";
        }
        return $text;
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
        if (!$this->isOperatingHours()) {
            $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
            return;
        }

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
                $actions = array_merge($common, ['confirm']); // confirm = pilih jenis via slots later → treat as clarify if no jenis
                break;
            case 'lokasi_check':
                $actions = array_merge($common, ['other_lokasi', 'delete_lokasi', 'ask_shareloc', 'want_instant', 'want_jam']);
                break;
            case 'ask_shareloc':
                $actions = array_merge($common, ['ask_shareloc']);
                break;
            case 'ask_lokasi_nama':
            case 'ask_lokasi_detail':
                $actions = array_merge($common, ['confirm']);
                break;
            case 'ask_layanan':
                $actions = array_merge($common, ['pick_layanan', 'want_instant', 'confirm', 'other_lokasi', 'delete_lokasi']);
                break;
            case 'pick_lokasi':
                $actions = array_merge($common, ['pick_lokasi', 'other_lokasi', 'delete_lokasi', 'ask_shareloc', 'want_instant']);
                break;
            case 'delete_lokasi':
                $actions = array_merge($common, ['delete_lokasi', 'pick_lokasi']);
                break;
            case 'confirm_lokasi':
                $actions = array_merge($common, ['confirm', 'other_lokasi', 'delete_lokasi', 'ask_shareloc', 'want_jam', 'want_instant']);
                break;
            case 'terms_setuju':
            case 'request_aktif':
                $actions = array_merge($common, ['want_jam', 'want_instant', 'noop_ack', 'delete_lokasi']);
                break;
            case 'wait_driver_jam':
                $actions = array_merge($common, ['noop_ack', 'want_jam']);
                break;
            case 'ask_jam_ampm':
                $actions = array_merge($common, ['want_jam', 'confirm']);
                break;
            case 'wait_continue_alt':
                $actions = array_merge($common, ['agree_alt', 'refuse_alt', 'want_instant']);
                break;
            case 'instant_confirm':
                $actions = array_merge($common, ['confirm', 'refuse_alt', 'other_lokasi', 'delete_lokasi']);
                break;
            case 'instant_pick':
                $actions = array_merge($common, ['pick_lokasi', 'other_lokasi', 'delete_lokasi']);
                break;
            default:
                $actions = array_merge($common, ['noop_ack', 'other_lokasi', 'delete_lokasi', 'want_jam', 'want_instant', 'confirm', 'pick_layanan']);
                break;
        }

        // Di luar jam: jangan biarkan AI pilih instant / ask layanan
        if (!$this->isOperatingHours()) {
            $actions = array_values(array_filter(
                $actions,
                static function ($a) {
                    return !in_array($a, ['want_instant', 'pick_layanan'], true);
                }
            ));
        }

        return $actions;
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
            . "Jika customer minta ubah alamat / ganti alamat / hapus lokasi → delete_lokasi (bukan edit; selalu hapus). "
            . "Di step delete_lokasi: customer pilih nomor lokasi yang dihapus → delete_lokasi + slots.pick_index. "
            . "Jika setuju lokasi/ongkir → confirm. "
            . "Jika batal/gak jadi/cancel → cancel. "
            . "Jika minta jam tertentu → want_jam (isi slots.jam/tanggal jika ada). "
            . "Jam 1-6 tanpa 'pagi' biasanya sore (jam 3=15). Tanya 'jam berapa' tanpa angka tetap want_jam. "
            . "Jika minta cepat/gojek/grab/gosend/instant/sekarang → want_instant (langsung, jangan tanya sameday lagi). "
            . "Layanan default selalu sameday; jangan tawarkan pilihan 1/2 kecuali customer minta instant. "
            . "Typo anter/dianter = antar. Ambil kain kotor = jemput. Bawakan kain yang siap = antar. "
            . "Jika customer minta antar sekaligus jemput (atau 'jemput juga' / ambil kotor + bawakan siap) → jenis antar, action confirm / lanjut flow, jangan clarify. "
            . "Di step wait_continue_alt: setuju jam alternatif driver → agree_alt; tolak/batal → refuse_alt; mau grab/instant → want_instant. "
            . "Di step ask_layanan (legacy): customer pilih sameday atau instant — action pick_layanan, isi slots.layanan = sameday|instant. "
            . "Jawaban bebas seperti 'sameday', 'grab', 'gosend', 'yang biasa' tetap pick_layanan di step itu. "
            . "Jika typo/kurang jelas → action unrelated atau diam (jangan clarify / jangan minta diketik ulang). "
            . "Di step ask_lokasi_nama / ask_lokasi_detail: customer jelaskan detail dalam satu jawaban (tidak dipilihkan kategori dulu). "
            . "Sistem infer kategori: Rumah/Kos/Mess/Asrama/Kantor/Penginapan/Toko (default Rumah jika tidak jelas). "
            . "Toko = studio/toko/warung/swalayan/kedai/minimarket/kios/cafe/sejenisnya. "
            . "Jika jawaban lengkap (kos azzahra kamar 2, rumah pagar kuning, toko sebelah Indomaret, mess BPK, hotel titip lobby) → confirm/lanjut tanpa tanya ulang. "
            . "Jika topik lain (estimasi siap, bill, harga, status, salam penutup, dll) → unrelated (jangan balas sebagai kurir). "
            . "Jangan minta shareloc jika pesan jelas tentang estimasi siap/hari ini. "
            . "Field reply: kalimat WhatsApp singkat (boleh kosong). "
            . "Jawab HANYA JSON valid tanpa markdown.";

        $user = $context . "\n\nFORMAT:\n"
            . '{"action":"...", "reply":"...", "suggested_text":"...", '
            . '"slots":{"jam":null,"tanggal":null,"pick_index":null,"jenis":null,"layanan":null}, '
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
                        $jenis = $this->detectKurirJenis($msg, $session);
                    }
                    if ($jenis) {
                        $set = ['jenis' => $jenis, 'step' => 'lokasi_check'];
                        $layananPref = null;
                        $slotLayanan = strtolower((string) ($slots['layanan'] ?? ''));
                        if (in_array($slotLayanan, ['sameday', 'instant'], true)) {
                            $layananPref = $slotLayanan;
                        } else {
                            $layananPref = $this->detectKurirLayanan($msg);
                        }
                        if ($layananPref) {
                            $set['layanan'] = $layananPref;
                            $summary = trim((string) ($session['summary'] ?? ''));
                            $summary = preg_replace('/\s*\|\s*prefer_layanan=(instant|sameday)/', '', $summary);
                            $set['summary'] = mb_substr(
                                trim($summary . ($summary !== '' ? ' | ' : '') . 'prefer_layanan=' . $layananPref),
                                0,
                                800
                            );
                        }
                        $this->saveKurirSession($waNumber, $set);
                        $session = $this->getKurirSession($waNumber) ?: $session;
                        $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                        $this->kurirAppendSummary($waNumber, $session, $note);
                        return;
                    }
                    $this->sendAutoreplyText(
                        $waNumber,
                        $aiReply ?: $this->kurirAskJenisPrompt($sapaan)
                    );
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if ($step === 'ask_layanan') {
                    $pickMsg = $msg;
                    $slotLayanan = strtolower((string) ($slots['layanan'] ?? ''));
                    if (in_array($slotLayanan, ['sameday', 'instant'], true)) {
                        $pickMsg = $slotLayanan;
                    }
                    $this->kurirHandleAskLayanan($waNumber, $sapaan, $session, $pickMsg);
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

            case 'delete_lokasi':
                $step = (string) ($session['step'] ?? '');
                if ($step === 'delete_lokasi') {
                    $pickMsg = $msg;
                    if (isset($slots['pick_index']) && $slots['pick_index'] !== null && $slots['pick_index'] !== '') {
                        $pickMsg = (string) ((int) $slots['pick_index']);
                    }
                    $this->kurirHandleDeleteLokasiPick($waNumber, $sapaan, $session, $pickMsg);
                } else {
                    $this->kurirStartDeleteLokasi($waNumber, $sapaan, $session, $msg);
                }
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

            case 'pick_layanan':
                $pickMsg = $msg;
                $slotLayanan = strtolower((string) ($slots['layanan'] ?? ''));
                if (in_array($slotLayanan, ['sameday', 'instant'], true)) {
                    $pickMsg = $slotLayanan;
                }
                $this->kurirHandleAskLayanan($waNumber, $sapaan, $session, $pickMsg);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'want_jam':
                $waktu = null;
                if (!empty($slots['jam'])) {
                    $jamRaw = (float) $slots['jam'];
                    $h = (int) floor($jamRaw);
                    $min = (int) round(($jamRaw - $h) * 100);
                    if ($min > 59) {
                        $min = (int) round(($jamRaw - $h) * 60);
                    }
                    $tgl = !empty($slots['tanggal']) ? (string) $slots['tanggal'] : date('Y-m-d');
                    $norm = $this->normalizeLaundryCustomerJam($h, $min, $msg);
                    if (!empty($norm['ask_ampm'])) {
                        $waktu = [
                            'jam' => null,
                            'tanggal' => $tgl,
                            'ask_ampm' => true,
                            'raw_hour' => $h,
                            'raw_min' => $min,
                        ];
                    } else {
                        $waktu = ['jam' => (float) $norm['jam'], 'tanggal' => $tgl];
                    }
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
                // Jangan kirim AI reply dulu — PHP yang balas (ack / cutoff / tanya ampm)
                $this->kurirProcessJamIntent($waNumber, $sapaan, $session, $msg, $waktu);
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
                // Jangan spam klarifikasi — abaikan jika AI tidak paham
                $this->kurirAppendSummary($waNumber, $session, $note ?: ('clarify_ignored: ' . mb_substr($msg, 0, 60)));
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'clarify_ignored');
                return;

            default:
                // Aksi tidak dikenal / tidak jelas → diam, jangan minta diperjelas
                $this->kurirAppendSummary($waNumber, $session, $note);
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'unknown_action_ignored action=' . $action);
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
