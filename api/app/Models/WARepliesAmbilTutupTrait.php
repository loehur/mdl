<?php

namespace App\Models;

use App\Core\DB;

/**
 * Intent AMBIL_LEWAT_TUTUP — customer ambil sendiri order selesai, lewat jam tutup.
 * Dipakai oleh WAReplies via `use`.
 */
trait WARepliesAmbilTutupTrait
{
    private const AMBIL_TUTUP_SESSION_TTL_MINUTES = 60;
    /** Maksimal menit setelah jam tutup yang masih boleh diajukan ke petugas */
    private const AMBIL_TUTUP_MAX_AFTER_CLOSE_MINUTES = 60;

    private function getAmbilTutupSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_ambil_tutup_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
                $this->clearAmbilTutupSession($waNumber);
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getAmbilTutupSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return null;
        }
    }

    private function saveAmbilTutupSession(string $waNumber, array $data): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $ttl = self::AMBIL_TUTUP_SESSION_TTL_MINUTES;
        $expires = date('Y-m-d H:i:s', time() + ($ttl * 60));
        $now = date('Y-m-d H:i:s');

        try {
            $db = DB::getInstance(0);
            $existing = null;
            $res = $db->query('SELECT * FROM wa_ambil_tutup_session WHERE phone = ? LIMIT 1', [$phone]);
            if ($res && $res->num_rows() > 0) {
                $existing = (array) $res->row();
            }

            $merge = static function (string $key, $default = null) use ($data, $existing) {
                if (array_key_exists($key, $data)) {
                    return $data[$key];
                }
                return $existing[$key] ?? $default;
            };

            $db->query(
                'INSERT INTO wa_ambil_tutup_session
                 (phone, id_penjualan, id_cabang, step, request_text, request_tanggal, request_jam,
                  request_granted, reject_reason, summary, updated_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id_penjualan = VALUES(id_penjualan),
                    id_cabang = VALUES(id_cabang),
                    step = VALUES(step),
                    request_text = VALUES(request_text),
                    request_tanggal = VALUES(request_tanggal),
                    request_jam = VALUES(request_jam),
                    request_granted = VALUES(request_granted),
                    reject_reason = VALUES(reject_reason),
                    summary = VALUES(summary),
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at)',
                [
                    $phone,
                    $merge('id_penjualan'),
                    $merge('id_cabang'),
                    $merge('step', 'ask_jam'),
                    $merge('request_text'),
                    $merge('request_tanggal'),
                    $merge('request_jam'),
                    $merge('request_granted'),
                    $merge('reject_reason'),
                    $merge('summary'),
                    $now,
                    $expires,
                ]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('saveAmbilTutupSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function clearAmbilTutupSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        try {
            DB::getInstance(0)->query('DELETE FROM wa_ambil_tutup_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Order selesai (notif tipe=2 + letak) yang belum diambil.
     */
    private function pickSelesaiSaleForAmbil(string $phoneIn, string $waNumber): ?array
    {
        $item = $this->pickEstimasiFocusItem($phoneIn, $waNumber);
        if ($item === null) {
            return null;
        }
        if (($item['fase'] ?? '') !== 'selesai') {
            // Cari kandidat selesai spesifik (pickEstimasi prefer unfinished)
            $db1 = DB::getInstance(1);
            $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
            $idPelanggans = array_column($pelanggan, 'id_pelanggan');
            if (empty($idPelanggans)) {
                return null;
            }
            $idsIn = implode(',', array_map('intval', $idPelanggans));
            $sales = $db1->query(
                "SELECT id_penjualan, id_cabang, letak
                 FROM sale
                 WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND id_pelanggan IN ($idsIn)
                 ORDER BY id_penjualan DESC"
            )->result_array();
            if (empty($sales)) {
                return null;
            }
            $idList = array_column($sales, 'id_penjualan');
            $quotedIds = array_map(static function ($id) {
                return "'" . (int) $id . "'";
            }, $idList);
            $idsInNotif = implode(',', $quotedIds);
            $existingNotifIds = !empty($idList)
                ? array_column(
                    $db1->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($idsInNotif)")->result_array(),
                    'no_ref'
                )
                : [];
            foreach ($sales as $sale) {
                $idPenjualan = (int) $sale['id_penjualan'];
                $letak = trim((string) ($sale['letak'] ?? ''));
                $hasNotif = in_array((string) $idPenjualan, array_map('strval', $existingNotifIds), true)
                    || in_array($idPenjualan, $existingNotifIds, true);
                if ($hasNotif && $letak !== '') {
                    $idCabang = isset($sale['id_cabang']) ? (int) $sale['id_cabang'] : 0;
                    return [
                        'id' => $idPenjualan,
                        'id_cabang' => $idCabang > 0 ? $idCabang : null,
                        'fase' => 'selesai',
                    ];
                }
            }
            return null;
        }
        return $item;
    }

    private function messageLooksLikeAmbilLewatTutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        // Ambil/jemput sendiri + konteks setelah/lewat tutup
        if (preg_match(
            '/\b(ambil|jemput|ngambil|mengambil)\b.{0,80}\b(setelah|lewat|habis|pas|nunggu|tunggu).{0,40}\b(tutup|jam\s*tutup)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match(
            '/\b(setelah|lewat|habis)\s*(jam\s*)?tutup\b.{0,60}\b(ambil|jemput)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match(
            '/\b(bisa|boleh|mau|nanti|malam|mlm)\b.{0,50}\b(ambil|jemput)\b.{0,60}\b(setelah|lewat)\s*(jam\s*)?tutup\b/iu',
            $t
        )) {
            return true;
        }
        // "nanti jam 9 malam ambil" / "ambil jam 22" — indikasi lewat jam operasional malam
        if (preg_match(
            '/\b(ambil|jemput|ngambil)\b.{0,40}\bjam\s*(\d{1,2})(?:[.:](\d{1,2}))?\b/iu',
            $t,
            $m
        ) || preg_match(
            '/\bjam\s*(\d{1,2})(?:[.:](\d{1,2}))?\b.{0,40}\b(ambil|jemput|ngambil)\b/iu',
            $t,
            $m
        )) {
            $h = (int) ($m[2] ?? $m[1] ?? 0);
            // Jam malam yang mungkin lewat tutup (setelah jam 20) + konteks malam / tutup / nunggu
            if ($h >= 20 && preg_match('/\b(malam|mlm|tutup|nunggu|tunggu|lewat|setelah)\b/iu', $t)) {
                return true;
            }
        }
        return false;
    }

    private function messageBreaksAmbilTutupSession(?string $text, array $keywordConfig): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        // Masih dalam alur jam / setuju → jangan break
        if ($this->parseEstimasiRequestWaktu($text) !== null) {
            return false;
        }
        if (preg_match('/\b(jam\s*(brp|brpa|berapa)|kira|kira[\s\-]*kira)\b/iu', $text)) {
            return false;
        }
        $breakHandlers = ['BON', 'TAGIHAN', 'NOTA', 'HARGA', 'HARGA_PAKET', 'HARGA_PAKET_D', 'STATUS', 'MINTA_JEMPUT_ANTAR'];
        foreach ($breakHandlers as $h) {
            if (!isset($keywordConfig[$h]['patterns']) || !is_array($keywordConfig[$h]['patterns'])) {
                continue;
            }
            foreach ($keywordConfig[$h]['patterns'] as $pat) {
                if (@preg_match($pat, $text)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return array{open_minutes:int,close_minutes:int,close_label:string,timezone:string}
     */
    private function getOperatingHoursBounds(): array
    {
        $config = require __DIR__ . '/../Config/OperatingHours.php';
        $closeH = (int) ($config['close_hour'] ?? 21);
        $closeM = (int) ($config['close_minute'] ?? 0);
        $openH = (int) ($config['open_hour'] ?? 7);
        $openM = (int) ($config['open_minute'] ?? 0);
        $closeLabel = sprintf('%02d.%02d', $closeH, $closeM);
        return [
            'open_minutes' => ($openH * 60) + $openM,
            'close_minutes' => ($closeH * 60) + $closeM,
            'close_label' => $closeLabel,
            'timezone' => (string) ($config['timezone'] ?? 'Asia/Jakarta'),
        ];
    }

    /**
     * Decimal jam DB (21.30) → menit sejak midnight.
     */
    private function decimalJamToMinutes($jam): int
    {
        $jam = (float) $jam;
        $h = (int) floor($jam);
        $frac = $jam - $h;
        // Format DECIMAL: 21.30 = 21:30 (bukan 0.3 * 60)
        $min = (int) round($frac * 100);
        if ($min > 59) {
            $min = (int) round($frac * 60);
        }
        return ($h * 60) + max(0, min(59, $min));
    }

    private function formatAmbilTutupJamLabel($jam): string
    {
        $mins = $this->decimalJamToMinutes($jam);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return sprintf('%02d.%02d', $h, $m);
    }

    /**
     * Handler AMBIL_LEWAT_TUTUP.
     * @return bool|void false = fall through routing
     */
    public function handleAmbil_Lewat_Tutup($phoneIn, $waNumber, $textBody = '')
    {
        // Di luar jam operasional → jangan masuk intent ini
        if (!$this->isOperatingHours()) {
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', 'outside_hours→JAM_OPERASIONAL');
            $this->clearAmbilTutupSession($waNumber);
            $this->currentHandler = 'JAM_OPERASIONAL';
            return $this->handleJam_operasional($phoneIn, $waNumber, $textBody ?? '', false, false);
        }

        $item = $this->pickSelesaiSaleForAmbil((string) $phoneIn, (string) $waNumber);
        if ($item === null) {
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', 'no_selesai_order→skip');
            $this->clearAmbilTutupSession($waNumber);
            return false;
        }

        $session = $this->getAmbilTutupSession($waNumber);
        $msg = trim((string) ($textBody ?? ''));
        $ctx = $this->getGreetingContext($waNumber);
        $sapaan = $ctx['sapaan'] ?? 'kak';
        $id = (int) $item['id'];
        $idCabang = isset($item['id_cabang']) ? (int) $item['id_cabang'] : null;
        if ($idCabang !== null && $idCabang <= 0) {
            $idCabang = null;
        }

        // Pending grant: jangan tanya ulang; ack singkat
        if ($session !== null
            && ($session['step'] ?? '') === 'pending_grant'
            && ($session['request_granted'] === null || $session['request_granted'] === '')) {
            $this->escalateAmbilTutupToPetugas($waNumber, $msg !== '' ? $msg : (string) ($session['request_text'] ?? ''), $idCabang, true, $session);
            return true;
        }

        $reqWaktu = $this->parseEstimasiRequestWaktu($msg);
        $step = $session['step'] ?? null;

        // Belum ada jam → tanya dulu (kecuali pesan sudah berisi jam)
        if ($reqWaktu === null && ($session === null || $step === 'ask_jam' || $step === null)) {
            $ask = "Baik {$sapaan}, laundry ID #{$id} sudah selesai. "
                . "Kira-kira jam berapa {$sapaan} sampai di laundry? "
                . "Agar kami cek dulu ke petugas ya 😊";
            $this->sendAutoreplyText($waNumber, $ask);
            $this->saveAmbilTutupSession($waNumber, [
                'id_penjualan' => $id,
                'id_cabang' => $idCabang,
                'step' => 'ask_jam',
                'request_text' => $msg !== '' ? $msg : null,
                'request_tanggal' => null,
                'request_jam' => null,
                'request_granted' => null,
                'reject_reason' => null,
                'summary' => '[pesan] ' . mb_substr($msg, 0, 120) . " | ask_jam #{$id}",
            ]);
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', "ask_jam id={$id}");
            return true;
        }

        if ($reqWaktu === null) {
            $this->sendAutoreplyText(
                $waNumber,
                "Maaf {$sapaan}, boleh sebut jamnya ya? Contoh: jam 21.30 😊"
            );
            $this->saveAmbilTutupSession($waNumber, [
                'id_penjualan' => $id,
                'id_cabang' => $idCabang,
                'step' => 'ask_jam',
                'summary' => 'ulang ask jam; pesan=' . mb_substr($msg, 0, 80),
            ]);
            return true;
        }

        return $this->processAmbilTutupJam(
            $waNumber,
            $sapaan,
            $id,
            $idCabang,
            $msg,
            $reqWaktu
        );
    }

    /**
     * @param array{jam:float,tanggal:?string} $reqWaktu
     */
    private function processAmbilTutupJam(
        string $waNumber,
        string $sapaan,
        int $id,
        ?int $idCabang,
        string $msg,
        array $reqWaktu
    ): bool {
        $bounds = $this->getOperatingHoursBounds();
        $closeMin = $bounds['close_minutes'];
        $openMin = $bounds['open_minutes'];
        $closeLabel = $bounds['close_label'];
        $reqJam = (float) $reqWaktu['jam'];
        $reqMin = $this->decimalJamToMinutes($reqJam);
        $tanggal = $reqWaktu['tanggal'] ?? date('Y-m-d');
        $today = date('Y-m-d');
        $jamLabel = $this->formatAmbilTutupJamLabel($reqJam);

        // Besok/lusa → arahkan datang di jam operasional
        if ($tanggal > $today) {
            $text = "Baik {$sapaan}, untuk tanggal tersebut silakan datang di jam operasional "
                . "(tutup jam {$closeLabel}). Terima kasih 😊\n\n_Ini adalah balasan AI Agent._";
            $this->sendAutoreplyText($waNumber, $text);
            $this->clearAmbilTutupSession($waNumber);
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', "future_date tanggal={$tanggal}");
            return true;
        }

        // Masih dalam jam buka (≤ tutup)
        if ($reqMin <= $closeMin) {
            $text = "Bisa {$sapaan}, kami masih buka sampai jam {$closeLabel}. "
                . "Laundry ID #{$id} sudah selesai, silakan datang jam {$jamLabel} ya 😊";
            $this->sendAutoreplyText($waNumber, $text);
            $this->clearAmbilTutupSession($waNumber);
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', "within_hours jam={$jamLabel}");
            return true;
        }

        $afterClose = $reqMin - $closeMin;
        $maxAfter = self::AMBIL_TUTUP_MAX_AFTER_CLOSE_MINUTES;

        // > 1 jam setelah tutup → auto tolak SOP
        if ($afterClose > $maxAfter) {
            $text = "Mohon maaf {$sapaan}, sesuai SOP petugas tidak diizinkan menunggu "
                . "hingga lebih dari 1 jam setelah tutup (tutup jam {$closeLabel}), "
                . "karena besok pagi sudah harus mulai bekerja kembali. "
                . "Silakan ambil besok di jam operasional ya {$sapaan}. 🙏\n\n"
                . "_Ini adalah balasan AI Agent._";
            $this->sendAutoreplyText($waNumber, $text);
            $this->clearAmbilTutupSession($waNumber);
            $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', "auto_reject after={$afterClose}m jam={$jamLabel}");
            return true;
        }

        // ≤ 1 jam setelah tutup → escalate petugas
        $summary = '[pesan] ' . mb_substr($msg, 0, 150)
            . " | Ambil lewat tutup jam {$jamLabel} (#{$id}); pending_grant";
        $sessionData = [
            'id_penjualan' => $id,
            'id_cabang' => $idCabang,
            'step' => 'pending_grant',
            'request_text' => $msg !== '' ? $msg : "Mau ambil jam {$jamLabel}",
            'request_tanggal' => $tanggal,
            'request_jam' => $reqJam,
            'request_granted' => null,
            'reject_reason' => null,
            'summary' => $summary,
        ];
        $this->saveAmbilTutupSession($waNumber, $sessionData);
        $this->escalateAmbilTutupToPetugas(
            $waNumber,
            $sessionData['request_text'],
            $idCabang,
            true,
            $sessionData
        );
        $this->logAutoreplyTrace($waNumber, 'AMBIL_LEWAT_TUTUP', "escalate jam={$jamLabel} after={$afterClose}m id={$id}");
        return true;
    }

    private function escalateAmbilTutupToPetugas(
        string $waNumber,
        string $customerMessage,
        ?int $idCabang = null,
        bool $sendAck = true,
        ?array $session = null
    ): void {
        $nama = trim($this->getContactNameForGreeting($waNumber));
        if ($nama === '') {
            $nama = 'Pelanggan';
        }
        $pesan = trim($customerMessage);
        if ($pesan === '') {
            $pesan = '(pesan kosong)';
        }
        $jamLabel = '';
        if ($session !== null && isset($session['request_jam']) && $session['request_jam'] !== null && $session['request_jam'] !== '') {
            $jamLabel = $this->formatAmbilTutupJamLabel($session['request_jam']);
        }
        $groupText = $jamLabel !== ''
            ? "{$nama} minta ambil lewat tutup jam {$jamLabel}: \"{$pesan}\". (AI Agent — AMBIL_LEWAT_TUTUP)"
            : "{$nama} minta ambil lewat tutup: \"{$pesan}\". (AI Agent — AMBIL_LEWAT_TUTUP)";

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            $groupId = $this->resolveEstimasiFonnteGroupId($idCabang);
            if ($groupId !== '') {
                $fonnte = new \App\Helpers\CRM\FonnteService();
                $fonnte->sendToGroup($groupId, $groupText);
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('escalateAmbilTutupToPetugas: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        if (!$sendAck || $this->isHumanAgentRecentlyActive($waNumber)) {
            return;
        }

        $summary = (string) (($session['summary'] ?? '') ?: '');
        if (preg_match('/ack_ts=(\d+)/', $summary, $m)) {
            $last = (int) $m[1];
            if ($last > 0 && (time() - $last) < 900) {
                return;
            }
        }

        $sapaan = $this->getSapaanForGreeting($waNumber);
        $ack = "Sebentar ya {$sapaan}, kami konfirmasikan dulu ke petugas apakah bisa menunggu. "
            . "Ini adalah AI Agent 😊";
        $this->sendAutoreplyText($waNumber, $ack);

        if ($session !== null) {
            $sum = preg_replace('/\s*\|\s*ack_ts=\d+/', '', (string) ($session['summary'] ?? ''));
            $this->saveAmbilTutupSession($waNumber, [
                'summary' => trim($sum . ($sum !== '' ? ' | ' : '') . 'ack_ts=' . time()),
                'id_penjualan' => $session['id_penjualan'] ?? null,
                'id_cabang' => $session['id_cabang'] ?? null,
                'step' => $session['step'] ?? 'pending_grant',
                'request_text' => $session['request_text'] ?? null,
                'request_tanggal' => $session['request_tanggal'] ?? null,
                'request_jam' => $session['request_jam'] ?? null,
                'request_granted' => $session['request_granted'] ?? null,
                'reject_reason' => $session['reject_reason'] ?? null,
            ]);
        }
    }
}
