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
        $step = (string) $merge('step', 'ask_jenis');
        $expires = date('Y-m-d H:i:s', time() + (self::KURIR_SESSION_TTL_MINUTES * 60));
        $sekalianJemput = (!empty($data['sekalian_jemput']) || !empty($existing['sekalian_jemput'])) ? 1 : 0;
        $vals = [
            $phone,
            $merge('id_pelanggan'),
            $merge('id_cabang'),
            $merge('jenis'),
            $sekalianJemput,
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
            $merge('group_notify_label'),
            $now,
            $expires,
        ];

        try {
            DB::getInstance(0)->query(
                'INSERT INTO wa_kurir_session
                  (phone, id_pelanggan, id_cabang, jenis, sekalian_jemput, layanan, step, id_lokasi, lokasi_nama, lokasi_detail,
                   latt, longt, tarif, request_text, request_tanggal, request_jam, request_granted,
                   butuh_estimasi, estimasi_tanggal, estimasi_jam, butuh_update_nama,
                   driver_alt_tanggal, driver_alt_jam, courier_company, courier_type, courier_name,
                   ongkir, rates_json, id_request, summary, group_notify_label, updated_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   id_pelanggan=VALUES(id_pelanggan), id_cabang=VALUES(id_cabang), jenis=VALUES(jenis),
                   sekalian_jemput=VALUES(sekalian_jemput),
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
                   group_notify_label=VALUES(group_notify_label),
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
        $expires = date('Y-m-d H:i:s', time() + (self::KURIR_SESSION_TTL_MINUTES * 60));
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
        // Pertanyaan ongkir/ongkos saja → lepas session kurir, bukan follow-up minta antar/jemput
        if ($this->messageLooksLikeOngkirOngkosInquiryOnly($text)) {
            return true;
        }
        if (preg_match('/\b(bon|bill|bil{1,}|tagihan|nota|invoice|pricelist|price\s*list)\b/iu', $text)) {
            return true;
        }
        // Ucapan terima kasih = penutup, bukan konfirmasi ya/ok lokasi
        if ($this->messageLooksLikeThanksPenutup($text)) {
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
            'KARYAWAN', 'KAS_LAUNDRY', 'CEK_TOKEN',
            'CEK_QRIS', 'SALDO', 'SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD', 'INFO_FONNTE', 'TARIK_TOKOPAY',
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
            $resolved = $this->kurirResolveJenisState($msg, null);
            $jenis = $resolved['jenis'];
            // Ambigu: ada order aktif → antar; tanpa order / baru → jemput (jangan tanya)
            if (!$jenis) {
                $jenis = $this->kurirInferJenisWhenAmbiguous($phoneIn, $waNumber);
            }
            $sekalianJemput = ($jenis === 'antar') ? (int) $resolved['sekalian_jemput'] : 0;
            // Antar: wajib ada sale belum tuntas + belum ambil (bin=0) sebelum lokasi / early activate
            if ($jenis === 'antar') {
                $sapaan = $this->getSapaanForGreeting($waNumber);
                if (!$this->kurirAllowAntarOrReject($waNumber, $idPelanggan, $sapaan)) {
                    return true;
                }
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
            if ($sekalianJemput) {
                $summary .= ' | sekalian_jemput=1';
            }
            $this->saveKurirSession($waNumber, [
                'id_pelanggan' => $idPelanggan,
                'id_cabang' => $idCabang,
                'jenis' => $jenis,
                'sekalian_jemput' => $sekalianJemput,
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
            // Early activate: board Delivery langsung dapat Jemput/Antar (lokasi boleh menyusul)
            $this->kurirEarlyActivateRequest($waNumber, $session);
            $session = $this->getKurirSession($waNumber) ?: $session;

            $sapaan = $this->getSapaanForGreeting($waNumber);
            // Chat langsung minta grab/gosend di luar jam → tolak sekali, lanjut sameday
            if ($outsideHours && $this->kurirLooksWantFast($msg)) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
            }

            // Request waktu / butuh estimasi di pesan pertama: simpan + ack driver dulu, baru state lokasi
            $this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg);
            $session = $this->getKurirSession($waNumber) ?: $session;

            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return true;
        }

        return $this->routeKurirStep($phoneIn, $waNumber, $msg, $session);
    }

    /**
     * Jenis kurir jika pesan ambigu: ada sale antarable (belum tuntas/ambil, bin=0) → antar; selain itu jemput.
     */
    private function kurirInferJenisWhenAmbiguous(string $phoneIn, string $waNumber): string
    {
        return $this->pelangganHasAntarableSaleForWa($phoneIn, $waNumber) ? 'antar' : 'jemput';
    }

    /**
     * Ada sale pelanggan yang boleh diantar: bin=0, tuntas=0, id_user_ambil=0.
     */
    private function pelangganHasAntarableSale(int $idPelanggan): bool
    {
        if ($idPelanggan <= 0) {
            return false;
        }
        try {
            $rows = DB::getInstance(1)->query(
                'SELECT id_penjualan FROM sale
                 WHERE id_pelanggan = ?
                   AND bin = 0
                   AND tuntas = 0
                   AND id_user_ambil = 0
                 LIMIT 1',
                [$idPelanggan]
            )->result_array();

            return !empty($rows);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('pelangganHasAntarableSale: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }

            return false;
        }
    }

    /** Cek antarable sale lewat nomor WA (bisa multi id_pelanggan). */
    private function pelangganHasAntarableSaleForWa(string $phoneIn, string $waNumber): bool
    {
        try {
            $db1 = DB::getInstance(1);
            $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
            $idPelanggans = array_column($pelanggan, 'id_pelanggan');
            if (empty($idPelanggans)) {
                return false;
            }
            $idsIn = implode(',', array_map('intval', $idPelanggans));
            $sales = $db1->query(
                "SELECT id_penjualan FROM sale
                 WHERE tuntas = 0 AND bin = 0 AND id_user_ambil = 0
                   AND id_pelanggan IN ($idsIn)
                 LIMIT 1"
            )->result_array();

            return !empty($sales);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('pelangganHasAntarableSaleForWa: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }

            return false;
        }
    }

    private function kurirFetchPelangganNama(int $idPelanggan): string
    {
        if ($idPelanggan <= 0) {
            return 'PELANGGAN';
        }
        try {
            $row = DB::getInstance(1)->query(
                'SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            )->row();
            $nama = strtoupper(trim((string) ($row->nama_pelanggan ?? '')));

            return $nama !== '' ? $nama : 'PELANGGAN';
        } catch (\Throwable $e) {
            return 'PELANGGAN';
        }
    }

    /** Tolak antar: semua laundry sudah diambil. */
    private function kurirAntarNoEligibleOrderReply(string $sapaan, string $namaPelanggan): string
    {
        $nama = strtoupper(trim($namaPelanggan));
        if ($nama === '') {
            $nama = 'PELANGGAN';
        }
        $emoji = $this->pickPenutupSoftSmile();

        return "Maaf {$sapaan}, semua laundry an. {$nama} sudah diambil {$emoji}";
    }

    /**
     * Gate antar sebelum lokasi/early-activate.
     * @return bool true = boleh lanjut; false = sudah kirim tolak + clear session
     */
    private function kurirAllowAntarOrReject(string $waNumber, int $idPelanggan, string $sapaan): bool
    {
        if ($this->pelangganHasAntarableSale($idPelanggan)) {
            return true;
        }
        $nama = $this->kurirFetchPelangganNama($idPelanggan);
        $this->sendAutoreplyText($waNumber, $this->kurirAntarNoEligibleOrderReply($sapaan, $nama));
        $this->clearKurirSession($waNumber);
        $this->logAutoreplyTrace($waNumber, 'MINTA_JEMPUT_ANTAR', 'antar_reject_no_unpicked_sale id_pelanggan=' . $idPelanggan);

        return false;
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
            return $this->routeKurirUnregisteredStep($waNumber, $sapaan, $session, $msg);
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
        $this->sendAutoreplyText($waNumber, $this->kurirAskNewNamaPrompt($sapaan));
        return true;
    }

    private function kurirAskNewNamaPrompt(string $sapaan): string
    {
        return "Maaf {$sapaan}, kalau boleh tau atas nama siapa ya?";
    }

    /**
     * @return bool true = ditangani; false = lepaskan ke intent lain
     */
    private function routeKurirUnregisteredStep(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): bool {
        $step = (string) ($session['step'] ?? '');

        if ($this->kurirLooksCancel($msg)) {
            $this->clearKurirSession($waNumber);
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, permintaan dibatalkan. Terima kasih.");
            return true;
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
                    return true;
                }
                if ($jenis === 'jemput' || $jenis === null) {
                    // Ambigu unregistered → jemput
                    $this->saveKurirSession($waNumber, [
                        'jenis' => 'jemput',
                        'layanan' => 'sameday',
                        'step' => 'new_ask_nama',
                    ]);
                    $this->sendAutoreplyText($waNumber, $this->kurirAskNewNamaPrompt($sapaan));
                    return true;
                }
                $this->sendAutoreplyText($waNumber, $this->kurirAskJenisPrompt($sapaan));
                return true;

            case 'new_ask_nama':
                $this->kurirHandleNewAskNama($waNumber, $sapaan, $session, $msg);
                return true;

            case 'new_ask_shareloc':
                if ($this->kurirExtractCoords($msg) === null) {
                    // Bukan pin → biarkan intent lain menjawab
                    return false;
                }
                $this->kurirHandleNewAskShareloc($waNumber, $sapaan, $session, $msg);
                return true;

            default:
                $this->sendAutoreplyText($waNumber, $this->kurirAskJenisPrompt($sapaan));
                $this->saveKurirSession($waNumber, ['step' => 'ask_jenis']);
                return true;
        }
    }

    private function kurirHandleNewAskNama(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $coords = $this->kurirExtractCoords($msg);
        if ($coords !== null) {
            $this->saveKurirSession($waNumber, [
                'step' => 'new_ask_nama',
                'latt' => $coords['lat'],
                'longt' => $coords['lng'],
            ]);
            $this->sendAutoreplyText($waNumber, $this->kurirAskNewNamaPrompt($sapaan));
            return;
        }

        $nama = $this->kurirParseNewPelangganSebutan($msg);
        if ($nama === null) {
            // Belum panggilan/sebutan — diam, tetap standby new_ask_nama
            return;
        }

        $summary = preg_replace('/\s*\|\s*new_nama_asli=[^|]*/', '', (string) ($session['summary'] ?? ''));
        $summary = trim($summary . ($summary !== '' ? ' | ' : '') . 'new_nama_asli=' . $nama);
        $summary = $this->kurirMarkSharelocAsked($summary);
        $session['summary'] = $summary;

        $this->saveKurirSession($waNumber, [
            'step' => 'new_ask_shareloc',
            'summary' => mb_substr($summary, 0, 800),
        ]);

        $lat = $session['latt'] ?? null;
        $lng = $session['longt'] ?? null;
        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
            $this->kurirHandleNewAskShareloc(
                $waNumber,
                $sapaan,
                $session,
                ((float) $lat) . ',' . ((float) $lng)
            );
            return;
        }

        $this->sendAutoreplyText(
            $waNumber,
            $this->kurirAskSharelocPrompt($sapaan, $session)
        );
    }

    /**
     * Panggilan/sebutan untuk pelanggan baru (boleh "Tukang Ayam", "Bang Gondrong", nama toko, dll).
     * null = belum sebutan (ack/sapaan/kosong) → jangan balas.
     */
    private function kurirParseNewPelangganSebutan(string $msg): ?string
    {
        $nama = trim(preg_replace("/[\r\n]+/", ' ', $msg) ?? '');
        $nama = trim(preg_replace('/\s+/u', ' ', $nama) ?? '');
        if ($nama === '' || mb_strlen($nama) < 2) {
            return null;
        }
        if (preg_match(
            '/^(kak|kk|bang|pak|bu|mbak|bg|gan|bro|sis|ya|iya|ok|oke|okeh|baik|halo|hai|hi|tes|test|hmm|oh|sip|siap|nanti|tunggu)\s*$/iu',
            $nama
        )) {
            return null;
        }
        $words = preg_split('/\s+/u', $nama) ?: [];
        if (count($words) > 12) {
            return null;
        }
        if (mb_strpos($nama, '?') !== false) {
            return null;
        }
        if (mb_strlen($nama) > 80) {
            $nama = mb_substr($nama, 0, 80);
        }

        return $nama;
    }

    private function kurirHandleNewAskShareloc(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $coords = $this->kurirExtractCoords($msg);
        if ($coords === null) {
            // Sudah pernah minta shareloc → diam
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
            $this->sendAutoreplyText($waNumber, $this->kurirAskNewNamaPrompt($sapaan));
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
     * Panggilan/sebutan disimpan utuh + #NEW# (petugas bisa rapikan nanti).
     * "Tukang Ayam" → "TUKANG AYAM #NEW#", "Bang Gondrong" → "BANG GONDRONG #NEW#"
     */
    private function kurirFormatNewPelangganNama(string $fullName): string
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return 'BARU #NEW#';
        }
        $name = mb_strtoupper($fullName);
        if (mb_strlen($name) > 70) {
            $name = mb_substr($name, 0, 70);
        }
        if (!preg_match('/#NEW#\s*$/u', $name)) {
            $name .= ' #NEW#';
        }

        return $name;
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
     * Deteksi jemput/antar. Typo "anter"/"antr" = antar.
     * Implicit: "ambil kain kotor" → jemput; "bawakan kain yang siap" → antar.
     * Jika keduanya (pesan + state) → antar (sekalian_jemput di kurirResolveJenisState).
     *
     * @return 'jemput'|'antar'|null
     */
    private function detectKurirJenis(string $msg, ?array $session = null): ?string
    {
        $resolved = $this->kurirResolveJenisState($msg, $session);
        $jenis = $resolved['jenis'] ?? null;

        return ($jenis === 'antar' || $jenis === 'jemput') ? $jenis : null;
    }

    /** @return array{jenis:'jemput'|'antar'|null,sekalian_jemput:int} */
    private function kurirResolveJenisState(string $msg, ?array $session = null): array
    {
        $msgBlob = mb_strtolower($msg);
        $hasJemputMsg = $this->kurirBlobHasJemput($msgBlob);
        $hasAntarMsg = $this->kurirBlobHasAntar($msgBlob);

        $prevJenis = is_array($session) ? (string) ($session['jenis'] ?? '') : '';
        $prevSekalian = is_array($session) && !empty($session['sekalian_jemput']);
        if (is_array($session) && $prevJenis === '') {
            $sumBlob = mb_strtolower((string) ($session['summary'] ?? ''));
            $hasJemputMsg = $hasJemputMsg || $this->kurirBlobHasJemput($sumBlob);
            $hasAntarMsg = $hasAntarMsg || $this->kurirBlobHasAntar($sumBlob);
        }

        $hasJemput = $hasJemputMsg || $prevJenis === 'jemput' || $prevSekalian;
        $hasAntar = $hasAntarMsg || $prevJenis === 'antar';

        if ($hasJemput && $hasAntar) {
            return ['jenis' => 'antar', 'sekalian_jemput' => 1];
        }
        if ($hasAntar) {
            return ['jenis' => 'antar', 'sekalian_jemput' => $prevSekalian ? 1 : 0];
        }
        if ($hasJemput) {
            return ['jenis' => 'jemput', 'sekalian_jemput' => 0];
        }

        $jenis = ($prevJenis === 'antar' || $prevJenis === 'jemput') ? $prevJenis : null;

        return ['jenis' => $jenis, 'sekalian_jemput' => $prevSekalian ? 1 : 0];
    }

    private function kurirBlobHasJemput(string $blob): bool
    {
        return (bool) preg_match('/\b(jemput|jmpt|jmput|jempt|dijemput|penjemputan)\b/u', $blob)
            || $this->kurirMsgLooksAmbilKotorJemput($blob);
    }

    private function kurirBlobHasAntar(string $blob): bool
    {
        return (bool) preg_match(
            '/\b(antar|anter|antr|diantar|dianter|diantr|pengantaran|kirim\s*(ke|ke\s+rumah)?)\b/u',
            $blob
        ) || $this->kurirMsgLooksBawakanSiapAntar($blob);
    }

    private function kurirMessageTouchesJenis(string $msg): bool
    {
        $blob = mb_strtolower($msg);
        if ($blob === '') {
            return false;
        }

        return $this->kurirBlobHasJemput($blob)
            || $this->kurirBlobHasAntar($blob)
            || $this->kurirLooksAddingOtherJenis($msg);
    }

    /**
     * Update jenis + sekalian_jemput dari follow-up. True jika state berubah.
     */
    private function kurirApplyJenisFollowup(
        string $waNumber,
        array $session,
        string $msg,
        string $sapaan
    ): bool {
        $resolved = $this->kurirResolveJenisState($msg, $session);
        $jenis = $resolved['jenis'];
        $sekalian = (int) $resolved['sekalian_jemput'];
        if ($jenis !== 'antar' && $jenis !== 'jemput') {
            return false;
        }

        $prevJenis = (string) ($session['jenis'] ?? '');
        $prevSekalian = !empty($session['sekalian_jemput']) ? 1 : 0;
        if ($jenis === $prevJenis && $sekalian === $prevSekalian) {
            return false;
        }

        if ($jenis === 'antar'
            && $prevJenis !== 'antar'
            && !$this->kurirAllowAntarOrReject($waNumber, (int) ($session['id_pelanggan'] ?? 0), $sapaan)
        ) {
            return false;
        }

        $set = ['jenis' => $jenis, 'sekalian_jemput' => $sekalian];
        $this->saveKurirSession($waNumber, $set);
        $session['jenis'] = $jenis;
        $session['sekalian_jemput'] = $sekalian;
        $note = 'jenis=' . $jenis . ($sekalian ? ' | sekalian_jemput=1' : '');
        $this->kurirAppendSummary($waNumber, $session, $note);
        $this->kurirEarlyActivateRequest($waNumber, $session);
        $session = $this->getKurirSession($waNumber) ?: $session;
        $this->kurirNotifyDeliveryGroupIfLabelChanged($waNumber, $session);

        return true;
    }

    private function kurirGroupJenisDisplay(array $session): string
    {
        if (!empty($session['sekalian_jemput']) && (($session['jenis'] ?? '') === 'antar' || ($session['jenis'] ?? '') === 'jemput')) {
            return 'Antar & Jemput';
        }

        return (($session['jenis'] ?? '') === 'antar') ? 'Antar' : 'Jemput';
    }

    /** Tag grup Fonnte: *PAK SAYFUL- SB* */
    private function kurirGroupCustomerStarTag(string $waNumber, array $session): string
    {
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idCabang = (int) ($session['id_cabang'] ?? 0);

        $nama = '';
        if ($idPelanggan > 0) {
            try {
                $row = DB::getInstance(1)->query(
                    'SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                    [$idPelanggan]
                )->row();
                $nama = trim((string) ($row->nama_pelanggan ?? ''));
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if ($nama === '') {
            $nama = trim($this->getContactNameForGreeting($waNumber));
        }
        if ($nama === '') {
            $nama = 'PELANGGAN';
        }
        $nama = mb_strtoupper($nama, 'UTF-8');

        $kode = '';
        if ($idCabang > 0) {
            try {
                $cab = DB::getInstance(1)->query(
                    'SELECT kode_cabang FROM cabang WHERE id_cabang = ? LIMIT 1',
                    [$idCabang]
                )->row();
                $kode = trim((string) ($cab->kode_cabang ?? ''));
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if ($kode === '') {
            $kode = (string) ($idCabang > 0 ? $idCabang : '-');
        }

        return "*{$nama}- {$kode}*";
    }

    /** hari ini jam 14:00 / besok jam 10:00 / 15/08 jam 14:00 */
    private function kurirGroupWaktuLabel(string $tgl, float $jam): string
    {
        $jamLabel = $this->formatKurirJamLabel($jam);
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        if ($tgl === $today) {
            $hari = 'hari ini';
        } elseif ($tgl === $tomorrow) {
            $hari = 'besok';
        } else {
            $ts = strtotime($tgl);
            $hari = $ts ? date('d/m', $ts) : $tgl;
        }

        return "{$hari} jam {$jamLabel}";
    }

    private function kurirNotifyDeliveryGroupIfLabelChanged(string $waNumber, array $session): void
    {
        $prev = trim((string) ($session['group_notify_label'] ?? ''));
        if ($prev === '') {
            return;
        }
        $label = $this->kurirGroupJenisDisplay($session);
        if ($label === $prev) {
            return;
        }
        $this->kurirNotifyDeliveryGroupRequestCreated(
            $waNumber,
            (int) ($session['id_pelanggan'] ?? 0),
            (int) ($session['id_cabang'] ?? 0),
            $session
        );
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
            '/\b(bawak|bawakan|bawa\s*kan|bawa\s*in|bawa\s*kan|anter|antar|antr|kirim(kan)?)\b.{0,100}\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,80}\b(yg|yang)?\s*(udah|sudah|udh|dah|sdh)?\s*(siap|selesai|kelar)\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(kain|baju|cucian|laundry|londry|londri|laondri|pakaian)\b.{0,40}\b(yg|yang)?\s*(udah|sudah|udh|dah|sdh)?\s*(siap|selesai|kelar)\b.{0,60}\b(bawak|bawakan|bawa\s*kan|bawa\s*in|antar|anter|antr|kirim(kan)?)\b/u',
            $blob
        ) || (bool) preg_match(
            '/\b(sekalian|sekaligus|juga)\s+(bawak|bawakan|bawa\s*kan|bawa\s*in|antar|anter|antr)\b.{0,80}\b(kain|baju|cucian|laundry|siap|selesai)\b/u',
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
            '/\b(jemput|jmpt|antar|anter|antr)\s*(juga|jg|jga|sekalian|sekaligus|bareng|sama)\b/iu',
            $t
        ) || (bool) preg_match(
            '/\b(juga|jg|jga|sekalian|sekaligus)\s*(di)?(jemput|jmpt|antar|anter|antr|bawak|bawakan|bawa)\b/iu',
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

        // Follow-up jenis: "antar juga" / "sekalian jemput" selama session hidup
        $jenisChanged = false;
        if ($this->kurirMessageTouchesJenis($msg)) {
            $jenisChanged = $this->kurirApplyJenisFollowup($waNumber, $session, $msg, $sapaan);
            $session = $this->getKurirSession($waNumber) ?: $session;
        }

        // Hard: pin/Maps coords (terutama ask_shareloc, atau customer kirim pin saat confirm/pick/aktif)
        $coords = $this->kurirExtractCoords($msg);
        if ($coords !== null && in_array($step, ['ask_shareloc', 'confirm_lokasi', 'pick_lokasi', 'lokasi_check'], true)) {
            $this->kurirHandleShareloc($waNumber, $sapaan, $session, $msg);
            $this->kurirAppendSummary($waNumber, $session, 'shareloc_coords');
            return true;
        }
        if ($coords !== null && $this->kurirStepAllowsLokasiUpdate($step) && !empty($session['id_request'])) {
            $this->kurirApplySharelocToAktifRequest($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // Klarifikasi alamat ("saya kos di taman sari") → update detail, bukan setuju / jam
        if ($this->kurirStepAllowsLokasiUpdate($step)
            && $this->kurirLooksLikeLokasiDetailClarification($msg, $session)
            && !($step === 'pick_lokasi' && $this->kurirMsgMatchesLokasiListItem($session, $msg))
        ) {
            $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // Request waktu / butuh estimasi: ack driver dulu, baru lanjut state
        if ($this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg)) {
            $session = $this->getKurirSession($waNumber) ?: $session;
            if (in_array($step, ['request_aktif', 'wait_driver_jam'], true)) {
                return true;
            }
            if ($step === 'confirm_lokasi') {
                if ($this->kurirLooksExplicitConfirmAgree($msg, $session)) {
                    $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                    return true;
                }
                $this->sendAutoreplyText(
                    $waNumber,
                    "Baik {$sapaan}, jamnya kami catat. Lokasinya sudah benar? "
                    . "Balas *ya* untuk lanjut, atau sebut alamat yang benar / kirim shareloc."
                );
                return true;
            }
            if (in_array($step, ['ask_shareloc', 'new_ask_shareloc', 'pick_lokasi'], true)) {
                return true;
            }
            if ($step === 'lokasi_check') {
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return true;
            }
            return true;
        }

        if ($jenisChanged) {
            $disp = $this->kurirGroupJenisDisplay($session);
            if (in_array($step, ['request_aktif', 'wait_driver_jam'], true)) {
                $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami catat *{$disp}* ya.");
                return true;
            }
            if ($step === 'ask_jenis' || empty($session['id_lokasi'])) {
                $this->saveKurirSession($waNumber, ['step' => 'lokasi_check']);
                $session['step'] = 'lokasi_check';
                $this->kurirLokasiCheck($waNumber, $sapaan, $session);
                return true;
            }
            $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami catat *{$disp}* ya.");
            return true;
        }

        // Menunggu shareloc tapi pesan bukan pin/maps → lepaskan ke intent lain
        // (jangan consume & jangan tanya shareloc lagi di tengah chat harga/status/dll.)
        if (in_array($step, ['ask_shareloc', 'new_ask_shareloc'], true)) {
            // Minta jemput/antar baru → anggap flow lama ditinggalkan
            if ($this->messageLooksLikeMintaJemputAntar(mb_strtolower($msg))) {
                $this->clearKurirSession($waNumber);
                $this->clearLokasiSession($waNumber);
            }
            $this->logAutoreplyTrace($waNumber, 'KURIR', 'ask_shareloc_waiting→release_other_intent');
            return false;
        }

        // wait_lokasi: serahkan ke LOKASI; jika bukan jawaban lokasi → lepaskan (jangan lokasiCheck/shareloc)
        if ($step === 'wait_lokasi') {
            if ($this->getLokasiSession($waNumber) !== null) {
                $lokOk = $this->handleLokasi($phoneIn, $waNumber, $msg);
                return $lokOk !== false;
            }
            if ($this->kurirSharelocAlreadyAsked($session)) {
                return false;
            }
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

        // Hard: ubah/hapus alamat
        if ($this->kurirLooksWantDeleteLokasi($msg)
            && in_array($step, [
                'lokasi_check', 'pick_lokasi', 'confirm_lokasi', 'ask_layanan',
                'request_aktif', 'wait_driver_jam', 'instant_confirm', 'instant_pick',
            ], true)
        ) {
            if (in_array($step, ['request_aktif', 'wait_driver_jam'], true)
                && !$this->kurirLooksHardDeleteLokasi($msg)
            ) {
                $this->sendAutoreplyText(
                    $waNumber,
                    "Sebut alamat yang benar ya {$sapaan}? Contoh: *kos Taman Sari*, atau kirim *shareloc*."
                );
                return true;
            }
            $this->kurirStartDeleteLokasi($waNumber, $sapaan, $session, $msg);
            return true;
        }

        // ask_jenis (session lama): deteksi dari pesan, atau infer dari order aktif
        if ($step === 'ask_jenis') {
            $jenis = $this->detectKurirJenis($msg, $session);
            if (!$jenis && preg_match('/\b(jemput|jmpt|jmput|jempt)\b/iu', $msg)) {
                $jenis = 'jemput';
            }
            if (!$jenis && preg_match('/\b(antar|anter|antr)\b/iu', $msg)) {
                $jenis = 'antar';
            }
            if (!$jenis) {
                $jenis = $this->kurirInferJenisWhenAmbiguous($phoneIn, $waNumber);
            }
            if ($jenis === 'antar'
                && !$this->kurirAllowAntarOrReject($waNumber, (int) ($session['id_pelanggan'] ?? 0), $sapaan)
            ) {
                return true;
            }
            $layananPref = $this->detectKurirLayanan($msg);
            $resolved = $this->kurirResolveJenisState($msg, $session);
            $sekalianJemput = ($jenis === 'antar') ? (int) $resolved['sekalian_jemput'] : 0;
            $set = ['jenis' => $jenis, 'sekalian_jemput' => $sekalianJemput, 'step' => 'lokasi_check'];
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
            $this->kurirEarlyActivateRequest($waNumber, $session);
            $session = $this->getKurirSession($waNumber) ?: $session;
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            $this->kurirAppendSummary($waNumber, $session, 'jenis=' . $jenis);
            return true;
        }

        // lokasi_check tanpa input bermakna: jalankan check (biasanya dipanggil internal)
        if ($step === 'lokasi_check' && trim($msg) === '') {
            $this->kurirLokasiCheck($waNumber, $sapaan, $session);
            return true;
        }

        // Request sudah jadi: jangan telan semua chat sebagai perkiraan jam driver.
        // Hanya jam kunjungan kurir / instant; alamat masih bisa di-update; selain itu lepas ke intent lain.
        if (in_array($step, ['request_aktif', 'wait_driver_jam'], true)) {
            if ($this->kurirLooksWantFast($msg)) {
                if (!$this->isOperatingHours()) {
                    $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                    return true;
                }
                $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
                return true;
            }
            if ($this->kurirLooksLikeLokasiDetailClarification($msg, $session)) {
                $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
                return true;
            }
            $this->logAutoreplyTrace($waNumber, 'KURIR', $step . '→release_other_intent');
            return false;
        }

        // AI decide (summary + konteks)
        $decision = $this->kurirAiDecide($waNumber, $session, $msg);
        if ($decision !== null) {
            // Override AI salah: "ya sudah gak pa2" / gpp ≠ batal
            $act = (string) ($decision['action'] ?? '');
            if ($this->kurirLooksNoProblemAck($msg) && in_array($act, ['cancel', 'refuse_alt'], true)) {
                $decision['action'] = ($step === 'wait_continue_alt') ? 'agree_alt' : 'confirm';
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'override_no_problem_ack→' . $decision['action']);
            }
            if (in_array($act, ['confirm', 'other_lokasi', 'pick_lokasi', 'noop_ack'], true)
                && $this->kurirStepAllowsLokasiUpdate($step)
                && $this->kurirLooksLikeLokasiDetailClarification($msg, $session)
            ) {
                $decision['action'] = 'update_detail';
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'override_clarification→update_detail');
            }
            if ($act === 'confirm' && $step === 'confirm_lokasi'
                && !$this->kurirLooksExplicitConfirmAgree($msg, $session)
                && ($decision['action'] ?? '') === 'confirm'
            ) {
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'block_confirm_not_explicit_agree');
                $this->sendAutoreplyText(
                    $waNumber,
                    "Lokasinya sudah benar {$sapaan}? Balas *ya* untuk lanjut, atau sebut alamat yang benar / kirim shareloc."
                );
                $this->kurirAppendSummary($waNumber, $session, 'confirm_blocked_not_agree');
                return true;
            }
            // wait_continue_alt: hanya batal eksplisit; selain itu lanjut jam alternatif (jangan unrelated/instant)
            if ($step === 'wait_continue_alt') {
                if ($this->kurirLooksCancel($msg)) {
                    $decision['action'] = 'cancel';
                } else {
                    $decision['action'] = 'agree_alt';
                }
            }
            if (($decision['action'] ?? '') === 'unrelated') {
                // Lepas session WA kurir supaya intent lain (estimasi/bill/harga) bisa jalan
                $this->clearKurirSession($waNumber);
                $this->clearLokasiSession($waNumber);
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

        if ($this->kurirLooksWantFast($msg) && in_array($step, ['confirm_lokasi', 'request_aktif', 'lokasi_check', 'pick_lokasi', 'ask_layanan'], true)) {
            if (!$this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirRejectInstantOutsideHoursAck($sapaan));
                if ($step === 'request_aktif') {
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
                    if ($this->handleLokasi($phoneIn, $waNumber, $msg) !== false) {
                        return;
                    }
                    // Lokasi tidak consume (bukan detail/pin) → jangan paksa lokasiCheck/shareloc
                    return;
                }
                // Tanpa session lokasi: jangan spam shareloc ulang
                if ($this->kurirSharelocAlreadyAsked($session)) {
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

    private function kurirSekalianJemputVal(array $session): int
    {
        return (!empty($session['sekalian_jemput']) && $this->kurirJenisLabel($session) === 'antar') ? 1 : 0;
    }

    private function kurirJenisNoun(array $session): string
    {
        return (($session['jenis'] ?? '') === 'antar') ? 'pengantaran' : 'penjemputan';
    }

    /**
     * Minta shareloc + info SLA 1×24 jam (noun mengikuti jenis jemput/antar).
     */
    private function kurirAskSharelocPrompt(string $sapaan, array $session): string
    {
        $noun = ucfirst($this->kurirJenisNoun($session));

        return "Baik {$sapaan}, {$noun} akan diproses dalam 1×24 jam sesuai antrian delivery ya {$sapaan}. "
            . "Kirimkan *shareloc* untuk melanjutkan.";
    }

    private function kurirLooksAgree(string $msg, ?array $session = null): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }
        // "ya sudah gak pa2" / gpp / gapapa = setuju lanjut, BUKAN tolak
        if ($this->kurirLooksNoProblemAck($msg)) {
            return true;
        }
        if ($session && $this->kurirLooksLikeLokasiDetailClarification($msg, $session)) {
            return false;
        }
        if ($this->kurirLooksCancel($msg)
            || $this->kurirLooksRefuse($msg)
            || $this->kurirLooksWantOtherLokasi($msg)
            || $this->kurirLooksWantDeleteLokasi($msg)
            || $this->kurirLooksWantJam($msg, $session)
            || $this->kurirLooksWantFast($msg)
        ) {
            return false;
        }
        return (bool) preg_match(
            '/\b(ya|iya|iyo|yoi|ok|oke|baik|setuju|boleh|sip|siap|lanjut|gas|bener|benar|betul|yuk|yo|deal)\b/u',
            $t
        );
    }

    /**
     * Konfirmasi lokasi: HANYA jika pesan mengarah setuju (ya/oke/baik/setuju/deal),
     * bukan karena kata itu kebetulan ada di kalimat panjang / koreksi alamat.
     */
    private function kurirLooksExplicitConfirmAgree(string $msg, ?array $session = null): bool
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $msg)));
        if ($t === '') {
            return false;
        }
        if ($session && $this->kurirLooksLikeLokasiDetailClarification($msg, $session)) {
            return false;
        }
        if ($this->kurirLooksCancel($msg)
            || $this->kurirLooksRefuse($msg)
            || $this->kurirLooksWantOtherLokasi($msg)
            || $this->kurirLooksHardDeleteLokasi($msg)
        ) {
            return false;
        }
        if (preg_match('/[?？]/u', $t)
            || preg_match('/\b(siapa|yang\s+mana|kenapa|knp|kok|gimana|gmn)\b/iu', $t)
        ) {
            return false;
        }
        if ($this->kurirLooksNoProblemAck($msg)) {
            return true;
        }

        $tok = 'ya+|iya|iye|iyo|yoi|ok+|oke|okeh|baik|setuju|deal|sip|siap|lanjut|gas|boleh|bener|benar|betul|yuk|yo|yes+';
        $filler = 'kak|kk|bang|pak|bu|mbak|min|deh|lah|dong|aja|saja|sudah|udah|dah|ya';
        if (preg_match('/^(?:' . $tok . ')(?:\s+(?:' . $tok . '|' . $filler . '))*\s*[.!,]*$/iu', $t)) {
            return true;
        }
        if (preg_match('/^(?:' . $tok . ')\b/iu', $t) && $this->kurirLooksWantJam($msg, $session ?? [])) {
            $rest = trim(preg_replace('/^(?:' . $tok . ')(?:\s+(?:' . $filler . '))*\s*/iu', '', $t));
            if ($rest !== '' && (
                $this->kurirParseLokasiJenis($rest) !== null
                || $this->lokasiLooksLikeAlamatJalanNomor($rest)
                || preg_match('/\b(kos|kost|taman|jalan|jl\.?|alamat|rumah|nomor|no\.?)\b/iu', $rest)
            )) {
                return false;
            }
            return true;
        }

        return false;
    }

    private function kurirStepAllowsLokasiUpdate(string $step): bool
    {
        return in_array($step, ['confirm_lokasi', 'pick_lokasi', 'request_aktif', 'wait_driver_jam'], true);
    }

    private function kurirLooksHardDeleteLokasi(string $msg): bool
    {
        return (bool) preg_match('/\b(hapus|delete|buang|hilangkan)\b/iu', $msg);
    }

    /** Sisa teks setelah "ganti/ubah alamat" — untuk update in-place, bukan hapus. */
    private function kurirStripLokasiEditPrefix(string $msg): string
    {
        $t = preg_replace('/\b(ubah|ganti|edit|update)\s*(lokasi|alamat|tempat|pin|detail)?\b/iu', ' ', $msg);
        $t = preg_replace('/\b(alamat(nya)?|lokasinya|detailnya)\s*(jadi|ke|:)?\b/iu', ' ', (string) $t);
        $t = trim(preg_replace('/\s+/u', ' ', (string) $t), " \t,.;:-");

        return $t;
    }

    /**
     * Ack "tidak apa-apa / gpp / ya sudah" = terima opsi yang ditawarkan (lanjut), bukan batal.
     */
    private function kurirLooksNoProblemAck(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }
        // Kalau jelas batal ("gak jadi") → bukan no-problem
        if ($this->kurirLooksCancel($msg)) {
            return false;
        }
        // gpp / gapapa / gakapa
        if (preg_match('/\b(gpp|gapapa|gaapa|gakapa|gapapa2)\b/iu', $t)) {
            return true;
        }
        // gak pa2 / gak apa2 / gak apa-apa / ga pa2 / tidak apa-apa
        if (preg_match(
            '/\b(gak|ga|gk|ngga|nggak|engga|enggak|tidak|tdk)\s*(apa[-\s]?apa|apa2|pa2|pa\s*2|pp)\b/iu',
            $t
        )) {
            return true;
        }
        // ya sudah / yaudah / oke sudah (penerimaan resign, tanpa "jadi")
        if (preg_match('/\b(ya\s*sudah|yaudah|ya\s*udah|yasudah|oke\s*sudah|ok\s*sudah)\b/iu', $t)) {
            return true;
        }
        return false;
    }

    private function kurirLooksRefuse(string $msg): bool
    {
        if ($this->kurirLooksNoProblemAck($msg)) {
            return false;
        }
        if ($this->kurirLooksCancel($msg)) {
            return true;
        }
        $t = mb_strtolower(trim($msg));
        // Tolak singkat murni: "tidak" / "gak" / "ga kak" — bukan "gak" di dalam "gak pa2"
        if (preg_match(
            '/^\s*(tidak|tdk|ga|gak|gk|ngga|nggak|engga|enggak|bukan|jangan|no)'
            . '(\s+(deh|lah|dong|aja|kak|kk|bang|pak|bu|mbak|min))?\s*[.!]?\s*$/iu',
            $t
        )) {
            return true;
        }
        if (preg_match('/\b(nanti\s*aja|jangan\s*(jadi|lanjut(kan)?))\b/iu', $t)) {
            return true;
        }
        return false;
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
     * Customer minta batalkan request antar/jemput.
     * Hanya frasa eksplisit: cancel / batal / gak jadi / gk jd / gak usah — bukan "gak pa2".
     */
    private function kurirLooksCancel(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }

        if (preg_match(
            '/\b('
            . 'batal(in|kan)?'
            . '|di\s*batal(in|kan)?'
            . '|cancel(l?ed)?'
            . ')\b/iu',
            $t
        )) {
            return true;
        }

        // gak jadi / gk jd / ga jadi / nggak jadi
        if (preg_match(
            '/\b(gak|ga|gk|ngga|nggak|engga|enggak|tidak|tdk)\s*(jadi|jd)\b/iu',
            $t
        )) {
            return true;
        }

        // gak usah (jelas tidak mau lanjut)
        if (preg_match('/\b(gak|ga|gk|ngga|nggak|engga|enggak)\s*usah\b/iu', $t)) {
            return true;
        }

        // Customer jemput/antar/ambil sendiri (bukan minta kurir)
        if (preg_match(
            '/\b(saya|aku|sy|gue|kami|awak)\s+(akan\s+)?(jemput|antar|antr|anter|ambil|ngambil)\s*(sendiri|sndiri|aja|aj)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match(
            '/\b(jemput|antar|antr|anter|ambil|ngambil)\s+sendiri(\s+(aja|aj|deh|lah))?\b/iu',
            $t
        )) {
            return true;
        }

        return false;
    }

    private function kurirJenisTokenPattern(string $jenis): string
    {
        if ($jenis === 'antar') {
            return '(di\s*)?(antar|anter|antr)';
        }

        return '(di\s*)?(jemput|jmpt|jmput|jempt)';
    }

    private function kurirMsgHasJenisToken(string $msg, string $jenis): bool
    {
        return (bool) preg_match('/\b' . $this->kurirJenisTokenPattern($jenis) . '\b/iu', $msg);
    }

    private function kurirActiveJenis(array $session): string
    {
        return (($session['jenis'] ?? '') === 'antar') ? 'antar' : 'jemput';
    }

    /**
     * Request waktu: jam angka + token jenis aktif. Tanpa hari = hari ini.
     */
    private function kurirLooksRequestWaktu(string $msg, array $session): bool
    {
        if ($this->kurirLooksCancel($msg) || $this->messageLooksLikeAntarKembaliDeadline($msg)) {
            return false;
        }
        if (!$this->kurirMsgHasJenisToken($msg, $this->kurirActiveJenis($session))) {
            return false;
        }
        $waktu = $this->parseEstimasiRequestWaktu($msg);

        return $this->estimasiWaktuIsResolved($waktu) || (!empty($waktu['ask_ampm']));
    }

    /**
     * Butuh estimasi: tanya kapan/jam berapa/pagi-siang-sore-malam + token jenis, tanpa jam angka.
     */
    private function kurirLooksButuhEstimasi(string $msg, array $session): bool
    {
        if ($this->kurirLooksCancel($msg) || $this->messageLooksLikeAntarKembaliDeadline($msg)) {
            return false;
        }
        if ($this->kurirLooksRequestWaktu($msg, $session)) {
            return false;
        }
        if (!$this->kurirMsgHasJenisToken($msg, $this->kurirActiveJenis($session))) {
            return false;
        }

        return (bool) preg_match(
            '/\b(kapan|jam\s*(brp|brpa|berapa)|pagi|siang|sore|malam)\b/iu',
            $msg
        );
    }

    private function kurirLooksWantJam(string $msg, ?array $session = null): bool
    {
        $session = $session ?? [];

        return $this->kurirLooksRequestWaktu($msg, $session)
            || $this->kurirLooksButuhEstimasi($msg, $session);
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
            "Baik {$sapaan}, permintaan dibatalkan. 🙏\n"
            . "Untuk pesan antar/jemput bisa juga via link berikut:\n"
            . "https://ml.nalju.com/J/kurir/{$id}"
        );
        $this->clearKurirSession($waNumber);
    }

    private function kurirSharelocAlreadyAsked(array $session): bool
    {
        $step = (string) ($session['step'] ?? '');
        if (in_array($step, ['ask_shareloc', 'new_ask_shareloc'], true)) {
            return true;
        }
        $sum = (string) ($session['summary'] ?? '');
        return (bool) preg_match('/\basked_shareloc=1\b/iu', $sum);
    }

    /** Tandai sudah pernah minta shareloc (agar tidak ditanya ulang). */
    private function kurirMarkSharelocAsked(string $summary): string
    {
        $summary = trim((string) $summary);
        if (preg_match('/\basked_shareloc=1\b/iu', $summary)) {
            return mb_substr($summary, 0, 800);
        }
        return mb_substr(($summary !== '' ? $summary . ' | ' : '') . 'asked_shareloc=1', 0, 800);
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
            // Sudah pernah minta shareloc → diam (jangan spam)
            if ($this->kurirSharelocAlreadyAsked($session)) {
                $this->saveKurirSession($waNumber, ['step' => 'ask_shareloc']);
                return;
            }
            $summary = $this->kurirMarkSharelocAsked((string) ($session['summary'] ?? ''));
            $this->saveKurirSession($waNumber, [
                'step' => 'ask_shareloc',
                'summary' => $summary,
            ]);
            $this->sendAutoreplyText(
                $waNumber,
                $this->kurirAskSharelocPrompt($sapaan, $session)
            );
            return;
        }

        // 1 lokasi lengkap → langsung; >1 → tanya max 2 lokasi terakhir pernah sukses dipakai
        if (count($list) === 1) {
            $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $list[0]);
            return;
        }

        $candidates = $this->kurirPickRecentSuccessfulLokasiCandidates($idPelanggan, $list, 2);
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
     * id_lokasi unik dari delivery selesai, urut recency (terbaru dulu).
     * @return int[]
     */
    private function kurirRecentSuccessfulLokasiIds(int $idPelanggan, int $limit = 2): array
    {
        if ($idPelanggan <= 0 || $limit <= 0) {
            return [];
        }
        try {
            $rows = DB::getInstance(1)->query(
                "SELECT id_lokasi
                 FROM delivery_request
                 WHERE id_pelanggan = ?
                   AND delivery_status = 'selesai'
                   AND id_lokasi IS NOT NULL AND id_lokasi > 0
                 ORDER BY COALESCE(selesaiTime, insertTime) DESC, id_request DESC",
                [$idPelanggan]
            )->result_array();
            $ids = [];
            foreach (is_array($rows) ? $rows : [] as $r) {
                $id = (int) ($r['id_lokasi'] ?? 0);
                if ($id > 0 && !isset($ids[$id])) {
                    $ids[$id] = $id;
                }
                if (count($ids) >= $limit) {
                    break;
                }
            }

            return array_values($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Kandidat lokasi untuk pick_lokasi: max 2 lokasi terakhir pernah dipakai sukses.
     * Jika belum pernah sukses → fallback 2 lokasi tersimpan terakhir (id_lokasi DESC).
     * @param list<array> $list lokasi lengkap pelanggan (nama+detail)
     * @return list<array>
     */
    private function kurirPickRecentSuccessfulLokasiCandidates(int $idPelanggan, array $list, int $limit = 2): array
    {
        if ($list === []) {
            return [];
        }

        $byId = [];
        foreach ($list as $lok) {
            $id = (int) ($lok['id_lokasi'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $lok;
            }
        }

        $recentIds = $this->kurirRecentSuccessfulLokasiIds($idPelanggan, $limit);
        $candidates = [];
        foreach ($recentIds as $id) {
            if (isset($byId[$id])) {
                $candidates[] = $byId[$id];
            }
        }
        if ($candidates !== []) {
            return $candidates;
        }

        usort($list, static function ($a, $b) {
            return (int) ($b['id_lokasi'] ?? 0) <=> (int) ($a['id_lokasi'] ?? 0);
        });

        return array_slice(array_values($list), 0, $limit);
    }

    /**
     * Request delivery selesai terakhir (semua layanan) yang punya id_lokasi.
     */
    private function kurirLastSuccessfulDeliveryRequest(int $idPelanggan): ?array
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        try {
            $row = DB::getInstance(1)->query(
                "SELECT * FROM delivery_request
                 WHERE id_pelanggan = ?
                   AND delivery_status = 'selesai'
                   AND id_lokasi IS NOT NULL AND id_lokasi > 0
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
     * Prefill lokasi early request: 1 lokasi langsung; >1 ambil dari delivery selesai terakhir.
     * @return array|null baris pelanggan_lokasi
     */
    private function kurirPickDefaultLokasiForEarlyRequest(int $idPelanggan): ?array
    {
        $list = $this->kurirListLokasi($idPelanggan);
        if ($list === []) {
            return null;
        }
        if (count($list) === 1) {
            return $list[0];
        }

        $lastOk = $this->kurirLastSuccessfulDeliveryRequest($idPelanggan);
        if ($lastOk === null) {
            return null;
        }
        $targetId = (int) ($lastOk['id_lokasi'] ?? 0);
        if ($targetId <= 0) {
            return null;
        }
        foreach ($list as $lok) {
            if ((int) ($lok['id_lokasi'] ?? 0) === $targetId) {
                return $lok;
            }
        }

        return null;
    }

    /** @return array{id_lokasi:int,lokasi_nama:string,lokasi_detail:string,lokasi_latt:float,lokasi_longt:float} */
    private function kurirLokasiFieldsForDeliveryRequest(array $lok): array
    {
        return [
            'id_lokasi' => (int) ($lok['id_lokasi'] ?? 0),
            'lokasi_nama' => (string) ($lok['nama'] ?? ''),
            'lokasi_detail' => (string) ($lok['detail'] ?? ''),
            'lokasi_latt' => (float) ($lok['latt'] ?? 0),
            'lokasi_longt' => (float) ($lok['longt'] ?? 0),
        ];
    }

    /** @return array<string,mixed> patch untuk wa_kurir_session */
    private function kurirSessionPatchFromLokasi(array $lok, int $idCabang): array
    {
        $patch = [
            'id_lokasi' => (int) ($lok['id_lokasi'] ?? 0),
            'lokasi_nama' => (string) ($lok['nama'] ?? ''),
            'lokasi_detail' => (string) ($lok['detail'] ?? ''),
            'latt' => (float) ($lok['latt'] ?? 0),
            'longt' => (float) ($lok['longt'] ?? 0),
        ];
        $latt = $patch['latt'];
        $longt = $patch['longt'];
        if ($idCabang > 0 && abs($latt) > 0.0001 && abs($longt) > 0.0001) {
            $cab = $this->kurirCabangCoords($idCabang);
            $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
            $patch['tarif'] = (int) $calc['tarif'];
        }

        return $patch;
    }

    private function kurirEarlyApplyLokasiToRequestAndSession(
        string $waNumber,
        int $idRequest,
        int $idCabang,
        array $lok
    ): void {
        $idLokasi = (int) ($lok['id_lokasi'] ?? 0);
        if ($idRequest <= 0 || $idLokasi <= 0) {
            return;
        }
        try {
            $db = DB::getInstance(1);
            $set = $this->kurirLokasiFieldsForDeliveryRequest($lok);
            $latt = $set['lokasi_latt'];
            $longt = $set['lokasi_longt'];
            if ($idCabang > 0 && abs($latt) > 0.0001 && abs($longt) > 0.0001) {
                $cab = $this->kurirCabangCoords($idCabang);
                $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
                $set['tarif_surcas'] = (int) $calc['tarif'];
            }
            $db->update('delivery_request', $set, ['id_request' => $idRequest]);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirEarlyApplyLokasi: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        $this->saveKurirSession($waNumber, $this->kurirSessionPatchFromLokasi($lok, $idCabang));
        $this->logAutoreplyTrace(
            $waNumber,
            'KURIR',
            'early_lokasi_prefill id_request=' . $idRequest . ' id_lokasi=' . $idLokasi
        );
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

    private function kurirPrepareConfirm(
        string $waNumber,
        string $sapaan,
        array $session,
        array $lok,
        bool $forceAsk = false
    ): void
    {
        $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
        $latt = (float) ($lok['latt'] ?? 0);
        $longt = (float) ($lok['longt'] ?? 0);
        $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
        $jenis = $this->kurirJenisLabel($session);
        $nama = trim((string) ($lok['nama'] ?? ''));
        $detail = trim((string) ($lok['detail'] ?? ''));
        if ($nama === '' || strcasecmp($nama, 'Shareloc') === 0 || strcasecmp($nama, 'Lainnya') === 0) {
            $nama = 'Rumah';
        }
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

        // Ongkir gratis / 0, atau riwayat sameday lokasi+tarif sama → skip konfirmasi, langsung create
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $lastOk = $this->kurirLastSuccessfulSamedayRequest($idPelanggan);
        $skipGratis = $tarif <= 0;
        $skipSame = $lastOk !== null
            && (int) ($lastOk['id_lokasi'] ?? 0) === $idLokasi
            && (int) ($lastOk['tarif_surcas'] ?? 0) === $tarif
            && mb_strtolower(trim((string) ($lastOk['lokasi_detail'] ?? ''))) === mb_strtolower(trim($detail));
        if (!$forceAsk && ($skipGratis || $skipSame)) {
            $this->logAutoreplyTrace(
                $waNumber,
                'KURIR',
                $skipGratis
                    ? "skip_confirm ongkir_gratis id_lokasi={$idLokasi} tarif={$tarif}"
                    : "skip_confirm same_lokasi_tarif id_lokasi={$idLokasi} tarif={$tarif}"
            );
            $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
            return;
        }

        $lokasiLabel = $detail !== '' ? "{$nama}, {$detail}" : $nama;
        $this->sendAutoreplyText(
            $waNumber,
            "Konfirmasi {$jenis} ke {$lokasiLabel} ya {$sapaan}? Ongkir {$tarifRp}. Balas ya untuk lanjut."
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
            // Sudah pernah minta shareloc → diam (jangan ulang tanya)
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
        return "Lokasi diterima {$sapaan}. Boleh jelaskan detailnya ya?\n"
            . "Contoh: kos zahra / rumah kuning / mess TNI / toko abadi";
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
        if ($this->kurirLooksLikeLokasiDetailClarification($msg, $session)
            && $this->kurirResolvePickedLokasiFromList($session, $msg) === null
        ) {
            $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
            return;
        }

        $list = $this->kurirLokasiListFromSession($session);
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

    /**
     * Klarifikasi alamat saat konfirmasi: "saya kos di taman sari" — update detail, bukan setuju.
     */
    private function kurirLooksLikeLokasiDetailClarification(string $msg, array $session): bool
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $msg)));
        if ($t === '' || mb_strlen($t) < 6) {
            return false;
        }
        if (preg_match('/^\s*(ya|iya|iyo|ok|oke|baik|setuju|deal|sip|siap|lanjut|gas)\s*(kak|kk|bang|pak|bu|ya)?\s*[.!]?\s*$/iu', $t)) {
            return false;
        }
        if ($this->kurirLooksCancel($msg)
            || $this->kurirLooksWantFast($msg)
            || $this->kurirExtractCoords($msg) !== null
        ) {
            return false;
        }
        if ($this->kurirLooksHardDeleteLokasi($msg)) {
            return false;
        }
        $editRemainder = $this->kurirStripLokasiEditPrefix($msg);
        $msgForInfer = $msg;
        if ($this->kurirLooksWantDeleteLokasi($msg)) {
            if (mb_strlen($editRemainder) < 3) {
                return false;
            }
            $msgForInfer = $editRemainder;
            $t = mb_strtolower($editRemainder);
        }
        if (preg_match('/\b(siapa|yang\s+mana|kenapa|knp|kok)\b/iu', $t)) {
            return false;
        }
        $hasSayaDi = (bool) preg_match(
            '/\b(saya|aku|sy|gue|awak)\s+.{0,24}\b(tinggal|ngekos|ngkos|kos|kost|di)\b/iu',
            $t
        );
        $hasPindah = (bool) preg_match(
            '/\b(pindah|pindahan|sekarang|skrg)\s*(ke\s*)?(di|kos|kost|ngekos|rumah)\b/iu',
            $t
        );
        $hasAlamatCue = (bool) preg_match(
            '/\b(alamat(nya)?|lokasinya|detailnya)\b/iu',
            mb_strtolower(trim(preg_replace('/\s+/u', ' ', $msg)))
        );
        if (!$hasSayaDi && !$hasPindah && !$hasAlamatCue
            && preg_match('/\b(gak|ga|gk|tidak|tdk|bukan|salah|engga)\b/iu', $t)
        ) {
            return false;
        }

        $inferred = $this->lokasiInferNamaDetailFromReply($msgForInfer);
        $detail = $this->kurirNormalizeClarifiedLokasiDetail((string) ($inferred['detail'] ?? ''), (string) ($inferred['nama'] ?? ''));
        if ($detail === '' || mb_strlen($detail) < 3) {
            return false;
        }
        $cur = mb_strtolower(trim((string) ($session['lokasi_detail'] ?? '')));
        if ($cur !== '' && $cur === mb_strtolower($detail)) {
            return false;
        }
        if ($hasSayaDi || $hasPindah || $hasAlamatCue) {
            return true;
        }
        if ($this->kurirParseLokasiJenis($msgForInfer) !== null) {
            return true;
        }

        return $this->lokasiLooksLikeAlamatJalanNomor($msgForInfer);
    }

    private function kurirNormalizeClarifiedLokasiDetail(string $detail, string $nama): string
    {
        $d = trim(preg_replace('/\s+/u', ' ', $detail));
        $d = preg_replace('/\b(saya|aku|sy|gue|awak|kami)\b/iu', ' ', $d) ?? $d;
        $d = preg_replace('/\b(tinggal|ngekos|ngkos)\b/iu', ' ', $d) ?? $d;
        $d = preg_replace('/^\s*di\s+/iu', '', $d) ?? $d;
        if ($nama !== '') {
            $extracted = $this->kurirExtractLokasiDetailFromJenisReply($d, $nama);
            if ($extracted !== null && $extracted !== '') {
                $d = $extracted;
            }
        }
        $d = trim(preg_replace('/\s+/u', ' ', (string) $d), " \t,.;:-");
        if ($d === '' || mb_strlen($d) < 2) {
            return '';
        }

        return mb_substr($d, 0, 255);
    }

    /**
     * @return list<array>
     */
    private function kurirLokasiListFromSession(array $session): array
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

        return is_array($list) ? $list : [];
    }

    /** Pilih lokasi dari daftar: nomor, atau nama/detail unik (bukan kategori "Kos"/"Rumah"). */
    private function kurirResolvePickedLokasiFromList(array $session, string $msg): ?array
    {
        $list = $this->kurirLokasiListFromSession($session);
        if (empty($list)) {
            return null;
        }
        if (preg_match('/^\s*(\d{1,2})\s*$/u', trim($msg), $m)) {
            $idx = (int) $m[1] - 1;

            return $list[$idx] ?? null;
        }
        $categories = ['rumah', 'kos', 'kost', 'kosan', 'mess', 'mes', 'asrama', 'kantor', 'penginapan', 'toko'];
        $hits = [];
        foreach ($list as $lok) {
            $nama = trim((string) ($lok['nama'] ?? ''));
            $detail = trim((string) ($lok['detail'] ?? ''));
            $ln = mb_strtolower($nama);
            $namaHit = $nama !== '' && mb_strlen($ln) >= 4 && !in_array($ln, $categories, true)
                && mb_stripos($msg, $nama) !== false;
            $detailHit = $detail !== '' && mb_strlen($detail) >= 4 && mb_stripos($msg, $detail) !== false;
            if ($namaHit || $detailHit) {
                $hits[] = $lok;
            }
        }

        return count($hits) === 1 ? $hits[0] : null;
    }

    private function kurirMsgMatchesLokasiListItem(array $session, string $msg): bool
    {
        return $this->kurirResolvePickedLokasiFromList($session, $msg) !== null;
    }

    private function kurirFindLokasiMatchingClarification(
        int $idPelanggan,
        string $nama,
        string $detail,
        int $excludeId = 0
    ): ?array {
        if ($idPelanggan <= 0 || $detail === '') {
            return null;
        }
        $dn = mb_strtolower(trim($detail));
        $nn = mb_strtolower(trim($nama));
        foreach ($this->kurirListLokasi($idPelanggan) as $lok) {
            $id = (int) ($lok['id_lokasi'] ?? 0);
            if ($id <= 0 || $id === $excludeId) {
                continue;
            }
            $ld = mb_strtolower(trim((string) ($lok['detail'] ?? '')));
            $ln = mb_strtolower(trim((string) ($lok['nama'] ?? '')));
            if ($ld === '') {
                continue;
            }
            $namaOk = ($nn === '' || $ln === $nn || $ln === '');
            $detailOk = ($ld === $dn)
                || (mb_strlen($dn) >= 4 && mb_strpos($ld, $dn) !== false)
                || (mb_strlen($ld) >= 6 && mb_strpos($dn, $ld) !== false);
            if ($namaOk && $detailOk) {
                return $lok;
            }
        }

        return null;
    }

    private function kurirPatchAktifDeliveryRequest(array $session, array $lok): void
    {
        $idRequest = (int) ($session['id_request'] ?? 0);
        if ($idRequest <= 0) {
            return;
        }
        $idLokasi = (int) ($lok['id_lokasi'] ?? 0);
        $nama = (string) ($lok['nama'] ?? '');
        $detail = (string) ($lok['detail'] ?? '');
        $latt = array_key_exists('latt', $lok) ? (float) $lok['latt'] : null;
        $longt = array_key_exists('longt', $lok) ? (float) $lok['longt'] : null;
        try {
            if ($latt !== null && $longt !== null && abs($latt) > 0.0001) {
                $tarif = (int) ($session['tarif'] ?? 0);
                DB::getInstance(1)->query(
                    'UPDATE delivery_request
                     SET lokasi_nama = ?, lokasi_detail = ?, id_lokasi = ?,
                         lokasi_latt = ?, lokasi_longt = ?, tarif_surcas = ?
                     WHERE id_request = ? AND delivery_status = \'berjalan\'',
                    [
                        $nama,
                        $detail,
                        $idLokasi > 0 ? $idLokasi : null,
                        $latt,
                        $longt,
                        $tarif,
                        $idRequest,
                    ]
                );
            } else {
                DB::getInstance(1)->query(
                    'UPDATE delivery_request
                     SET lokasi_nama = ?, lokasi_detail = ?, id_lokasi = ?
                     WHERE id_request = ? AND delivery_status = \'berjalan\'',
                    [$nama, $detail, $idLokasi > 0 ? $idLokasi : null, $idRequest]
                );
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function kurirApplySharelocToAktifRequest(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $coords = $this->kurirExtractCoords($msg);
        if ($coords === null) {
            return;
        }
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $latt = (float) $coords['lat'];
        $longt = (float) $coords['lng'];
        $nama = trim((string) ($session['lokasi_nama'] ?? '')) ?: 'Rumah';
        $detail = trim((string) ($session['lokasi_detail'] ?? ''));

        if ($idLokasi > 0) {
            $near = $this->lokasiFindNear($idPelanggan, $latt, $longt);
            if ($near !== null && (int) $near['id_lokasi'] !== $idLokasi) {
                $idLokasi = (int) $near['id_lokasi'];
                $nama = trim((string) ($near['nama'] ?? $nama)) ?: $nama;
                $detail = trim((string) ($near['detail'] ?? $detail));
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
            if (!empty($saved['nama'])) {
                $nama = (string) $saved['nama'];
            }
            if (!empty($saved['detail'])) {
                $detail = (string) $saved['detail'];
            }
        }

        $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
        $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
        $tarif = (int) $calc['tarif'];
        $this->saveKurirSession($waNumber, [
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
        ]);
        $this->kurirPatchAktifDeliveryRequest($session, [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
        ]);
        $this->kurirAppendSummary($waNumber, $session, 'update_shareloc_aktif');
        $label = $detail !== '' ? "{$nama}, {$detail}" : $nama;
        $tarifRp = AntarTarif::formatRp($tarif);
        $this->sendAutoreplyText(
            $waNumber,
            "Baik {$sapaan}, titik lokasi kami perbarui ke *{$label}*. Ongkir {$tarifRp}."
        );
    }

    private function kurirApplyLokasiDetailClarification(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg
    ): void {
        $inferred = $this->lokasiInferNamaDetailFromReply($msg);
        $nama = (string) ($inferred['nama'] ?? '');
        if ($nama === '') {
            $nama = trim((string) ($session['lokasi_nama'] ?? '')) ?: 'Rumah';
        }
        $detail = $this->kurirNormalizeClarifiedLokasiDetail((string) ($inferred['detail'] ?? ''), $nama);
        if ($detail === '') {
            $this->sendAutoreplyText(
                $waNumber,
                "Boleh sebut detail alamat yang benar ya {$sapaan}? Contoh: *kos Taman Sari*."
            );
            return;
        }

        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $matched = $this->kurirFindLokasiMatchingClarification($idPelanggan, $nama, $detail, $idLokasi);
        if ($matched !== null) {
            $mNama = (string) ($matched['nama'] ?? $nama);
            $mDetail = (string) ($matched['detail'] ?? $detail);
            $mId = (int) ($matched['id_lokasi'] ?? 0);
            $mLatt = (float) ($matched['latt'] ?? 0);
            $mLongt = (float) ($matched['longt'] ?? 0);
            $cab = $this->kurirCabangCoords((int) ($session['id_cabang'] ?? 0));
            $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $mLatt, $mLongt);
            $this->saveKurirSession($waNumber, [
                'id_lokasi' => $mId,
                'lokasi_nama' => $mNama,
                'lokasi_detail' => $mDetail,
                'latt' => $mLatt,
                'longt' => $mLongt,
                'tarif' => (int) $calc['tarif'],
            ]);
            $session = $this->getKurirSession($waNumber) ?: array_merge($session, [
                'id_lokasi' => $mId,
                'lokasi_nama' => $mNama,
                'lokasi_detail' => $mDetail,
                'latt' => $mLatt,
                'longt' => $mLongt,
                'tarif' => (int) $calc['tarif'],
            ]);
            $this->kurirAppendSummary($waNumber, $session, 'update_detail_switch=' . $mNama . ', ' . $mDetail);
            $this->kurirPatchAktifDeliveryRequest($session, [
                'id_lokasi' => $mId,
                'nama' => $mNama,
                'detail' => $mDetail,
                'latt' => $mLatt,
                'longt' => $mLongt,
            ]);
            if ((int) ($session['id_request'] ?? 0) > 0) {
                $label = $mDetail !== '' ? "{$mNama}, {$mDetail}" : $mNama;
                $this->sendAutoreplyText(
                    $waNumber,
                    "Baik {$sapaan}, alamat kami perbarui jadi *{$label}* ya."
                );
                return;
            }
            $this->kurirAfterLokasiReady($waNumber, $sapaan, $session, $matched, $msg);
            return;
        }

        $latt = (float) ($session['latt'] ?? 0);
        $longt = (float) ($session['longt'] ?? 0);
        if ($idLokasi > 0) {
            $this->kurirUpdateLokasi($idLokasi, $idPelanggan, [
                'nama' => $nama,
                'detail' => $detail,
            ]);
        }

        $this->saveKurirSession($waNumber, [
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
        ]);
        $session = $this->getKurirSession($waNumber) ?: array_merge($session, [
            'lokasi_nama' => $nama,
            'lokasi_detail' => $detail,
        ]);
        $this->kurirAppendSummary($waNumber, $session, 'update_detail=' . $nama . ', ' . $detail);

        $label = $detail !== '' ? "{$nama}, {$detail}" : $nama;
        $lok = [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
        ];
        if ((int) ($session['id_request'] ?? 0) > 0) {
            $this->kurirPatchAktifDeliveryRequest($session, $lok);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, alamat kami perbarui jadi *{$label}* ya."
            );
            return;
        }

        if ($idLokasi <= 0 && abs($latt) < 0.0001) {
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, kami catat *{$label}*. Pilih nomor lokasi atau kirim *shareloc* ya."
            );
            return;
        }

        $this->kurirPrepareConfirm($waNumber, $sapaan, $session, $lok, true);
    }

    private function kurirHandleConfirmLokasi(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksLikeLokasiDetailClarification($msg, $session)) {
            $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
            return;
        }
        if ($this->kurirLooksWantJam($msg, $session)) {
            $this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg);
            $session = $this->getKurirSession($waNumber) ?: $session;
            if ($this->kurirLooksExplicitConfirmAgree($msg, $session)) {
                $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                return;
            }
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, jamnya kami catat. Lokasinya sudah benar? "
                . "Balas *ya* untuk lanjut, atau sebut alamat yang benar / kirim shareloc."
            );
            return;
        }
        if ($this->kurirLooksExplicitConfirmAgree($msg, $session)) {
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
            "Lokasinya sudah benar {$sapaan}? Balas *ya* untuk lanjut, atau sebut alamat yang benar / kirim shareloc."
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
        $summary = $this->kurirMarkSharelocAsked($summary);
        $curStep = (string) ($session['step'] ?? '');

        // Sudah menunggu shareloc → diam (jangan spam)
        if (in_array($curStep, ['ask_shareloc', 'new_ask_shareloc'], true)) {
            $this->saveKurirSession($waNumber, [
                'step' => 'ask_shareloc',
                'summary' => $summary,
            ]);
            return;
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
            $this->kurirAskSharelocPrompt($sapaan, $session)
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
        $aksi = (($session['jenis'] ?? '') === 'antar') ? 'di antar' : 'di jemput';
        if (!empty($session['sekalian_jemput'])) {
            $noun = 'pengantaran & penjemputan';
            $aksi = 'di antar & di jemput';
        }
        $text = "Baik {$sapaan}, {$noun} sudah masuk antrian, laundry akan {$aksi} dalam 1 x 24 jam. mohon ditunggu 😊";
        $this->saveKurirSession($waNumber, ['step' => 'request_aktif']);
        $this->sendAutoreplyText($waNumber, $text);
        return true;
    }

    /**
     * Request sudah jalan — siap jam khusus, instant, atau batal.
     */
    private function kurirHandleRequestAktif(string $waNumber, string $sapaan, array $session, string $msg): void
    {
        if ($this->kurirLooksLikeLokasiDetailClarification($msg, $session)) {
            $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
            return;
        }
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

        $wantJam = $this->kurirLooksWantJam($msg, $session);
        if ($wantJam) {
            $this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg);
            return;
        }

        // Request sudah dikonfirmasi — jangan kirim ack berulang.
        $this->logAutoreplyTrace($waNumber, 'KURIR', 'request_aktif_silent');
    }

    /**
     * Simpan request waktu / butuh estimasi. Ack driver dulu.
     * Fonnte hanya sekali: jika sudah pending, cukup update session.
     * Tidak menimpa step lokasi (shareloc/pilih/konfirmasi).
     *
     * @return bool true = intent jam tertangkap
     */
    private function kurirTryCaptureJamIntent(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        ?array $waktuOverride = null
    ): bool {
        $waktu = $waktuOverride;
        if ($waktu === null) {
            $waktu = $this->parseEstimasiRequestWaktu($msg);
        }

        if ($waktuOverride === null
            && preg_match('/ask_ampm hour=(\d{1,2})/', (string) ($session['summary'] ?? ''), $ampmM)
            && preg_match('/\b(pagi|malam)\b/iu', $msg)
        ) {
            $rawH = (int) $ampmM[1];
            $ampmWord = preg_match('/\bmalam\b/iu', $msg) ? 'malam' : 'pagi';
            $synthetic = "jam {$rawH} {$ampmWord}";
            $prev = (string) ($session['request_text'] ?? '');
            if (preg_match('/\b(besok|bsk|hari\s*ini|lusa)\b/iu', $prev, $dayM)) {
                $synthetic .= ' ' . $dayM[0];
            }
            $parsedAmpm = $this->parseEstimasiRequestWaktu($synthetic);
            if ($this->estimasiWaktuIsResolved($parsedAmpm)) {
                $waktu = $parsedAmpm;
                $waktuOverride = $parsedAmpm;
            }
        }

        $forceResolved = $waktuOverride !== null && $this->estimasiWaktuIsResolved($waktuOverride);
        $isRequest = $forceResolved || $this->kurirLooksRequestWaktu($msg, $session);
        $isEst = !$isRequest && $this->kurirLooksButuhEstimasi($msg, $session);
        if (!$isRequest && !$isEst) {
            return false;
        }

        if ($isRequest && is_array($waktu) && !empty($waktu['ask_ampm']) && !$this->estimasiWaktuIsResolved($waktu)) {
            $rawH = (int) ($waktu['raw_hour'] ?? 0);
            $tgl = $waktu['tanggal'] ?? date('Y-m-d');
            $keepStep = $this->kurirShouldKeepLokasiStep($session);
            $set = [
                'request_text' => $msg,
                'request_tanggal' => $tgl,
                'request_jam' => null,
                'request_granted' => null,
                'summary' => mb_substr(
                    trim((string) ($session['summary'] ?? '') . ' | ask_ampm hour=' . $rawH),
                    0,
                    800
                ),
            ];
            if (!$keepStep) {
                $set['step'] = 'ask_jam_ampm';
            }
            $this->saveKurirSession($waNumber, $set);
            $this->replyAskJamPagiMalam($waNumber, $sapaan, $rawH);
            return true;
        }

        $alreadyPending = $this->kurirJamPendingInSession($session);
        $keepStep = $this->kurirShouldKeepLokasiStep($session);

        if ($isRequest) {
            if ($waktu === null || !$this->estimasiWaktuIsResolved($waktu)) {
                $waktu = $this->parseEstimasiRequestWaktu($msg);
            }
            if (!$this->estimasiWaktuIsResolved($waktu)) {
                return false;
            }
            $tgl = $waktu['tanggal'] ?? date('Y-m-d');
            $jam = $waktu['jam'];
            $today = date('Y-m-d');
            if ($tgl === $today && $this->kurirIsJamAfterNightCutoff(isset($jam) ? (float) $jam : null)) {
                $this->kurirReplyHardCutScheduleTomorrow($waNumber, $sapaan, $session);
                return true;
            }
            $set = [
                'request_text' => $msg,
                'request_tanggal' => $tgl,
                'request_jam' => $jam,
                'request_granted' => null,
                'butuh_estimasi' => 0,
                'estimasi_tanggal' => null,
                'estimasi_jam' => null,
                'driver_alt_tanggal' => null,
                'driver_alt_jam' => null,
            ];
            if (!$keepStep) {
                $set['step'] = 'wait_driver_jam';
            }
            $this->saveKurirSession($waNumber, $set);
            $this->kurirSendJamDriverAck($waNumber, $sapaan, (string) $tgl);
            if (!$alreadyPending) {
                $this->kurirForwardJamToGroups($waNumber, $session, $msg, (string) $tgl, (float) $jam);
            } else {
                $this->logAutoreplyTrace($waNumber, 'KURIR', 'jam_request_update_skip_fonnte');
            }
            return true;
        }

        $tglHint = $this->kurirPreferTanggalFromMsg($msg) ?: date('Y-m-d');
        $set = [
            'request_text' => $msg,
            'request_tanggal' => $tglHint,
            'request_jam' => null,
            'request_granted' => null,
            'butuh_estimasi' => 1,
            'estimasi_tanggal' => null,
            'estimasi_jam' => null,
            'driver_alt_tanggal' => null,
            'driver_alt_jam' => null,
        ];
        if (!$keepStep) {
            $set['step'] = 'wait_driver_jam';
        }
        $this->saveKurirSession($waNumber, $set);
        $this->kurirSendJamDriverAck($waNumber, $sapaan, (string) $tglHint);
        if (!$alreadyPending) {
            $this->kurirForwardJamEstimasiToGroups($waNumber, $session, $msg, (string) $tglHint);
        } else {
            $this->logAutoreplyTrace($waNumber, 'KURIR', 'jam_estimasi_update_skip_fonnte');
        }
        return true;
    }

    private function kurirJamPendingInSession(array $session): bool
    {
        $butuh = !empty($session['butuh_estimasi'])
            && ($session['estimasi_jam'] === null || $session['estimasi_jam'] === '');
        $req = $session['request_jam'] !== null && $session['request_jam'] !== ''
            && ($session['request_granted'] === null || $session['request_granted'] === '');

        return $butuh || $req;
    }

    private function kurirShouldKeepLokasiStep(array $session): bool
    {
        $step = (string) ($session['step'] ?? '');

        return in_array($step, [
            'lokasi_check', 'ask_shareloc', 'new_ask_shareloc', 'pick_lokasi',
            'confirm_lokasi', 'ask_layanan', 'wait_lokasi', 'ask_jenis',
        ], true);
    }

    private function kurirSendJamDriverAck(string $waNumber, string $sapaan, string $tgl): void
    {
        $today = date('Y-m-d');
        if ($tgl === $today && $this->kurirIsPastSamedayJamCutoff()) {
            if ($this->isOperatingHours()) {
                $this->sendAutoreplyText($waNumber, $this->kurirPastCutoffAlternatifAck($sapaan));
            } else {
                $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
            }
            return;
        }
        if (!$this->isOperatingHours()) {
            $this->sendAutoreplyText($waNumber, $this->kurirEscalateOutsideHoursAck($sapaan));
            return;
        }
        $emoji = $this->pickPenutupSoftSmile();
        $this->sendAutoreplyText($waNumber, "Baik {$sapaan}, kami tanyakan dulu ke driver ya {$emoji}");
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
        if ($this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg, $waktuOverride)) {
            return;
        }
        $this->logAutoreplyTrace($waNumber, 'KURIR', 'jam_intent_unresolved_no_escalate');
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
        $this->kurirTryCaptureJamIntent(
            $waNumber,
            $sapaan,
            $session,
            $prev !== '' ? $prev : $synthetic,
            $waktu
        );
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
        $this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg);
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
        $tag = $this->kurirGroupCustomerStarTag($waNumber, $session);
        $jenis = $this->kurirJenisLabel($session);
        $groupText = "{$tag} minta perkiraan {$jenis}.";

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
        $this->kurirTryCaptureJamIntent($waNumber, $sapaan, $session, $msg, $waktu);
    }

    private function kurirForwardJamToGroups(
        string $waNumber,
        array $session,
        string $msg,
        string $tgl,
        float $jam
    ): void {
        $tag = $this->kurirGroupCustomerStarTag($waNumber, $session);
        $jenis = $this->kurirJenisLabel($session);
        $waktu = $this->kurirGroupWaktuLabel($tgl, $jam);
        $groupText = "{$tag} minta {$jenis} {$waktu}.";

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte')) {
                require_once __DIR__ . '/../Config/Fonnte.php';
            }
            $driverG = \App\Config\Fonnte::getDriverGroupId();
            if ($driverG === '') {
                return;
            }
            $fonnte = new \App\Helpers\CRM\FonnteService();
            $fonnte->sendToGroup($driverG, $groupText);
            $this->logAutoreplyTrace($waNumber, 'MINTA_JEMPUT_ANTAR', 'forward_jam_delivery_group ok');
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
        // Batal hanya frasa jelas (batal/cancel/gak jadi/jemput sendiri) — selain itu = lanjut jam alternatif
        if ($this->kurirLooksCancel($msg)) {
            $this->kurirCancelDeliveryRequest($session);
            $id = (int) ($session['id_pelanggan'] ?? 0);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, permintaan dibatalkan. 🙏\n"
                . "Untuk pesan antar/jemput bisa juga via link berikut:\n"
                . "https://ml.nalju.com/J/kurir/{$id}"
            );
            $this->clearKurirSession($waNumber);
            return;
        }

        $alt = [
            'tanggal' => $session['driver_alt_tanggal'] ?? date('Y-m-d'),
            'jam' => $session['driver_alt_jam'] ?? null,
        ];
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
        return "Apakah permintaan {$jenis} tetap dilanjutkan{$altPart} {$sapaan}?";
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
     * Aktifkan kembali delivery_request Antar yang di-pending dari board Delivery.
     *
     * @return int id_request (0 jika tidak ada)
     */
    private function kurirReactivatePendingAntar(
        string $waNumber,
        array $session,
        int $idPelanggan,
        int $preferId = 0
    ): int {
        if ($this->kurirJenisLabel($session) !== 'antar') {
            return 0;
        }
        $db = DB::getInstance(1);
        try {
            $row = null;
            if ($preferId > 0) {
                $row = $db->query(
                    "SELECT id_request, id_cabang FROM delivery_request
                     WHERE id_request = ?
                       AND jenis = 'antar'
                       AND delivery_status = 'pending'
                       AND layanan = 'sameday'
                     LIMIT 1",
                    [$preferId]
                )->row();
            }
            if (!$row && $idPelanggan > 0) {
                $row = $db->query(
                    "SELECT id_request, id_cabang FROM delivery_request
                     WHERE id_pelanggan = ?
                       AND jenis = 'antar'
                       AND delivery_status = 'pending'
                       AND layanan = 'sameday'
                     ORDER BY id_request DESC
                     LIMIT 1",
                    [$idPelanggan]
                )->row();
            }
            $idRequest = (int) ($row->id_request ?? 0);
            if ($idRequest <= 0) {
                return 0;
            }
            $db->update(
                'delivery_request',
                ['delivery_status' => 'berjalan'],
                ['id_request' => $idRequest]
            );
            $idCabang = (int) ($session['id_cabang'] ?? 0);
            if ($idCabang <= 0) {
                $idCabang = (int) ($row->id_cabang ?? 0);
            }
            $this->saveKurirSession($waNumber, [
                'id_request' => $idRequest,
                'group_notify_label' => '',
            ]);
            $notifySession = $session;
            $notifySession['id_request'] = $idRequest;
            $notifySession['group_notify_label'] = '';
            $this->kurirNotifyDeliveryGroupRequestCreated(
                $waNumber,
                $idPelanggan,
                $idCabang,
                $notifySession
            );
            $this->kurirAppendSummary($waNumber, $session, 'reactivate_pending=' . $idRequest);
            $this->logAutoreplyTrace(
                $waNumber,
                'MINTA_JEMPUT_ANTAR',
                'reactivate_pending_antar id=' . $idRequest
            );
            return $idRequest;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirReactivatePendingAntar: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return 0;
        }
    }

    /**
     * Early activate: buat/reuse delivery_request segera setelah jenis ketahuan.
     * Lokasi/tarif boleh kosong — driver tetap bisa selesaikan; chat bisa melengkapi nanti.
     *
     * @return int id_request (0 jika gagal)
     */
    private function kurirEarlyActivateRequest(string $waNumber, array $session): int
    {
        $existingId = (int) ($session['id_request'] ?? 0);
        $idPelanggan = (int) ($session['id_pelanggan'] ?? 0);
        $idCabang = (int) ($session['id_cabang'] ?? 0);
        $jenis = $this->kurirJenisLabel($session);
        $defaultLok = $this->kurirPickDefaultLokasiForEarlyRequest($idPelanggan);

        if ($existingId > 0) {
            $st = null;
            try {
                $st = DB::getInstance(1)->query(
                    'SELECT delivery_status, jenis FROM delivery_request WHERE id_request = ? LIMIT 1',
                    [$existingId]
                )->row();
            } catch (\Throwable $e) {
                $st = null;
            }
            $stStatus = strtolower((string) ($st->delivery_status ?? ''));
            $stJenis = strtolower((string) ($st->jenis ?? ''));
            if ($stStatus === 'pending') {
                if ($jenis === 'antar' && $stJenis === 'antar') {
                    $ok = $this->kurirReactivatePendingAntar($waNumber, $session, $idPelanggan, $existingId);
                    if ($ok > 0) {
                        return $ok;
                    }
                }
                $this->saveKurirSession($waNumber, ['id_request' => null]);
                $existingId = 0;
                $session['id_request'] = 0;
            }
        }

        if ($jenis === 'antar' && $existingId <= 0) {
            $pendingId = $this->kurirReactivatePendingAntar($waNumber, $session, $idPelanggan, 0);
            if ($pendingId > 0) {
                return $pendingId;
            }
        }

        if ($existingId > 0) {
            $this->kurirSyncEarlyRequestJenis($existingId, $jenis, $this->kurirSekalianJemputVal($session));
            if ($defaultLok !== null && (int) ($session['id_lokasi'] ?? 0) <= 0) {
                $this->kurirEarlyApplyLokasiToRequestAndSession($waNumber, $existingId, $idCabang, $defaultLok);
            }
            return $existingId;
        }

        if ($idPelanggan <= 0 || $idCabang <= 0) {
            return 0;
        }

        $phoneTail = $this->kurirPhoneTail($idPelanggan, $waNumber);
        if (strlen($phoneTail) < 8) {
            return 0;
        }

        $db = DB::getInstance(1);
        try {
            // Reuse request berjalan jenis sama (hindari dobel di board)
            $row = $db->query(
                "SELECT id_request, id_lokasi FROM delivery_request
                 WHERE id_pelanggan = ?
                   AND jenis = ?
                   AND delivery_status IN ('berjalan','menunggu_pembayaran')
                   AND layanan = 'sameday'
                 ORDER BY
                   CASE WHEN COALESCE(id_lokasi, 0) = 0 THEN 0 ELSE 1 END,
                   id_request DESC
                 LIMIT 1",
                [$idPelanggan, $jenis]
            )->row();
            $reuseId = (int) ($row->id_request ?? 0);
            if ($reuseId > 0) {
                $this->saveKurirSession($waNumber, ['id_request' => $reuseId]);
                $this->kurirSyncEarlyRequestJenis($reuseId, $jenis, $this->kurirSekalianJemputVal($session));
                if ($defaultLok !== null && (int) ($row->id_lokasi ?? 0) <= 0) {
                    $this->kurirEarlyApplyLokasiToRequestAndSession($waNumber, $reuseId, $idCabang, $defaultLok);
                }
                $this->kurirAppendSummary($waNumber, $session, 'early_reuse=' . $reuseId . '/' . $jenis);
                return $reuseId;
            }

            $now = date('Y-m-d H:i:s');
            $insData = [
                'sumber' => 'customer',
                'jenis' => $jenis,
                'sekalian_jemput' => $this->kurirSekalianJemputVal($session),
                'layanan' => 'sameday',
                'delivery_status' => 'berjalan',
                'id_pelanggan' => $idPelanggan,
                'phone_tail' => $phoneTail,
                'id_cabang' => $idCabang,
                'id_lokasi' => 0,
                'lokasi_nama' => '',
                'lokasi_detail' => '',
                'lokasi_latt' => 0,
                'lokasi_longt' => 0,
                'insertTime' => $now,
                'catatan_kurir' => 'Early activate chat (lokasi menyusul)',
            ];
            if ($defaultLok !== null) {
                $insData = array_merge($insData, $this->kurirLokasiFieldsForDeliveryRequest($defaultLok));
                $insData['catatan_kurir'] = 'Early activate chat';
                $latt = (float) ($defaultLok['latt'] ?? 0);
                $longt = (float) ($defaultLok['longt'] ?? 0);
                if (abs($latt) > 0.0001 && abs($longt) > 0.0001) {
                    $cab = $this->kurirCabangCoords($idCabang);
                    $calc = AntarTarif::tarifFromCoords($cab['latt'], $cab['long'], $latt, $longt);
                    $insData['tarif_surcas'] = (int) $calc['tarif'];
                }
            }
            $idRequest = $db->insert('delivery_request', $insData);
            $idRequest = $idRequest ? (int) $idRequest : 0;
            if ($idRequest <= 0) {
                throw new \RuntimeException('early_insert_id empty');
            }

            // Antar: tautkan item eligible bila ada (opsional — driver tetap bisa pilih saat selesai)
            if ($jenis === 'antar') {
                $eligibleIds = $this->kurirEligibleSaleIds($idPelanggan, false);
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

            $this->saveKurirSession($waNumber, ['id_request' => $idRequest]);
            if ($defaultLok !== null) {
                $this->saveKurirSession($waNumber, $this->kurirSessionPatchFromLokasi($defaultLok, $idCabang));
            }
            $this->kurirAppendSummary($waNumber, $session, 'early_activate=' . $idRequest . '/' . $jenis);
            return $idRequest;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirEarlyActivateRequest: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return 0;
        }
    }

    private function kurirSyncEarlyRequestJenis(int $idRequest, string $jenis, int $sekalianJemput = 0): void
    {
        if ($idRequest <= 0 || !in_array($jenis, ['antar', 'jemput'], true)) {
            return;
        }
        $sekalianJemput = ($jenis === 'antar' && $sekalianJemput) ? 1 : 0;
        try {
            $db = DB::getInstance(1);
            $row = $db->query(
                'SELECT jenis, sekalian_jemput FROM delivery_request WHERE id_request = ? LIMIT 1',
                [$idRequest]
            )->row();
            $set = [];
            if ($row && strtolower((string) ($row->jenis ?? '')) !== $jenis) {
                $set['jenis'] = $jenis;
            }
            $prevSekalian = (int) ($row->sekalian_jemput ?? 0);
            if ($row && $prevSekalian !== $sekalianJemput) {
                $set['sekalian_jemput'] = $sekalianJemput;
            }
            if ($set !== []) {
                $db->update('delivery_request', $set, ['id_request' => $idRequest]);
            }
        } catch (\Throwable $e) {
            // Kolom sekalian_jemput mungkin belum ada — coba jenis saja
            try {
                DB::getInstance(1)->update(
                    'delivery_request',
                    ['jenis' => $jenis],
                    ['id_request' => $idRequest]
                );
            } catch (\Throwable $e2) {
                // ignore
            }
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
        $existingId = (int) ($session['id_request'] ?? 0);

        // Early request sudah ada: lengkapi lokasi/tarif (lokasi wajib untuk update final dari chat)
        if ($existingId > 0) {
            if ($idPelanggan <= 0 || $idCabang <= 0) {
                $this->sendAutoreplyText($waNumber, 'Maaf, data cabang belum lengkap. Silakan ulangi permintaan.');
                return false;
            }
            if ($idLokasi <= 0) {
                $this->sendAutoreplyText($waNumber, 'Maaf, data lokasi belum lengkap. Silakan ulangi permintaan.');
                return false;
            }
        } elseif ($idPelanggan <= 0 || $idCabang <= 0 || $idLokasi <= 0) {
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
            // Early request: boleh kosong (driver pilih item saat selesai). Insert baru tetap butuh item.
            if (empty($eligibleIds) && $existingId <= 0) {
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
        if ($tarif <= 0 && $idLokasi > 0) {
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
            if ($existingId > 0) {
                $set = [
                    'jenis' => $jenis,
                    'sekalian_jemput' => $this->kurirSekalianJemputVal($session),
                    'id_cabang' => $idCabang,
                    'id_lokasi' => $idLokasi,
                    'lokasi_nama' => (string) ($session['lokasi_nama'] ?? ''),
                    'lokasi_detail' => (string) ($session['lokasi_detail'] ?? ''),
                    'lokasi_latt' => (float) ($session['latt'] ?? 0),
                    'lokasi_longt' => (float) ($session['longt'] ?? 0),
                    'tarif_surcas' => $tarif,
                ];
                try {
                    $prevSt = $db->query(
                        'SELECT delivery_status FROM delivery_request WHERE id_request = ? LIMIT 1',
                        [$existingId]
                    )->row();
                    if (strtolower((string) ($prevSt->delivery_status ?? '')) === 'pending') {
                        $set['delivery_status'] = 'berjalan';
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
                if ($catatan !== '') {
                    $set['catatan_kurir'] = mb_substr($catatan, 0, 150);
                } else {
                    // Hapus catatan early-activate saja jika lokasi sudah lengkap
                    $prev = $db->query(
                        'SELECT catatan_kurir FROM delivery_request WHERE id_request = ? LIMIT 1',
                        [$existingId]
                    )->row();
                    $prevCatatan = trim((string) ($prev->catatan_kurir ?? ''));
                    if (stripos($prevCatatan, 'Early activate') === 0) {
                        $set['catatan_kurir'] = '';
                    }
                }
                $db->update('delivery_request', $set, ['id_request' => $existingId]);

                if ($jenis === 'antar' && !empty($eligibleIds)) {
                    $this->kurirEnsureRequestItems($db, $existingId, $eligibleIds);
                }

                if ($jenis === 'antar' && $tarif > 0 && !empty($eligibleIds)) {
                    $this->kurirTryAttachSurcasPengantaran(
                        $db,
                        $waNumber,
                        $idPelanggan,
                        $idCabang,
                        $eligibleIds,
                        $tarif,
                        $existingId
                    );
                }

                $this->saveKurirSession($waNumber, ['id_request' => $existingId, 'step' => 'request_aktif']);
                $sessionNotify = $this->getKurirSession($waNumber) ?: $session;
                $sessionNotify['jenis'] = $jenis;
                $this->kurirNotifyDeliveryGroupRequestCreated($waNumber, $idPelanggan, $idCabang, $sessionNotify);
                return true;
            }

            $insData = [
                'sumber' => 'customer',
                'jenis' => $jenis,
                'sekalian_jemput' => $this->kurirSekalianJemputVal($session),
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
                if ($tarif > 0 && !empty($eligibleIds)) {
                    $this->kurirTryAttachSurcasPengantaran(
                        $db,
                        $waNumber,
                        $idPelanggan,
                        $idCabang,
                        $eligibleIds,
                        $tarif,
                        $idRequest
                    );
                }
            }
            $this->saveKurirSession($waNumber, ['id_request' => $idRequest, 'step' => 'request_aktif']);
            $sessionNotify = $this->getKurirSession($waNumber) ?: $session;
            $sessionNotify['jenis'] = $jenis;
            $this->kurirNotifyDeliveryGroupRequestCreated($waNumber, $idPelanggan, $idCabang, $sessionNotify);
            return true;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertSamedayRequest: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->sendAutoreplyText($waNumber, 'Maaf, gagal membuat permintaan. Coba lagi atau pakai link portal.');
            return false;
        }
    }

    /**
     * Notif singkat ke group delivery Fonnte saat request antar/jemput berhasil.
     * Contoh:
     * ANDI - TG
     * *Antar*
     * Jika label berubah setelah terkirim: *Antar & Jemput* + (update)
     */
    private function kurirNotifyDeliveryGroupRequestCreated(
        string $waNumber,
        int $idPelanggan,
        int $idCabang,
        array $session
    ): void {
        try {
            $jenisLabel = $this->kurirGroupJenisDisplay($session);
            $prevLabel = trim((string) ($session['group_notify_label'] ?? ''));
            if ($prevLabel !== '' && $prevLabel === $jenisLabel) {
                return;
            }
            $isUpdate = $prevLabel !== '';

            $nama = '';
            if ($idPelanggan > 0) {
                $row = DB::getInstance(1)->query(
                    'SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                    [$idPelanggan]
                )->row();
                $nama = trim((string) ($row->nama_pelanggan ?? ''));
            }
            if ($nama === '') {
                $nama = trim($this->getContactNameForGreeting($waNumber));
            }
            if ($nama === '') {
                $nama = 'PELANGGAN';
            }
            $nama = mb_strtoupper($nama, 'UTF-8');

            $kode = '';
            if ($idCabang > 0) {
                $cab = DB::getInstance(1)->query(
                    'SELECT kode_cabang FROM cabang WHERE id_cabang = ? LIMIT 1',
                    [$idCabang]
                )->row();
                $kode = trim((string) ($cab->kode_cabang ?? ''));
            }
            if ($kode === '') {
                $kode = (string) ($idCabang > 0 ? $idCabang : '-');
            }

            $text = "{$nama} - {$kode}\n*{$jenisLabel}*";
            if ($isUpdate) {
                $text .= "\n(update)";
            }

            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte')) {
                require_once __DIR__ . '/../Config/Fonnte.php';
            }
            $driverG = \App\Config\Fonnte::getDriverGroupId();
            if ($driverG === '') {
                return;
            }
            $fonnte = new \App\Helpers\CRM\FonnteService();
            $fonnte->sendToGroup($driverG, $text);
            $this->saveKurirSession($waNumber, ['group_notify_label' => $jenisLabel]);
            $this->logAutoreplyTrace(
                $waNumber,
                'MINTA_JEMPUT_ANTAR',
                'notify_delivery_group ok label=' . $jenisLabel . ($isUpdate ? ' update=1' : '')
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirNotifyDeliveryGroupRequestCreated: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Antar sameday sukses: tambah surcas pengantaran ke satu no_ref belum tuntas (paling lama).
     * @param object $db
     * @param int[] $eligibleIds
     */
    private function kurirTryAttachSurcasPengantaran(
        $db,
        string $waNumber,
        int $idPelanggan,
        int $idCabang,
        array $eligibleIds,
        int $tarif,
        int $idDeliveryRequest
    ): void {
        $noRef = $this->kurirPickOldestBelumTuntasRef($idPelanggan, $eligibleIds);
        if ($noRef === null || $noRef === '') {
            $this->logAutoreplyTrace($waNumber, 'KURIR_SURCAS', 'skip no eligible ref');
            return;
        }

        $result = $this->kurirInsertSurcasPengantaran($db, $idCabang, $noRef, $tarif, $idDeliveryRequest);
        $this->logAutoreplyTrace(
            $waNumber,
            'KURIR_SURCAS',
            'ref=' . $noRef . ' jumlah=' . $tarif . ' id_request=' . $idDeliveryRequest . ' result=' . (string) $result
        );
    }

    /**
     * Satu no_ref belum tuntas (paling lama) dari item eligible — mirror J.php pickBelumTuntasRef + id_user_ambil=0.
     * @param int[] $eligibleIds
     */
    private function kurirPickOldestBelumTuntasRef(int $idPelanggan, array $eligibleIds): ?string
    {
        if ($idPelanggan <= 0 || empty($eligibleIds)) {
            return null;
        }
        $safeIds = [];
        foreach ($eligibleIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $safeIds[$id] = $id;
            }
        }
        if (empty($safeIds)) {
            return null;
        }
        $idsIn = implode(',', array_values($safeIds));

        try {
            $rows = DB::getInstance(1)->query(
                "SELECT no_ref, MIN(id_penjualan) AS min_id
                 FROM sale
                 WHERE id_pelanggan = ?
                   AND bin = 0
                   AND tuntas = 0
                   AND id_user_ambil = 0
                   AND id_penjualan IN ($idsIn)
                   AND no_ref IS NOT NULL
                   AND TRIM(no_ref) <> ''
                 GROUP BY no_ref
                 ORDER BY min_id ASC
                 LIMIT 1",
                [$idPelanggan]
            )->result_array();
            if (empty($rows)) {
                return null;
            }
            $noRef = trim((string) ($rows[0]['no_ref'] ?? ''));

            return $noRef !== '' ? $noRef : null;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirPickOldestBelumTuntasRef: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }

            return null;
        }
    }

    /**
     * Insert surcas Pengantaran ke no_ref — skip jika sudah ada.
     * @param object $db
     * @return true|'exists'|false
     */
    private function kurirInsertSurcasPengantaran($db, int $idCabang, string $noRef, int $jumlah, int $idDeliveryRequest = 0)
    {
        $noRef = trim($noRef);
        $jumlah = (int) $jumlah;
        $idDeliveryRequest = (int) $idDeliveryRequest;
        if ($noRef === '' || $jumlah <= 0 || $idCabang <= 0) {
            return false;
        }

        $jenis = AntarTarif::SURCAS_JENIS_PENGANTARAN;
        try {
            $existing = $db->query(
                'SELECT id_surcas FROM surcas
                 WHERE id_cabang = ?
                   AND transaksi_jenis = 1
                   AND id_jenis_surcas = ?
                   AND no_ref = ?
                 LIMIT 1',
                [$idCabang, $jenis, $noRef]
            )->row();
            if ($existing && !empty($existing->id_surcas)) {
                return 'exists';
            }

            $row = [
                'id_cabang' => $idCabang,
                'transaksi_jenis' => 1,
                'id_jenis_surcas' => $jenis,
                'jumlah' => $jumlah,
                'id_user' => 0,
                'no_ref' => is_numeric($noRef) ? (0 + $noRef) : $noRef,
                'dari_delivery' => 1,
            ];
            if ($idDeliveryRequest > 0) {
                $row['id_delivery_request'] = $idDeliveryRequest;
            }
            $ok = $db->insert('surcas', $row);

            return $ok !== false ? true : false;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('kurirInsertSurcasPengantaran: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }

            return false;
        }
    }

    /**
     * Pastikan item antar ter-link ke request (skip yang sudah ada).
     * @param object $db
     * @param int[] $eligibleIds
     */
    private function kurirEnsureRequestItems($db, int $idRequest, array $eligibleIds): void
    {
        if ($idRequest <= 0 || empty($eligibleIds)) {
            return;
        }
        try {
            $existing = $db->query(
                'SELECT id_penjualan FROM delivery_request_item WHERE id_request = ?',
                [$idRequest]
            )->result_array();
            $have = [];
            foreach (is_array($existing) ? $existing : [] as $r) {
                $sid = (int) ($r['id_penjualan'] ?? 0);
                if ($sid > 0) {
                    $have[$sid] = true;
                }
            }
            foreach ($eligibleIds as $idSale) {
                $idSale = (int) $idSale;
                if ($idSale <= 0 || isset($have[$idSale])) {
                    continue;
                }
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
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function kurirPhoneTail(int $idPelanggan, string $waNumber): string
    {
        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }
        try {
            $row = DB::getInstance(1)->query(
                'SELECT nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ? LIMIT 1',
                [$idPelanggan]
            )->row();
            $fromPel = \App\Helpers\CRM\WaSenderContext::key((string) ($row->nomor_pelanggan ?? ''));
            if (strlen($fromPel) >= 8) {
                return $fromPel;
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return \App\Helpers\CRM\WaSenderContext::key($waNumber);
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

        $kapasitas = $this->kurirInstantKapasitasNote($session);
        if (count($rates) === 1) {
            $r = $rates[0];
            $rp = AntarTarif::formatRp((int) ($r['price'] ?? 0));
            $name = $r['courier_name'] ?? ($r['courier_company'] . ' ' . $r['courier_type']);
            $this->sendAutoreplyText(
                $waNumber,
                "Ada opsi Instant *{$name}* {$rp}. Lanjut pesan {$jenis} Instant {$sapaan}?\n"
                . $kapasitas
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
        $lines[] = $kapasitas;
        $this->sendAutoreplyText($waNumber, implode("\n", $lines));
    }

    private function kurirInstantKapasitasNote(array $session): string
    {
        $jenis = $this->kurirJenisLabel($session);
        $verb = ($jenis === 'antar') ? 'diantar' : 'dijemput';
        return "Pastikan laundry yg {$verb} sesuai kapasitas driver ya 😊";
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
            $this->sendAutoreplyText(
                $waNumber,
                "Lanjut pesan *{$name}* {$rp} {$sapaan}?\n"
                . $this->kurirInstantKapasitasNote($session)
            );
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
                'sekalian_jemput' => $this->kurirSekalianJemputVal($session),
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
            case 'confirm_lokasi':
                $actions = array_merge($common, ['confirm', 'update_detail', 'other_lokasi', 'delete_lokasi', 'ask_shareloc', 'want_jam', 'want_instant']);
                break;
            case 'pick_lokasi':
                $actions = array_merge($common, ['pick_lokasi', 'update_detail', 'other_lokasi', 'delete_lokasi', 'ask_shareloc', 'want_instant']);
                break;
            case 'delete_lokasi':
                $actions = array_merge($common, ['delete_lokasi', 'pick_lokasi']);
                break;
            case 'terms_setuju':
            case 'request_aktif':
                $actions = array_merge($common, ['want_jam', 'want_instant', 'noop_ack', 'delete_lokasi', 'update_detail']);
                break;
            case 'wait_driver_jam':
                $actions = array_merge($common, ['noop_ack', 'want_jam', 'update_detail']);
                break;
            case 'ask_jam_ampm':
                $actions = array_merge($common, ['want_jam', 'confirm']);
                break;
            case 'wait_continue_alt':
                $actions = array_merge($common, ['agree_alt', 'refuse_alt']);
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
        $lines[] = '- sekalian_jemput: ' . (!empty($session['sekalian_jemput']) ? '1' : '0');
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
            . "Jika setuju lokasi/ongkir → confirm HANYA bila pesan jelas setuju: ya/iya/oke/ok/baik/setuju/deal/sip. "
            . "Jangan confirm hanya karena ada kata 'ya' di kalimat panjang. "
            . "Jika customer mengoreksi/klarifikasi alamat (saya kos di X, bukan nomor itu, pindah kos, detail salah) "
            . "→ update_detail (JANGAN confirm). Update nama+detail lokasi yang sama, lalu konfirmasi ulang. "
            . "Di step request_aktif / wait_driver_jam: koreksi alamat atau shareloc baru → update_detail (request tetap berjalan). "
            . "Jika batal/gak jadi/gk jd/cancel/gak usah → cancel. "
            . "PENTING: 'ya sudah gak pa2' / 'gpp' / 'gapapa' / 'gak apa-apa' / 'yaudah' = SETUJU lanjut (agree/confirm/agree_alt), BUKAN cancel. "
            . "Jika minta jam tertentu → want_jam (isi slots.jam/tanggal jika ada). "
            . "Jam 1-6 tanpa 'pagi' biasanya sore (jam 3=15). Tanya 'jam berapa' tanpa angka tetap want_jam. "
            . "Jika minta cepat/gojek/grab/gosend/instant/sekarang → want_instant (langsung, jangan tanya sameday lagi). "
            . "Layanan default selalu sameday; jangan tawarkan pilihan 1/2 kecuali customer minta instant. "
            . "Typo anter/antr/dianter/diantr = antar. Ambil kain kotor = jemput. Bawakan kain yang siap = antar. "
            . "Jika customer minta antar sekaligus jemput (atau 'jemput juga' / ambil kotor + bawakan siap) → jenis antar, action confirm / lanjut flow, jangan clarify. "
            . "Di step wait_continue_alt: JANGAN tawarkan instant/grab/gojek. "
            . "Batal HANYA jika jelas: batal / cancel / gak jadi / gk jd / gak usah / saya jemput sendiri / antar sendiri. "
            . "Selain itu (ya, oke, gpp, gapapa, ok, atau jawaban bebas) → agree_alt (lanjut jam alternatif driver). "
            . "Di step ask_layanan (legacy): customer pilih sameday atau instant — action pick_layanan, isi slots.layanan = sameday|instant. "
            . "Jawaban bebas seperti 'sameday', 'grab', 'gosend', 'yang biasa' tetap pick_layanan di step itu. "
            . "Jika typo/kurang jelas → action unrelated atau diam (jangan clarify / jangan minta diketik ulang). "
            . "Di step ask_lokasi_nama / ask_lokasi_detail: customer jelaskan detail dalam satu jawaban (tidak dipilihkan kategori dulu). "
            . "Sistem infer kategori: Rumah/Kos/Mess/Asrama/Kantor/Penginapan/Toko (default Rumah jika tidak jelas). "
            . "Toko = studio/toko/warung/swalayan/kedai/minimarket/kios/cafe/sejenisnya. "
            . "Jika jawaban lengkap (kos azzahra kamar 2, rumah pagar kuning, toko sebelah Indomaret, mess BPK, hotel titip lobby) → confirm/lanjut tanpa tanya ulang. "
            . "Jika topik lain (estimasi siap, bill, harga, status, salam penutup, dll) → unrelated (jangan balas sebagai kurir). "
            . "Di step request_aktif / wait_driver_jam: HANYA want_jam jika ada kata jemput/antar sesuai jenis session "
            . "PLUS (jam angka = request waktu, ATAU kapan/jam berapa/pagi/siang/sore/malam = butuh estimasi). "
            . "'antar kembali' / 'selambatnya' / tanpa kata jemput/antar → unrelated. Jangan want_jam default. "
            . "Di step ask_shareloc: jika sudah pernah minta shareloc, JANGAN minta lagi (action noop / diam). "
            . "Hanya minta shareloc sekali; tunggu pin/link tanpa mengulang pertanyaan. "
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
                if ($this->kurirStepAllowsLokasiUpdate($step)
                    && $this->kurirLooksLikeLokasiDetailClarification($msg, $session)
                ) {
                    $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if ($step === 'ask_jenis') {
                    $jenis = null;
                    $slotJenis = strtolower((string) ($slots['jenis'] ?? ''));
                    if (in_array($slotJenis, ['antar', 'jemput'], true)) {
                        $jenis = $slotJenis;
                    } else {
                        $jenis = $this->detectKurirJenis($msg, $session);
                    }
                    if ($jenis) {
                        $resolved = $this->kurirResolveJenisState($msg, $session);
                        $sekalianJemput = ($jenis === 'antar') ? (int) $resolved['sekalian_jemput'] : 0;
                        $set = ['jenis' => $jenis, 'sekalian_jemput' => $sekalianJemput, 'step' => 'lokasi_check'];
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
                if ($step === 'confirm_lokasi') {
                    if (!$this->kurirLooksExplicitConfirmAgree($msg, $session)) {
                        $this->sendAutoreplyText(
                            $waNumber,
                            "Lokasinya sudah benar {$sapaan}? Balas *ya* untuk lanjut, atau sebut alamat yang benar / kirim shareloc."
                        );
                        $this->kurirAppendSummary($waNumber, $session, 'confirm_blocked_not_agree');
                        return;
                    }
                    $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                if (in_array($step, ['request_aktif', 'wait_driver_jam'], true)) {
                    return;
                }
                $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'update_detail':
                $this->kurirApplyLokasiDetailClarification($waNumber, $sapaan, $session, $msg);
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
                // Sudah pernah minta shareloc → diam (jangan kirim ulang / AI reply ulang)
                if ($this->kurirSharelocAlreadyAsked($session)) {
                    $this->saveKurirSession($waNumber, ['step' => 'ask_shareloc']);
                    return;
                }
                if ($aiReply) {
                    $summary = $this->kurirMarkSharelocAsked(trim((string) ($session['summary'] ?? '')));
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
                if (!$this->kurirLooksWantJam($msg, $session)
                    && !preg_match('/ask_ampm hour=/', (string) ($session['summary'] ?? ''))
                ) {
                    $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'want_jam_ignored_no_jenis_or_jam');
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
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
                // Ack driver dulu, baru konfirmasi lokasi/antrian
                $this->kurirProcessJamIntent($waNumber, $sapaan, $session, $msg, $waktu);
                if ($step === 'confirm_lokasi') {
                    $session = $this->getKurirSession($waNumber) ?: $session;
                    $this->kurirAcceptLokasiAndCreateRequest($waNumber, $sapaan, $session);
                }
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'want_instant':
                $step = (string) ($session['step'] ?? '');
                if ($step === 'wait_continue_alt') {
                    $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, $msg);
                    $this->kurirAppendSummary($waNumber, $session, $note);
                    return;
                }
                $this->kurirStartInstant($waNumber, $sapaan, $session, $msg);
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'agree_alt':
                $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, $msg !== '' ? $msg : 'ya');
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'refuse_alt':
                $step = (string) ($session['step'] ?? '');
                if ($step === 'wait_continue_alt') {
                    $this->kurirHandleContinueAlt($waNumber, $sapaan, $session, $msg);
                } elseif (in_array($step, ['instant_confirm', 'instant_pick'], true)) {
                    $this->kurirHandleInstantChoice($waNumber, $sapaan, $session, 'tidak');
                } else {
                    $this->kurirCancelAndReply($waNumber, $sapaan, $session);
                    return;
                }
                $this->kurirAppendSummary($waNumber, $session, $note);
                return;

            case 'noop_ack':
                // Jangan spam ack "permintaan sudah kami terima"
                $this->kurirAppendSummary($waNumber, $session, $note);
                $this->logAutoreplyTrace($waNumber, 'KURIR_AI', 'noop_ack_silent');
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
