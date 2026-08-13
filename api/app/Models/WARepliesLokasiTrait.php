<?php

namespace App\Models;

use App\Core\DB;
use App\Helpers\Laundry\AntarTarif;

/**
 * Intent LOKASI — simpan/lengkapi pelanggan_lokasi (terpisah dari MINTA_JEMPUT_ANTAR).
 * Saling melengkapi lewat DB: kurir membaca lokasi lengkap dari pelanggan_lokasi.
 */
trait WARepliesLokasiTrait
{
    /** Session LOKASI idle max 1 jam */
    private const LOKASI_SESSION_TTL_MINUTES = 60;
    /** Titik dianggap lokasi yang sama (km) */
    private const LOKASI_NEAR_KM = 0.08;
    /** Jangan spam tanya lengkapi dalam session yang sama */
    private const LOKASI_ASK_COOLDOWN_MINUTES = 60;

    private function getLokasiSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_lokasi_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
                $this->clearLokasiSession($waNumber);
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getLokasiSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return null;
        }
    }

    private function clearLokasiSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        try {
            DB::getInstance(0)->query('DELETE FROM wa_lokasi_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function saveLokasiSession(string $waNumber, array $data): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $existing = null;
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_lokasi_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if ($res && $res->num_rows() > 0) {
                $existing = (array) $res->row();
            }
        } catch (\Throwable $e) {
            $existing = null;
        }

        $merge = static function (string $key, $default = null) use ($data, $existing) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
            return $existing[$key] ?? $default;
        };

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::LOKASI_SESSION_TTL_MINUTES * 60));
        $vals = [
            $phone,
            $merge('id_pelanggan'),
            $merge('id_lokasi'),
            (string) $merge('step', 'ask_detail'),
            $merge('lokasi_nama'),
            $merge('lokasi_detail'),
            $merge('latt'),
            $merge('longt'),
            $merge('last_ask_at'),
            $merge('summary'),
            $now,
            $expires,
        ];

        try {
            DB::getInstance(0)->query(
                'INSERT INTO wa_lokasi_session
                  (phone, id_pelanggan, id_lokasi, step, lokasi_nama, lokasi_detail,
                   latt, longt, last_ask_at, summary, updated_at, expires_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   id_pelanggan=VALUES(id_pelanggan), id_lokasi=VALUES(id_lokasi),
                   step=VALUES(step), lokasi_nama=VALUES(lokasi_nama),
                   lokasi_detail=VALUES(lokasi_detail), latt=VALUES(latt), longt=VALUES(longt),
                   last_ask_at=VALUES(last_ask_at), summary=VALUES(summary),
                   updated_at=VALUES(updated_at), expires_at=VALUES(expires_at)',
                $vals
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('saveLokasiSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Ambil lat/lng dari teks (pin Fonnte/YCloud, q=@, shortlink maps_server).
     * Dipakai LOKASI dan kurir (kurirExtractCoords mendelegasi ke sini).
     *
     * @return array{lat:float,lng:float}|null
     */
    private function lokasiExtractCoords(string $msg): ?array
    {
        if (preg_match('/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/', $msg, $m)
            || preg_match('/(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)/', $msg, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
        if (preg_match('/[?&]q=(-?\d+\.?\d*),\s*(-?\d+\.?\d*)/i', $msg, $m)
            || preg_match('/@(-?\d+\.?\d+),(-?\d+\.?\d+)/', $msg, $m)
            || preg_match('/maps\/place\/(-?\d+\.?\d+),(-?\d+\.?\d+)/i', $msg, $m)) {
            $lat = (float) $m[1];
            $lng = (float) $m[2];
            if (abs($lat) <= 90 && abs($lng) <= 180 && !($lat == 0.0 && $lng == 0.0)) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }
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

    /** Pesan jelas minta jemput/antar (prioritas di atas LOKASI). */
    private function messageLooksLikeMintaJemputAntar(string $text, array $keywordConfig = []): bool
    {
        // Pertanyaan ongkir/ongkos saja — bukan minta kurir
        if ($this->messageLooksLikeOngkirOngkosInquiryOnly($text)) {
            return false;
        }
        // "kalau/klo ... antar/jemput" = hipotetis, bukan permintaan kurir
        if ($this->messageLooksLikeKalauAntarJemputBukanMinta($text)) {
            return false;
        }
        // "kami aja yang antar" / "saya yang jemput" = customer sendiri, bukan minta kurir
        if ($this->messageLooksLikeCustomerSelfAntarAtauJemput($text)) {
            return false;
        }
        if ($this->detectKurirJenis($text, null)) {
            // deteksi jenis saja tidak cukup (kata "antar" di konteks lain) — cek pola kuat
        }
        $patterns = $keywordConfig['MINTA_JEMPUT_ANTAR']['patterns'] ?? [];
        if ($patterns === []) {
            try {
                $full = $this->loadAutoreplyKeywordConfig();
                $patterns = $full['MINTA_JEMPUT_ANTAR']['patterns'] ?? [];
            } catch (\Throwable $e) {
                $patterns = [];
            }
        }
        foreach ($patterns as $pattern) {
            if (@preg_match($pattern, $text)) {
                return true;
            }
        }
        // Pin + kata jemput/antar eksplisit
        if (preg_match('/\b(tolong|minta|bantu|bisa|boleh)\s*(di)?(jemput|antar|anter)\b/iu', $text)) {
            return true;
        }
        return false;
    }

    /** Shareloc / link maps / teks pin. */
    private function lokasiLooksLikeShareloc(string $msg): bool
    {
        if ($this->lokasiExtractCoords($msg) !== null) {
            return true;
        }
        if (preg_match('/📍|shared\s*location|share\s*loc|shareloc/iu', $msg)) {
            return true;
        }
        if (preg_match('/https?:\/\/(?:maps\.app\.goo\.gl|goo\.gl\/maps|[^\s]*google\.[^\s]*\/maps)/i', $msg)) {
            return true;
        }
        return false;
    }

    /** Deskripsi alamat tanpa minta kurir. */
    private function lokasiLooksLikeAddressExplain(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '' || mb_strlen($t) > 220) {
            return false;
        }
        if ($this->messageLooksLikeMintaJemputAntar($t)) {
            return false;
        }
        // "ini alamatnya", "lokasi saya", "rumah pagar kuning", "kos azzahra"
        if (preg_match('/\b(ini\s+)?(alamat|lokasi|pin|titik)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(rumah|rmh|kos|kost|kosan|mess|mes|asrama|kantor|penginapan|hotel)\b/iu', $t)
            && preg_match('/\b(pagar|kuning|kamar|lobby|nomor|no\.?|nama|dekat|sebelah|belakang|depan|lt\.?|lantai)\b/iu', $t)
        ) {
            return true;
        }
        if (preg_match('/^\s*(rumah|kos|kost|mess|asrama|kantor|penginapan)\b.{0,80}$/iu', $t)) {
            return true;
        }
        return false;
    }

    private function lokasiRowIncomplete(?array $row): bool
    {
        if ($row === null) {
            return true;
        }
        $nama = trim((string) ($row['nama'] ?? $row['lokasi_nama'] ?? ''));
        $detail = trim((string) ($row['detail'] ?? $row['lokasi_detail'] ?? ''));
        if ($nama === '' || strcasecmp($nama, 'Shareloc') === 0) {
            return true;
        }
        if ($detail === '') {
            return true;
        }
        return false;
    }

    private function lokasiAskCooldownOk(?array $session): bool
    {
        $last = $session['last_ask_at'] ?? null;
        if ($last === null || $last === '') {
            return true;
        }
        $ts = strtotime((string) $last);
        if ($ts === false) {
            return true;
        }
        return (time() - $ts) >= (self::LOKASI_ASK_COOLDOWN_MINUTES * 60);
    }

    /**
     * Cari lokasi existing dalam radius, atau null.
     *
     * @return array|null
     */
    private function lokasiFindNear(int $idPelanggan, float $lat, float $lng): ?array
    {
        if ($idPelanggan <= 0) {
            return null;
        }
        try {
            $rows = DB::getInstance(1)->query(
                'SELECT id_lokasi, nama, detail, latt, longt
                 FROM pelanggan_lokasi
                 WHERE id_pelanggan = ?
                   AND latt IS NOT NULL AND longt IS NOT NULL
                   AND ABS(latt) > 0.0001 AND ABS(longt) > 0.0001
                 ORDER BY id_lokasi DESC',
                [$idPelanggan]
            )->result_array();
        } catch (\Throwable $e) {
            return null;
        }
        foreach ($rows as $r) {
            $km = AntarTarif::distanceKm($lat, $lng, (float) $r['latt'], (float) $r['longt']);
            if ($km <= self::LOKASI_NEAR_KM) {
                return $r;
            }
        }
        return null;
    }

    /**
     * Simpan koordinat: insert baru atau pakai yang hampir sama.
     *
     * @return array{id_lokasi:int,nama:string,detail:string,latt:float,longt:float,created:bool}|null
     */
    private function lokasiUpsertCoords(int $idPelanggan, float $lat, float $lng): ?array
    {
        $near = $this->lokasiFindNear($idPelanggan, $lat, $lng);
        if ($near !== null) {
            return [
                'id_lokasi' => (int) $near['id_lokasi'],
                'nama' => (string) ($near['nama'] ?? ''),
                'detail' => (string) ($near['detail'] ?? ''),
                'latt' => (float) $near['latt'],
                'longt' => (float) $near['longt'],
                'created' => false,
            ];
        }
        $id = $this->kurirInsertLokasi($idPelanggan, '', '', $lat, $lng);
        if ($id <= 0) {
            return null;
        }
        return [
            'id_lokasi' => $id,
            'nama' => '',
            'detail' => '',
            'latt' => $lat,
            'longt' => $lng,
            'created' => true,
        ];
    }

    /**
     * Entry intent LOKASI (regex/AI) + follow-up session.
     *
     * @return bool true = sudah ditangani
     */
    private function handleLokasi($phoneIn, $waNumber, $textBody = '')
    {
        $msg = trim((string) $textBody);
        $idPelanggan = $this->resolveIdPelangganForKurirLink($phoneIn, $waNumber);
        if (!$idPelanggan) {
            // Belum terdaftar: biarkan kurir/onboarding yang handle bila jemput
            return false;
        }
        $idPelanggan = (int) $idPelanggan;
        $sapaan = $this->getSapaanForGreeting($waNumber);
        $session = $this->getLokasiSession($waNumber);

        // Ada session → lanjut step
        if ($session !== null) {
            return $this->lokasiRouteStep($waNumber, $sapaan, $session, $msg, $idPelanggan);
        }

        $coords = $this->lokasiExtractCoords($msg);
        if ($coords !== null) {
            return $this->lokasiStartFromCoords($waNumber, $sapaan, $idPelanggan, $coords, $msg, true);
        }

        // Penjelasan alamat tanpa pin: mulai tanya shareloc bila belum ada lokasi lengkap
        if ($this->lokasiLooksLikeAddressExplain($msg)) {
            $incomplete = $this->kurirFindIncompleteLokasi($idPelanggan);
            if ($incomplete !== null) {
                return $this->lokasiResumeIncomplete($waNumber, $sapaan, $idPelanggan, $incomplete, true);
            }
            $complete = $this->kurirCompleteLokasiList($idPelanggan);
            if (!empty($complete)) {
                // Sudah ada lokasi lengkap — simpan petunjuk ke summary singkat, tidak ganggu
                $this->sendAutoreplyText(
                    $waNumber,
                    "Baik {$sapaan}, catatan alamat diterima. "
                    . "Kalau mau tambah titik baru, kirim *shareloc* / pin WhatsApp atau link Google Maps ya."
                );
                return true;
            }
            $this->saveLokasiSession($waNumber, [
                'id_pelanggan' => $idPelanggan,
                'step' => 'ask_shareloc',
                'summary' => '[alamat] ' . mb_substr($msg, 0, 200),
                'last_ask_at' => date('Y-m-d H:i:s'),
            ]);
            $this->sendAutoreplyText(
                $waNumber,
                "Baik {$sapaan}, supaya akurat boleh kirim *shareloc* / pin lokasi WhatsApp atau link Google Maps?"
            );
            return true;
        }

        return false;
    }

    /**
     * Shareloc di luar flow kurir ask_shareloc — simpan + boleh tanya lengkapi.
     *
     * @return bool true = sudah ditangani (ada balasan / cukup save silent+ask)
     */
    private function maybeHandleOpportunisticLokasi(string $phoneIn, string $waNumber, string $textBody): bool
    {
        if (!$this->lokasiLooksLikeShareloc($textBody)) {
            return false;
        }
        // Sedang di step kurir yang handle shareloc sendiri
        $kurir = $this->getKurirSession($waNumber);
        if ($kurir !== null) {
            $kStep = (string) ($kurir['step'] ?? '');
            if (in_array($kStep, [
                'ask_shareloc', 'new_ask_shareloc', 'confirm_lokasi', 'pick_lokasi', 'lokasi_check',
                'ask_lokasi_nama', 'ask_lokasi_detail',
            ], true)) {
                return false;
            }
        }
        if ($this->messageLooksLikeMintaJemputAntar(mb_strtolower($textBody))) {
            return false;
        }
        return $this->handleLokasi($phoneIn, $waNumber, $textBody);
    }

    /**
     * @param array{lat:float,lng:float} $coords
     */
    private function lokasiStartFromCoords(
        string $waNumber,
        string $sapaan,
        int $idPelanggan,
        array $coords,
        string $msg,
        bool $mayAsk
    ): bool {
        $saved = $this->lokasiUpsertCoords($idPelanggan, (float) $coords['lat'], (float) $coords['lng']);
        if ($saved === null) {
            $this->sendAutoreplyText($waNumber, "Maaf {$sapaan}, gagal menyimpan titik lokasi. Coba kirim shareloc lagi ya.");
            return true;
        }

        $row = [
            'id_lokasi' => $saved['id_lokasi'],
            'nama' => $saved['nama'],
            'detail' => $saved['detail'],
            'latt' => $saved['latt'],
            'longt' => $saved['longt'],
        ];

        if (!$this->lokasiRowIncomplete($row)) {
            // Lengkap — silent (atau ack singkat bila pesan murni shareloc)
            if ($mayAsk && $this->lokasiLooksLikeShareloc($msg) && !$this->lokasiLooksLikeAddressExplain($msg)) {
                // tidak perlu tanya; opsional tidak balas agar tidak ganggu — ack singkat saja
                $this->lokasiTryResumeKurirAfterComplete($waNumber, $sapaan, $row);
                return true;
            }
            $this->lokasiTryResumeKurirAfterComplete($waNumber, $sapaan, $row);
            return true;
        }

        $session = $this->getLokasiSession($waNumber);
        if ($mayAsk && !$this->lokasiAskCooldownOk($session)) {
            // Simpan coords saja, jangan tanya lagi
            $this->saveLokasiSession($waNumber, [
                'id_pelanggan' => $idPelanggan,
                'id_lokasi' => $saved['id_lokasi'],
                'latt' => $saved['latt'],
                'longt' => $saved['longt'],
                'lokasi_nama' => $saved['nama'] !== '' ? $saved['nama'] : null,
                'step' => 'ask_detail',
                'summary' => '[shareloc] saved_no_ask',
            ]);
            return true;
        }

        return $this->lokasiResumeIncomplete($waNumber, $sapaan, $idPelanggan, $row, true);
    }

    private function lokasiResumeIncomplete(
        string $waNumber,
        string $sapaan,
        int $idPelanggan,
        array $incomplete,
        bool $sendPrompt
    ): bool {
        $idLokasi = (int) ($incomplete['id_lokasi'] ?? 0);
        $nama = trim((string) ($incomplete['nama'] ?? ''));
        $detail = trim((string) ($incomplete['detail'] ?? ''));
        $latt = (float) ($incomplete['latt'] ?? 0);
        $longt = (float) ($incomplete['longt'] ?? 0);

        if ($nama !== '' && strcasecmp($nama, 'Shareloc') !== 0 && $detail !== '') {
            $this->clearLokasiSession($waNumber);
            $this->lokasiTryResumeKurirAfterComplete($waNumber, $sapaan, $incomplete);
            return true;
        }

        // Langsung minta detail (nama diinfer dari jawaban) — skip tanya rumah/kos/...
        $existing = $this->getLokasiSession($waNumber);
        $alreadyWaiting = $existing !== null
            && in_array((string) ($existing['step'] ?? ''), ['ask_detail', 'ask_shareloc', 'ask_nama'], true);

        $this->saveLokasiSession($waNumber, [
            'id_pelanggan' => $idPelanggan,
            'id_lokasi' => $idLokasi,
            'latt' => $latt,
            'longt' => $longt,
            'lokasi_nama' => ($nama !== '' && strcasecmp($nama, 'Shareloc') !== 0) ? $nama : null,
            'lokasi_detail' => null,
            'step' => 'ask_detail',
            'last_ask_at' => $alreadyWaiting
                ? ($existing['last_ask_at'] ?? date('Y-m-d H:i:s'))
                : date('Y-m-d H:i:s'),
        ]);
        // Sudah pernah ditanya → diam (jangan ulang prompt)
        if ($sendPrompt && !$alreadyWaiting) {
            $this->sendAutoreplyText($waNumber, $this->lokasiAskDetailPrompt($sapaan));
        }
        return true;
    }

    private function lokasiRouteStep(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        int $idPelanggan
    ): bool {
        $step = (string) ($session['step'] ?? 'ask_detail');
        // Session lama ask_nama → perlakukan sebagai ask_detail
        if ($step === 'ask_nama') {
            $step = 'ask_detail';
        }

        // Shareloc baru di tengah sesi
        $coords = $this->lokasiExtractCoords($msg);
        if ($coords !== null && in_array($step, ['ask_shareloc', 'ask_detail'], true)) {
            return $this->lokasiStartFromCoords($waNumber, $sapaan, $idPelanggan, $coords, $msg, true);
        }

        if ($step === 'ask_shareloc') {
            if ($coords === null) {
                // Bukan pin/maps — jangan spam "kirim shareloc"; biarkan intent lain (ingat, bon, dll.)
                return false;
            }
            return $this->lokasiStartFromCoords($waNumber, $sapaan, $idPelanggan, $coords, $msg, true);
        }

        if ($step === 'ask_detail') {
            // Jawaban detail biasanya teks bebas; jika mirip keyword singkat intent lain → lepas
            if (preg_match('/^\s*(reminder|remind|ingatkan|ingat|pengingat|bon|bill|cek|status|key)\s*$/iu', $msg)) {
                return false;
            }
            return $this->lokasiHandleDetail($waNumber, $sapaan, $session, $msg, $idPelanggan);
        }

        $this->clearLokasiSession($waNumber);
        return false;
    }

    private function lokasiHandleDetail(
        string $waNumber,
        string $sapaan,
        array $session,
        string $msg,
        int $idPelanggan
    ): bool {
        $inferred = $this->lokasiInferNamaDetailFromReply($msg);
        $nama = $inferred['nama'];
        $detail = $inferred['detail'];

        if ($detail === null || $detail === '') {
            $this->saveLokasiSession($waNumber, [
                'step' => 'ask_detail',
                'last_ask_at' => date('Y-m-d H:i:s'),
            ]);
            $this->sendAutoreplyText(
                $waNumber,
                "Detail masih kurang jelas {$sapaan}. "
                . "Contoh: *kos Azzahra kamar 2* / *rumah pagar kuning* / *toko sebelah Indomaret*."
            );
            return true;
        }

        $idLokasi = (int) ($session['id_lokasi'] ?? 0);
        $latt = (float) ($session['latt'] ?? 0);
        $longt = (float) ($session['longt'] ?? 0);

        if ($idLokasi > 0) {
            $this->kurirUpdateLokasi($idLokasi, $idPelanggan, [
                'nama' => $nama,
                'detail' => $detail,
            ]);
        } elseif (abs($latt) > 0.0001 && abs($longt) > 0.0001) {
            $idLokasi = $this->kurirInsertLokasi($idPelanggan, $nama, $detail, $latt, $longt);
        }

        $row = [
            'id_lokasi' => $idLokasi,
            'nama' => $nama,
            'detail' => $detail,
            'latt' => $latt,
            'longt' => $longt,
        ];
        $this->clearLokasiSession($waNumber);
        $resumed = $this->lokasiTryResumeKurirAfterComplete($waNumber, $sapaan, $row);
        if (!$resumed) {
            $this->sendAutoreplyText(
                $waNumber,
                "Terima kasih {$sapaan}, lokasi *{$nama}* ({$detail}) sudah disimpan."
            );
        }
        return true;
    }

    /**
     * Setelah lokasi lengkap: lanjutkan kurir bila session jemput/antar menunggu lokasi.
     *
     * @return bool true = kurir dilanjutkan (sudah kirim prompt kurir)
     */
    private function lokasiTryResumeKurirAfterComplete(string $waNumber, string $sapaan, array $lokRow): bool
    {
        $kurir = $this->getKurirSession($waNumber);
        if ($kurir === null) {
            return false;
        }
        $jenis = trim((string) ($kurir['jenis'] ?? ''));
        if ($jenis === '') {
            return false;
        }
        $step = (string) ($kurir['step'] ?? '');
        if (!in_array($step, [
            'ask_shareloc', 'ask_lokasi_nama', 'ask_lokasi_detail', 'lokasi_check',
            'wait_lokasi', 'pick_lokasi', 'confirm_lokasi',
        ], true)) {
            return false;
        }

        $idLokasi = (int) ($lokRow['id_lokasi'] ?? 0);
        $latt = (float) ($lokRow['latt'] ?? 0);
        $longt = (float) ($lokRow['longt'] ?? 0);
        $nama = trim((string) ($lokRow['nama'] ?? ''));
        $detail = trim((string) ($lokRow['detail'] ?? ''));

        $this->saveKurirSession($waNumber, [
            'id_lokasi' => $idLokasi > 0 ? $idLokasi : ($kurir['id_lokasi'] ?? null),
            'latt' => abs($latt) > 0.0001 ? $latt : ($kurir['latt'] ?? null),
            'longt' => abs($longt) > 0.0001 ? $longt : ($kurir['longt'] ?? null),
            'lokasi_nama' => $nama !== '' ? $nama : ($kurir['lokasi_nama'] ?? null),
            'lokasi_detail' => $detail !== '' ? $detail : ($kurir['lokasi_detail'] ?? null),
            'step' => 'lokasi_check',
        ]);
        $session = $this->getKurirSession($waNumber) ?: $kurir;
        $this->kurirLokasiCheck($waNumber, $sapaan, $session);
        return true;
    }

    /**
     * Kurir minta lengkapi lokasi → serahkan ke session LOKASI (state terpisah).
     */
    private function lokasiHandOffFromKurir(
        string $waNumber,
        string $sapaan,
        array $kurirSession,
        array $incomplete
    ): void {
        $idPelanggan = (int) ($kurirSession['id_pelanggan'] ?? 0);
        if ($idPelanggan <= 0) {
            return;
        }
        $this->saveKurirSession($waNumber, ['step' => 'wait_lokasi']);
        $this->lokasiResumeIncomplete($waNumber, $sapaan, $idPelanggan, $incomplete, true);
    }
}
