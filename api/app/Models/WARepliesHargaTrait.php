<?php

namespace App\Models;

use App\Core\DB;

/**
 * Intent HARGA unified — per item/kilo + paket/member, dengan parameter service/durasi/delivery/paket.
 * Menggantikan HARGA_PAKET dan HARGA_PAKET_D (legacy handler delegasi ke handleHarga).
 */
trait WARepliesHargaTrait
{
    private static $hargaSessionTtlMinutes = 30;

    /** @var array<string, string> */
    private static $hargaServiceDefaults = [
        'service' => 'cuci_setrika',
        'durasi' => 'regular',
    ];

    // ─── Session ───────────────────────────────────────────────────────────────

    private function getHargaSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }
        try {
            $res = DB::getInstance(0)->query(
                'SELECT * FROM wa_harga_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            if (empty($row['expires_at']) || strtotime($row['expires_at']) < time()) {
                $this->clearHargaSession($waNumber);
                return null;
            }
            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getHargaSession: ' . $e->getMessage(), 'wa_error', 'Harga');
            }
            return null;
        }
    }

    private function clearHargaSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        try {
            DB::getInstance(0)->query('DELETE FROM wa_harga_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function saveHargaSession(string $waNumber, array $params, string $step = 'ready'): void
    {
        if ($this->intentLabMode) {
            return;
        }
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::$hargaSessionTtlMinutes * 60));
        try {
            DB::getInstance(0)->query(
                'INSERT INTO wa_harga_session
                    (phone, service, durasi, delivery, paket, item_keyword, step, updated_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    service = VALUES(service),
                    durasi = VALUES(durasi),
                    delivery = VALUES(delivery),
                    paket = VALUES(paket),
                    item_keyword = VALUES(item_keyword),
                    step = VALUES(step),
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at)',
                [
                    $phone,
                    $params['service'] ?? 'cuci_setrika',
                    $params['durasi'] ?? 'regular',
                    !empty($params['delivery']) ? 1 : 0,
                    !empty($params['paket']) ? 1 : 0,
                    $params['item'] ?? null,
                    $step,
                    $now,
                    $expires,
                ]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('saveHargaSession: ' . $e->getMessage(), 'wa_error', 'Harga');
            }
        }
    }

    /**
     * Intent tegas lain → lepaskan session HARGA.
     */
    private function messageBreaksHargaSession(string $text, array $keywordConfig): bool
    {
        if ($this->messageIsHargaBarangTambahan($text)) {
            return true;
        }
        if ($this->messageLooksLikeThanksPenutup($text) || $this->messageMatchesStrictPenutupAllowlist($text)) {
            return true;
        }

        $breakout = [
            'TAGIHAN', 'NOTA', 'STATUS', 'PEMBUKA', 'PENUTUP',
            'JAM_OPERASIONAL', 'JAM_TUTUP', 'JAM_BUKA',
            'KURIR', 'PERMINTAAN',
            'LOKASI', 'REKENING', 'SKEMA_GAJI', 'FEE',
        ];
        foreach ($breakout as $handler) {
            $patterns = $keywordConfig[$handler]['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $text)) {
                    return true;
                }
            }
        }

        return false;
    }

    // ─── Parameter extraction ────────────────────────────────────────────────

    /**
     * @return array{service: ?string, durasi: ?string, delivery: ?bool, paket: ?bool, item: ?string, service_ambiguous: bool}
     */
    private function extractHargaParamsFromText(string $text): array
    {
        $t = mb_strtolower(trim(preg_replace('/[*_~`]/', '', $text)), 'UTF-8');

        $params = [
            'service' => null,
            'durasi' => null,
            'delivery' => null,
            'paket' => null,
            'item' => null,
            'service_ambiguous' => false,
        ];

        if ($t === '') {
            return $params;
        }

        // paket
        if (preg_match('/\b(paket|member|langganan|deposit|bulanan)\b/u', $t)) {
            $params['paket'] = true;
        }

        // delivery
        if ($this->messageIsHargaDeliveryQuestion($t)) {
            $params['delivery'] = true;
        } elseif (preg_match('/\b(reguler|regular|ekspres|express|kilat)\s*[-]?\s*d\b/iu', $t)) {
            $params['delivery'] = true;
        }

        // durasi
        if (preg_match('/\b(kilat|super\s*cepat)\b/u', $t)) {
            $params['durasi'] = 'kilat';
        } elseif (preg_match('/\b(ekspres|express|ekspress)\b/u', $t)
            || preg_match('/\b(1\s*hari|sehari|se\s*hari|satu\s*hari)\b/u', $t)) {
            $params['durasi'] = 'ekspres';
        } elseif (preg_match('/\b(reguler|regular|reg)\b/u', $t)) {
            $params['durasi'] = 'regular';
        }

        // service
        if (preg_match('/\b(setrika|strika|gosok)\s*(aja|saja|aj)\b/u', $t)
            || (preg_match('/\b(setrika|strika|gosok)\b/u', $t) && !preg_match('/\bcuci\b/u', $t))) {
            $params['service'] = 'setrika_saja';
        } elseif (preg_match('/\b(cuci\s*(saja|aja)|cuci\s*&?\s*pack|pack\s*saja)\b/u', $t)) {
            $params['service'] = 'cuci_pack';
        } elseif (preg_match('/\b(cuci\s*\+?\s*setrika|cuci\s*setrika|cuci\s*&?\s*strika)\b/u', $t)) {
            $params['service'] = 'cuci_setrika';
        } elseif (preg_match('/\bcuci\b/u', $t)
            && preg_match('/\b(harga|berapa|biaya|tarif|brp|brpa|ongkos|ongkir)\b/u', $t)
            && !preg_match('/\b(setrika|strika|pack|saja|aja)\b/u', $t)) {
            $params['service_ambiguous'] = true;
        }

        // Penyebutan kategori eksplisit harus mengalahkan item dari session sebelumnya.
        if (preg_match('/\b(pakaian\s+harian|harian)\b/iu', $t)) {
            $params['item'] = 'pakaian_harian';
        }

        // item keyword (reuse token extraction logic)
        $specialItemPattern = '/\b(gorden|gordyn|gorder|gor?d?en|tenda|bed\s*cover|bedcover|selimut|karpet|sepatu|tas|boneka|jaket|sprei|kemeja|gaun|jas|hoodie|sweater|mukena|jilbab|kerudung)\b/iu';
        if ($params['item'] !== 'pakaian_harian' && preg_match($specialItemPattern, $t, $m)) {
            $params['item'] = mb_strtolower($m[1], 'UTF-8');
        }

        return $params;
    }

    /**
     * Delivery = durasi mengandung -D (antar/jemput include tarif).
     */
    private function messageIsHargaDeliveryQuestion(string $textLower): bool
    {
        if ($this->messageIsHargaPaketAntarJemputCombinedQuestion($textLower)) {
            return true;
        }
        $t = (string) $textLower;
        if (!preg_match('/\b(antar|jemput|dijemput|diantar|antar\s*jemput|jemput\s*antar|delivery|deliveri|include\s*antar|sekalian\s*antar)\b/u', $t)) {
            return false;
        }
        if (!preg_match('/\b(harga|berapa|biaya|daftar|tarif|rate|brp|brpa|ongkos|ongkir|brapa)\b/u', $t)) {
            return false;
        }
        if (preg_match('/\b(tolong|minta|bantu)\s+(di)?(jemput|antar)\b/i', $t)
            && !preg_match('/\b(harga|berapa|biaya|tarif|brp|brpa|ongkos|ongkir)\b/u', $t)) {
            return false;
        }
        return true;
    }

    /**
     * @param array|null $sessionRow
     * @return array{service: string, durasi: string, delivery: bool, paket: bool, item: ?string}
     */
    private function mergeHargaParams(array $extracted, ?array $sessionRow = null): array
    {
        $base = [
            'service' => 'cuci_setrika',
            'durasi' => 'regular',
            'delivery' => false,
            'paket' => false,
            'item' => null,
            'service_ambiguous' => false,
        ];

        if ($sessionRow !== null) {
            $base['service'] = (string) ($sessionRow['service'] ?? 'cuci_setrika');
            $base['durasi'] = (string) ($sessionRow['durasi'] ?? 'regular');
            $base['delivery'] = !empty($sessionRow['delivery']);
            $base['paket'] = !empty($sessionRow['paket']);
            $base['item'] = $sessionRow['item_keyword'] ?? null;
        }

        foreach (['service', 'durasi', 'item'] as $key) {
            if (!empty($extracted[$key])) {
                $base[$key] = $extracted[$key];
            }
        }
        if ($extracted['delivery'] !== null) {
            $base['delivery'] = (bool) $extracted['delivery'];
        }
        if ($extracted['paket'] !== null) {
            $base['paket'] = (bool) $extracted['paket'];
        }
        if (!empty($extracted['service_ambiguous'])) {
            $base['service_ambiguous'] = true;
        } elseif (!empty($extracted['service'])) {
            $base['service_ambiguous'] = false;
        }

        return $base;
    }

    private function hargaServiceLabel(string $service): string
    {
        switch ($service) {
            case 'setrika_saja':
                return 'Setrika saja';
            case 'cuci_pack':
                return 'Cuci saja / Cuci + Pack';
            case 'cuci_setrika':
            default:
                return 'Cuci + Setrika';
        }
    }

    private function hargaDurasiLabel(string $durasi): string
    {
        switch ($durasi) {
            case 'ekspres':
                return 'Ekspres (±1 hari)';
            case 'kilat':
                return 'Kilat (< 1 hari)';
            case 'regular':
            default:
                return 'Regular';
        }
    }

    // ─── Handlers ────────────────────────────────────────────────────────────

    /**
     * Intent HARGA unified (per item/kilo + paket/member).
     */
    private function handleHarga($phoneIn, $waNumber, $textBody = '')
    {
        $sapaan = $this->getSapaanForGreeting($waNumber);
        $extracted = $this->extractHargaParamsFromText((string) $textBody);
        $sessionRow = $this->getHargaSession($waNumber);
        $params = $this->mergeHargaParams($extracted, $sessionRow);

        if (!empty($params['service_ambiguous'])) {
            $this->saveHargaSession($waNumber, $params, 'ask_service');
            $ask = "Baik {$sapaan}, maksud *cuci saja/pack* atau *cuci + setrika* ya?";
            $res = $this->sendQuotedFreeText($waNumber, $ask);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $ask, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            $this->logAutoreplyTrace($waNumber, 'HARGA', 'ask_service_ambiguous');
            return;
        }

        // Follow-up jawaban service ambigu
        if ($sessionRow !== null && ($sessionRow['step'] ?? '') === 'ask_service') {
            $t = mb_strtolower((string) $textBody, 'UTF-8');
            if (preg_match('/\b(setrika|strika)\b/u', $t) && preg_match('/\b(saja|aja|aj)\b/u', $t)) {
                $params['service'] = 'setrika_saja';
            } elseif (preg_match('/\b(cuci\s*\+?\s*setrika|cuci\s*setrika)\b/u', $t)) {
                $params['service'] = 'cuci_setrika';
            } elseif (preg_match('/\b(cuci|pack)\b/u', $t)) {
                $params['service'] = 'cuci_pack';
            }
            $params['service_ambiguous'] = false;
        }

        $this->logAutoreplyTrace(
            $waNumber,
            'HARGA',
            sprintf(
                'params service=%s durasi=%s delivery=%s paket=%s item=%s',
                $params['service'],
                $params['durasi'],
                $params['delivery'] ? '1' : '0',
                $params['paket'] ? '1' : '0',
                $params['item'] ?? '-'
            )
        );

        if (!empty($params['paket'])) {
            $this->runHargaPaketAutoreply($phoneIn, $waNumber, $textBody, $params, $sapaan);
        } else {
            $this->runHargaItemAutoreply($phoneIn, $waNumber, $textBody, $params, $sapaan);
        }

        // Simpan session untuk follow-up ("kalau ekspres?", "ada paket?")
        unset($params['service_ambiguous']);
        $this->saveHargaSession($waNumber, $params, 'ready');
    }

    /** Legacy — delegasi ke handleHarga unified. */
    private function handleHarga_Paket($phoneIn, $waNumber, $textBody = '')
    {
        $this->handleHarga($phoneIn, $waNumber, $textBody);
    }

    /** Legacy — delegasi ke handleHarga unified. */
    private function handleHarga_Paket_D($phoneIn, $waNumber, $textBody = '')
    {
        $this->handleHarga($phoneIn, $waNumber, $textBody);
    }

    /**
     * Autoreply harga per item/kilo via AI.
     *
     * @param array{service: string, durasi: string, delivery: bool, paket: bool, item: ?string} $params
     */
    private function runHargaItemAutoreply($phoneIn, $waNumber, $textBody, array $params, string $sapaan): void
    {
        $priceDataText = $this->loadHargaDataForAI(
            (string) $textBody,
            20,
            $params['service'],
            $params['durasi'],
            $params['delivery'],
            $params['item']
        );
        if (empty($priceDataText)) {
            $fallback = "Mohon maaf {$sapaan}, saya belum bisa menampilkan harga saat ini.\nBoleh sebutkan itemnya (mis. pakaian harian, bedcover, sepatu, gorden) agar saya bantu cek harga?";
            $res = $this->sendQuotedFreeText($waNumber, $fallback);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $fallback, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            return;
        }

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                return;
            }

            $serviceLabel = $this->hargaServiceLabel($params['service']);
            $durasiLabel = $this->hargaDurasiLabel($params['durasi']);
            $deliveryNote = $params['delivery']
                ? 'Harga sudah varian include antar/jemput (durasi -D).'
                : 'Harga BUKAN varian antar/jemput.';

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu asisten harga laundry Madinah. Jawab HANYA dari data.\n\nFILTER: {$serviceLabel}; {$durasiLabel}; {$deliveryNote}\nATURAN: tenda, bedcover, dan selimut = Kain Tebal/Panjang; gorden mengikuti kategori gorden. Jika customer menyebut *Pakaian Harian* secara eksplisit, WAJIB gunakan Pakaian Harian walau chat sebelumnya membahas item lain.\n\nWAJIB SANGAT RINGKAS (maks. 40 kata):\n- LANGSUNG mulai dari baris tarif, TANPA sapaan, pembuka, atau judul.\n- Pertanyaan spesifik: hanya tarif relevan. Pertanyaan umum: maks. 3 tarif pertama sesuai urutan data.\n- Satu baris per tarif: *Kategori* — *harga/unit*; tambahkan min. order dan waktu hanya bila ada.\n- TANPA tawaran, pertanyaan lanjutan, atau penutup (mis. 'mau cek yang lain?').\n- Jangan menulis catatan Antar/Jemput; sistem akan menambahkannya.\n- Jangan mengubah urutan atau angka dari data.",
                ],
                [
                    'role' => 'user',
                    'content' => "DATA HARGA LAUNDRY (filter: {$serviceLabel} | {$durasiLabel}):\n\n"
                        . $priceDataText
                        . "\n\n---\n\nPertanyaan customer: "
                        . $textBody
                        . "\n\nJawab ringkas untuk {$sapaan}. Jika tidak spesifik item, tampilkan maks. 3 baris pertama.",
                ],
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 180);
            $text = $this->compactHargaAiReply((string) $answer);
            if ($text === '') {
                $text = "Mohon maaf {$sapaan}, saya belum bisa menampilkan harga saat ini.\nBoleh sebutkan itemnya agar saya bantu cek?";
            }

            $text = $this->appendHargaDeliveryDisclaimer($text, $params['delivery'], $params['paket']);

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write('runHargaItemAutoreply ERROR: ' . $e->getMessage(), 'wa_error', 'Harga');
            }
            $fallback = "Mohon maaf {$sapaan}, sistem sedang sibuk saat cek harga.\nSilakan kirim lagi dengan item yang dicari (contoh: setrika, bedcover, sepatu, gorden).";
            $res = $this->sendQuotedFreeText($waNumber, $fallback);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $fallback, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        }
    }

    /**
     * Autoreply harga paket via AI.
     *
     * @param array{service: string, durasi: string, delivery: bool, paket: bool, item: ?string} $params
     */
    private function runHargaPaketAutoreply($phoneIn, $waNumber, $textBody, array $params, string $sapaan): void
    {
        $deliveryOnly = (bool) $params['delivery'];
        $logTag = $deliveryOnly ? 'HargaPaketD' : 'HargaPaket';

        $priceDataText = $this->loadHargaPaketDataForAI(
            $deliveryOnly,
            $params['service'],
            $params['durasi']
        );
        if (empty($priceDataText)) {
            $fallback = "Mohon maaf {$sapaan}, data paket belum tersedia saat ini.\nBoleh coba lagi nanti ya?";
            $res = $this->sendQuotedFreeText($waNumber, $fallback);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $fallback, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            return;
        }

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                return;
            }

            $serviceLabel = $this->hargaServiceLabel($params['service']);
            $durasiLabel = $this->hargaDurasiLabel($params['durasi']);

            if ($deliveryOnly) {
                $systemExtra = "\n\nPENTING - PAKET ANTAR/JEMPUT (DELIVERY):\n- Data HANYA paket varian antar-jemput (-D).\n- Judul sudah berformat '+ Antar Jemput'. Pertahankan saat menjawab.";
                $dataLabel = 'DATA HARGA PAKET/MEMBER + ANTAR JEMPUT';
            } else {
                $systemExtra = "\n\nPENTING - TANPA ANTAR/JEMPUT:\n- Data paket standar (tanpa antar/jemput). JANGAN menawarkan paket include antar/jemput kecuali customer minta.";
                $dataLabel = 'DATA HARGA PAKET/MEMBER LAUNDRY';
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu asisten harga paket/member laundry. Jawab HANYA dari data.\n\nSAPAAN: Mulai dengan \"{$sapaan}\" (natural).\n\nPAKET BULANAN = PAKET MEMBER = HARGA PAKET (sama). JANGAN bilang tidak ada paket bulanan.\n\nFILTER AKTIF:\n- Layanan: {$serviceLabel}\n- Durasi: {$durasiLabel}\n- Tampilkan HANYA paket yang match filter layanan & durasi.\n- Jika customer tanya spesifik layanan, filter paket yang cocok saja.\n- Jika tanya umum paket/member, tampilkan semua data (sesuai filter) ringkas."
                        . $systemExtra
                        . "\n\nURUTAN & FORMAT: JANGAN ubah urutan. *bold*, _italic_, line break. Jangan pakai === di judul paket.",
                ],
                [
                    'role' => 'user',
                    'content' => "{$dataLabel} (filter: {$serviceLabel} | {$durasiLabel}):\n\n"
                        . $priceDataText
                        . "\n\n---\n\nPertanyaan customer: "
                        . $textBody
                        . "\n\nJawab untuk {$sapaan}. Filter paket sesuai layanan yang ditanya.",
                ],
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 600);
            $text = trim((string) $answer);
            if ($text === '') {
                return;
            }

            $text .= "\n\n_Catatan:_\n- Pembayaran dimuka/deposit\n- Kuota berlaku selamanya\n- Kuota tidak dapat direfund";
            $text = $this->appendHargaDeliveryDisclaimer($text, $params['delivery'], true);

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write('runHargaPaketAutoreply ERROR: ' . $e->getMessage(), 'wa_error', $logTag);
            }
        }
    }

    private function appendHargaDeliveryDisclaimer(string $text, bool $delivery, bool $isPaket): string
    {
        if ($delivery) {
            return $text;
        }
        if (stripos($text, 'tidak termasuk antar') !== false) {
            return $text;
        }
        return $text . "\n\n_Harga di atas tidak termasuk Antar/Jemput_";
    }

    /** Hapus sapaan/pembuka dan tawaran follow-up yang tidak diperlukan dari jawaban harga AI. */
    private function compactHargaAiReply(string $text): string
    {
        $lines = preg_split('/\r?\n/', trim($text)) ?: [];
        $kept = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Jangan kirim sapaan pembuka atau pertanyaan/tawaran cek lanjutan.
            $line = preg_replace('/^(?:hai|halo|hi|bang|kak|kk|pak|bu|mas|mbak)[,!\s]+/iu', '', $line) ?? $line;
            $line = preg_replace('/\s*(?:[.!]?\s*)?(?:mau|ingin|ada|kalau\s+mau)\s+(?:cek|tanya)(?:\s+yang\s+lain)?\??\s*$/iu', '', $line) ?? $line;
            $line = trim($line);
            // Heading seperti "ini tarif ...:" tidak menambah informasi tarif.
            if ($line === '' || (!preg_match('/(?:rp\s*\d|\d[\d.,]*\s*(?:kg|pcs|item))/iu', $line)
                && preg_match('/\b(?:tarif|harga|layanan)\b.*:?$/iu', $line))) {
                continue;
            }
            $kept[] = $line;
        }
        return trim(implode("\n", $kept));
    }

    // ─── Data loaders ──────────────────────────────────────────────────────────

    private function hargaLayananMatchesService(string $layananStr, string $service): bool
    {
        $l = mb_strtolower($layananStr, 'UTF-8');
        $hasCuci = (bool) preg_match('/\bcuci\b/u', $l);
        $hasSetrika = (bool) preg_match('/\b(setrika|strika|gosok)\b/u', $l);

        switch ($service) {
            case 'setrika_saja':
                return $hasSetrika && !$hasCuci;
            case 'cuci_pack':
                return $hasCuci && !$hasSetrika;
            case 'cuci_setrika':
            default:
                return $hasCuci && $hasSetrika;
        }
    }

    private function hargaDurasiMatchesParam(string $durasiStr, string $durasiParam, bool $delivery): bool
    {
        $isDelivery = (bool) preg_match('/-(?:D|d)\b/i', $durasiStr);
        if ($delivery !== $isDelivery) {
            return false;
        }
        $base = mb_strtolower(preg_replace('/-(?:D|d)\b/i', '', $durasiStr), 'UTF-8');
        switch ($durasiParam) {
            case 'ekspres':
                return (bool) preg_match('/ekspres/u', $base);
            case 'kilat':
                return (bool) preg_match('/kilat/u', $base);
            case 'regular':
            default:
                return (bool) preg_match('/reguler|regular/u', $base);
        }
    }

    /**
     * Load harga per item/kilo — filter service, durasi, delivery (-D).
     */
    private function loadHargaDataForAI(
        string $questionText = '',
        int $maxRows = 20,
        string $service = 'cuci_setrika',
        string $durasi = 'regular',
        bool $delivery = false,
        ?string $itemKeyword = null
    ): string {
        $db = DB::getInstance(1);

        $itemGroup = [];
        foreach ($db->query('SELECT id_item_group, item_kategori FROM item_group')->result_array() as $r) {
            $itemGroup[$r['id_item_group']] = $r['item_kategori'] ?? '';
        }
        $penjualan = [];
        foreach ($db->query('SELECT id_penjualan_jenis, penjualan_jenis, id_satuan FROM penjualan_jenis')->result_array() as $r) {
            $penjualan[$r['id_penjualan_jenis']] = ['nama' => $r['penjualan_jenis'] ?? '', 'id_satuan' => (int) ($r['id_satuan'] ?? 0)];
        }
        $satuan = [];
        foreach ($db->query('SELECT id_satuan, nama_satuan FROM satuan')->result_array() as $r) {
            $satuan[$r['id_satuan']] = $r['nama_satuan'] ?? '';
        }
        $durasiMap = [];
        foreach ($db->query('SELECT id_durasi, durasi FROM durasi')->result_array() as $r) {
            $durasiMap[$r['id_durasi']] = $r['durasi'] ?? '';
        }
        $layananMap = [];
        foreach ($db->query('SELECT id_layanan, layanan FROM layanan')->result_array() as $r) {
            $layananMap[$r['id_layanan']] = $r['layanan'] ?? '';
        }

        $rows = $db->query(
            'SELECT h.id_penjualan_jenis, h.id_item_group, h.list_layanan, h.id_durasi, h.harga, h.min_order, h.hari, h.jam, h.sort
             FROM harga h
             INNER JOIN durasi d ON h.id_durasi = d.id_durasi
             WHERE h.is_active = 1
             ORDER BY h.sort DESC, h.id_penjualan_jenis, h.id_item_group, h.list_layanan, h.id_durasi'
        )->result_array();

        if (empty($rows)) {
            return '';
        }

        $questionLower = mb_strtolower(trim($questionText), 'UTF-8');
        $keywords = [];
        if ($itemKeyword !== null && $itemKeyword !== '') {
            $keywords[] = $itemKeyword;
        }
        if ($questionLower !== '') {
            preg_match_all('/[a-z0-9\-]{3,}/iu', $questionLower, $m);
            $tokens = $m[0] ?? [];
            $stopwords = [
                'harga', 'berapa', 'brp', 'kak', 'bang', 'pak', 'bu', 'mau', 'saya', 'aku', 'yang',
                'untuk', 'dan', 'atau', 'ini', 'itu', 'ada', 'bisa', 'tolong', 'info', 'dong', 'ya',
                'laundry', 'cuci', 'setrika', 'strika', 'reguler', 'regular', 'ekspres', 'kilat',
                'antar', 'jemput', 'paket', 'member', 'ongkos', 'ongkir',
            ];
            foreach ($tokens as $token) {
                if (!in_array($token, $stopwords, true)) {
                    $keywords[] = $token;
                }
            }
        }
        $keywords = array_values(array_unique($keywords));

        $classificationText = trim($questionLower . ' ' . mb_strtolower((string) $itemKeyword, 'UTF-8'));
        $specialItemPattern = '/\b(gorden|gordyn|gorder|gor?d?en|tenda|bed\s*cover|bedcover|selimut|karpet|sepatu|tas|boneka|jaket|sprei|kemeja|gaun|jas|hoodie|sweater|mukena|jilbab|kerudung)\b/iu';
        $mentionsSpecialItem = (bool) preg_match($specialItemPattern, $classificationText);
        $mentionsPakaianHarian = (bool) preg_match('/\b(pakaian\s+harian|harian)\b/iu', $classificationText);
        // Tenda, bedcover, dan selimut selalu memakai tarif Kain Tebal/Panjang.
        // Gorden sengaja dikecualikan karena punya kategori/tarifnya sendiri.
        $mentionsKainTebal = !preg_match('/\b(gorden|gordyn|gorder|gor?d?en)\b/iu', $classificationText)
            && (bool) preg_match('/\b(tenda|bed\s*cover|bedcover|selimut)\b/iu', $classificationText);

        $enrichedRows = [];
        foreach ($rows as $r) {
            $idPj = $r['id_penjualan_jenis'];
            $pj = $penjualan[$idPj] ?? null;
            $namaJenis = $pj ? $pj['nama'] : 'Layanan';
            $idSatuan = $pj ? $pj['id_satuan'] : 0;
            $unit = $satuan[$idSatuan] ?? '';

            $kategori = $itemGroup[$r['id_item_group']] ?? 'Item';
            $listL = @unserialize($r['list_layanan'] ?? '');
            $layananParts = [];
            if (is_array($listL)) {
                foreach ($listL as $lid) {
                    if (!empty($layananMap[$lid])) {
                        $layananParts[] = $layananMap[$lid];
                    }
                }
            }
            $layananStr = !empty($layananParts) ? implode(' + ', $layananParts) : '-';
            $durasiStr = $durasiMap[$r['id_durasi']] ?? '';

            if (!$this->hargaLayananMatchesService($layananStr, $service)) {
                continue;
            }
            if (!$this->hargaDurasiMatchesParam($durasiStr, $durasi, $delivery)) {
                continue;
            }

            $harga = (int) ($r['harga'] ?? 0);
            $minOrder = (int) ($r['min_order'] ?? 0);
            $hari = (int) ($r['hari'] ?? 0);
            $jam = (int) ($r['jam'] ?? 0);

            $line = "{$kategori} | {$layananStr} | {$durasiStr} | Rp " . number_format($harga, 0, ',', '.') . "/{$unit}";
            if ($minOrder > 0) {
                $line .= " | Min order: {$minOrder}{$unit}";
            }
            if ($hari > 0 || $jam > 0) {
                $waktuParts = [];
                if ($hari > 0) {
                    $waktuParts[] = $hari . ' Hari';
                }
                if ($jam > 0) {
                    $waktuParts[] = $jam . ' Jam';
                }
                $line .= ' | Waktu: ' . implode(' ', $waktuParts);
            }

            $searchBlob = mb_strtolower(implode(' ', [
                (string) $kategori,
                (string) $namaJenis,
                (string) $layananStr,
                (string) $durasiStr,
                (string) $line,
            ]), 'UTF-8');

            $matchScore = 0;
            foreach ($keywords as $kw) {
                if ($kw !== '' && mb_strpos($searchBlob, $kw) !== false) {
                    $matchScore++;
                }
            }

            $enrichedRows[] = [
                'kategori' => $kategori,
                'namaJenis' => $namaJenis,
                'unit' => $unit,
                'line' => $line,
                'score' => $matchScore,
            ];
        }

        if (empty($enrichedRows)) {
            return '';
        }

        $defaultRows = $enrichedRows;
        if ($mentionsPakaianHarian) {
            $pakaianHarianRows = array_values(array_filter($enrichedRows, function ($row) {
                $kategori = mb_strtolower((string) ($row['kategori'] ?? ''), 'UTF-8');
                return mb_strpos($kategori, 'pakaian') !== false && mb_strpos($kategori, 'harian') !== false;
            }));
            if (!empty($pakaianHarianRows)) {
                $defaultRows = $pakaianHarianRows;
            }
        } elseif ($mentionsKainTebal) {
            $kainTebalRows = array_values(array_filter($enrichedRows, function ($row) {
                $kategori = mb_strtolower((string) ($row['kategori'] ?? ''), 'UTF-8');
                return mb_strpos($kategori, 'kain') !== false
                    && (mb_strpos($kategori, 'tebal') !== false || mb_strpos($kategori, 'panjang') !== false);
            }));
            if (!empty($kainTebalRows)) {
                $defaultRows = $kainTebalRows;
            }
        } elseif (!$mentionsSpecialItem && ($itemKeyword === null || $itemKeyword === '')) {
            $pakaianHarianRows = array_values(array_filter($enrichedRows, function ($row) {
                $kategori = mb_strtolower((string) ($row['kategori'] ?? ''), 'UTF-8');
                return mb_strpos($kategori, 'pakaian') !== false && mb_strpos($kategori, 'harian') !== false;
            }));
            if (!empty($pakaianHarianRows)) {
                $defaultRows = $pakaianHarianRows;
            }
        }

        if (!empty($keywords)) {
            $filtered = array_values(array_filter($defaultRows, function ($row) {
                return (int) ($row['score'] ?? 0) > 0;
            }));
        } else {
            $filtered = [];
        }

        $selectedRows = !empty($filtered) ? $filtered : $defaultRows;
        $selectedRows = array_slice($selectedRows, 0, max(1, (int) $maxRows));

        $lines = [];
        $currentJenis = '';
        $lineNum = 0;
        foreach ($selectedRows as $row) {
            $lineNum++;
            if ($row['namaJenis'] !== $currentJenis) {
                $currentJenis = $row['namaJenis'];
                $lines[] = "\n=== " . strtoupper($row['namaJenis']) . ' (per ' . $row['unit'] . ') ===';
            }
            $lines[] = "{$lineNum}. " . $row['line'];
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Load harga paket — filter delivery (-D), service, durasi.
     */
    private function loadHargaPaketDataForAI(
        bool $deliveryOnlyPakets = false,
        string $service = 'cuci_setrika',
        string $durasi = 'regular'
    ): string {
        $db = DB::getInstance(1);

        $itemGroup = [];
        foreach ($db->query('SELECT id_item_group, item_kategori FROM item_group')->result_array() as $r) {
            $itemGroup[$r['id_item_group']] = $r['item_kategori'] ?? '';
        }
        $penjualan = [];
        foreach ($db->query('SELECT id_penjualan_jenis, penjualan_jenis, id_satuan FROM penjualan_jenis')->result_array() as $r) {
            $penjualan[$r['id_penjualan_jenis']] = ['nama' => $r['penjualan_jenis'] ?? '', 'id_satuan' => (int) ($r['id_satuan'] ?? 0)];
        }
        $satuan = [];
        foreach ($db->query('SELECT id_satuan, nama_satuan FROM satuan')->result_array() as $r) {
            $satuan[$r['id_satuan']] = $r['nama_satuan'] ?? '';
        }
        $durasiMap = [];
        foreach ($db->query('SELECT id_durasi, durasi FROM durasi')->result_array() as $r) {
            $durasiMap[$r['id_durasi']] = $r['durasi'] ?? '';
        }
        $layananMap = [];
        foreach ($db->query('SELECT id_layanan, layanan FROM layanan')->result_array() as $r) {
            $layananMap[$r['id_layanan']] = $r['layanan'] ?? '';
        }

        $rows = $db->query(
            'SELECT hp.id_harga, hp.qty, hp.harga
             FROM harga_paket hp
             INNER JOIN harga h ON hp.id_harga = h.id_harga
             ORDER BY hp.id_harga ASC, hp.qty ASC'
        )->result_array();

        if (empty($rows)) {
            return '';
        }

        $hargaCache = [];
        $groups = [];
        $order = [];

        foreach ($rows as $r) {
            $idHarga = (int) ($r['id_harga'] ?? 0);
            $qty = (int) ($r['qty'] ?? 0);
            $harga = (int) ($r['harga'] ?? 0);

            if (!isset($hargaCache[$idHarga])) {
                $hRows = $db->query(
                    'SELECT id_item_group, id_penjualan_jenis, list_layanan, id_durasi FROM harga WHERE id_harga = ' . (int) $idHarga
                )->result_array();
                if (empty($hRows)) {
                    continue;
                }
                $h = $hRows[0];
                $kategori = $itemGroup[$h['id_item_group'] ?? 0] ?? 'Item';
                $listL = @unserialize($h['list_layanan'] ?? '');
                $layananParts = [];
                if (is_array($listL)) {
                    foreach ($listL as $lid) {
                        if (!empty($layananMap[$lid])) {
                            $layananParts[] = $layananMap[$lid];
                        }
                    }
                }
                $layananStr = !empty($layananParts) ? implode(' + ', $layananParts) : '-';
                $durasiStr = $durasiMap[$h['id_durasi'] ?? 0] ?? '';
                $pj = $penjualan[$h['id_penjualan_jenis'] ?? 0] ?? null;
                $idSatuan = $pj['id_satuan'] ?? 0;
                $unit = $satuan[$idSatuan] ?? '';
                $hargaCache[$idHarga] = ['nama' => "{$kategori} | {$layananStr} | {$durasiStr}", 'unit' => $unit, 'layanan' => $layananStr, 'durasi' => $durasiStr];
            }

            $cache = $hargaCache[$idHarga];
            $nama = $cache['nama'];
            $unit = $cache['unit'];

            if (!$this->hargaLayananMatchesService($cache['layanan'], $service)) {
                continue;
            }
            if (!$this->hargaDurasiMatchesParam($cache['durasi'], $durasi, $deliveryOnlyPakets)) {
                continue;
            }

            $isDelivery = $this->hargaPaketNamaIsDeliveryVariant($nama);
            if ($deliveryOnlyPakets) {
                if (!$isDelivery) {
                    continue;
                }
            } elseif ($isDelivery) {
                continue;
            }

            if (!isset($groups[$nama])) {
                $groups[$nama] = ['unit' => $unit, 'rows' => []];
                $order[] = $nama;
            }

            $qtyUnit = $qty . $unit;
            $groups[$nama]['rows'][] = '  ' . $qtyUnit . ': Rp ' . number_format($harga, 0, ',', '.');
        }

        $lines = [];
        foreach ($order as $nama) {
            $g = $groups[$nama];
            $unit = $g['unit'];
            $judul = $deliveryOnlyPakets
                ? $this->formatHargaPaketDeliveryDisplayTitle($nama)
                : strtoupper($nama);
            $lines[] = "\n\n" . $judul . " ({$unit})";
            foreach ($g['rows'] as $rowLine) {
                $lines[] = $rowLine;
            }
        }

        return trim(implode("\n", $lines));
    }

    /**
     * Pertanyaan harga paket/member sekaligus antar-jemput.
     *
     * @param string $textLower lowercase, tanpa formatter WA
     */
    private function messageIsHargaPaketAntarJemputCombinedQuestion($textLower)
    {
        $t = (string) $textLower;
        if ($t === '') {
            return false;
        }
        if (!preg_match('/\b(paket|member|langganan|deposit)\b/u', $t)) {
            return false;
        }
        if (!preg_match('/\b(antar|jemput|dijemput|diantar|antar\s*jemput|jemput\s*antar|ongkir|ongkos|kurir|pickup|pick\s*up|include|delivery|deliveri)\b/u', $t)) {
            return false;
        }
        if (preg_match('/\b(tolong|minta|bantu)\s+(di)?(jemput|antar)\b/i', $t)
            && !preg_match('/\b(harga|berapa|biaya|daftar|tarif|rate|brp|brpa|paket|member)\b/u', $t)) {
            return false;
        }
        if (preg_match('/\b(harga|berapa|biaya|daftar|tarif|rate|brp|brpa)\b/u', $t)) {
            return true;
        }
        if (preg_match('/\b(paket|member|langganan|deposit)\b/u', $t)
            && preg_match('/\b(antar|jemput|antar\s*jemput|jemput\s*antar|delivery|include)\b/u', $t)) {
            return true;
        }

        return false;
    }

    private function hargaPaketNamaIsDeliveryVariant($nama)
    {
        return (bool) preg_match('/-(?:D|d)\b/i', (string) $nama);
    }

    private function formatHargaPaketDeliveryDisplayTitle($nama)
    {
        $nama = trim((string) $nama);
        $parts = array_map('trim', explode('|', $nama));
        if (count($parts) >= 3) {
            $durasiPart = preg_replace('/-(?:D|d)\b/i', '', $parts[2]);
            $durasiPart = trim($durasiPart);

            return 'Paket ' . $parts[0] . ' | ' . $parts[1] . ' | ' . $durasiPart . ' + Antar Jemput';
        }

        $base = preg_replace('/-(?:D|d)\b/i', '', $nama);

        return 'Paket ' . trim($base) . ' + Antar Jemput';
    }

    /**
     * Ongkos/ongkir + durasi proses atau tier layanan → HARGA, bukan minta kurir.
     */
    private function messageIsHargaOngkosByDurasiAtauLayanan($text)
    {
        $t = (string) $text;
        if ($t === '') {
            return false;
        }
        if (!preg_match('/\b(ongkos|ongkir|ong\s*kos|ong\s*kir)\b/iu', $t)) {
            return false;
        }
        if (!preg_match('/\b(brp|brpa|brapa|berapa|harga|biaya|tarif)\b/iu', $t)) {
            return false;
        }
        $hasDurasi = (bool) preg_match('/\b(sehari|se\s*hari|satu\s*hari|dua\s*hari|tiga\s*hari|\d{1,2}\s*hari)\b/iu', $t);
        $hasTier = (bool) preg_match('/\b(regular|reguler|ekspres|ekspress|express|kilat)\b/iu', $t);

        return $hasDurasi || $hasTier;
    }
}
