<?php

namespace App\Models;

use App\Core\DB;
use App\Helpers\Payment\BankAccountGuide;

// Pastikan trait ter-load (jangan andalkan autoload saja saat require_once WAReplies.php dari webhook)
require_once __DIR__ . '/WARepliesKurirTrait.php';
require_once __DIR__ . '/WARepliesLokasiTrait.php';
require_once __DIR__ . '/WARepliesPermintaanTrait.php';
require_once __DIR__ . '/WARepliesHargaTrait.php';

class WAReplies
{
    use WARepliesKurirTrait;
    use WARepliesLokasiTrait;
    use WARepliesPermintaanTrait;
    use WARepliesHargaTrait;

    private $waService = null;
    private function getNoRegisterText()
    {
        return 'Mohon maaf, nomor kakak tidak ditemukan di sistem. '
            . 'Boleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. '
            . 'Terima kasih ' . $this->pickPenutupSoftSmile() . '.';
    }
    /** @var string|null Nama handler saat ini (untuk log saat send gagal) */
    private $currentHandler = null;
    /** @var string|null Nama contact dari process() untuk sapaan AI (pak/bu/kak) */
    private $currentContactName = null;
    /** @var array Cache sapaan AI per nama (nama => kak/bang) untuk hindari panggilan berulang */
    private $sapaanAiCache = [];

    /**
     * ID pesan masuk yang sedang dijawab (yCloud wamid / Fonnte inboxid) untuk quote-reply.
     * @var string|null
     */
    private $inboundReplyToId = null;

    /** Line YCloud penerima inbound (admin/cs) — autoreply harus from nomor yang sama. */
    private $inboundLineKey = null;

    /** @var string|null E.164 business phone dari webhook to */
    private $inboundBusinessPhone = null;

    /**
     * Jika true: tidak INSERT/UPDATE wa_conversations (intent lab / skip persist).
     */
    private $skipConversationPersist = false;

    /** Provider untuk wa_auto_reply_log: A = yCloud, B = Fonnte (kecuali handler DEFAULT — cooldown menyatu, lihat shouldHandleDefaultUnified) */
    private $autoReplyProvider = 'A';

    /**
     * Intent Lab: klasifikasi saja — tanpa kirim WA, tanpa tulis session/cooldown/case CRM.
     */
    private $intentLabMode = false;

    /** @var list<string> */
    private $intentLabTraces = [];

    /** @var string|null Intent final saat lab (bisa beda dari currentHandler di exit tanpa handler) */
    private $intentLabIntent = null;

    /** @var string|null regex|ai|short|amount|false|exit|… */
    private $intentLabSource = null;

    /** @var list<string> Teks yang akan dikirim (lab: tidak kirim WA) */
    private $intentLabReplies = [];

    /** Cache per process(): null = belum dicek, bool = hasil isHumanAgentRecentlyActive */
    private $humanActiveCache = null;

    /** True jika PEMBUKA skip kirim sapaan (outbound masih hangat) — jangan catat cooldown. */
    private $pembukaSkippedGreeting = false;

    /** Hasil kirim tagihan manual CRM: sent|no_pelanggan|no_data|skipped */
    private $tagihanSendOutcome = null;

    /** Hasil kirim status manual CRM: sent|no_pelanggan|no_data|skipped */
    private $statusSendOutcome = null;

    /** Idle agent manusia (menit) sebelum AI kembali boleh balas intent sosial */
    private const HUMAN_ACTIVE_IDLE_MINUTES = 60;

    /** PEMBUKA: cooldown handler + jeda sapaan jika ada outbound terakhir */
    private const PEMBUKA_RECENT_CHAT_MINUTES = 30;


    /** @var array|null Cache keyword config (DB/loader) untuk cek rate limit per handler */
    private $autoreplyKeywordConfig = null;

    /**
     * Get WhatsApp Service instance (lazy loading)
     */
    private function getWaService()
    {
        if ($this->waService === null) {
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../Helpers/CRM/WhatsAppService.php';
            }
            $this->waService = new \App\Helpers\CRM\WhatsAppService();
        }
        return $this->waService;
    }

    public function setSkipConversationPersist(bool $skip): void
    {
        $this->skipConversationPersist = $skip;
    }

    public function setAutoReplyProvider(string $provider): void
    {
        $this->autoReplyProvider = ($provider === 'B') ? 'B' : 'A';
    }

    /**
     * Pasang ID pesan masuk agar semua autoreply jadi quote-reply ke chat itu.
     * yCloud: wamid (fallback id). Fonnte: inboxid.
     */
    public function setInboundReplyToMessageId($id): void
    {
        if ($id === null) {
            $this->inboundReplyToId = null;
            return;
        }
        $id = trim((string) $id);
        if ($id === '' || $id === '0') {
            $this->inboundReplyToId = null;
            return;
        }
        $this->inboundReplyToId = $id;
    }

    /**
     * Set line bisnis dari webhook YCloud (msg.to) agar autoreply from = line penerima.
     */
    public function setInboundLine(?string $lineKey, ?string $businessPhone = null): void
    {
        $this->inboundLineKey = ($lineKey !== null && trim($lineKey) !== '') ? trim($lineKey) : null;
        $this->inboundBusinessPhone = ($businessPhone !== null && trim($businessPhone) !== '') ? trim($businessPhone) : null;
    }

    private function resolveOutboundLineKey(): ?string
    {
        if ($this->inboundLineKey !== null && $this->inboundLineKey !== '') {
            return $this->inboundLineKey;
        }
        if ($this->inboundBusinessPhone !== null && $this->inboundBusinessPhone !== '') {
            if (!class_exists('\\App\\Helpers\\CRM\\WaLineResolver')) {
                require_once __DIR__ . '/../Helpers/CRM/WaLineResolver.php';
            }
            $line = \App\Helpers\CRM\WaLineResolver::fromBusinessPhone($this->inboundBusinessPhone);

            return $line['key'] ?? null;
        }

        return null;
    }

    /** @var array|null Hasil WaSenderContext::resolve() */
    private $senderContext = null;

    public function setSenderContext(array $ctx): void
    {
        $this->senderContext = $ctx;
    }

    /**
     * @return array<string, mixed>
     */
    private function ensureSenderContext(string $waNumber): array
    {
        if (is_array($this->senderContext) && ($this->senderContext['nomor'] ?? '') !== '') {
            return $this->senderContext;
        }
        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }
        $this->senderContext = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);

        return $this->senderContext;
    }

    private function senderIdPelanggan(): int
    {
        return (int) ($this->senderContext['id_pelanggan'] ?? 0);
    }

    /** @return list<int> */
    private function senderIdsPelanggan(): array
    {
        $ids = $this->senderContext['ids_pelanggan'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $ids)));
    }

    /** Pengaman kedua: intent admin harus is_admin, meski gerbang DB terlupa. */
    private function requireAdminSender(string $waNumber, string $handler): bool
    {
        if ($this->intentLabMode) {
            return true;
        }
        if (!empty($this->senderContext['is_admin'])) {
            return true;
        }
        $this->logAutoreplyTrace($waNumber, $handler, 'require_admin');

        return false;
    }

    private function senderPassesIntentGate(array $config): bool
    {
        if ($this->intentLabMode) {
            return true;
        }
        $needAdmin = !empty($config['is_admin']);
        $needKaryawan = !empty($config['is_karyawan']);
        $needPelanggan = !empty($config['is_pelanggan']);
        if (!$needAdmin && !$needKaryawan && !$needPelanggan) {
            return true;
        }
        $ctx = $this->senderContext ?? [];
        if ($needAdmin && !empty($ctx['is_admin'])) {
            return true;
        }
        if ($needKaryawan && !empty($ctx['is_karyawan'])) {
            return true;
        }
        if ($needPelanggan && !empty($ctx['is_pelanggan'])) {
            return true;
        }

        return false;
    }

    private function intentVisibleForSender(array $config): bool
    {
        return $this->senderPassesIntentGate($config);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function filterKeywordConfigBySenderGate(array $config): array
    {
        if ($this->intentLabMode) {
            return $config;
        }
        $out = [];
        foreach ($config as $code => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if ($this->intentVisibleForSender($cfg)) {
                $out[$code] = $cfg;
            }
        }

        return $out;
    }

    /**
     * Gerbang gagal: skip intent (lanjut intent lain), tanpa balasan.
     *
     * @return object|null|false
     */
    private function gateDenyOrContinue(
        string $handler,
        array $config,
        $db,
        string $waNumber,
        $contactName,
        $assigned_user_id,
        $code,
        $cust_id,
        $lastMessage
    ) {
        if ($this->senderPassesIntentGate($config)) {
            return null;
        }
        $this->logAutoreplyTrace($waNumber, 'GATE_SKIP', 'handler=' . $handler);
        return false;
    }

    /**
     * Dry-run klasifikasi intent (Intent Lab). Hanya teks — tanpa phone/session customer.
     *
     * @return array{ok:bool,text:string,intent:?string,source:?string,case:mixed,notify:bool,no_handler:bool,ask:?bool,trace:list<string>,replies:list<string>}
     */
    public function classifyIntentLab(string $textBody): array
    {
        $textBody = trim($textBody);
        $this->intentLabMode = true;
        $this->intentLabTraces = [];
        $this->intentLabReplies = [];
        $this->intentLabIntent = null;
        $this->intentLabSource = null;
        $this->currentHandler = null;
        $this->humanActiveCache = false;
        $this->setSkipConversationPersist(true);

        // Intent Lab: regex DB menang dulu — tanpa REGEX_SKIP/remap produksi (AI hanya jika tidak ada match).
        $regexFirst = $this->classifyIntentLabRegexFirst($textBody);
        if ($regexFirst !== null) {
            $this->intentLabMode = false;
            return $regexFirst;
        }

        $waNumber = '6289990000001';
        $phoneIn = "'6289990000001','089990000001','+6289990000001','89990000001'";
        $result = null;

        try {
            $result = $this->process(
                $phoneIn,
                $textBody,
                $waNumber,
                'Intent Lab',
                null,
                null,
                'i- lab',
                null
            );
        } catch (\Throwable $e) {
            $replies = $this->intentLabReplies;
            $this->intentLabMode = false;
            return [
                'ok' => false,
                'text' => $textBody,
                'intent' => null,
                'source' => 'error',
                'case' => null,
                'notify' => false,
                'no_handler' => true,
                'ask' => null,
                'trace' => array_merge($this->intentLabTraces, ['ERROR: ' . $e->getMessage()]),
                'replies' => $replies,
                'message' => $e->getMessage(),
            ];
        }

        $replies = $this->intentLabReplies;
        $this->intentLabMode = false;

        $intent = $this->intentLabIntent ?? $this->currentHandler;
        if ($intent === null || $intent === '') {
            if (!empty($result->no_handler)) {
                $intent = 'FALSE';
            } elseif ($this->intentLabSource === 'short' || $this->intentLabSource === 'amount') {
                $intent = 'NONE';
            } else {
                $intent = 'FALSE';
            }
        }

        $ask = null;
        if (isset($result->ask)) {
            $ask = (bool) $result->ask;
        } elseif (!empty($result->no_handler) && (int) ($result->case ?? 0) === 4) {
            $ask = true;
        }

        return [
            'ok' => true,
            'text' => $textBody,
            'intent' => $intent,
            'source' => $this->intentLabSource,
            'case' => $result->case ?? null,
            'notify' => (bool) ($result->notify ?? false),
            'no_handler' => !empty($result->no_handler),
            'ask' => $ask,
            'trace' => $this->intentLabTraces,
            'replies' => $replies,
        ];
    }

    /**
     * Intent Lab — scan pattern DB mentah (urut sort_order). Tanpa skip/remap produksi.
     *
     * @return array{ok:bool,text:string,intent:string,source:string,case:mixed,notify:bool,no_handler:bool,ask:null,trace:list<string>,replies:list<string>}|null
     */
    private function classifyIntentLabRegexFirst(string $textBody): ?array
    {
        $textBody = trim($textBody);
        if ($textBody === '') {
            return null;
        }
        $textCheck = $this->normalizeTextBodyForRegex($textBody);
        $keywordConfig = $this->loadAutoreplyKeywordConfig();
        if ($keywordConfig === []) {
            return null;
        }
        $messageLength = mb_strlen($textBody);

        foreach ($keywordConfig as $handler => $config) {
            if (!is_array($config)) {
                continue;
            }
            $code = strtoupper(trim((string) $handler));
            if ($code === '') {
                continue;
            }
            if ($this->intentExceedsChatMaxlength($config, $messageLength)) {
                continue;
            }
            foreach ($config['patterns'] ?? [] as $pattern) {
                $pattern = trim((string) $pattern);
                if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                    $pattern = \App\Helpers\Laundry\IntentTeachHelper::sanitizePatternString($pattern);
                } else {
                    $pattern = preg_replace('/\s+(?=\/[a-zA-Z]*$)/', '', $pattern) ?? $pattern;
                    $pattern = preg_replace('/\\\\([?!.,;:])\\\\\/(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;
                }
                if ($pattern === '' || @preg_match($pattern, '') === false) {
                    continue;
                }
                $matched = false;
                if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                    $matched = \App\Helpers\Laundry\IntentTeachHelper::patternMatchesText(
                        $pattern,
                        $textBody,
                        $textCheck
                    );
                } elseif (@preg_match($pattern, $textCheck) === 1 || @preg_match($pattern, $textBody) === 1) {
                    $matched = true;
                }
                if ($matched) {
                    return [
                        'ok' => true,
                        'text' => $textBody,
                        'intent' => $code,
                        'source' => 'regex',
                        'case' => $config['case'] ?? null,
                        'notify' => (bool) ($config['notify'] ?? false),
                        'no_handler' => false,
                        'ask' => null,
                        'trace' => ['LAB_REGEX_MATCH handler=' . $code],
                        'replies' => [],
                    ];
                }
            }
        }

        return null;
    }

    private function normalizeTextBodyForRegex(string $textBody): string
    {
        $t = preg_replace('/[*_~`]/', '', $textBody) ?? $textBody;
        $t = preg_replace('/^>\s*/m', '', $t) ?? $t;

        return strtolower(trim($t));
    }

    private function intentLabMark(string $intent, string $source): void
    {
        if (!$this->intentLabMode) {
            return;
        }
        $this->intentLabIntent = $intent !== '' ? $intent : null;
        $this->intentLabSource = $source;
        if ($intent !== '') {
            $this->currentHandler = $intent;
        }
    }

    /**
     * Kirim teks ke customer sebagai quote-reply ke pesan masuk yang sedang diproses.
     */
    private function sendQuotedFreeText($waNumber, $text, $senderCode = null): array
    {
        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/SapaanStatsHelper.php';
        }
        $code = ($senderCode !== null && trim((string) $senderCode) !== '')
            ? trim((string) $senderCode)
            : \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY;

        return $this->getWaService()->sendFreeText(
            $waNumber,
            $text,
            $this->inboundReplyToId,
            $code,
            null,
            $this->resolveOutboundLineKey()
        );
    }

    /**
     * Kirim autoreply via WA; push WebSocket hanya untuk yCloud (bukan Fonnte).
     * @param string $waNumber
     * @param string $text
     * @return array Response dari sendFreeText
     */
    private function sendAutoreplyText($waNumber, $text)
    {
        if ($this->intentLabMode) {
            $this->intentLabReplies[] = (string) $text;
            $this->logAutoreplyTrace($waNumber, 'REPLY', mb_substr((string) $text, 0, 200));
            return ['success' => true, 'data' => null, 'error' => null];
        }
        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/SapaanStatsHelper.php';
        }
        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            // WhatsAppService::saveOutboundMessage sudah broadcast agent_message_sent.
        } else {
            $handler = $this->currentHandler ?? 'unknown';
            if (class_exists('\Log')) {
                $err = $res['error'] ?? ('HTTP ' . ($res['http_code'] ?? 'unknown'));
                \Log::write("✗ Autoreply [$handler] FAILED to send to $waNumber: $err", 'wa_error', 'Autoreply');
            }
        }
        return $res;
    }

    /**
     * Jejak alur autoreply → file logs/{tanggal}/wa_autoreply.log
     */
    private function logAutoreplyTrace(?string $waNumber, string $stage, string $detail = ''): void
    {
        $detail = str_replace(["\r", "\n"], ' ', (string) $detail);
        if (mb_strlen($detail) > 480) {
            $detail = mb_substr($detail, 0, 480) . '…';
        }
        if ($this->intentLabMode) {
            $line = $stage . ($detail !== '' ? (' | ' . $detail) : '');
            $this->intentLabTraces[] = $line;
            if (stripos($stage, 'REGEX_MATCH') === 0) {
                $this->intentLabSource = $this->intentLabSource ?: 'regex';
            } elseif (stripos($stage, 'AI_PATH') === 0 || stripos($stage, 'HANDLER_RUN') === 0 && stripos($detail, 'ai ') !== false) {
                $this->intentLabSource = $this->intentLabSource ?: 'ai';
            } elseif (stripos($stage, 'ai_') === 0 || stripos($stage, 'AI_') === 0 || stripos($stage, 'ai_override') !== false || stripos($stage, 'AI_REMAP') === 0) {
                $this->intentLabSource = 'ai';
            }
            return;
        }
        if (!class_exists('\Log')) {
            return;
        }
        $wa = $waNumber ?? '-';
        \Log::write("{$stage} | {$wa} | {$detail}", 'wa', 'autoreply');
    }

    /**
     * Keyword config: DB mdl_main saja (AutoReplyKeywordsLoader + cache version).
     * @return array<string, mixed>
     */
    private function loadAutoreplyKeywordConfig(): array
    {
        if (!class_exists('\\App\\Config\\AutoReplyKeywordsLoader')) {
            $path = __DIR__ . '/../Config/AutoReplyKeywordsLoader.php';
            if (is_file($path)) {
                require_once $path;
            }
        }
        if (class_exists('\\App\\Config\\AutoReplyKeywordsLoader')) {
            return \App\Config\AutoReplyKeywordsLoader::all();
        }
        return [];
    }

    /** Pesan melebihi chat_maxlength intent (DB). NULL/0 = tanpa batas. */
    private function intentExceedsChatMaxlength(array $config, int $messageLength): bool
    {
        $max = (int) ($config['chat_maxlength'] ?? 0);

        return $max > 0 && $messageLength > $max;
    }

    /** Intent boleh diproses (regex/AI/handler) untuk panjang pesan ini. */
    private function intentAllowedForMessageLength(
        string $handler,
        array $fullKeywordConfig,
        int $messageLength,
        ?string $textBody = null
    ): bool {
        if ($handler === '' || $handler === 'FALSE') {
            return true;
        }
        if (!$this->intentLabMode
            && strtoupper($handler) === 'PENUTUP'
            && $textBody !== null
            && $this->penutupShouldAutoreplyThanksOrPayment($textBody)
        ) {
            return true;
        }

        return !$this->intentExceedsChatMaxlength($fullKeywordConfig[$handler] ?? [], $messageLength);
    }

    /**
     * @param array<string, mixed> $keywordConfig
     * @return array<string, mixed>
     */
    private function filterKeywordConfigByChatMaxlength(
        array $keywordConfig,
        int $messageLength,
        ?string $textBody = null
    ): array {
        $out = [];
        foreach ($keywordConfig as $code => $config) {
            if (!is_array($config)) {
                continue;
            }
            if ($this->intentExceedsChatMaxlength($config, $messageLength)) {
                if (!$this->intentLabMode
                    && strtoupper((string) $code) === 'PENUTUP'
                    && $textBody !== null
                    && $this->penutupShouldAutoreplyThanksOrPayment($textBody)
                ) {
                    $out[$code] = $config;
                }
                continue;
            }
            $out[$code] = $config;
        }

        return $out;
    }

    /**
     * Tidak ada reply tanpa cooldown. Dulu: perintah admin / tanpa ai_prompt di-skip.
     */
    private function handlerSkipsAutoreplyRateLimit(string $handler): bool
    {
        return false;
    }

    private function senderIsStaff(): bool
    {
        $ctx = $this->senderContext ?? [];

        return !empty($ctx['is_admin']) || !empty($ctx['is_karyawan']);
    }

    /** Intent gerbang admin/karyawan di DB (SALDO, slip gaji, dll.). */
    private function intentIsStaffTarget(string $handler): bool
    {
        $h = strtoupper($handler);
        if (in_array($h, ['SALDO', 'SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD', 'INFO_FONNTE'], true)) {
            return true;
        }
        if ($this->autoreplyKeywordConfig === null) {
            $this->autoreplyKeywordConfig = $this->loadAutoreplyKeywordConfig();
        }
        $config = $this->autoreplyKeywordConfig[$handler]
            ?? $this->autoreplyKeywordConfig[$h]
            ?? null;
        if ($config === null) {
            return false;
        }

        return !empty($config['is_admin']) || !empty($config['is_karyawan']);
    }

    /**
     * Durasi cooldown per handler (menit). Default 1; jam operasional/tutup = 60;
     * PEMBUKA = 30. Admin/karyawan (pengirim atau intent staf) selalu 1 menit.
     */
    private function getAutoreplyCooldownMinutes(string $handler): int
    {
        if ($this->senderIsStaff() || $this->intentIsStaffTarget($handler)) {
            return 1;
        }
        $h = strtoupper($handler);
        if ($h === 'PEMBUKA') {
            return self::PEMBUKA_RECENT_CHAT_MINUTES;
        }
        if ($h === 'JAM_OPERASIONAL' || $h === 'JAM_TUTUP') {
            return 60;
        }
        if ($h === 'KURIR' || $h === 'LOKASI' || $h === 'PERMINTAAN') {
            return 1;
        }

        return 1;
    }

    /**
     * Ada outbound agent manusia (sender_code terisi & bukan AR) dalam HUMAN_ACTIVE_IDLE_MINUTES terakhir?
     * Autoreply (NULL / AR) tidak dihitung.
     */
    private function isHumanAgentRecentlyActive(string $waNumber, ?int $idleMinutes = null): bool
    {
        if ($this->intentLabMode) {
            $this->humanActiveCache = false;
            return false;
        }
        if ($this->humanActiveCache !== null) {
            return $this->humanActiveCache;
        }

        $idleMinutes = $idleMinutes ?? self::HUMAN_ACTIVE_IDLE_MINUTES;
        $db = DB::getInstance(0);
        $phones = $this->waMessagesOutPhoneVariants($waNumber);
        if (empty($phones)) {
            $this->humanActiveCache = false;
            return false;
        }

        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/SapaanStatsHelper.php';
        }
        $ar = \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY;

        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $sql = "SELECT created_at FROM wa_messages_out
                WHERE phone IN ($placeholders)
                  AND sender_code IS NOT NULL AND TRIM(sender_code) <> ''
                  AND UPPER(TRIM(sender_code)) NOT IN (?, 'AI', '-AI')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                ORDER BY created_at DESC LIMIT 1";
        $params = array_merge(array_values($phones), [$ar, $idleMinutes]);

        try {
            $result = $db->query($sql, $params);
            $this->humanActiveCache = ($result && $result->num_rows() > 0);
        } catch (\Throwable $e) {
            $this->humanActiveCache = false;
            if (class_exists('\Log')) {
                \Log::write('isHumanAgentRecentlyActive error: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        return $this->humanActiveCache;
    }

    /**
     * Variasi format phone untuk match wa_messages_out.phone (+62 / 62 / 08).
     */
    private function waMessagesOutPhoneVariants(string $waNumber): array
    {
        $clean = preg_replace('/[^0-9]/', '', $waNumber);
        if ($clean === '') {
            return [];
        }
        if (strpos($clean, '62') === 0) {
            $local = '0' . substr($clean, 2);
        } elseif (strpos($clean, '0') === 0) {
            $local = $clean;
            $clean = '62' . substr($clean, 1);
        } else {
            $local = '0' . $clean;
            $clean = '62' . $clean;
        }

        return array_values(array_unique([
            '+' . $clean,
            $clean,
            $local,
            $waNumber,
        ]));
    }

    /**
     * Ada pesan keluar (autoreply atau manusia) dalam N menit terakhir — yCloud + Fonnte.
     */
    private function hasRecentOutboundMessage(string $waNumber, int $minutes): bool
    {
        if ($this->intentLabMode || $minutes <= 0) {
            return false;
        }
        $phones = $this->waMessagesOutPhoneVariants($waNumber);
        if ($phones === []) {
            return false;
        }
        $db = DB::getInstance(0);
        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        $params = array_merge(array_values($phones), [$minutes]);
        $tables = ['wa_messages_out'];
        foreach ($tables as $table) {
            try {
                $res = $db->query(
                    "SELECT id FROM {$table}
                     WHERE phone IN ({$placeholders})
                       AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                     LIMIT 1",
                    $params
                );
                if ($res && $res->num_rows() > 0) {
                    return true;
                }
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write("hasRecentOutboundMessage {$table}: " . $e->getMessage(), 'wa_error', 'Autoreply');
                }
            }
        }

        return false;
    }

    /** Sapaan PEMBUKA / sisipan: diam jika percakapan masih hangat. */
    private function pembukaShouldSkipGreeting(string $waNumber): bool
    {
        $minutes = ($this->senderIsStaff())
            ? 1
            : self::PEMBUKA_RECENT_CHAT_MINUTES;

        return $this->hasRecentOutboundMessage($waNumber, $minutes);
    }

    /**
     * Intent yang tetap boleh autoreply saat agent manusia baru aktif.
     * Data/self-service + perintah admin/karyawan.
     */
    private function isIntentAllowedDuringHumanActive(string $handler): bool
    {
        $h = strtoupper($handler);
        $allow = [
            'STATUS',
            'TAGIHAN',
            'NOTA',
            'HARGA',
            'JAM_OPERASIONAL',
            'JAM_TUTUP',
            'JAM_BUKA',
            'REKENING',
            'PERMINTAAN'
        ];
        if (in_array($h, $allow, true)) {
            return true;
        }
        if ($h === 'PENUTUP') {
            return true;
        }

        return $this->intentIsStaffTarget($handler);
    }

    /**
     * Silent exit saat human aktif + intent sosial: tanpa balasan, tanpa case 4, tanpa DEFAULT fallback.
     */
    private function silentExitHumanActive(
        $db,
        string $waNumber,
        $contactName,
        $assigned_user_id,
        $code,
        $cust_id,
        $lastMessage,
        string $reason
    ): object {
        $this->logAutoreplyTrace($waNumber, 'EXIT', 'human_active_skip ' . $reason);
        $conversationId = $this->getOrCreateConversationWithCase(
            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
        );

        return (object) [
            'case' => null,
            'notify' => false,
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * @param string $waNumber Phone number
     * @param string $handler Handler name (bon, status, buka, etc)
     * @param int|null $cooldownMinutes Null = pakai getAutoreplyCooldownMinutes($handler)
     * @return bool True jika masih dalam jendela cooldown (jangan kirim balasan)
     */
    private function isInAutoreplyCooldown($waNumber, $handler, $cooldownMinutes = null): bool
    {
        if ($this->intentLabMode) {
            return false;
        }
        $cooldownMinutes = $cooldownMinutes ?? $this->getAutoreplyCooldownMinutes($handler);
        if ($this->senderIsStaff() || $this->intentIsStaffTarget($handler)) {
            $cooldownMinutes = 1;
        }
        $db = DB::getInstance(0);

        if ($handler === 'DEFAULT') {
            $sql = "SELECT created_at FROM wa_auto_reply_log 
                    WHERE phone = ? AND handler = 'DEFAULT' 
                    ORDER BY created_at DESC LIMIT 1";
            $result = $db->query($sql, [$waNumber]);
            if ($result && $result->num_rows() > 0) {
                $lastReply = $result->row()->created_at;
                $cooldownEnd = date('Y-m-d H:i:s', strtotime($lastReply) + ($cooldownMinutes * 60));
                if (date('Y-m-d H:i:s') < $cooldownEnd) {
                    return true;
                }
            }
            return false;
        }

        $provider = $this->autoReplyProvider;
        $h = strtoupper($handler);

        // JAM_OPERASIONAL & JAM_TUTUP berbagi jendela 60 menit (sapaan tutup vs tanya jam vs DEFAULT fallback)
        if ($h === 'JAM_OPERASIONAL' || $h === 'JAM_TUTUP') {
            $sql = "SELECT created_at FROM wa_auto_reply_log 
                    WHERE phone = ? AND provider = ? AND handler IN ('JAM_OPERASIONAL', 'JAM_TUTUP') 
                    ORDER BY created_at DESC LIMIT 1";
            $result = $db->query($sql, [$waNumber, $provider]);
        } else {
            $sql = "SELECT created_at FROM wa_auto_reply_log 
                    WHERE phone = ? AND handler = ? AND provider = ? 
                    ORDER BY created_at DESC LIMIT 1";
            $result = $db->query($sql, [$waNumber, $handler, $provider]);
        }

        if ($result && $result->num_rows() > 0) {
            $lastReply = $result->row()->created_at;
            $cooldownEnd = date('Y-m-d H:i:s', strtotime($lastReply) + ($cooldownMinutes * 60));

            if (date('Y-m-d H:i:s') < $cooldownEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $waNumber Phone number
     * @param string $handler Handler name (bon, status, buka, etc)
     * @param int|null $cooldownMinutes Null = pakai getAutoreplyCooldownMinutes($handler)
     * @return bool True if can send reply (cek cooldown + catat log)
     */
    private function shouldHandle($waNumber, $handler, $cooldownMinutes = null)
    {
        if ($this->handlerSkipsAutoreplyRateLimit($handler)) {
            return true;
        }

        $cooldownMinutes = $cooldownMinutes ?? $this->getAutoreplyCooldownMinutes($handler);
        if ($this->senderIsStaff() || $this->intentIsStaffTarget($handler)) {
            $cooldownMinutes = 1;
        }

        if ($handler === 'DEFAULT') {
            return $this->shouldHandleDefaultUnified($waNumber, $cooldownMinutes);
        }

        if ($this->isInAutoreplyCooldown($waNumber, $handler, $cooldownMinutes)) {
            return false;
        }

        $this->recordHandlerCooldown($waNumber, $handler);

        return true;
    }

    /**
     * Perbarui wa_auto_reply_log untuk handler tanpa cek rate limit (upsert created_at).
     * Dipakai saat satu intent memicu handler lain yang tidak lewat shouldHandle() terpisah.
     */
    private function recordHandlerCooldown($waNumber, string $handler): void
    {
        if ($this->intentLabMode) {
            return;
        }
        $db = DB::getInstance(0);
        $provider = $this->autoReplyProvider;
        $existing = $db->get_where('wa_auto_reply_log', [
            'phone' => $waNumber,
            'handler' => $handler,
            'provider' => $provider,
        ])->row();

        if ($existing) {
            $db->update(
                'wa_auto_reply_log',
                ['created_at' => date('Y-m-d H:i:s'), 'provider' => $provider],
                ['phone' => $waNumber, 'handler' => $handler, 'provider' => $provider]
            );
        } else {
            $db->insert('wa_auto_reply_log', [
                'phone' => $waNumber,
                'handler' => $handler,
                'provider' => $provider,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Cooldown fallback DEFAULT: satu jejak per nomor (abaikan provider A/B) agar pelanggan tidak
     * mendapat dua arahan berbeda (yCloud vs Fonnte) dalam jendela cooldown yang sama.
     */
    private function shouldHandleDefaultUnified(string $waNumber, int $cooldownMinutes): bool
    {
        $db = DB::getInstance(0);
        $handler = 'DEFAULT';

        $sql = "SELECT created_at FROM wa_auto_reply_log 
                WHERE phone = ? AND handler = ? 
                ORDER BY created_at DESC LIMIT 1";

        $result = $db->query($sql, [$waNumber, $handler]);

        if ($result && $result->num_rows() > 0) {
            $lastReply = $result->row()->created_at;
            $cooldownEnd = date('Y-m-d H:i:s', strtotime($lastReply) + ($cooldownMinutes * 60));

            if (date('Y-m-d H:i:s') < $cooldownEnd) {
                return false;
            }
        }

        $now = date('Y-m-d H:i:s');
        $db->update(
            'wa_auto_reply_log',
            ['created_at' => $now],
            ['phone' => $waNumber, 'handler' => $handler]
        );

        if ($db->affected_rows() > 0) {
            return true;
        }

        $db->insert('wa_auto_reply_log', [
            'phone' => $waNumber,
            'handler' => $handler,
            'provider' => $this->autoReplyProvider,
            'created_at' => $now,
        ]);

        return true;
    }

    /**
     * Rate limit balasan fallback per nomor via wa_auto_reply_log (handler DEFAULT).
     * Cooldown menyatu untuk yCloud (A) dan Fonnte (B): tidak boleh double fallback ke channel lain dalam jendela yang sama.
     *
     * @param string $waNumber Nomor WhatsApp (+62...)
     * @param int $cooldownMinutes Default 60
     * @return bool True jika boleh kirim fallback (di luar cooldown)
     */
    public function shouldSendFonnteFallbackReply($waNumber, $cooldownMinutes = 1440)
    {
        return $this->shouldHandle($waNumber, 'DEFAULT', $cooldownMinutes);
    }

    /**
     * Kirim teks fallback DEFAULT via yCloud (sendAutoreplyText: API + WebSocket).
     * Panggil setelah shouldSendFonnteFallbackReply mengembalikan true.
     */
    public function sendDefaultFallbackAutoreply(string $waNumber, string $text): array
    {
        $this->currentHandler = 'DEFAULT';

        return $this->sendAutoreplyText($waNumber, $text);
    }

    /**
     * Kirim tagihan manual dari CRM — logic sama handleTagihan + cooldown handler TAGIHAN.
     *
     * @return array{ok:bool,message?:string,cooldown?:bool,outcome?:string}
     */
    public function sendTagihanFromCrm(string $waNumber, ?int $custId = null): array
    {
        $waNumber = (string) ($this->normalizePhoneNumber($waNumber) ?? '');
        if ($waNumber === '') {
            return ['ok' => false, 'message' => 'Nomor WA tidak valid'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }

        $ctx = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
        if ($custId !== null && $custId > 0) {
            $ctx['id_pelanggan'] = $custId;
            $ctx['cust_id'] = $custId;
            if (!in_array($custId, $ctx['ids_pelanggan'] ?? [], true)) {
                $ctx['ids_pelanggan'][] = $custId;
            }
            $ctx['is_pelanggan'] = true;
        }
        $this->setSenderContext($ctx);

        if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/CrmChatMergeHelper.php';
        }
        if (!class_exists('\\App\\Config\\WaLines')) {
            require_once __DIR__ . '/../Config/WaLines.php';
        }

        $dbCsw = DB::getInstance(0);
        $csw = \App\Helpers\CRM\CrmChatMergeHelper::getCswStatus($dbCsw, $waNumber);
        $lineKey = \App\Helpers\CRM\CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return [
                'ok' => false,
                'message' => 'CSW sudah tutup — tidak bisa kirim tagihan',
                'outcome' => 'csw_closed',
            ];
        }

        $lineMeta = \App\Config\WaLines::get($lineKey);
        $this->setInboundLine($lineKey, is_array($lineMeta) ? ($lineMeta['phone'] ?? null) : null);
        $this->setAutoReplyProvider($lineKey === \App\Config\WaLines::KEY_ADMIN ? 'B' : 'A');

        if ($this->isInHandlerCooldownAnyProvider($waNumber, 'TAGIHAN')) {
            return [
                'ok' => false,
                'message' => 'Cooldown TAGIHAN masih aktif — tunggu sebentar',
                'cooldown' => true,
            ];
        }

        $nomor = (string) ($ctx['nomor'] ?? '');
        if ($nomor === '') {
            $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber) ?? '';
        }
        if ($nomor === '') {
            return ['ok' => false, 'message' => 'Nomor WA tidak valid'];
        }

        $phoneIn = \App\Helpers\CRM\WaSenderContext::phoneInClause($nomor);
        $this->tagihanSendOutcome = null;
        $this->handleTagihan($phoneIn, $waNumber, 'bill', true);
        $this->recordHandlerCooldown($waNumber, 'TAGIHAN');

        $outcome = (string) ($this->tagihanSendOutcome ?? 'skipped');
        if ($outcome === 'sent') {
            return ['ok' => true, 'message' => 'Tagihan terkirim', 'outcome' => $outcome];
        }
        if ($outcome === 'no_data') {
            return ['ok' => true, 'message' => 'Info belum ada tagihan terkirim', 'outcome' => $outcome];
        }
        if ($outcome === 'no_pelanggan') {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan di laundry', 'outcome' => $outcome];
        }

        return ['ok' => false, 'message' => 'Tagihan tidak terkirim', 'outcome' => $outcome];
    }

    /**
     * Kirim status manual dari CRM — logic sama handleStatus + cooldown handler STATUS.
     *
     * @return array{ok:bool,message?:string,cooldown?:bool,outcome?:string}
     */
    public function sendStatusFromCrm(string $waNumber, ?int $custId = null): array
    {
        $waNumber = (string) ($this->normalizePhoneNumber($waNumber) ?? '');
        if ($waNumber === '') {
            return ['ok' => false, 'message' => 'Nomor WA tidak valid'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }

        $ctx = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
        if ($custId !== null && $custId > 0) {
            $ctx['id_pelanggan'] = $custId;
            $ctx['cust_id'] = $custId;
            if (!in_array($custId, $ctx['ids_pelanggan'] ?? [], true)) {
                $ctx['ids_pelanggan'][] = $custId;
            }
            $ctx['is_pelanggan'] = true;
        }
        $this->setSenderContext($ctx);

        if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/CrmChatMergeHelper.php';
        }
        if (!class_exists('\\App\\Config\\WaLines')) {
            require_once __DIR__ . '/../Config/WaLines.php';
        }

        $dbCsw = DB::getInstance(0);
        $csw = \App\Helpers\CRM\CrmChatMergeHelper::getCswStatus($dbCsw, $waNumber);
        $lineKey = \App\Helpers\CRM\CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return [
                'ok' => false,
                'message' => 'CSW sudah tutup — tidak bisa kirim status',
                'outcome' => 'csw_closed',
            ];
        }

        $lineMeta = \App\Config\WaLines::get($lineKey);
        $this->setInboundLine($lineKey, is_array($lineMeta) ? ($lineMeta['phone'] ?? null) : null);
        $this->setAutoReplyProvider($lineKey === \App\Config\WaLines::KEY_ADMIN ? 'B' : 'A');

        if ($this->isInHandlerCooldownAnyProvider($waNumber, 'STATUS')) {
            return [
                'ok' => false,
                'message' => 'Cooldown STATUS masih aktif — tunggu sebentar',
                'cooldown' => true,
            ];
        }

        $nomor = (string) ($ctx['nomor'] ?? '');
        if ($nomor === '') {
            $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber) ?? '';
        }
        if ($nomor === '') {
            return ['ok' => false, 'message' => 'Nomor WA tidak valid'];
        }

        $phoneIn = \App\Helpers\CRM\WaSenderContext::phoneInClause($nomor);
        $this->statusSendOutcome = null;
        $this->handleStatus($phoneIn, $waNumber, 'status', true);
        $this->recordHandlerCooldown($waNumber, 'STATUS');

        $outcome = (string) ($this->statusSendOutcome ?? 'skipped');
        if ($outcome === 'sent') {
            return ['ok' => true, 'message' => 'Status terkirim', 'outcome' => $outcome];
        }
        if ($outcome === 'no_data') {
            return ['ok' => true, 'message' => 'Info belum ada status laundry terkirim', 'outcome' => $outcome];
        }
        if ($outcome === 'no_pelanggan') {
            return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan di laundry', 'outcome' => $outcome];
        }

        return ['ok' => false, 'message' => 'Status tidak terkirim', 'outcome' => $outcome];
    }

    /**
     * Kirim gambar QRIS manual dari CRM — gambar sama dengan halaman Laundry/I/q.
     *
     * @return array{ok:bool,message?:string,cooldown?:bool,outcome?:string}
     */
    public function sendQrisFromCrm(string $waNumber, ?int $custId = null): array
    {
        $waNumber = (string) ($this->normalizePhoneNumber($waNumber) ?? '');
        if ($waNumber === '') {
            return ['ok' => false, 'message' => 'Nomor WA tidak valid'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }

        $ctx = \App\Helpers\CRM\WaSenderContext::resolve($waNumber);
        if ($custId !== null && $custId > 0) {
            $ctx['id_pelanggan'] = $custId;
            $ctx['cust_id'] = $custId;
            if (!in_array($custId, $ctx['ids_pelanggan'] ?? [], true)) {
                $ctx['ids_pelanggan'][] = $custId;
            }
            $ctx['is_pelanggan'] = true;
        }
        $this->setSenderContext($ctx);

        if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/CrmChatMergeHelper.php';
        }
        if (!class_exists('\\App\\Config\\WaLines')) {
            require_once __DIR__ . '/../Config/WaLines.php';
        }

        $dbCsw = DB::getInstance(0);
        $csw = \App\Helpers\CRM\CrmChatMergeHelper::getCswStatus($dbCsw, $waNumber);
        $lineKey = \App\Helpers\CRM\CrmChatMergeHelper::resolveReplyLine($csw, 'auto');
        if ($lineKey === null) {
            return [
                'ok' => false,
                'message' => 'CSW sudah tutup — tidak bisa kirim QRIS',
                'outcome' => 'csw_closed',
            ];
        }

        $lineMeta = \App\Config\WaLines::get($lineKey);
        $this->setInboundLine($lineKey, is_array($lineMeta) ? ($lineMeta['phone'] ?? null) : null);
        $this->setAutoReplyProvider($lineKey === \App\Config\WaLines::KEY_ADMIN ? 'B' : 'A');

        if ($this->isInHandlerCooldownAnyProvider($waNumber, 'REKENING')) {
            return [
                'ok' => false,
                'message' => 'Cooldown REKENING masih aktif — tunggu sebentar',
                'cooldown' => true,
            ];
        }

        $qrisMedia = $this->fetchLaundryQrisMedia();
        $imageUrl = trim((string) ($qrisMedia['image_url'] ?? ''));
        if ($imageUrl === '') {
            return ['ok' => false, 'message' => 'URL gambar QRIS tidak tersedia', 'outcome' => 'failed'];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/SapaanStatsHelper.php';
        }
        $senderCode = \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
        $caption = 'QRIS Madinah Laundry 😊';

        $res = $this->getWaService()->sendImage(
            $waNumber,
            $imageUrl,
            $caption,
            $senderCode,
            $this->resolveOutboundLineKey()
        );
        $this->recordHandlerCooldown($waNumber, 'REKENING');

        if (!empty($res['success'])) {
            return ['ok' => true, 'message' => 'QRIS terkirim', 'outcome' => 'sent'];
        }

        return [
            'ok' => false,
            'message' => (string) ($res['error'] ?? 'Gagal mengirim QRIS'),
            'outcome' => 'failed',
        ];
    }

    /**
     * Cooldown handler lintas provider (CRM manual ↔ autoreply A/B).
     */
    private function isInHandlerCooldownAnyProvider(string $waNumber, string $handler): bool
    {
        if ($this->intentLabMode) {
            return false;
        }
        $handler = strtoupper(trim($handler));
        if ($handler === '') {
            return false;
        }
        $cooldownMinutes = $this->getAutoreplyCooldownMinutes($handler);
        $db = DB::getInstance(0);
        $result = $db->query(
            "SELECT created_at FROM wa_auto_reply_log
             WHERE phone = ? AND handler = ?
             ORDER BY created_at DESC LIMIT 1",
            [$waNumber, $handler]
        );
        if (!$result || $result->num_rows() === 0) {
            return false;
        }
        $lastReply = $result->row()->created_at ?? null;
        if (!$lastReply) {
            return false;
        }
        $cooldownEnd = date('Y-m-d H:i:s', strtotime((string) $lastReply) + ($cooldownMinutes * 60));

        return date('Y-m-d H:i:s') < $cooldownEnd;
    }

    /**
     * Fallback DEFAULT (CS menunggu) hanya di jam operasional.
     * Di luar jam operasional → handleJam_operasional (jam tutup/libur), dengan cooldown 60 menit
     * yang sama dengan JAM_TUTUP / JAM_OPERASIONAL (jangan skip, jangan bakar cooldown DEFAULT).
     *
     * Di dalam jam: AI cek dulu — hanya balas "mohon menunggu" jika pesan = pertanyaan/permintaan.
     * Info/status/ack/omongan biasa → abaikan (jangan bakar cooldown DEFAULT).
     *
     * @return bool True jika ada balasan terkirim (atau cooldown DEFAULT tercatat)
     */
    public function trySendDefaultFallbackAutoreply($phoneIn, string $waNumber, ?string $textBody, string $fallbackText, int $cooldownMinutes = 1440): bool
    {
        // Human agent baru aktif: jangan kirim "CS/Admin sedang..." (biarkan CS; tanpa case 4)
        if ($this->isHumanAgentRecentlyActive($waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'human_active_skip');
            return false;
        }

        // Pesan tanpa handler dari nomor yang belum terhubung ke pelanggan
        // tetap masuk CRM, tetapi jangan kirim fallback otomatis ke WhatsApp.
        if (empty(($this->senderContext ?? [])['is_pelanggan'])) {
            $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'no_pelanggan_silent');
            return false;
        }

        if (!$this->isOperatingHours()) {
            // Cek dulu sebelum catat DEFAULT — pesan tutup memakai cooldown jam operasional, bukan DEFAULT 24 jam
            if ($this->isInAutoreplyCooldown($waNumber, 'JAM_TUTUP')) {
                $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'outside_hours→JAM_TUTUP_cooldown_skip');
                return false;
            }
            $this->currentHandler = 'JAM_OPERASIONAL';
            $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'outside_hours→JAM_OPERASIONAL');
            // skipJamTutupCooldown=false: hormati shouldHandle(JAM_TUTUP) 60 menit
            $sent = $this->handleJam_operasional($phoneIn, $waNumber, $textBody ?? '', false, false);

            return (bool) $sent;
        }

        // Gate AI: jangan balas default ke chat yang bukan pertanyaan/permintaan
        if (!$this->messageIsQuestionOrRequestForDefaultFallback($textBody, $waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'skip_not_question_or_request');
            return false;
        }

        if (!$this->shouldSendFonnteFallbackReply($waNumber, $cooldownMinutes)) {
            return false;
        }

        $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK', 'inside_hours→CS_wait');
        $this->sendDefaultFallbackAutoreply($waNumber, $fallbackText);

        return true;
    }

    /**
     * Gate sebelum DEFAULT "Maaf, mohon menunggu...":
     * true = pertanyaan atau permintaan (boleh balas default);
     * false = info/ack/status update/omongan biasa → abaikan.
     */
    private function messageIsQuestionOrRequestForDefaultFallback(?string $text, ?string $waNumber = null): bool
    {
        $msg = trim((string) ($text ?? ''));
        if ($msg === '') {
            return false;
        }

        $aiDecision = $this->aiDecideIsQuestionOrRequest($msg, $waNumber);
        if ($aiDecision !== null) {
            return $aiDecision;
        }

        // Fallback heuristik jika AI off/gagal (jangan balas default sembarangan)
        return $this->messageLooksLikeQuestionOrRequestHeuristic($msg);
    }

    /**
     * @return bool|null true/false dari AI; null = AI tidak tersedia / gagal parse
     */
    private function aiDecideIsQuestionOrRequest(string $msg, ?string $waNumber = null): ?bool
    {
        try {
            if (!class_exists('\\App\\Config\\AI')) {
                $cfg = __DIR__ . '/../Config/AI.php';
                if (!file_exists($cfg)) {
                    return null;
                }
                require_once $cfg;
            }
            if (!\App\Config\AI::isEnabled()) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        $system = "Kamu gatekeeper WhatsApp Madinah Laundry sebelum balasan default "
            . "\"Maaf, mohon menunggu. CS/Admin sedang melayani customer lain\".\n"
            . "Tugas: tentukan apakah PESAN customer adalah PERTANYAAN atau PERMINTAAN yang butuh respon CS.\n"
            . "is_question_or_request=true jika:\n"
            . "- Bertanya (ada/tidak ada tanda ?): status, harga, jam, estimasi, cara, dll.\n"
            . "- Meminta bantuan/aksi: minta jemput/antar, cek, kirim nota/bill, komplain, info, tolong, dll.\n"
            . "is_question_or_request=false jika:\n"
            . "- Hanya info/pemberitahuan (sudah otw, sudah bayar nanti, kabar lokasi, daftar item tanpa minta aksi)\n"
            . "- Ack/penutup singkat (ok, siap, baik, makasih) tanpa permintaan baru\n"
            . "- Omongan sosial / bukan minta layanan\n"
            . "- Status update operasional ke petugas tanpa pertanyaan\n"
            . "Jika ragu tapi ada nada minta bantuan → true. Jika ragu dan hanya cerita → false.\n"
            . "Jawab HANYA JSON: {\"is_question_or_request\":true,\"reason\":\"...\"}";

        $user = 'PESAN: ' . mb_substr($msg, 0, 500);

        try {
            $raw = $this->executeOpenAIRequestWithMessages(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                80,
                $waNumber
            );
        } catch (\Throwable $e) {
            if ($waNumber) {
                $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK_AI', 'error ' . mb_substr($e->getMessage(), 0, 120));
            }
            return null;
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json) && preg_match('/\{.*\}/s', (string) $raw, $m)) {
            $json = json_decode($m[0], true);
        }
        if (!is_array($json) || !array_key_exists('is_question_or_request', $json)) {
            if ($waNumber) {
                $this->logAutoreplyTrace($waNumber, 'DEFAULT_FALLBACK_AI', 'bad_json');
            }
            return null;
        }

        $yes = !empty($json['is_question_or_request']);
        if ($waNumber) {
            $reason = mb_substr((string) ($json['reason'] ?? ''), 0, 120);
            $this->logAutoreplyTrace(
                $waNumber,
                'DEFAULT_FALLBACK_AI',
                ($yes ? 'yes' : 'no') . ($reason !== '' ? ' ' . $reason : '')
            );
        }

        return $yes;
    }

    /** Heuristik murah jika AI tidak bisa dipanggil. */
    private function messageLooksLikeQuestionOrRequestHeuristic(string $msg): bool
    {
        if (strpos($msg, '?') !== false || strpos($msg, '？') !== false) {
            return true;
        }
        // Kata tanya / permintaan umum (ID + singkatan WA)
        if (preg_match(
            '/\b(apa|apakah|gimana|bagaimana|berapa|brp|brpa|kapan|kpn|kenapa|mengapa|boleh|bisakah|minta|tolong|mohon|tolongin|cek|info|infokan|kabari|kirim|bantu|bantuin|mau\s+(tanya|minta|jemput|antar)|bisa\s+(minta|tolong|cek|kirim))\b/iu',
            $msg
        )) {
            return true;
        }

        return false;
    }

    /**
     * Ambil nama contact untuk sapaan AI (pak/bu/kak/bang).
     * Prioritas: currentContactName dari process(), lalu wa_conversations.contact_name.
     * @param string $waNumber Nomor WhatsApp
     * @return string Nama atau kosong (AI pakai kak jika kosong/membingungkan)
     */
    private function getContactNameForGreeting($waNumber)
    {
        $name = trim($this->currentContactName ?? '');
        if ($name !== '') {
            return $name;
        }
        $db = DB::getInstance(0);
        $conv = $this->findExistingWaConversationRow($db, $waNumber);
        return $conv ? trim($conv->contact_name ?? '') : '';
    }

    /**
     * Log jejak pemilihan sapaan → file logs/{tanggal}/wa_sapaan.log (app=wa, controller=sapaan).
     */
    private function logSapaanResolve(string $line): void
    {
        if (!class_exists('Log', false)) {
            $p = __DIR__ . '/../Helpers/Log.php';
            if (is_file($p)) {
                require_once $p;
            }
        }
        if (class_exists('Log', false)) {
            \Log::write($line, 'wa', 'sapaan');
        }
    }

    /**
     * Ambil context greeting: contactName + sapaan (pak/bu/kak/bang/bg/…).
     * Fungsi terpusat untuk handler yang butuh keduanya (PEMBUKA, PENUTUP, JAM_OPERASIONAL).
     *
     * Urutan wajib sapaan (berhenti di langkah pertama yang menghasilkan nilai):
     * 1) sapaan_stats (db0), jumlah terbesar — tidak lanjut ke 2 atau 3.
     * 2) regex pada nama kontak — tidak lanjut ke 3 jika ketemu.
     * 3) AI dari nama (kak/bang) — jika tidak bisa diputuskan, default "kak".
     * Pengecualian: nama kontak kosong → langsung "kak" (tanpa regex/AI).
     *
     * @param string $waNumber Nomor WhatsApp
     * @return array{contactName: string, sapaan: string}
     */
    private function getGreetingContext($waNumber)
    {
        $contactName = $this->getContactNameForGreeting($waNumber);
        $cnLog = str_replace(["\r", "\n"], ' ', $contactName);

        $fromStats = $this->getSapaanFromStats($waNumber);
        if ($fromStats !== null && $fromStats !== '') {
            $this->logSapaanResolve(
                "GREETING final wa_number={$waNumber} sapaan={$fromStats} source=1_sapaan_stats contact_name={$cnLog}"
            );

            return [
                'contactName' => $contactName,
                'sapaan' => $fromStats,
            ];
        }

        if (trim((string) $contactName) === '') {
            $this->logSapaanResolve("GREETING final wa_number={$waNumber} sapaan=kak source=default (nama_kosong, tanpa regex/ai)");

            return [
                'contactName' => $contactName,
                'sapaan' => 'kak',
            ];
        }

        $fromRegex = $this->getSapaanFromContactNameRegex($contactName);
        if ($fromRegex !== null && $fromRegex !== '') {
            $this->logSapaanResolve(
                "GREETING final wa_number={$waNumber} sapaan={$fromRegex} source=2_regex_nama contact_name={$cnLog}"
            );

            return [
                'contactName' => $contactName,
                'sapaan' => $fromRegex,
            ];
        }

        $fromAi = $this->getSapaanFromContactNameAI($contactName);
        $sapaan = ($fromAi === 'bang') ? 'bang' : 'kak';
        $this->logSapaanResolve(
            "GREETING final wa_number={$waNumber} sapaan={$sapaan} source=3_ai_nama contact_name={$cnLog}"
        );

        return [
            'contactName' => $contactName,
            'sapaan' => $sapaan,
        ];
    }

    /**
     * Sapaan dari sapaan_stats: ORDER BY jumlah DESC (paling sering dipakai agent ke nomor ini).
     * Urutan lookup: exact wa_number (variasi) → match digit penuh kolom = digit nomor webhook → suffix nasional 852….
     *
     * @return string|null null jika belum ada baris atau nilai tidak valid
     */
    private function getSapaanFromStats(string $waNumber): ?string
    {
        try {
            $db = DB::getInstance(0);

            foreach ($this->waNumberLookupVariants($waNumber) as $variant) {
                $db->query(
                    'SELECT sapaan, jumlah, wa_number AS wn FROM sapaan_stats WHERE wa_number = ? ORDER BY jumlah DESC, sapaan ASC LIMIT 1',
                    [$variant]
                );
                if ($db->num_rows() > 0) {
                    $row = $db->row();
                    $raw = (string) ($row->sapaan ?? '');
                    $normalized = $this->normalizeSapaanFromStats($raw);
                    if ($normalized !== null) {
                        $this->logSapaanResolve(
                            "STATS hit=exact_variant variant={$variant} row_wn=" . ($row->wn ?? '') . " raw={$raw} jumlah=" . ($row->jumlah ?? '') . " normalized={$normalized}"
                        );

                        return $normalized;
                    }
                    $this->logSapaanResolve("STATS skip_normalize raw={$raw} (tidak ada di SapaanStatsKeywords) variant={$variant}");
                }
            }

            $digitsIn = $this->normalizePhoneDigitsOnlyPhp($waNumber);
            if (strlen($digitsIn) >= 10) {
                $expr = $this->sqlPhoneColumnDigitsOnlyExpr('wa_number');
                $db->query(
                    'SELECT sapaan, jumlah, wa_number AS wn FROM sapaan_stats WHERE ' . $expr . ' = ? ORDER BY jumlah DESC, sapaan ASC LIMIT 1',
                    [$digitsIn]
                );
                if ($db->num_rows() > 0) {
                    $row = $db->row();
                    $raw = (string) ($row->sapaan ?? '');
                    $normalized = $this->normalizeSapaanFromStats($raw);
                    if ($normalized !== null) {
                        $this->logSapaanResolve(
                            "STATS hit=full_digits digits_in={$digitsIn} row_wn=" . ($row->wn ?? '') . " raw={$raw} jumlah=" . ($row->jumlah ?? '') . " normalized={$normalized}"
                        );

                        return $normalized;
                    }
                    $this->logSapaanResolve("STATS full_digits row tapi normalize gagal raw={$raw} digits_in={$digitsIn}");
                }
            }

            $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber);
            if ($nomor === null) {
                $this->logSapaanResolve("STATS miss wa_number={$waNumber} (nomor nasional null)");

                return null;
            }

            $digits = $this->sqlPhoneColumnDigitsOnlyExpr('wa_number');
            $sql = 'SELECT sapaan, jumlah, wa_number AS wn FROM sapaan_stats WHERE '
                . $digits . ' LIKE ? ORDER BY jumlah DESC, sapaan ASC LIMIT 1';
            $db->query($sql, ['%' . $nomor]);
            if ($db->num_rows() > 0) {
                $row = $db->row();
                $raw = (string) ($row->sapaan ?? '');
                $normalized = $this->normalizeSapaanFromStats($raw);
                if ($normalized !== null) {
                    $this->logSapaanResolve(
                        "STATS hit=nomor_suffix nomor={$nomor} row_wn=" . ($row->wn ?? '') . " raw={$raw} jumlah=" . ($row->jumlah ?? '') . " normalized={$normalized}"
                    );

                    return $normalized;
                }
                $this->logSapaanResolve("STATS nomor_suffix row tapi normalize gagal raw={$raw}");
            }

            $this->logSapaanResolve("STATS miss wa_number={$waNumber} digits_in={$digitsIn} nomor={$nomor} (tidak ada baris sapaan_stats)");
        } catch (\Throwable $e) {
            $this->logSapaanResolve('STATS exception ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            if (class_exists('Log', false)) {
                \Log::write('getSapaanFromStats: ' . $e->getMessage(), 'wa_error', 'WAReplies');
            }
        }

        return null;
    }

    /**
     * Validasi token dari kolom sapaan (bukan “menebak” sapaan): harus ada di SapaanStatsKeywords.
     *
     * @return string|null null jika kosong atau tidak ada di daftar
     */
    private function normalizeSapaanFromStats(string $raw): ?string
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return null;
        }

        $path = __DIR__ . '/../Config/SapaanStatsKeywords.php';
        if (is_file($path)) {
            $cfg = require $path;
            $allowed = array_map('strtolower', $cfg['keywords'] ?? []);
            if ($allowed !== [] && !in_array($s, $allowed, true)) {
                return null;
            }
        }

        return $s;
    }

    /**
     * Sapaan untuk greeting — hanya lewat getGreetingContext:
     * 1) sapaan_stats → 2) regex nama → 3) AI (kak/bang), default kak.
     *
     * @param string $waNumber Nomor WhatsApp
     * @return string token sapaan (mis. pak, bu, kak, bang, bg, …)
     */
    private function getSapaanForGreeting($waNumber)
    {
        $ctx = $this->getGreetingContext($waNumber);
        return $ctx['sapaan'];
    }

    /**
     * Langkah 2: sapaan dari pola pada nama kontak (tanpa AI).
     *
     * @return string|null null jika tidak ada pola yang cocok → lanjut langkah 3 (AI)
     */
    private function getSapaanFromContactNameRegex(?string $contactName): ?string
    {
        $n = strtolower(trim((string) $contactName));
        if ($n === '') {
            return null;
        }

        if (preg_match('/\b(pakde|pak\s*de)\b/i', $n)) {
            return 'pakde';
        }
        if (preg_match('/\b(bude|bukde|bu\s*de|buk\s*de)\b/i', $n)) {
            return 'bude';
        }
        if (preg_match('/\b(ibu|ibuk|bu|buk)\b/', $n)) {
            return 'bu';
        }
        if (preg_match('/^b\s|^b\./i', $n)) {
            return 'bu';
        }
        if (preg_match('/\b(bapak|pak|bpk)\b/', $n)) {
            return 'pak';
        }
        if (preg_match('/\bom\b/', $n)) {
            return 'om';
        }
        if (preg_match('/\bmas\b/', $n)) {
            return 'mas';
        }
        if (preg_match('/\bmbak\b/', $n)) {
            return 'mbak';
        }
        if (preg_match('/\b(bg|bang)\b|^bg/i', $n)) {
            return 'bang';
        }
        if (preg_match('/\b(kak|kakak)\b/', $n)) {
            return 'kak';
        }

        return null;
    }

    /**
     * Langkah 3: AI memilih kak atau bang dari nama. Default akhir: "kak".
     *
     * @return string 'kak'|'bang'
     */
    private function getSapaanFromContactNameAI(string $contactName): string
    {
        $n = strtolower(trim($contactName));
        if ($n === '') {
            return 'kak';
        }
        if (isset($this->sapaanAiCache[$n])) {
            return $this->sapaanAiCache[$n];
        }
        $sapaan = $this->detectSapaanFromNameWithAI($contactName);
        if ($sapaan !== 'bang') {
            $sapaan = 'kak';
        }
        $this->sapaanAiCache[$n] = $sapaan;

        return $sapaan;
    }

    /**
     * AI pilih sapaan kak atau bang dari nama Indonesia.
     * Kalau ragu, utamakan kak.
     * @return string 'kak' atau 'bang'
     */
    private function detectSapaanFromNameWithAI($contactName)
    {
        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                return 'kak';
            }
            $contactName = trim((string) ($contactName ?? ''));
            $firstName = trim(preg_split('/\s+/', $contactName, 2)[0] ?? '') ?: $contactName;
            $messages = [
                ['role' => 'system', 'content' => "Kamu classifier sapaan Indonesia. Dari nama depan, pilih: 'kak' (perempuan/neutral) atau 'bang' (laki-laki).\n\nAturan:\n- Nama khas perempuan (Siti, Dewi, Ani, Rina, dll) -> kak\n- Nama khas laki-laki (Budi, Bambang, Ahmad, Rudi, Alim, dll) -> bang\n- Nama netral/tidak jelas (Dian, Wawan, dsb) -> utamakan kak\n- Ragu atau nama singkat/tidak dikenal -> utamakan kak\n\nPENTING: Jika ragu antara kak dan bang, pilih kak.\n\nJawab HANYA satu kata: kak atau bang."],
                ['role' => 'user', 'content' => "Nama: {$firstName}"],
            ];
            $answer = trim(strtolower($this->executeOpenAIRequestWithMessages($messages, 10)));
            if ($answer === 'bang') return 'bang';
            return 'kak';
        } catch (\Exception $e) {
            return 'kak';
        }
    }

    /**
     * Emote happy untuk PENUTUP random. Sortir di sini sesuai selera.
     */
    private function getPenutupSoftSmileEmojis(): array
    {
        return [
            '😊', '🙂', '☺️', '😇', '🤗',
        ];
    }

    private function pickPenutupSoftSmile(): string
    {
        $emojis = $this->getPenutupSoftSmileEmojis();

        return $emojis[array_rand($emojis)];
    }

    /**
     * Gabungkan template kalimat + emote soft (satu kalimat × banyak emote).
     *
     * @param string[] $templates Kalimat tanpa emote; boleh berisi {sapaan}
     */
    private function expandPenutupRepliesWithSoftSmiles(array $templates, string $sapaan): array
    {
        $out = [];
        foreach ($templates as $tpl) {
            $base = str_replace('{sapaan}', $sapaan, $tpl);
            foreach ($this->getPenutupSoftSmileEmojis() as $emoji) {
                $out[] = $base . ' ' . $emoji;
            }
        }

        return $out;
    }

    private function pickRandomFromList(array $replies): string
    {
        return $replies[array_rand($replies)];
    }

    /**
     * PENUTUP subtype: ucapan terima kasih.
     */
    private function pickPenutupThanksReply(string $sapaan): string
    {
        return $this->pickRandomFromList($this->expandPenutupRepliesWithSoftSmiles([
            'Sama-sama {sapaan}',
            'Iya {sapaan}, sama-sama',
        ], $sapaan));
    }

    /**
     * PENUTUP subtype: info sudah bayar / lunas / bukti transfer.
     */
    private function pickPenutupPaymentReply(string $sapaan): string
    {
        return $this->pickRandomFromList($this->expandPenutupRepliesWithSoftSmiles([
            'Baik {sapaan}, terima kasih',
            'Terima kasih {sapaan}',
        ], $sapaan));
    }

    /**
     * Token ok/oke/okey/okeyy (huruf e/y berlebih).
     */
    private function penutupOkTokenPattern(): string
    {
        return 'okk*(?:e+y*|ey+)?';
    }

    /**
     * PENUTUP lainnya (ok/siap/sticker) tidak dibalas — jangan cooldown, biar thanks/bayar berikutnya tetap bisa.
     */
    private function penutupLainnyaSkipsCooldown(string $handler, string $textBody): bool
    {
        if (strtoupper($handler) !== 'PENUTUP') {
            return false;
        }
        if ($this->penutupShouldAutoreplyThanksOrPayment($textBody)) {
            return false;
        }

        return true;
    }

    /** Ucapan thanks / konfirmasi bayar — selalu balas (lewati human-active & rate limit). */
    private function penutupShouldAutoreplyThanksOrPayment(?string $textBody): bool
    {
        if ($textBody === null || trim($textBody) === '') {
            return false;
        }
        $norm = $this->normalizeTextBodyForRegex($textBody);

        return $this->messageLooksLikeThanksPenutup($norm)
            || $this->messageLooksLikePaymentConfirmationPenutup($norm);
    }

    /** PEMBUKA skip sapaan karena chat masih hangat — jangan catat cooldown 30 menit. */
    private function pembukaSkippedGreetingCooldown(string $handler): bool
    {
        return strtoupper($handler) === 'PEMBUKA' && $this->pembukaSkippedGreeting;
    }

    /**
     * Kalimat pendek ambigu (mis. closed order, order closed): tetap intent PENUTUP tapi jangan dibalas AI.
     * @return bool True jika pesan ambigu dan tidak boleh dibalas
     */
    private function isAmbiguousPenutupShortPhrase($text)
    {
        $t = strtolower(trim($text ?? ''));
        if ($t === '') return false;
        return preg_match('/\bclosed\s*order\b/i', $t) || preg_match('/\border\s*closed\b/i', $t);
    }

    /**
     * Balasan salam (waalaikumsalam, dll.) — bukan pembuka seperti assalamualaikum.
     */
    private function messageIsWalaikumsalamReply(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = trim($text);
        // Pembuka dari customer: assalamualaikum — tetap bukan balasan waalaikumsalam
        if (preg_match('/^\s*(assalam|asalamu)/iu', $t)) {
            return false;
        }
        // waalaikumsalam / walaikumsalam / wa alaikum salam / waalaikumussalam (bukan assalamualaikum)
        return (bool) preg_match(
            '/^\s*w+a+\s*laikums+alam\b|^\s*w+a\s*laikums+alam\b|^\s*wa\s+alaikum\s+salam\b|^\s*walaikum\s+salam\b|^\s*waalaikum\s+salam\b|^\s*(waalaikumussalam|walaikumussalam)\b/iu',
            $t
        );
    }

    /**
     * Instruksi/daftar item laundry panjang (bukan penutup percakapan).
     */
    private function messageLooksLikeLaundryItemListNotPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $len = mb_strlen($text);
        if ($len < 80) {
            return false;
        }
        if (substr_count($text, ',') < 1) {
            return false;
        }
        return (bool) preg_match(
            '/\b(baju|celana|rok|kemeja|kaos|levis|jeans|sprei|selimut|jaket|handuk|dress|gamis|jilbab|celana\s*pendek|lengan\s*panjang)\b/iu',
            $text
        );
    }

    /**
     * Konfirmasi pembayaran/transfer/lunas — tetap PENUTUP meski pesan panjang (bukti + nominal + terima kasih).
     * Termasuk typo: uda/udah + saya bayar + barusan.
     */
    private function messageLooksLikePaymentConfirmationPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = mb_strtolower($text);
        if (preg_match($this->paymentAlreadyDoneRegex(), $t)) {
            return true;
        }
        if (preg_match(
            '/\bberikut\s+bukti\s+(lunas(\s+bayar)?|bayar|transfer|pembayaran|tf|trf)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match('/\bbukti\s+(lunas(\s+bayar)?|bayar|transfer|pembayaran|tf|trf)\b/iu', $t)) {
            return true;
        }
        // Trailing ellipsis/emoji/punctuation OK: "Lunas ya kak...🙏"
        if (preg_match('/^\s*lunas(\s+ya)?(\s+(kak|kk|ka|bang|min|mbak|pak|bu))?\s*[^\p{L}\p{N}]*$/iu', $t)) {
            return true;
        }
        if (preg_match('/^\s*(sudah|udah|udh|sdh|uda|dah)\s+lunas(\s+(ya\s*)?(kak|kk|ka|bang|min|mbak|pak|bu))?\s*[^\p{L}\p{N}]*$/iu', $t)) {
            return true;
        }

        return false;
    }

    /** Regex: sudah/uda/udah + (saya) + bayar/transfer/lunas, atau bayar barusan. */
    private function paymentAlreadyDoneRegex(): string
    {
        $already = '(sudah|udah|udh|sdh|uda|dah)';
        $pay = '(bayar|byr|transfer|tf|trf|lunas)';
        $subj = '(saya|aku|sy|kami|kita|gue)';

        return '/'
            . 'telah\s+berhasil\s+mengirimkan'
            . '|' . $already . '\s+(?:' . $subj . '\s+)?' . $pay
            . '|' . $subj . '\s+' . $already . '\s+' . $pay
            . '|' . $already . '\s+(?:' . $subj . '\s+)?(kirim|mengirim)'
            . '|\b' . $subj . '\s+' . $pay . '\b.{0,32}\b(barusan|baru\s*saja|tadi)\b'
            . '|\b(barusan|baru\s*saja)\b.{0,32}\b' . $pay . '\b'
            . '|\b(info\s+)?pelunasan\b|\blunas\s+bayar\b'
            . '/iu';
    }

    /**
     * Ucapan terima kasih (termasuk typo umum) — cukup untuk PENUTUP.
     * Contoh: terima kasih, makasih, makasi, makaci, makaseh, trima ksih, mksh, thanks.
     */
    private function messageLooksLikeThanksPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b('
            . 'makasi|'                             // eksplisit: makasi (tanpa h)
            . 'ma*ka*(s|c)(i|e)+h?|'               // makasih, makasi, makaci, makaseh, ...
            . 'te*ri*ma*ka*si*h|'                   // terimakasih / trimakasih (satu kata)
            . '(trima|terima)\s+(kasih|ksih|ksh)|' // trima ksih / terima kasih
            . 'trima*kasih|trimakasih|trmksh|trm\s*ksh|mksh|'
            . 'tha*nks|thx|tq|ty'
            . ')\b/iu',
            $text
        );
    }

    /**
     * Minta antar/jemput setelah siap: "kalo uda selesai antar ya" — ini KURIR, bukan hipotetis.
     */
    private function messageLooksLikeKalauSelesaiAntarMinta(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        return (bool) preg_match(
            '/\b(kalau|kalo|klo|klu|klau)\b.{0,80}?\b(udah|uda|udh|sdh|sudah|dah|dh|sudh)\s+(siap|selesai|jadi)\b.{0,40}?\b(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu',
            $text
        ) || (bool) preg_match(
            '/\b(kalau|kalo|klo|klu|klau)\b.{0,80}?\b(siap|selesai|jadi)\b.{0,40}?\b(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu',
            $text
        );
    }

    /**
     * Hipotetis/kondisional: "kalau/klo/kalo" lalu antar/jemput — BUKAN permintaan kurir.
     * Contoh: "Kalau express di antar sekarang", "klo jemput brp?"
     * Kecuali: "kalo uda selesai antar ya" = minta antar setelah siap (tetap KURIR).
     */
    private function messageLooksLikeKalauAntarJemputBukanMinta(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if ($this->messageLooksLikeKalauSelesaiAntarMinta($text)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(kalau|kalo|klo|klu|klau)\b.{0,120}?\b(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu',
            $text
        );
    }

    /**
     * Customer yang antar/jemput SENDIRI — BUKAN minta kurir.
     * Contoh: "kami antar", "kami aja yang antar", "saya yang antar", "aku jemput", "saya ambil".
     */
    private function messageLooksLikeCustomerSelfAntarAtauJemput(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $subj = '(kami|kita|aku|saya|sy|gue|awak)';
        // "saya/aku/kami ambil" (ambil sendiri)
        if (preg_match('/\b' . $subj . '\s+ambil\b/iu', $text)) {
            return true;
        }
        // "kami antar" / "saya jemput" (langsung — bukan minta kurir)
        if (preg_match('/\b' . $subj . '\s+(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu', $text)) {
            return true;
        }
        // "kami aja yang antar" / "kami aja antar"
        if (preg_match('/\b' . $subj . '\s+(aja|saja)\s+(yang\s+)?(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu', $text)) {
            return true;
        }
        // "saya yang antar" / "aku yang jemput"
        if (preg_match('/\b' . $subj . '\s+yang\s+(di\s*)?(antar|anter|antr|jemput|jmpt)\b/iu', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Sticker WhatsApp (label webhook "🎨 Sticker", opsional URL media) — PENUTUP lainnya.
     */
    private function messageLooksLikeStickerPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        return (bool) preg_match('/^\s*(🎨\s*)?sticker(\s+https?:\/\/\S+)?\s*$/iu', trim($text));
    }

    /**
     * PENUTUP ketat — hanya salah satu dari:
     * (1) ucapan terima kasih, (2) info sudah bayar/lunas/pelunasan, (3) ack murni (ok/baik/sip/siap/sejenis) tanpa isi lain.
     * Contoh BUKAN penutup: "Ok kk,aku otw ya kk"
     * Contoh PENUTUP: "Oke kk, di tunggu kbr ny dn trima ksih byk"
     */
    private function messageMatchesStrictPenutupAllowlist(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = trim($text);

        // (1) Terima kasih (termasuk typo: trima ksih, mksh kk, dll.)
        if ($this->messageLooksLikeThanksPenutup($t)) {
            return true;
        }

        // (2) Sudah bayar / lunas / bukti / pelunasan
        if ($this->messageLooksLikePaymentConfirmationPenutup($t)) {
            return true;
        }

        // Reaction / emoji saja
        if (preg_match('/^reacted\s+/iu', $t)) {
            return true;
        }
        if (mb_strlen($t) <= 6 && preg_match('/^[^\p{L}\p{N}]+$/u', $t)) {
            return true;
        }
        // Sticker (label webhook: "🎨 Sticker", opsional URL media)
        if ($this->messageLooksLikeStickerPenutup($t)) {
            return true;
        }

        // (3) Ack singkat murni — seluruh pesan hanya token ack (+ sapaan opsional); ok/okk/okkk/oke ok
        $okTok = $this->penutupOkTokenPattern();
        if (preg_match(
            '/^\s*\b(' . $okTok . '|baik+|sip+|sia+p+|gpp|gak\s*apa\s*apa|ga\s*apa\s*apa|iya+|ya+)'
            . '(\s+(deh|lah|dong|ya))*'
            . '(?:\s+(kak|kk|bang|min|mbak|pak|bu|buk|mas|om|dek|nte|penya|punya))*'
            . '\s*[.!?]*\s*$/iu',
            $t
        )) {
            return true;
        }
        if (preg_match(
            '/^\s*\b(' . $okTok . '|baik|sip)\s+(sia+p+|sip)(?:\s+(kak|kk|bang|min|mbak|pak|bu|ya))?\s*\??\s*$/iu',
            $t
        )) {
            return true;
        }

        return false;
    }

    /**
     * PENUTUP yang bukan closing ketat: info/otw/daftar item/jadwal/janji bayar (bukan ack penutup).
     */
    private function messageIsNonStrictPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if ($this->messageLooksLikeLaundryItemListNotPenutup($text)
            || $this->messageLooksLikeBelumDiambilInfo($text)
            || $this->messageLooksLikeSudahDiantarInfo($text)
            || $this->messageLooksLikeJadwalRujukOrderBukanPenutup($text)
            || $this->messageLooksLikeFuturePaymentCommitmentBukanPenutup($text)
        ) {
            return true;
        }
        if (!$this->messageMatchesStrictPenutupAllowlist($text)) {
            return true;
        }

        return false;
    }

    /**
     * Informasi "belum diambil" adalah status/info, bukan penutup.
     */
    private function messageLooksLikeBelumDiambilInfo(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = mb_strtolower($text);
        return (bool) preg_match('/\b(bl?m|belum|belom)\s+(di\s*)?ambil\b/iu', $t);
    }

    /**
     * Info "sudah diantar/di anter (oleh saya/suami/dll)" adalah info proses, bukan penutup.
     */
    private function messageLooksLikeSudahDiantarInfo(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = mb_strtolower($text);
        return (bool) preg_match(
            '/\b(sudah|udah|udh)\s+(di\s*)?(antar|anter)\b.*\b(saya|sy|aku|kami|suami|istri)\b|\b(antar|anter)\b.*\b(sama|oleh)\b.*\b(suami|istri|saya|aku|kami)\b/iu',
            $t
        );
    }

    /**
     * Rujukan order/waktu (yg tadi sore, dll.) + jadwal ambil/jemput (besok di ambil) — info operasional, bukan ack penutup singkat.
     */
    private function messageLooksLikeJadwalRujukOrderBukanPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        if (!preg_match('/\b(yg|yang)\s+(td|tdi|tadi)\s+(sore|pagi|siang|malam)\b/iu', $t)) {
            return false;
        }
        if (!preg_match('/\bbesok\b/iu', $t)) {
            return false;
        }
        return (bool) preg_match('/\b(di\s*)?(ambil|jemput|antar|anter)\b/iu', $t);
    }

    /**
     * Janji/konfirmasi AKAN bayar/transfer (belum dilakukan) — bukan PENUTUP.
     * PENUTUP untuk pembayaran hanya jika sudah kirim/sudah transfer/sudah bayar.
     */
    private function messageLooksLikeFuturePaymentCommitmentBukanPenutup(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = mb_strtolower($text);
        if (preg_match($this->paymentAlreadyDoneRegex(), $t)) {
            return false;
        }
        if (preg_match(
            '/\b(nanti|nntk|nnti|ntar|besok)\b.{0,52}\b(bayar|byr|transfer|tf|trf)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match(
            '/\b(bayar|byr|transfer|tf|trf)\b.{0,52}\b(nanti|nntk|nnti|ntar|besok)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match('/\bakan\s+(bayar|transfer|tf|trf|kirim(\s+uang)?)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\d/', $t)
            && preg_match('/\b(bayar|byr|transfer|tf|trf)\b/iu', $t)
            && preg_match('/\b(dl|deal)\b/iu', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Tanya harga barang tambahan/ritel (parfum, plastik, dll.) — bukan tarif laundry di intent HARGA.
     * Nanti bisa intent terpisah (harga barang khusus).
     */
    private function messageIsHargaBarangTambahan(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = mb_strtolower($text);
        return (bool) preg_match(
            '/\b(parfum|perfume|plastik|pewangi|hanger|tissue|kantong\s*plastik)\b/iu',
            $t
        );
    }

    /**
     * Minta pricelist / price list / daftar harga (tarif laundry), bukan konteks lain.
     */
    /**
     * Memberitahu pilihan layanan untuk cucian yang baru/kemarin diantar/masukkan — NOTA (detail order/bon).
     */
    private function messageLooksLikeNotaLayananSetelahAntar(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        $hasDropOffRef = (bool) preg_match(
            '/\b(yg|yang)\s+(saya|aku|kami)\s+(masukkan|antar|anter|serahkan|titip|bawa|nyerahkan|nitip)\b/iu',
            $t
        ) || (bool) preg_match(
            '/\b(kain|pakaian|cucian|baju|item)\s+(yang\s+)?(saya|aku|kami)\s+(antar|anter|masukkan|titip|bawa|nyerahkan|nitip)\b/iu',
            $t
        ) || (bool) preg_match(
            '/\b(saya|aku|kami)\s+(antar|anter|masukkan|titip|bawa|nyerahkan|nitip)\s+(tadi|kemarin|kmrn|td|pagi|siang|sore|malam|hari\s+ini)\b/iu',
            $t
        );
        if (!$hasDropOffRef) {
            return false;
        }
        // Harus ada pilihan layanan nyata — jangan anggap kata laundry/loundry/item saja sebagai layanan
        return (bool) preg_match(
            '/\b(cuci\s*setrika|cuci\s*strika|setrika\s*aja|strika\s*aja|setrika\s*manual|cuci\s*aja|reguler|regular|ekspres|ekspress|express|kilat|manual|biasa|pilih\s+(yang\s+)?(ekspres|express|reguler|kilat|setrika))\b/iu',
            $t
        );
    }

    private function messageLooksLikePricelistRequest(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        return (bool) preg_match(
            '/\bpricelist\w*\b|\bprice\s*list\w*\b|\b(daftar|list)\s+harga\w*\b/iu',
            $text
        );
    }

    /**
     * Pertanyaan tidak selalu memakai tanda (?). Contoh: "Alhamdulillah foto dimana"
     * PEMBUKA/PENUTUP tidak boleh jika ini true.
     */
    private function messageLooksLikeQuestion(?string $textBody): bool
    {
        if ($textBody === null || trim($textBody) === '') {
            return false;
        }
        if (strpos($textBody, '?') !== false || mb_strpos($textBody, '？') !== false) {
            return true;
        }
        $t = mb_strtolower($textBody);
        if (preg_match(
            '/\b(dimana|dimna|kemana|kmana|darimana|kenapa|knp|knapa|gimana|bagaimana|gmn|gmna|siapa|berapa|kapan)\b/iu',
            $t
        )) {
            return true;
        }
        if (preg_match('/\bjam\s*(brp|brpa|berapa)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(boleh|bisa)\s*(gak|ga|g|kah)\s*$/iu', trim($t))) {
            return true;
        }
        return false;
    }

    /**
     * Pesan yang hanya berisi nominal (mis. "175.000 kak", "Rp 38.000", "38.000 normalnya kak") bukan intent TAGIHAN/HARGA.
     */
    private function messageLooksLikeAmountOnly(?string $textBody): bool
    {
        if ($textBody === null) {
            return false;
        }
        $t = trim(mb_strtolower($textBody));
        if ($t === '') {
            return false;
        }
        // Jika ada kata tanya/konteks, jangan dianggap nominal-only
        if (preg_match('/\b(berapa|brp|total|tagihan|bil+|biaya|bayar|transfer|harga|cuci|setrika|strika|gosok)\b/iu', $t)) {
            return false;
        }
        // Hapus emoji & simbol umum yang sering ikut
        $t = preg_replace('/[^\p{L}\p{N}\s\.,_]/u', ' ', $t);
        $t = preg_replace('/\s+/', ' ', trim($t));

        $amount = '(rp\s*)?[\d\.,]+(\s*(rb|ribu|jt|juta|k))?';
        $sapaan = '(kak|kk|bang|pak|bu|mbak|mas|sis)?';
        $konteks = '(normalnya|biasanya|biasa)';

        // "175.000 kak", "rp 38.000", "200rb", "1.5jt", "175000"
        $rePlain = '/^' . $amount . '\s*' . $sapaan . '\s*$/iu';
        // "38.000 normalnya kak" — info nominal, bukan tanya harga
        $reAmountKonteks = '/^' . $amount . '\s+' . $konteks . '\s*' . $sapaan . '\s*$/iu';
        // "normalnya 38.000 kak"
        $reKonteksAmount = '/^' . $konteks . '\s+' . $amount . '\s*' . $sapaan . '\s*$/iu';

        if (!preg_match($rePlain, $t) && !preg_match($reAmountKonteks, $t) && !preg_match($reKonteksAmount, $t)) {
            return false;
        }
        // Pastikan ada digit
        return preg_match('/\d/', $t) === 1;
    }

    /**
     * Boleh kirim balasan generik saat TIDAK ada data ("belum ada tagihan...")?
     * Hanya jika pesan tegas satu kata: bon|nota|struk, bil|bill|tagihan, cek|status.
     *
     * Bukan syarat untuk mengirim rincian tagihan, nota, atau status — itu tetap jalan jika ada data di DB.
     */
    private function shouldSendGenericNoDataAutoreply(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = preg_replace('/[*_~`]/', '', trim($text));
        return (bool) (
            preg_match('/^\s*(bon|nota|struk)\s*$/i', $t)
            || preg_match('/^\s*(bil+|tagihan)\s*$/i', $t)
            || preg_match('/^\s*(cek|sta*tu*s)\s*$/i', $t)
        );
    }

    /**
     * Balasan generik "belum ada tagihan..." — hanya saat tidak ada data + pola tegas.
     */
    private function trySendBelumAdaTagihanAutoreply($waService, string $waNumber, ?string $textBody, string $namaPelanggan, ?string $linkSuffix = null, string $logPrefix = 'SKIP', bool $force = false): void
    {
        if (!$force && !$this->shouldSendGenericNoDataAutoreply($textBody)) {
            $this->logAutoreplyTrace($waNumber, $logPrefix, 'belum_ada_tagihan_skipped_not_strict_keyword');
            if ($force === false && $logPrefix === 'TAGIHAN') {
                $this->tagihanSendOutcome = 'skipped';
            } elseif ($force === false && $logPrefix === 'STATUS') {
                $this->statusSendOutcome = 'skipped';
            }
            return;
        }
        $text = 'Pak/Bu *' . $namaPelanggan . '*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih 😊';
        if ($linkSuffix !== null && $linkSuffix !== '') {
            $text .= "\n" . $linkSuffix;
        }
        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            if ($logPrefix === 'TAGIHAN') {
                $this->tagihanSendOutcome = 'no_data';
            } elseif ($logPrefix === 'STATUS') {
                $this->statusSendOutcome = 'no_data';
            }
        } elseif ($logPrefix === 'TAGIHAN') {
            $this->tagihanSendOutcome = 'skipped';
        } elseif ($logPrefix === 'STATUS') {
            $this->statusSendOutcome = 'skipped';
        }
        $this->logAutoreplyTrace($waNumber, $logPrefix, 'belum_ada_tagihan_sent');
    }

    /**
     * pagi/siang/sore/malam + kak/bang/… di tengah kalimat — abaikan jika rujukan waktu order
     * (mis. "tadi pagi kak", "kemarin sore kak"), bukan sapaan waktu.
     */
    private function messageHasTimeOfDaySalutationWithHonorific(string $textLower): bool
    {
        if (!preg_match_all('/\b(pagi|siang|sore|malam)\s*(kak|bang|pak|bu|adek)/iu', $textLower, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        foreach ($matches[0] as $match) {
            $before = mb_substr($textLower, 0, $match[1]);
            if (!preg_match('/\b(tadi|td|tdi|kemarin|kmrn|semalam|smlm)\s+$/iu', $before)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah pesan mengandung sapaan (assalamualaikum, pagi, halo, dll) di awal.
     * Untuk menentukan apakah perlu intro sapaan di balasan.
     */
    private function hasGreetingInMessage($textBody)
    {
        $t = strtolower(trim($textBody ?? ''));
        if ($t === '') {
            return false;
        }
        if (preg_match('/^(assalam+u[a-z]*|asalam+u[a-z]*|salam|halo|hai|pagi|siang|sore|malam)\b/i', $t)) {
            return true;
        }
        if (preg_match('/\b(assalam+u|asalam+u|salam)\s*(kak|bang|pak|bu|adek)/i', $t)) {
            return true;
        }

        return $this->messageHasTimeOfDaySalutationWithHonorific($t);
    }

    /**
     * Balasan sapaan waktu/salam yang mengikuti KATA DI AWAL pesan (bukan kata di tengah, mis. "tdi pagi").
     * Menghindari "Siang kak, ... nota ... tdi pagi" salah dibalas "Pagi" karena preg_match /pagi/ di seluruh teks.
     */
    private function getMirrorTimeGreetingReplyLine(string $textBody, string $sapaan): string
    {
        $t = strtolower(trim((string) $textBody));
        if ($t === '') {
            return "Halo {$sapaan}";
        }
        // Hilangkan pembuka halo/hai + sapaan opsional + koma agar terbaca sapaan waktu berikutnya
        if (preg_match('/^(halo|hai)\s*[,]?\s*\S{0,14}\s*,\s*/u', $t, $m)) {
            $t = trim(mb_substr($t, mb_strlen($m[0])));
        }
        $head = mb_substr($t, 0, 72);

        if (preg_match('/^(assalam+u[a-z]*|asalam+u[a-z]*)\b/i', $head)) {
            return "Waalaikumsalam {$sapaan}";
        }
        if (preg_match('/^salam\b/i', $head)) {
            return "Waalaikumsalam {$sapaan}";
        }
        // Waktu: cek urutan di AWAL (siang/sore/malam sebelum pagi) — jangan pakai /pagi/ pada seluruh string
        foreach (['siang' => 'Siang', 'sore' => 'Sore', 'malam' => 'Malam', 'pagi' => 'Pagi'] as $word => $label) {
            if (preg_match('/^' . preg_quote($word, '/') . '\b/i', $head)) {
                return "{$label} {$sapaan}";
            }
        }
        if (preg_match('/^(halo|hai)\b/i', $head)) {
            return "Halo {$sapaan}";
        }

        return "Halo {$sapaan}";
    }

    /**
     * Kirim balasan salam/sapaan dulu jika pesan mengandung sapaan + intent lain.
     * Dipanggil sebelum handler lain (STATUS, dll) agar intent dijalankan satu per satu: PEMBUKA dulu, baru handler lain.
     * Di luar jam operasional: jangan balas sapaan (biar hanya pesan tutup/jam operasional).
     * @return bool True jika sudah mengirim greeting
     */
    private function sendGreetingReplyFirst($waNumber, $textBody)
    {
        if (!$this->isOperatingHours()) {
            return false;
        }
        if ($this->pembukaShouldSkipGreeting($waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'greeting_first_skip_recent_outbound');
            return false;
        }
        $textLower = strtolower(trim($textBody ?? ''));
        if (mb_strlen($textLower) < 10) {
            return false;
        }
        $hasGreeting = $this->hasGreetingInMessage($textBody);
        $hasOtherIntent = preg_match('/siap|sudah|dah|udah|udh|bisa|jemput|antar|berapa|brp|harga|transfer|bayar|cek|status|laundry|tagihan|kirim|nota|bon|struk|tutup|buka|jam/i', $textLower);
        if (!$hasGreeting || !$hasOtherIntent) {
            return false;
        }
        $sapaan = $this->getSapaanForGreeting($waNumber);
        $reply = $this->getMirrorTimeGreetingReplyLine($textBody, $sapaan);
        $res = $this->sendAutoreplyText($waNumber, $reply);
        // Fonnte (provider B): dua API beruntun kadang sampai terbalik di UI — jeda singkat sebelum nota/handler berikutnya
        if ($this->autoReplyProvider === 'B' && ($res['success'] ?? false)) {
            usleep(450000);
        }
        return true;
    }

    /**
     * Process inbound message text and perform actions
     * 
     * @param string $phoneIn CSV string of phone numbers properly quoted for SQL IN clause
     * @param string $textBody The text body of the message
     * @param string $waNumber The sender's WhatsApp number (e.g. +62...)
     * @return object { ai: bool, priority: int }
     */
    public function process($phoneIn, $textBody, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $lastMessage = null, $cust_id = null)
    {
        if ($contactName !== null) {
            $contactName = trim((string) $contactName);
        }
        $this->currentContactName = $contactName;
        $this->humanActiveCache = null;
        $this->pembukaSkippedGreeting = false;
        if (!$this->intentLabMode) {
            $ctx = $this->ensureSenderContext((string) $waNumber);
            $ctxName = trim((string) ($ctx['contact_name'] ?? ''));
            if (!empty($ctx['is_karyawan']) && $ctxName !== '') {
                $contactName = $ctxName;
                $this->currentContactName = $contactName;
            } elseif ($contactName === null || $contactName === '') {
                $contactName = $ctxName !== '' ? $ctxName : $contactName;
                $this->currentContactName = $contactName;
            }
            if (!empty($ctx['is_karyawan'])) {
                $assigned_user_id = null;
            } elseif ($assigned_user_id === null || $assigned_user_id === '') {
                $assigned_user_id = \App\Helpers\CRM\WaSenderContext::cswAssignedUserId($ctx);
            }
            if ($code === null || $code === '') {
                $code = $ctx['code'] ?? $code;
            }
            if ($cust_id === null || $cust_id === '') {
                $cust_id = $ctx['cust_id'] ?? $cust_id;
            }
            $nomor = (string) ($ctx['nomor'] ?? '');
            if ($nomor !== '' && class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
                $phoneIn = \App\Helpers\CRM\WaSenderContext::phoneInClause($nomor);
            }
            $this->logAutoreplyTrace(
                $waNumber,
                'SENDER',
                'nomor=' . $nomor
                . ' admin=' . (!empty($ctx['is_admin']) ? '1' : '0')
                . ' karyawan=' . (!empty($ctx['is_karyawan']) ? '1' : '0')
                . ' pelanggan=' . (!empty($ctx['is_pelanggan']) ? '1' : '0')
                . ' id_pelanggan=' . (int) ($ctx['id_pelanggan'] ?? 0)
                . ' ids=' . implode(',', $ctx['ids_pelanggan'] ?? [])
            );
        }
        // Strip WhatsApp formatters: * (bold), _ (italic), ~ (strikethrough), ` (monospace)
        $textBodyToCheck = preg_replace('/[*_~`]/', '', $textBody ?? '');
        // Strip quote prefix (> at start of line)
        $textBodyToCheck = preg_replace('/^>\s*/m', '', $textBodyToCheck);
        $textBodyToCheck = strtolower(trim($textBodyToCheck));

        $db = DB::getInstance(0);

        // Pending klarifikasi typo (YCloud & Fonnte): "ya" → pakai teks yang disarankan AI
        $pendingRewrite = $this->consumePendingClarifyIfAny($waNumber, $textBodyToCheck);
        if ($pendingRewrite === '__abort__') {
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
            );
            return (object) [
                'case' => null,
                'notify' => false,
                'conversation_id' => $conversationId,
            ];
        }
        if (is_string($pendingRewrite) && $pendingRewrite !== '') {
            $textBody = $pendingRewrite;
            $textBodyToCheck = preg_replace('/[*_~`]/', '', $textBody);
            $textBodyToCheck = preg_replace('/^>\s*/m', '', $textBodyToCheck);
            $textBodyToCheck = strtolower(trim($textBodyToCheck));
            $this->logAutoreplyTrace($waNumber, 'CLARIFY', 'accepted_rewrite=' . mb_substr($pendingRewrite, 0, 120));
        }

        $messageLength = mb_strlen($textBodyToCheck);
        $preview = mb_substr(preg_replace('/\s+/', ' ', (string) ($textBody ?? '')), 0, 120);
        $this->logAutoreplyTrace($waNumber, 'START', 'len=' . $messageLength . ' preview=' . $preview);
        $this->logAutoreplyTrace($waNumber, 'CHECKPOINT', 'db_ok');

        // Partner (wa_conversations.partner = 1): channel mitra — tanpa deteksi intent & tanpa autoreply (yCloud + Fonnte)
        if (!$this->intentLabMode && $this->isWaPartnerChannel($db, $waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'partner_skip_intent_autoreply');
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
            );

            return (object) [
                'case' => null,
                'notify' => true,
                'conversation_id' => $conversationId,
            ];
        }

        // Nominal-only (contoh: "175.000 kak") -> jangan dianggap intent apa pun.
        if ($this->messageLooksLikeAmountOnly($textBodyToCheck)) {
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'amount_only_no_intent');
            $this->intentLabMark('NONE', 'amount');
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
            );
            return (object) [
                'case' => null,
                'notify' => false,
                'conversation_id' => $conversationId
            ];
        }

        // Load keyword configuration (DB + cache)
        $keywordConfig = $this->loadAutoreplyKeywordConfig();
        $this->autoreplyKeywordConfig = $keywordConfig;
        $this->logAutoreplyTrace($waNumber, 'CHECKPOINT', 'AutoReplyKeywords loaded');
        
        // Simpan config lengkap untuk akses case dan notify nanti
        $fullKeywordConfig = $keywordConfig;
        $keywordConfig = $this->filterKeywordConfigBySenderGate($keywordConfig);
        $this->logAutoreplyTrace(
            $waNumber,
            'GATE_FILTER',
            'intents=' . count($keywordConfig) . '/' . count($fullKeywordConfig)
        );
        $keywordConfig = $this->filterKeywordConfigByChatMaxlength($keywordConfig, $messageLength, $textBody);
        $this->logAutoreplyTrace(
            $waNumber,
            'MAXLENGTH_FILTER',
            'intents=' . count($keywordConfig) . '/' . count($fullKeywordConfig) . ' len=' . $messageLength
        );

        // Permintaan/instruksi: jangan auto-reply, biarkan CS manusia yang merespon
        $permintaanPatterns = [
            '/\b(bisa|tolong|minta)\s*(sy|saya|aku)\s*(ambil|jemput)\b/i',
            '/\b(sy|saya|aku)\s*(ambil|jemput)\b/i',
            '/\bletak\s*(aj|aja)?\s*(di|di\s*kursi|dikursi)\b/i',
            '/\bletakkan\s*(di|di\s*kursi)\b/i',
            '/\btaruh\s*(aj|aja)?\s*(di)\b/i',
            '/\bsimpan\s*(di|di\s*kursi)\b/i',
            '/\b(tolong|minta)\s*letak/i',
        ];
        foreach ($permintaanPatterns as $pp) {
            if (preg_match($pp, $textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'permintaan_pickup_place_manual_cs');
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => true,
                    'conversation_id' => $conversationId
                ];
            }
        }

        // Komplain/keluhan: jangan auto-reply, biarkan CS manusia yang merespon
        $complaintPatterns = [
            '/\bsalah\s*hitung\b/i',
            '/\bkomplain\b/i',
            '/\bkeluhan\b/i',
            '/\bsalah\s*(tagihan|total|jumlah|biaya)\b/i',
            '/\bkurang\s*(bayar|transfer|dibayar|dikirim)\b/i',
            '/\bkelebihan\s*(bayar|charge|dibayar)\b/i',
            '/\bsalah\s*(nomor|no\.?)\b/i',
            '/\bada\s*salah\b/i',
        ];
        foreach ($complaintPatterns as $cp) {
            if (preg_match($cp, $textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'complaint_manual_cs');
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => true,
                    'conversation_id' => $conversationId
                ];
            }
        }

        // Intent Lab = klasifikasi teks saja; jangan pakai session nomor lab (bikin source kosong / menyesatkan)
        if (!$this->intentLabMode) {
        // Session PERMINTAAN aktif: standby rangkum AI (tanpa autoreply), case 3
        if ($this->getPermintaanSession($waNumber) !== null
            && !$this->messageBreaksPermintaanSession($textBodyToCheck, $fullKeywordConfig)
            && $this->intentAllowedForMessageLength('PERMINTAAN', $fullKeywordConfig, $messageLength)
        ) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'permintaan_session_followup→PERMINTAAN case=3');
            $this->currentHandler = 'PERMINTAAN';
            $this->handlePermintaan($phoneIn, $waNumber, $textBody);
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 3
            );
            $this->logAutoreplyTrace($waNumber, 'DONE', 'permintaan_session_followup_ok');
            return (object) [
                'case' => 3,
                'notify' => true,
                'conversation_id' => $conversationId,
            ];
        }


        // Session HARGA aktif: follow-up parameter (durasi, paket, layanan, dll.)
        $hargaSessionEarly = $this->getHargaSession($waNumber);
        if ($hargaSessionEarly !== null) {
            if (!$this->messageBreaksHargaSession($textBodyToCheck, $fullKeywordConfig)
                && $this->intentAllowedForMessageLength('HARGA', $fullKeywordConfig, $messageLength)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'harga_session_followup→HARGA');
                $this->currentHandler = 'HARGA';
                $this->handleHarga($phoneIn, $waNumber, $textBody);
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                    $fullKeywordConfig['HARGA']['case'] ?? null
                );
                $this->logAutoreplyTrace($waNumber, 'DONE', 'harga_session_followup_ok');
                return (object) [
                    'case' => $fullKeywordConfig['HARGA']['case'] ?? null,
                    'notify' => (bool) ($fullKeywordConfig['HARGA']['notify'] ?? false),
                    'conversation_id' => $conversationId,
                ];
            }
            $this->clearHargaSession($waNumber);
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'harga_session_cleared→other_intent');
        }

        // Session LOKASI aktif: lengkapi alamat (kecuali intent jelas lain / jemput-antar)
        try {
            if ($this->getLokasiSession($waNumber) !== null
                && !$this->messageLooksLikeKurir($textBodyToCheck, $fullKeywordConfig)
                && !$this->messageBreaksLokasiSession($textBodyToCheck, $fullKeywordConfig)
                && $this->intentAllowedForMessageLength('LOKASI', $fullKeywordConfig, $messageLength)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'lokasi_session_followup→LOKASI');
                $this->currentHandler = 'LOKASI';
                $lokasiOk = $this->handleLokasi($phoneIn, $waNumber, $textBody);
                if ($lokasiOk !== false) {
                    $conversationId = $this->getOrCreateConversationWithCase(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                    );
                    $this->logAutoreplyTrace($waNumber, 'DONE', 'lokasi_session_followup_ok');
                    return (object) [
                        'case' => null,
                        'notify' => false,
                        'conversation_id' => $conversationId,
                    ];
                }
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'lokasi_session_unrelated→continue_routing');
            } elseif ($this->getLokasiSession($waNumber) !== null
                && $this->messageBreaksLokasiSession($textBodyToCheck, $fullKeywordConfig)
            ) {
                // Topik pindah (bill/status/harga/…) → lepaskan session lokasi agar tidak nyangkut tanya shareloc/detail lagi
                $this->clearLokasiSession($waNumber);
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'lokasi_session_cleared→other_intent');
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('LOKASI session followup: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        // Session KURIR aktif: follow-up KURIR
        if ($this->getKurirSession($waNumber) !== null) {
            $kurirSessEarly = $this->getKurirSession($waNumber);
            // Kurir menunggu LOKASI selesai → serahkan ke lokasi bila session ada
            try {
                if ($kurirSessEarly && (string) ($kurirSessEarly['step'] ?? '') === 'wait_lokasi') {
                    if ($this->getLokasiSession($waNumber) !== null
                        && !$this->messageLooksLikeKurir($textBodyToCheck, $fullKeywordConfig)
                        && !$this->messageBreaksLokasiSession($textBodyToCheck, $fullKeywordConfig)
                        && $this->intentAllowedForMessageLength('LOKASI', $fullKeywordConfig, $messageLength)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_wait_lokasi→LOKASI');
                        $this->currentHandler = 'LOKASI';
                        if ($this->handleLokasi($phoneIn, $waNumber, $textBody) !== false) {
                            $conversationId = $this->getOrCreateConversationWithCase(
                                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                            );
                            return (object) [
                                'case' => null,
                                'notify' => false,
                                'conversation_id' => $conversationId,
                            ];
                        }
                    }
                }
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write('kurir wait_lokasi→LOKASI: ' . $e->getMessage(), 'wa_error', 'Autoreply');
                }
            }
            $hasActiveSale = $this->pelangganHasActiveSale($phoneIn, $waNumber);
            $estimasiCtx = $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                || $this->parseEstimasiRequestedRelativeDay($textBodyToCheck) !== null;
            if ($estimasiCtx && $hasActiveSale) {
                $this->clearKurirSession($waNumber);
                $this->clearLokasiSession($waNumber);
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_cleared→estimasi_context has_sale');
                // fall through ke routing PERMINTAAN
            } elseif ($this->messageBreaksKurirSession($textBodyToCheck, $fullKeywordConfig, $hasActiveSale)) {
                // Topik pindah (status/bill/harga/ingat/…) → clear agar tidak tiba-tiba tanya shareloc lagi
                $this->clearKurirSession($waNumber);
                $this->clearLokasiSession($waNumber);
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_cleared→other_intent');
            } elseif ($this->intentAllowedForMessageLength('KURIR', $fullKeywordConfig, $messageLength)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_followup→KURIR case=2');
                $this->currentHandler = 'KURIR';
                // Bypass cooldown for active session
                $kurirConsumed = $this->handleKurir($phoneIn, $waNumber, $textBody);
                if ($kurirConsumed !== false) {
                    $conversationId = $this->getOrCreateConversationWithCase(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 2
                    );
                    $this->logAutoreplyTrace($waNumber, 'DONE', 'kurir_session_followup_ok');

                    $kurirNotify = (bool) ($fullKeywordConfig['KURIR']['notify'] ?? false);
                    return (object) [
                        'case' => 2,
                        'notify' => $kurirNotify,
                        'conversation_id' => $conversationId,
                    ];
                }
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_unrelated→continue_routing');
                // fall through ke regex/AI intent lain
            } else {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_skip→exceeds_chat_maxlength');
            }
        }
        } // end !$intentLabMode session followups

        // "masih/msh/msih bisa/bs terima kain?" atau "masih nerima ga klo gosok aj?" -> konfirmasi ke petugas + jam operasional (PRIORITAS)
        // BEDA dengan "masih buka?" yang jawab "masih buka kak/bang"
        $masihBisaTerimaPattern = '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis|b\s*s)(?:\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?)?\s*\??\s*$/i';
        $masihTerimaGosokPattern = '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i';
        if (preg_match($masihBisaTerimaPattern, $textBodyToCheck) || preg_match($masihTerimaGosokPattern, $textBodyToCheck)) {
            if (!$this->intentAllowedForMessageLength('JAM_OPERASIONAL', $fullKeywordConfig, $messageLength)) {
                $this->logAutoreplyTrace($waNumber, 'SKIP', 'masih_bisa_terima→JAM_OPERASIONAL exceeds_chat_maxlength');
            } else {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'masih_bisa_terima_kain→JAM_OPERASIONAL+notify');
            if ($this->shouldHandle($waNumber, 'JAM_OPERASIONAL')) {
                $this->currentHandler = 'JAM_OPERASIONAL';
                $this->handleJam_operasional($phoneIn, $waNumber, $textBody ?? '', true);
            }
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                $fullKeywordConfig['JAM_OPERASIONAL']['case'] ?? null
            );
            return (object) [
                'case' => $fullKeywordConfig['JAM_OPERASIONAL']['case'] ?? null,
                'notify' => $fullKeywordConfig['JAM_OPERASIONAL']['notify'] ?? false,
                'conversation_id' => $conversationId
            ];
            }
        }
        
        $matchPattern = [];
        // Check each handler's patterns
        foreach ($keywordConfig as $handler => $config) {
            $patterns = $config['patterns'] ?? [];
            // Check regex patterns
            foreach ($patterns as $patternIndex => $pattern) {
                if (preg_match($pattern, $textBodyToCheck)) {
                    $regexSourceHandler = $handler;
                    // REKENING pattern match tapi pesan konfirmasi pembayaran (sudah transfer) = PENUTUP, skip REKENING
                    if ($handler === 'REKENING' && $this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)) {
                        continue;
                    }
                    if ($handler !== 'PENUTUP' && $this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)
                        && $this->intentAllowedForMessageLength('PENUTUP', $fullKeywordConfig, $messageLength, $textBody)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', $handler . '→PENUTUP payment_confirm');
                        $handler = 'PENUTUP';
                    }
                    if ($handler !== 'PENUTUP'
                        && $this->messageLooksLikeThanksPenutup($textBodyToCheck)
                        && !$this->messageLooksLikeQuestion($textBody)
                        && $this->intentAllowedForMessageLength('PENUTUP', $fullKeywordConfig, $messageLength, $textBody)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', $handler . '→PENUTUP thanks');
                        $handler = 'PENUTUP';
                    }
                    // "cek qris ..." = intent admin CEK_QRIS, bukan minta rekening/QRIS pelanggan
                    if ($handler === 'REKENING' && preg_match('/^\s*cek\s+qris\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // "tarik saldo ..." = TARIK_TOKOPAY, bukan cek SALDO
                    if ($handler === 'SALDO' && preg_match('/\btarik\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // HARGA laundry: bukan harga parfum/plastik/pewangi/dll (nanti intent terpisah)
                    if ($handler === 'HARGA' && $this->messageIsHargaBarangTambahan($textBodyToCheck)) {
                        continue;
                    }
                    // Pertanyaan (termasuk tanpa tanda ?) TIDAK boleh masuk PEMBUKA atau PENUTUP
                    if (($handler === 'PEMBUKA' || $handler === 'PENUTUP') && $this->messageLooksLikeQuestion($textBody)) {
                        continue;
                    }
                    // waalaikumsalam = balasan salam, bukan PEMBUKA (beda dari assalamualaikum)
                    if ($handler === 'PEMBUKA' && $this->messageIsWalaikumsalamReply($textBodyToCheck)) {
                        continue;
                    }
                    // KURIR: "saya/aku ambil" / "kami aja yang antar" = info, bukan minta kurir
                    if ($handler === 'KURIR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'KURIR→customer_self_antar_jemput');
                        continue;
                    }
                    // LOKASI tapi jelas minta jemput/antar → KURIR
                    if ($handler === 'LOKASI' && $this->messageLooksLikeKurir($textBodyToCheck, $fullKeywordConfig)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'LOKASI→KURIR');
                        $handler = 'KURIR';
                    }
                    // KURIR: udh/sdh/sudah + bisa dijemput/diambil = tanya STATUS order, bukan minta kurir
                    if ($handler === 'KURIR' && preg_match('/\b(udah|sudah|udh|sdh|dah|dh)\s+bisa\s*(di\s*)?(jemput|ambil)\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // STATUS: tanya kapan/jam berapa siap = PERMINTAAN (estimasi selesai)
                    if ($handler === 'STATUS'
                        && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'STATUS→PERMINTAAN perkiraan');
                        $handler = 'PERMINTAAN';
                        $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                    }
                    // Ambigu "bisa jemput … sore/pagi ini": ada order aktif → PERMINTAAN; tidak ada → tetap MINTA kurir
                    if ($handler === 'KURIR' && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)) {
                        if ($this->pelangganHasActiveSale($phoneIn, $waNumber)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'KURIR→PERMINTAAN has_sale');
                            $handler = 'PERMINTAAN';
                            $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                        } else {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_KEEP', 'KURIR keep (no active sale)');
                        }
                    }
                    // "diantar kembali selambatnya hari minggu" tanpa sale = PERMINTAAN, bukan jam kurir
                    if ($handler === 'KURIR' && $this->messageLooksLikeAntarKembaliDeadline($textBodyToCheck)
                        && !$this->pelangganHasActiveSale($phoneIn, $waNumber)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'KURIR→PERMINTAAN antar_kembali_deadline');
                        $handler = 'PERMINTAAN';
                        $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                    }
                    // KURIR: tanya harga paket/member + antar/jemput = HARGA (bukan minta kurir)
                    if ($handler === 'KURIR' && $this->messageIsHargaDeliveryQuestion($textBodyToCheck)) {
                        continue;
                    }
                    // Legacy intent HARGA_PAKET(_D) → unified HARGA
                    if (in_array($handler, ['HARGA_PAKET', 'HARGA_PAKET_D'], true)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', $handler . '→HARGA unified');
                        $handler = 'HARGA';
                    }
                    // KURIR: "kalau/klo ... antar/jemput" = hipotetis, bukan minta kurir
                    if ($handler === 'KURIR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'KURIR→kalau_hypothetical');
                        continue;
                    }
                    // KURIR: ongkos + durasi hari / tipe layanan = HARGA (regex HARGA dicek lebih dulu; ini cadangan)
                    if ($handler === 'KURIR' && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBodyToCheck)) {
                        continue;
                    }
                    // KURIR: pertanyaan ongkir/ongkos saja = bukan minta kurir
                    if ($handler === 'KURIR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'KURIR→ongkir_inquiry_only');
                        continue;
                    }
                    // KURIR: "masih bisa/bs jemput" = tanya availabilitas layanan = JAM_OPERASIONAL (regex MINTA lewat sub-bisa jemput)
                    if ($handler === 'KURIR' && preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // KURIR: minta satu pakaian/item diambil/dulukan dulu dari order = PERMINTAAN, bukan KURIR ke alamat
                    if ($handler === 'KURIR' && $this->messageIsPermintaanAmbilPakaianDulu($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'KURIR→PERMINTAAN ambil_pakaian_dulu');
                        $handler = 'PERMINTAAN';
                        $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                    }
                    // KURIR: jenis jemput + order aktif (tuntas=0,bin=0,id_user_ambil=0) = abaikan
                    if ($handler === 'KURIR'
                        && $this->kurirJemputBlockedByActiveSale($phoneIn, $waNumber, $textBodyToCheck)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'KURIR→jemput_has_antarable_sale');
                        continue;
                    }
                    // PENUTUP yang bukan closing ketat (info/otw/item/jadwal/janji bayar) → biarkan CS
                    if ($handler === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'PENUTUP→non_strict');
                        continue;
                    }
                    // "jam berapa bisa jemput/diambil?" / "bisa jemput sore ini":
                    // ada order → PERMINTAAN (estimasi selesai); tanpa order → kurir MINTA
                    if ($handler === 'JAM_OPERASIONAL' && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)) {
                        if ($this->pelangganHasActiveSale($phoneIn, $waNumber)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'JAM_OPERASIONAL→PERMINTAAN has_sale');
                            $handler = 'PERMINTAAN';
                            $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                        } elseif (!$this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'JAM_OPERASIONAL→KURIR no_sale');
                            $handler = 'KURIR';
                        }
                    }
                    // "bs jmpt?" tanpa estimasi = KURIR (minta jemput), bukan JAM_OPERASIONAL
                    if ($handler === 'JAM_OPERASIONAL' && preg_match('/\b(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)\b/i', $textBodyToCheck) && !preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)/i', $textBodyToCheck)) {
                        continue;
                    }
                    // Get case from config (pakai handler final — bisa sudah di-remap ke PERMINTAAN)
                    $caseVal = $fullKeywordConfig[$handler]['case'] ?? ($config['case'] ?? null);
                    $notify = $fullKeywordConfig[$handler]['notify'] ?? ($config['notify'] ?? false);
                    if ($handler === 'PERMINTAAN') {
                        if ($caseVal === null || (int) $caseVal === 0) {
                            $caseVal = 3;
                        }
                        $notify = true;
                    }
                    $matchPattern[] = $handler;

                    // Legacy intent label dari DB lama → PERMINTAAN
                    $handler = $this->applyPermintaanHandlerMeta($handler, $fullKeywordConfig, $caseVal, $notify, $config);

                    // Pesan melebihi chat_maxlength intent (termasuk hasil remap) → skip
                    if (!$this->intentAllowedForMessageLength($handler, $fullKeywordConfig, $messageLength, $textBody)) {
                        $this->logAutoreplyTrace(
                            $waNumber,
                            'REGEX_SKIP',
                            $handler . '→exceeds_chat_maxlength max=' . (int) (($fullKeywordConfig[$handler]['chat_maxlength'] ?? 0))
                        );
                        continue;
                    }

                    if ($this->isHumanAgentRecentlyActive($waNumber)
                        && !$this->isIntentAllowedDuringHumanActive($handler)) {
                        return $this->silentExitHumanActive(
                            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                            'regex handler=' . $handler
                        );
                    }

                    $gateCfg = $fullKeywordConfig[$handler] ?? $config;
                    $gateResult = $this->gateDenyOrContinue(
                        $handler,
                        $gateCfg,
                        $db,
                        $waNumber,
                        $contactName,
                        $assigned_user_id,
                        $code,
                        $cust_id,
                        $lastMessage
                    );
                    if ($gateResult === false) {
                        continue;
                    }
                    if (is_object($gateResult)) {
                        return $gateResult;
                    }

                    if (class_exists('\Log')) {
                        \Log::write(mb_substr($textBody ?? '', 0, 100) . " | {$handler} | regex", 'wa', 'intent');
                    }
                    $this->logAutoreplyTrace(
                        $waNumber,
                        'REGEX_MATCH',
                        'handler=' . $handler
                        . ($regexSourceHandler !== $handler ? ' regex_source=' . $regexSourceHandler : '')
                    );
                    
                    // Unset matched keyword from config to optimize AI detection
                    // AI tidak perlu cek keyword yang sudah match di regex
                    unset($keywordConfig[$handler]);
                    
                    // Rate limit: cek saja; catat log setelah handler sukses dijalankan
                    // Case CRM (simbol kuning case 2, dll.) tetap dibentuk meski autoreply di-skip
                    if (!$this->handlerSkipsAutoreplyRateLimit($handler)
                        && !($handler === 'PENUTUP' && $this->penutupShouldAutoreplyThanksOrPayment($textBody))
                        && $this->isInAutoreplyCooldown($waNumber, $handler)) {
                        $this->logAutoreplyTrace($waNumber, 'EXIT', 'regex_rate_limit handler=' . $handler);
                        $conversationId = $this->getOrCreateConversationWithCase(
                            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, $caseVal
                        );
                        
                        return (object) [
                            'case' => $caseVal,
                            'notify' => (bool) ($notify || ($caseVal !== null && (int) $caseVal !== 0)),
                            'conversation_id' => $conversationId
                        ];
                    }
                    
                    //pass rate limit check
                    $conversationId = $this->getOrCreateConversationWithCase(
                        $db, 
                        $waNumber, 
                        $contactName, 
                        $assigned_user_id, 
                        $code, 
                        $cust_id,
                        $lastMessage,
                        $caseVal
                    );

                    // Dynamically call handler method (will send auto-reply)
                    $handlerName = ucwords(strtolower($handler), '_');
                    $methodName = 'handle' . $handlerName;

                    if (method_exists($this, $methodName)) {
                        $this->currentHandler = $handler;
                        $this->intentLabMark($handler, 'regex');
                        // Kalimat PENUTUP ambigu (closed order, dll): tetap intent PENUTUP tapi jangan dibalas AI
                        if ($handler === 'PENUTUP' && $this->isAmbiguousPenutupShortPhrase($textBodyToCheck)) {
                            $this->logAutoreplyTrace($waNumber, 'EXIT', 'regex_penutup_ambiguous_no_reply');
                            return (object) [
                                'case' => $caseVal,
                                'notify' => $notify,
                                'conversation_id' => $conversationId
                            ];
                        }
                        // Jika handler BUKAN PEMBUKA dan pesan ada sapaan+intent lain: kirim sapaan dulu, baru handler (satu per satu)
                        // PERMINTAAN: standby tanpa autoreply (termasuk sapaan)
                        if ($handler !== 'PEMBUKA' && $handler !== 'PERMINTAAN') {
                            $this->sendGreetingReplyFirst($waNumber, $textBody);
                        }
                        $this->logAutoreplyTrace($waNumber, 'HANDLER_RUN', 'regex method=' . $methodName);
                        $this->$methodName($phoneIn, $waNumber, $textBody);
                        if (!$this->handlerSkipsAutoreplyRateLimit($handler)
                            && !$this->penutupLainnyaSkipsCooldown($handler, $textBody)
                            && !$this->pembukaSkippedGreetingCooldown($handler)) {
                            $this->recordHandlerCooldown($waNumber, $handler);
                        }
                        $this->logAutoreplyTrace($waNumber, 'DONE', 'regex_ok handler=' . $handler);
                        return (object) [
                            'case' => $caseVal,
                            'notify' => $notify,
                            'conversation_id' => $conversationId
                        ];
                    }
                    // Handler match tapi method tidak ada: create conversation, return, biarkan CS / DEFAULT fallback
                    $this->logAutoreplyTrace($waNumber, 'EXIT', 'regex_no_php_method handler=' . $handler);
                    $this->intentLabMark($handler, 'regex');
                    return (object) [
                        'case' => $caseVal,
                        'notify' => $notify,
                        'conversation_id' => $conversationId,
                        'no_handler' => true
                    ];
                }
            }
        }

        // Short message (likely not a real query) - still create conversation!
        // Kecuali ucapan terima kasih pendek (makasi, thanks, thx) → tetap PENUTUP
        if ($messageLength >= 0 && $messageLength <= 7
            && !$this->messageLooksLikeThanksPenutup($textBodyToCheck)
            && !$this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)
        ) {
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'short_message_no_regex len=' . $messageLength . ' (no AI)');
            $this->intentLabMark('NONE', 'short');
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
            );
            
            return (object) [
                'case' => null,
                'notify' => false,
                'conversation_id' => $conversationId
            ];
        }

        $this->logAutoreplyTrace($waNumber, 'AI_PATH', 'no_regex_match len=' . $messageLength);
        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig);

        if ($this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)
            && $this->intentAllowedForMessageLength('PENUTUP', $fullKeywordConfig, $messageLength, $textBody)
        ) {
                if (!is_array($aiResult)) {
                    $aiResult = [];
                }
                $prevAi = strtoupper((string) ($aiResult['intent'] ?? 'FALSE'));
                if ($prevAi !== 'PENUTUP') {
                    $this->logAutoreplyTrace($waNumber, 'BRANCH', 'force_PENUTUP payment_confirm was=' . $prevAi);
                }
                $aiResult['intent'] = 'PENUTUP';
        } elseif ($this->messageLooksLikeThanksPenutup($textBodyToCheck)
            && !$this->messageLooksLikeQuestion($textBody)
            && $this->intentAllowedForMessageLength('PENUTUP', $fullKeywordConfig, $messageLength, $textBody)
        ) {
                if (!is_array($aiResult)) {
                    $aiResult = [];
                }
                $prevAi = strtoupper((string) ($aiResult['intent'] ?? 'FALSE'));
                if ($prevAi !== 'PENUTUP') {
                    $this->logAutoreplyTrace($waNumber, 'BRANCH', 'force_PENUTUP thanks was=' . $prevAi);
                }
                $aiResult['intent'] = 'PENUTUP';
        }

        // Check if AI successfully detected a valid intent
        if ($aiResult && is_array($aiResult) && isset($aiResult['intent']) && strtoupper($aiResult['intent']) !== 'FALSE') {
            $aiIntent = strtoupper($aiResult['intent']);
            // Pertanyaan (termasuk tanpa tanda ?) TIDAK boleh masuk PEMBUKA atau PENUTUP
            $isQuestion = $this->messageLooksLikeQuestion($textBody);
            if ($isQuestion && in_array($aiIntent, ['PEMBUKA', 'PENUTUP'])) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_question_as_pembuka_penutup intent=' . $aiIntent);
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => false,
                    'conversation_id' => $conversationId
                ];
            }

            // AI salah: waalaikumsalam = balasan salam, bukan PEMBUKA
            if ($aiIntent === 'PEMBUKA' && $this->messageIsWalaikumsalamReply($textBodyToCheck)) {
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_waalaikumsalam'
                    );
                }
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_waalaikumsalam_as_pembuka');
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // Gunakan fullKeywordConfig untuk akses case dan notify (config lengkap)
            $aiCase = $fullKeywordConfig[$aiIntent]['case'] ?? null;
            $aiNotify = $fullKeywordConfig[$aiIntent]['notify'] ?? false;

            // AI salah: tanya harga (termasuk paket + antar/jemput) = HARGA unified, bukan minta kurir
            if ($aiIntent === 'KURIR' && $this->messageIsHargaDeliveryQuestion($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_kurir→HARGA delivery_question');
                $aiIntent = 'HARGA';
                $aiCase = $fullKeywordConfig['HARGA']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA']['notify'] ?? false;
            }

            // Legacy HARGA_PAKET / HARGA_PAKET_D → unified HARGA
            if (in_array($aiIntent, ['HARGA_PAKET', 'HARGA_PAKET_D'], true)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_' . $aiIntent . '→HARGA unified');
                $aiIntent = 'HARGA';
                $aiCase = $fullKeywordConfig['HARGA']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA']['notify'] ?? false;
            }

            // AI salah: "kalau/klo ... antar/jemput" = hipotetis (bukan minta kurir) → biarkan CS (FALSE + ask)
            if ($aiIntent === 'KURIR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_kurir_kalau_hypothetical');
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_reject_kurir_kalau_hypothetical'
                    );
                }
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // AI salah: "kami aja yang antar" / "saya yang jemput" = info (bukan minta kurir)
            if ($aiIntent === 'KURIR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_kurir_customer_self_antar_jemput');
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_reject_kurir_customer_self'
                    );
                }
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // AI salah: ongkos + durasi (hari) / jenis layanan = HARGA, bukan minta kurir
            if ($aiIntent === 'KURIR' && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_kurir→HARGA ongkos_durasi_tier');
                $aiIntent = 'HARGA';
                $aiCase = $fullKeywordConfig['HARGA']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA']['notify'] ?? false;
            }

            // AI salah: pertanyaan ongkir/ongkos antar-jemput saja = FALSE (CS), bukan minta kurir
            if ($aiIntent === 'KURIR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_kurir→FALSE ongkir_inquiry_only');
                $aiIntent = 'FALSE';
                $aiCase = 4;
                $aiNotify = false;
            }

            // AI salah: minta satu pakaian/item diambil/dulukan dulu dari cucian/order = PERMINTAAN, bukan minta kurir jemput/antar
            if ($aiIntent === 'KURIR' && $this->messageIsPermintaanAmbilPakaianDulu($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_kurir→PERMINTAAN ambil_pakaian_dulu');
                $aiIntent = 'PERMINTAAN';
                $aiCase = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
                $aiNotify = $fullKeywordConfig['PERMINTAAN']['notify'] ?? true;
            }

            // Default case/notify PERMINTAAN (CRM merah case 3) jika intent dari AI/config
            if ($aiIntent === 'PERMINTAAN') {
                if ($aiCase === null || (int) $aiCase === 0) {
                    $aiCase = 3;
                }
                $aiNotify = true;
            }

            // STATUS / MINTA / JAM → PERMINTAAN (tanya kapan/jam berapa siap) jika ada order aktif
            if (in_array($aiIntent, ['STATUS', 'KURIR', 'JAM_OPERASIONAL'], true)
                && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                && $this->pelangganHasActiveSale($phoneIn, $waNumber)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_' . $aiIntent . '→PERMINTAAN has_sale');
                $aiIntent = 'PERMINTAAN';
                $aiCase = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
                $aiNotify = true;
            }

            if ($aiIntent === 'KURIR'
                && $this->messageLooksLikeAntarKembaliDeadline($textBodyToCheck)
                && !$this->pelangganHasActiveSale($phoneIn, $waNumber)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_kurir→PERMINTAAN antar_kembali_deadline');
                $aiIntent = 'PERMINTAAN';
                $aiCase = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
                $aiNotify = $fullKeywordConfig['PERMINTAAN']['notify'] ?? true;
            }

            // Legacy label ESTIMASI_SELESAI dari AI → PERMINTAAN
            if ($aiIntent === 'ESTIMASI_SELESAI') {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_ESTIMASI_SELESAI→PERMINTAAN');
                $aiIntent = 'PERMINTAAN';
                $aiCase = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
                $aiNotify = true;
            }

            // JAM_OPERASIONAL AI salah: "bs jmpt baju?" / "bisa jemput?" tanpa "masih" = KURIR (bukan tanya jam buka)
            // Kecuali "kalau/klo ... jemput/antar" (hipotetis)
            if ($aiIntent === 'JAM_OPERASIONAL' && preg_match('/\b(bisa|bs|bis|boleh)\s*(jmpt|jemput|antar)\b/i', $textBodyToCheck)
                && !preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)/i', $textBodyToCheck)
                && !$this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)
                && !(
                    $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                    && $this->pelangganHasActiveSale($phoneIn, $waNumber)
                )) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_jam_operasional→KURIR bs_jmput_tanpa_masih');
                $aiIntent = 'KURIR';
                $aiCase = $fullKeywordConfig['KURIR']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['KURIR']['notify'] ?? false;
            }

            if ($aiIntent !== 'FALSE'
                && !$this->intentAllowedForMessageLength($aiIntent, $fullKeywordConfig, $messageLength, $textBody)
            ) {
                $this->logAutoreplyTrace(
                    $waNumber,
                    'EXIT',
                    'ai_reject_exceeds_chat_maxlength intent=' . $aiIntent
                        . ' max=' . (int) (($fullKeywordConfig[$aiIntent]['chat_maxlength'] ?? 0))
                );
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => false,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // Human agent aktif: setelah remap, hanya intent data/self-service yang boleh balas
            if ($this->isHumanAgentRecentlyActive($waNumber)
                && !$this->isIntentAllowedDuringHumanActive($aiIntent)) {
                return $this->silentExitHumanActive(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                    'ai intent=' . $aiIntent
                );
            }

            // "kabari ya kak" / "infokan ya" = minta CS update, bukan sapaan
            if ($aiIntent === 'PEMBUKA'
                && (preg_match('/\b(kabari|kabarin)\s+(ya|dong)\b/iu', $textBodyToCheck) || preg_match('/\binfokan\s+(ya|dong)\b/iu', $textBodyToCheck))) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_kabari_ya intent=' . $aiIntent);
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // Note: Tidak perlu cek in_array($aiIntent, $matchPattern) lagi
            // karena keyword yang sudah match di regex sudah di-unset dari $keywordConfig
            // Jadi jika AI detect intent, berarti intent tersebut belum match di regex

            // Rate limit check for AI intent
            // ========================================
            if (!$this->handlerSkipsAutoreplyRateLimit($aiIntent)
                && !($aiIntent === 'PENUTUP' && $this->penutupShouldAutoreplyThanksOrPayment($textBody))
                && $this->isInAutoreplyCooldown($waNumber, $aiIntent)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_rate_limit intent=' . $aiIntent);
                // Rate limited - tetap tulis case ke CRM (jangan null), skip auto-reply saja
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, $aiCase
                );
                
                return (object) [
                    'case' => $aiCase,
                    'notify' => $aiNotify,
                    'conversation_id' => $conversationId
                ];
            }

            // PENUTUP yang bukan closing ketat → biarkan CS
            if ($aiIntent === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_penutup_non_strict');
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // HARGA laundry: AI salah klasifikasi untuk harga parfum/plastik/dll → biarkan CS (case 4)
            if ($aiIntent === 'HARGA' && $this->messageIsHargaBarangTambahan($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_harga_barang_tambahan');
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // LOKASI AI tapi jelas minta jemput/antar
            if ($aiIntent === 'LOKASI' && $this->messageLooksLikeKurir($textBodyToCheck, $fullKeywordConfig)) {
                $this->logAutoreplyTrace($waNumber, 'AI_REMAP', 'LOKASI→KURIR');
                $aiIntent = 'KURIR';
                $aiCase = $fullKeywordConfig['KURIR']['case'] ?? 2;
                $aiNotify = $fullKeywordConfig['KURIR']['notify'] ?? false;
            }

            // Jenis jemput + order aktif (tuntas=0,bin=0,id_user_ambil=0) = bukan MINTA jemput
            if ($aiIntent === 'KURIR'
                && $this->kurirJemputBlockedByActiveSale($phoneIn, $waNumber, $textBodyToCheck)
            ) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_kurir_has_antarable_sale');
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_reject_kurir_has_antarable_sale'
                    );
                }
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                );
                return (object) [
                    'case' => 4,
                    'notify' => true,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }

            // Rate limit passed - create conversation with AI case
            $gateCfg = $fullKeywordConfig[$aiIntent] ?? [];
            $gateResult = $this->gateDenyOrContinue(
                $aiIntent,
                $gateCfg,
                $db,
                $waNumber,
                $contactName,
                $assigned_user_id,
                $code,
                $cust_id,
                $lastMessage
            );
            if ($gateResult === false) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_gate_silent intent=' . $aiIntent);
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => false,
                    'conversation_id' => $conversationId,
                    'no_handler' => true,
                ];
            }
            if (is_object($gateResult)) {
                return $gateResult;
            }

            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, $aiCase
            );

            // Call handler method
            $handlerName = ucwords(strtolower($aiIntent), '_');
            $methodName = 'handle' . $handlerName;
            $methodExists = method_exists($this, $methodName);
            if ($methodExists) {
                $this->currentHandler = $aiIntent;
                $this->intentLabMark($aiIntent, 'ai');
                // Kalimat PENUTUP ambigu (closed order, dll): tetap intent PENUTUP tapi jangan dibalas AI
                if ($aiIntent === 'PENUTUP' && $this->isAmbiguousPenutupShortPhrase($textBodyToCheck)) {
                    $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_penutup_ambiguous_no_reply');
                    return (object) [
                        'case' => $aiCase,
                        'notify' => $aiNotify,
                        'conversation_id' => $conversationId
                    ];
                }
                // Jika handler BUKAN PEMBUKA dan pesan ada sapaan+intent lain: kirim sapaan dulu, baru handler (satu per satu)
                // PERMINTAAN: standby tanpa autoreply (termasuk sapaan)
                if ($aiIntent !== 'PEMBUKA' && $aiIntent !== 'PERMINTAAN') {
                    $this->sendGreetingReplyFirst($waNumber, $textBody);
                }
                $this->logAutoreplyTrace($waNumber, 'HANDLER_RUN', 'ai method=' . $methodName);
                $this->$methodName($phoneIn, $waNumber, $textBody);
                if (!$this->handlerSkipsAutoreplyRateLimit($aiIntent)
                    && !$this->penutupLainnyaSkipsCooldown($aiIntent, $textBody)
                    && !$this->pembukaSkippedGreetingCooldown($aiIntent)) {
                    $this->recordHandlerCooldown($waNumber, $aiIntent);
                }
                $this->logAutoreplyTrace($waNumber, 'DONE', 'ai_ok intent=' . $aiIntent);
            } else {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_no_php_method intent=' . $aiIntent);
                $this->intentLabMark($aiIntent, 'ai');
            }

            return (object) [
                'case' => $aiCase,
                'notify' => $aiNotify,
                'conversation_id' => $conversationId,
                'no_handler' => !$methodExists
            ];
        }

        // Case 4 HANYA jika intent FALSE + ask true (pertanyaan/permintaan). Selain itu null.
        $intentFalse = is_array($aiResult)
            && strtoupper((string) ($aiResult['intent'] ?? '')) === 'FALSE';
        $ask = $intentFalse && !empty($aiResult['ask']);
        $caseVal = $ask ? 4 : null;

        $this->logAutoreplyTrace(
            $waNumber,
            'EXIT',
            $intentFalse
                ? ('ai_false ask=' . ($ask ? '1' : '0') . ' case=' . ($caseVal === null ? 'null' : (string) $caseVal))
                : 'ai_no_valid_intent (AI_SKIP / AI_REJECT / AI_ERROR) case=null'
        );
        $this->intentLabMark('FALSE', $intentFalse ? 'ai_false' : 'ai_none');

        // AI FALSE / tanpa intent — human aktif: diam (jangan case 4 / DEFAULT)
        if ($this->isHumanAgentRecentlyActive($waNumber)) {
            return $this->silentExitHumanActive(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                'ai_no_valid_intent'
            );
        }

        // Soft clarify AI dimatikan — jangan spam "kurang dapat saya pahami"
        // (pesan tanpa intent → biarkan human / fallthrough di bawah)

        $conversationId = $this->getOrCreateConversationWithCase(
            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, $caseVal
        );

        // no_handler true HANYA jika FALSE + ask → webhook boleh DEFAULT fallback ("CS menunggu")
        // FALSE + ask false / AI error → diam (tanpa case 4, tanpa DEFAULT)
        return (object) [
            'case' => $caseVal,
            'notify' => (bool) $ask,
            'conversation_id' => $conversationId,
            'no_handler' => (bool) $ask,
            'ask' => (bool) $ask,
        ];
    }

    /**
     * Hanya untuk handleStatus: kirim nota (notif tipe=1) untuk no_ref yang belum punya notif.
     * Tanpa cabang TAGIHAN / tanpa pesan "belum ada tagihan" / tanpa noReg — jika tidak ada missingRefs, no-op.
     */
    private function trySendMissingNotaNotifsForStatus($phoneIn, $waNumber, $waService, $db1): void
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);
        $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        if (empty($id_pelanggans)) {
            return;
        }
        $ids_in = implode(',', $id_pelanggans);
        $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan ORDER BY insertTime DESC")->result_array();
        $noRefs = array_column($sales, 'no_ref');
        if (empty($noRefs)) {
            return;
        }
        $noRefsIn = "'" . implode("','", $noRefs) . "'";
        $existingRefs = array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 1 AND no_ref IN ($noRefsIn)")->result_array(), 'no_ref');
        $missingRefs = array_diff($noRefs, $existingRefs);
        if (empty($missingRefs)) {
            return;
        }
        $this->sendNotaNotifsForMissingRefs($db1, $waService, $waNumber, $phone0, $sales, $noRefs, $missingRefs, true);
    }

    /**
     * Fetch wa_nota, insert notif tipe=1, kirim WA + update state (sama logika dengan cabang missingRefs di handleNota).
     *
     * @param array $missingRefs no_ref yang belum punya baris notif tipe 1 (non-empty)
     */
    private function sendNotaNotifsForMissingRefs($db1, $waService, $waNumber, $phone0, array $sales, array $noRefs, array $missingRefs, bool $wsDelayOneSecond): void
    {
        $refToPel = [];
        foreach ($sales as $saleRow) {
            if (!empty($saleRow['no_ref'])) {
                $refToPel[(string) $saleRow['no_ref']] = (int) ($saleRow['id_pelanggan'] ?? 0);
            }
        }

        foreach ($missingRefs as $ref) {
            $opts = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                ]
            ];
            $context = stream_context_create($opts);

            $apiUrl = "https://ml.nalju.com/Get/wa_nota/" . urlencode($ref);
            $apiResponse = @file_get_contents($apiUrl, false, $context);

            if ($apiResponse) {
                $responseData = json_decode($apiResponse, true);
                if (!empty($responseData['text'])) {
                    $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
                    $insertData = [
                        'id_notif' => $id_notif,
                        'id_cabang' => $sales[array_search($ref, $noRefs)]['id_cabang'],
                        'tipe' => 1,
                        'no_ref' => $ref,
                        'text' => $responseData['text'],
                        'id_pelanggan' => (int) ($refToPel[(string) $ref] ?? 0),
                        'state' => 'pending',
                    ];

                    $isInserted = $db1->insert('notif', $insertData);

                    if ($isInserted !== false) {
                        $res = $this->sendQuotedFreeText($waNumber, $responseData['text']);

                        $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                        $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null);
                        $wamid = $res['data']['wamid'] ?? null;

                        $updateData = ['state' => $status];
                        if ($msgId) {
                            $updateData['id_api'] = $msgId;
                        }

                        $db1->update('notif', $updateData, ['id_notif' => $id_notif]);

                        if ($res['success']) {
                            $timestamp = $wsDelayOneSecond ? date('Y-m-d H:i:s', strtotime('+1 second')) : null;
                            $payload = $this->buildWsPayload($waNumber, $responseData['text'], $msgId, $wamid, $timestamp);
                            $this->pushToWebSocket($payload);
                        }
                    } else {
                        $conn = $db1->conn();
                        $errorMsg = $conn->error ?? 'No Error Msg';
                        if (empty($errorMsg) && !empty($conn->error_list)) {
                            $errorMsg = json_encode($conn->error_list);
                        }

                        \Log::write("Notif insert FAILED - Error: $errorMsg | Data: " . json_encode($insertData), 'wa_error', 'Notif');
                    }
                }
            }
        }
    }

    private function handleNota($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        // Use DB(1)
        $db1 = DB::getInstance(1);

        // Derive phone from waNumber (+628... or 628...)
        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);
        $limitTime = date('Y-m-d H:i:s', strtotime('-48 hours'));

        $pelangganRows = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        $id_pelanggans = array_values(array_filter(array_map('intval', array_column($pelangganRows, 'id_pelanggan'))));
        if ($id_pelanggans === []) {
            $this->logAutoreplyTrace($waNumber, 'NOTA', 'no_pelanggan_for_pending');
            return;
        }
        $ids_in = implode(',', $id_pelanggans);

        $sql = "SELECT * FROM notif 
                WHERE tipe = 1 AND state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND id_pelanggan IN ($ids_in)
                ORDER BY insertTime ASC";

        $pendingNotifs = $db1->query($sql)->result_array();

        // --- Ada data: notif nota pending — kirim tanpa cek pola tegas ---
        if (!empty($pendingNotifs)) {
            foreach ($pendingNotifs as $notif) {
                $idNotif = $notif['id_notif'];

                // 🔒 LOCK: Update state to 'sending' first to prevent race condition
                $success = $db1->update(
                    'notif',
                    ['state' => 'sending'],
                    ['id_notif' => $idNotif, 'state' => 'pending'] // Only update if still pending
                );

                // If lock failed (already being sent by another process), skip
                if (!$success || $db1->affected_rows() <= 0) {
                    continue;
                }

                // Send message (Free text is allowed now since customer just messaged us)
                $res = $this->sendQuotedFreeText($waNumber, $notif['text']);

                $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null);
                $wamid = $res['data']['wamid'] ?? null;

                $updateData = ['state' => $status];
                if ($msgId) {
                    $updateData['id_api'] = $msgId;
                }

                $updated = $db1->update('notif', $updateData, ['id_notif' => $notif['id_notif']]);
                if (!$updated) {
                    \Log::write("FAILED to update Notif #$idNotif - " . $db1->conn()->error, 'wa_error', 'Notif');
                }

                // Broadcast to WebSocket
                if ($res['success']) {
                    $payload = $this->buildWsPayload($waNumber, $notif['text'], $msgId, $wamid);
                    $this->pushToWebSocket($payload);
                }
            }
            $this->logAutoreplyTrace($waNumber, 'NOTA_SEND', 'pending_notif count=' . count($pendingNotifs));
        } else {
            // Find customer (LIKE '%852…' setelah buang +62/62/0)
            $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan, nama_pelanggan');
            $id_pelanggans = array_column($pelanggan, 'id_pelanggan');

            if (empty($id_pelanggans)) {
                $this->logAutoreplyTrace($waNumber, 'NOTA', 'no_pelanggan');
                return;
            }

            $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan', 'id_pelanggan');
            $primaryId = $this->senderIdPelanggan() ?: (int) ($id_pelanggans[0] ?? 0);
            $nama_pelanggan = strtoupper(trim((string) ($nama_pelanggans[$primaryId] ?? $nama_pelanggans[$id_pelanggans[0] ?? 0] ?? 'PELANGGAN')));

            $ids_in = implode(',', $id_pelanggans);

            // Find unfinished sales
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan ORDER BY insertTime DESC")->result_array();
            $noRefs = array_column($sales, 'no_ref');

            // --- Ada data: sale aktif — kirim nota / tagihan tanpa cek pola tegas ---
            if (!empty($noRefs)) {
                $noRefsIn = "'" . implode("','", $noRefs) . "'";
                $existingRefs = array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 1 AND no_ref IN ($noRefsIn)")->result_array(), 'no_ref');
                $missingRefs = array_diff($noRefs, $existingRefs);

                if (count($missingRefs) > 0) {
                    $this->sendNotaNotifsForMissingRefs($db1, $waService, $waNumber, $phone0, $sales, $noRefs, $missingRefs, false);
                    $this->logAutoreplyTrace($waNumber, 'NOTA_SEND', 'missing_refs count=' . count($missingRefs));
                } else {
                    $this->recordHandlerCooldown($waNumber, 'TAGIHAN');
                    $this->handleTagihan($phoneIn, $waNumber, $textBody);
                }
            } else {
                // --- Tidak ada sale aktif: generik hanya pola tegas ---
                $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, null, 'NOTA');
            }
        }
    }

    /**
     * Handle intent REKENING - balas data rekening pembayaran dan QRIS (customer bisa menyebut "barcode")
     * Sumber: BankAccountGuide (Env::BCA_PAYMENT_ACCOUNTS), sama dengan CRM.
     */
    private function handleRekening($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $rekeningBody = $this->fetchLaundryRekeningMessage();
        $text = "Berikut *Rekening Pembayaran* Madinah Laundry:\n\n" .
            $rekeningBody . "\n\n" .
            "Terima kasih 😊";

        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    /**
     * Ambil teks rekening terformat dari sumber backend terpusat.
     */
    /**
     * @return array{page_url:string,image_url:string}
     */
    private function fetchLaundryQrisMedia(): array
    {
        $qrisUrl = (class_exists('Env') && defined('Env::QRIS_PUBLIC_URL'))
            ? (string) \Env::QRIS_PUBLIC_URL
            : 'https://ml.nalju.com/I/q';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'ml.nalju.com'));
        $imageUrl = $scheme . '://' . $host . '/mdl/laundry/in_assets/img/qris/qris_1.jpeg';

        $payload = BankAccountGuide::publicPayload($qrisUrl, $imageUrl);

        return [
            'page_url' => trim((string) ($payload['qris_url'] ?? $qrisUrl)),
            'image_url' => trim((string) ($payload['qris_image_url'] ?? $imageUrl)),
        ];
    }

    private function fetchLaundryRekeningMessage(): string
    {
        $qrisUrl = (class_exists('Env') && defined('Env::QRIS_PUBLIC_URL'))
            ? (string) \Env::QRIS_PUBLIC_URL
            : 'https://ml.nalju.com/I/q';

        $payload = BankAccountGuide::publicPayload($qrisUrl);
        if (!empty($payload['message']) && is_string($payload['message'])) {
            return trim($payload['message']);
        }

        return "QRIS\nhttps://ml.nalju.com/I/q\n\n"
            . "BCA (BANK CENTRAL ASIA)\n8455103793\n\n"
            . "BRI (BANK RAKYAT INDONESIA)\n327901031534535\n\n"
            . "an. LUHUR GUNAWAN";
    }

    /**
     * Intent KURIR — logic di WARepliesKurirTrait.
     */

    /**
     * id_pelanggan untuk link J/kurir: sale terakhir (insertTime DESC), fallback id terkecil dari WA.
     * @return int|null null jika nomor belum ada di tabel pelanggan
     */
    private function resolveIdPelangganForKurirLink(string $phoneIn, string $waNumber): ?int
    {
        $db1 = DB::getInstance(1);
        $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        if (empty($pelanggan)) {
            return null;
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', array_column($pelanggan, 'id_pelanggan')))));
        if (empty($ids)) {
            return null;
        }

        $primary = $this->senderIdPelanggan();
        if ($primary > 0 && in_array($primary, $ids, true)) {
            return $primary;
        }

        $idsIn = implode(',', $ids);
        $row = $db1->query(
            "SELECT id_pelanggan FROM sale WHERE bin = 0 AND id_pelanggan IN ($idsIn) ORDER BY insertTime DESC LIMIT 1"
        )->row();

        if ($row && !empty($row->id_pelanggan)) {
            return (int) $row->id_pelanggan;
        }

        return $ids[0];
    }

    /**
     * Handle intent PEMBUKA - balas sapaan pembuka dengan AI sebagai customer service laundry.
     * Di luar jam operasional tetap balas sapaan (tanpa pesan "sedang tutup").
     */
    private function handlePembuka($phoneIn, $waNumber, $textBody = '')
    {
        $textLower = strtolower(trim($textBody ?? ''));
        $textStripped = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]/u', '', $textLower);
        $len = mb_strlen($textStripped);
        $hasOtherIntent = preg_match('/siap|dah|udah|bisa|jemput|antar|anter|berapa|harga|transfer|bayar/i', $textLower) && mb_strlen($textLower) > 15;

        $ctx = $this->getGreetingContext($waNumber);
        $contactName = $ctx['contactName'];
        $sapaan = $ctx['sapaan'];

        if ($this->pembukaShouldSkipGreeting($waNumber)) {
            $this->pembukaSkippedGreeting = true;
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'pembuka_skip_recent_outbound');
            if ($hasOtherIntent) {
                $this->pembukaTryRunOtherIntent($phoneIn, $waNumber, $textBody);
            }
            return;
        }

        // Regex quick path: pesan singkat (P, ., 1-2 huruf) -> singkat & santai
        if ($len <= 2 || preg_match('/^[\.\,\!\?\-\s]+$/u', $textStripped)) {
            $haloShort = [
                "ya {$sapaan}, ada yang bisa dibantu? 😊",
                "Halo {$sapaan} 😊",
                "iya {$sapaan}, ada yang ingin ditanyakan? 😊",
                "iya {$sapaan}, ada yang mau ditanyakan? 😊",
                "Halo {$sapaan}, ada yang bisa kami bantu? 😊",
                "ya {$sapaan} 😊",
                "Halo {$sapaan}, ada yang ingin ditanyakan? 😊",
            ];
            $this->sendAutoreplyText($waNumber, $haloShort[array_rand($haloShort)]);
            return;
        }

        // Regex quick path: sapaan + intent lain -> salam singkat lalu trigger handler lain
        if ($hasOtherIntent) {
            $reply = $this->getMirrorTimeGreetingReplyLine($textBody, $sapaan);
            $res = $this->sendAutoreplyText($waNumber, $reply);
            if ($this->autoReplyProvider === 'B' && ($res['success'] ?? false)) {
                usleep(450000);
            }
            $this->pembukaTryRunOtherIntent($phoneIn, $waNumber, $textBody);
            return;
        }

        $nameContext = $contactName !== ''
            ? "Nama di data (konteks saja, JANGAN sebut di balasan): \"{$contactName}\".\n"
            : '';

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
                return;
            }

            $systemPembuka = "Kamu adalah customer service Madinah Laundry. Balas HANYA sapaan pembuka dari customer. JANGAN jawab pertanyaan/permintaan lain.\n\n"
                . "CRITICAL — SAPAAN TUNGGAL (WAJIB):\n"
                . "- Di setiap balasan, panggilan sopan HARUS memakai tepat kata sapaan ini: {$sapaan}\n"
                . "- Jangan ganti ke kak/bang/pak/bu lain. Jika sapaan wajib adalah bg, tulis \"bg\" (bukan kak).\n"
                . "- Contoh format (ganti tidak boleh — pakai {$sapaan}): \"Halo {$sapaan}, ada yang bisa dibantu?\" / \"Sore {$sapaan}, ada yang bisa dibantu?\" / \"Pagi {$sapaan} 😊\"\n\n"
                . "CRITICAL - SINGKAT & SANTAI:\n"
                . "- Balas PENDEK (1 kalimat, max 8-10 kata). Jangan formal. Santai tapi ramah.\n"
                . "- JANGAN kalimat panjang. JANGAN pakai tanda seru (!).\n\n"
                . "CRITICAL - JANGAN PERNAH sebut nama customer dalam balasan.\n\n"
                . "CRITICAL - JANGAN JAWAB PERTANYAAN:\n"
                . "- Jika pesan mengandung sapaan + pertanyaan, balas CUKUP salam saja. Handler lain yang jawab pertanyaannya.\n\n"
                . "PENTING:\n"
                . "- Assalamualaikum -> Waalaikumsalam + {$sapaan}. Halo/pagi/siang/sore/malam -> sesuaikan waktu + {$sapaan}.\n"
                . "- JANGAN kata 'Anda'. Boleh singkatan: siap, oke. JANGAN 'mk'. Santai, ramah.";

            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemPembuka,
                ],
                [
                    'role' => 'user',
                    'content' => $nameContext . "Sapaan WAJIB untuk balasan ini (dari sistem/CRM): {$sapaan}\n\nPesan customer: \"{$textBody}\"\n\nBalas singkat. Semua sapaan dalam balasan harus memakai kata \"{$sapaan}\".",
                ],
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 120);
            $text = trim(str_replace('!', '', $answer));
            if (empty($text) || mb_strlen($text) <= 2) {
                $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
                return;
            }

            $text = $this->sanitizePembukaSapaanAiOutput($text, $sapaan);

            $this->sendAutoreplyText($waNumber, $text);
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handlePembuka ERROR: " . $e->getMessage(), 'wa_error', 'Pembuka');
            }
            $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
        }
    }

    /**
     * Jika AI masih menulis kak/kk padahal sapaan wajib dari CRM lain (bg, bang, …), ganti token tersebut.
     */
    private function sanitizePembukaSapaanAiOutput(string $text, string $sapaan): string
    {
        $s = strtolower(trim($sapaan));
        if ($s === '' || in_array($s, ['kak', 'kk'], true)) {
            return $text;
        }

        $out = preg_replace('/\b(kak|kk)\b/iu', $sapaan, $text);
        if ($out !== $text) {
            $this->logSapaanResolve('PEMBUKA_AI sanitize kak/kk→' . $sapaan . ' | was=' . mb_substr($text, 0, 160));
        }

        return $out ?? $text;
    }

    /** Sapaan + intent lain: jalankan handler berikutnya tanpa mengulang PEMBUKA. */
    private function pembukaTryRunOtherIntent($phoneIn, string $waNumber, string $textBody): void
    {
        $textBodyToCheck = preg_replace('/[*_~`]/', '', $textBody);
        $textBodyToCheck = preg_replace('/^>\s*/m', '', $textBodyToCheck);
        $textBodyToCheck = strtolower(trim($textBodyToCheck));
        $messageLength = mb_strlen($textBodyToCheck);

        $keywordConfig = $this->loadAutoreplyKeywordConfig();
        unset($keywordConfig['PEMBUKA']);
        $keywordConfig = $this->filterKeywordConfigByChatMaxlength($keywordConfig, $messageLength, $textBody);
        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig);
        if ($aiResult && isset($aiResult['intent']) && strtoupper($aiResult['intent']) !== 'FALSE') {
            $aiIntent = strtoupper($aiResult['intent']);
            $handlerName = ucwords(strtolower($aiIntent), '_');
            $methodName = 'handle' . $handlerName;
            if (method_exists($this, $methodName) && $methodName !== 'handlePembuka') {
                $this->currentHandler = $aiIntent;
                $this->$methodName($phoneIn, $waNumber, $textBody);
            }
        }
    }

    /**
     * Handle intent PENUTUP — balas hanya (1) terima kasih (2) sudah bayar/lunas.
     * Subtype lainnya (ok/siap/sticker/emoji) → tidak balas.
     * Di luar jam operasional → tidak balas.
     */
    private function handlePenutup($phoneIn, $waNumber, $textBody = '')
    {
        $textNorm = $this->normalizeTextBodyForRegex((string) ($textBody ?? ''));
        $shouldReplyThanksOrPayment = $this->penutupShouldAutoreplyThanksOrPayment($textBody);

        $inHours = $this->isOperatingHours();
        if (!$inHours && !$shouldReplyThanksOrPayment) {
            if ($this->intentLabMode) {
                $this->logAutoreplyTrace($waNumber, 'LAB_NOTE', 'live would skip: di luar jam operasional');
            } else {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'penutup_skip_outside_hours');
                return;
            }
        }

        $ctx = $this->getGreetingContext($waNumber);
        $sapaan = $ctx['sapaan'];

        // (2) Sudah bayar/lunas — prioritas di atas thanks (pesan campur sering ada makasih)
        if ($this->messageLooksLikePaymentConfirmationPenutup($textNorm)) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'penutup_subtype=payment');
            $this->sendAutoreplyText($waNumber, $this->pickPenutupPaymentReply($sapaan));
            return;
        }

        // (1) Ucapan terima kasih
        if ($this->messageLooksLikeThanksPenutup($textNorm)) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'penutup_subtype=thanks');
            $this->sendAutoreplyText($waNumber, $this->pickPenutupThanksReply($sapaan));
            return;
        }

        // (3) Lainnya: ok/siap/sticker/emoji/ack — tidak balas
        $this->logAutoreplyTrace($waNumber, 'EXIT', 'penutup_subtype=other_no_reply len=' . mb_strlen($textNorm));
    }

    /**
     * Pertanyaan ongkir/ongkos antar-jemput saja — belum minta kurir (→ FALSE/CS, bukan KURIR).
     * Contoh: "udah sm ongkir ni kak?", "brp ongkirnya?", "berapa ongkos antar?"
     *
     * @param string $text asli atau lowercase
     */
    private function messageLooksLikeOngkirOngkosInquiryOnly($text): bool
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return false;
        }
        if (!preg_match('/\b(ongkos|ongkir|ong\s*kos|ong\s*kir|ong\s*n)\b/iu', $t)) {
            return false;
        }
        // Ongkos + durasi/tier layanan → HARGA, bukan inquiry kurir biasa
        if ($this->messageIsHargaOngkosByDurasiAtauLayanan($t)) {
            return false;
        }
        $hasOngkirQuestionCue = (bool) preg_match(
            '/\?|？|\b(brp|brpa|brapa|berapa|biaya|tarif|harga)\b|\b(udah|udh|sudah|sdh|dah|dh)\s+(sm|sama|include|termasuk)\b|\b(sm|sama|include|termasuk)\s+(ongkos|ongkir|ong\s*kos|ong\s*kir)\b|\b(ongkos|ongkir|ong\s*kos|ong\s*kir|ong\s*n)\s*(nya|brp|brpa|berapa)\b/iu',
            $t
        );
        if (!$hasOngkirQuestionCue) {
            return false;
        }
        // Permintaan kurir kuat tanpa fokus tanya ongkir → tetap minta kurir
        if (preg_match('/\b(tolong|minta|bantu)\s*(di)?(jemput|antar|anter)\b/iu', $t)
            && !preg_match('/\b(brp|brpa|brapa|berapa)\s+(ongkos|ongkir|ong\s*kos|ong\s*kir|ong\s*n)\b/iu', $t)
            && !preg_match('/\?\s*$/u', trim($t))
        ) {
            return false;
        }
        if (preg_match('/\b(besok|bsk|hari\s*ini)\s+(di)?(jemput|antar|anter)\b/iu', $t)
            && !preg_match('/\b(brp|brpa|brapa|berapa|udah|udh|sudah|sdh|sm|sama)\b.*\b(ongkos|ongkir|ong\s*kos|ong\s*kir|ong\s*n)\b/iu', $t)
        ) {
            return false;
        }

        return true;
    }

    /**
     * Minta satu jenis pakaian/item diambil/dulukan dulu dari order — PERMINTAAN, bukan KURIR jemput ke kamar/alamat.
     *
     * @param string $textLower lowercase, tanpa formatter WA
     */
    private function messageIsPermintaanAmbilPakaianDulu($textLower)
    {
        $t = (string) $textLower;
        if ($t === '') {
            return false;
        }
        if (preg_match('/\b(ambil|jemput)\b.{0,100}?\b(kamar|hotel|rumah\s*sakit|depan\s+kamar|alamat|jalan\s*\.?)\b/iu', $t)) {
            return false;
        }
        if (preg_match('/\b(baju|pakaian|seragam|celana|jaket|kemeja|dress|rok|dinas)\b.{0,160}?\b(di\s*)?amb(i|l)\b.{0,40}?\b(dulu|dlu|duluan|dulukan)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(di\s*)?amb(i|l)\b.{0,40}?\b(dulu|dlu|duluan).{0,120}?\b(baju|pakaian|seragam|celana|dinas)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(didulukan|dulukan|prioritas|utamakan)\b.{0,80}?\b(baju|pakaian|seragam|celana)\b/iu', $t)) {
            return true;
        }
        return false;
    }

    private function handleTagihan($phoneIn, $waNumber, $textBody = '', bool $crmManual = false)
    {
        $waService = $this->getWaService();
        $db = DB::getInstance(1);

        $pelanggan = $this->queryPelangganRowsByWaNumber($db, $phoneIn, $waNumber, 'id_pelanggan, nama_pelanggan, id_cabang');

        if (empty($pelanggan)) {
            $this->logAutoreplyTrace($waNumber, 'TAGIHAN', 'no_pelanggan');
            if ($crmManual) {
                $this->tagihanSendOutcome = 'no_pelanggan';
            }
            return;
        }

        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        $ids_in = implode(',', array_map('intval', $id_pelanggans));
        $cabangByPelanggan = [];
        foreach ($pelanggan as $p) {
            $cabangByPelanggan[(int) $p['id_pelanggan']] = (int) ($p['id_cabang'] ?? 0);
        }

        // Cari id_pelanggan dari sales yang tuntas=0 dulu (sama seperti handleNota)
        $sales = $db->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan ORDER BY insertTime DESC")->result_array();
        $id_pelanggans_from_sale = array_unique(array_column($sales, 'id_pelanggan'));

        // Juga cek member lunas=0
        $members = $db->query("SELECT id_pelanggan FROM member WHERE bin = 0 AND id_pelanggan IN ($ids_in) AND lunas = 0")->result_array();
        $id_pelanggans_from_member = array_unique(array_column($members, 'id_pelanggan'));

        $id_pelanggans_to_check = array_unique(array_merge($id_pelanggans_from_sale, $id_pelanggans_from_member));
        // --- Tidak ada sale/member aktif: generik hanya pola tegas ---
        if (empty($id_pelanggans_to_check)) {
            $id_pelanggan = $this->senderIdPelanggan() ?: (int) $id_pelanggans[0];
            $nama_pelanggan = strtoupper(trim((string) ($pelanggan[array_search($id_pelanggan, $id_pelanggans)]['nama_pelanggan'] ?? $pelanggan[0]['nama_pelanggan'] ?? 'PELANGGAN')));
            $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, 'https://ml.nalju.com/I/' . $id_pelanggan, 'TAGIHAN', $crmManual);
            return;
        }

        // --- Ada data tagihan: bangun rincian (tanpa cek pola tegas) ---

        $ids_bill = implode(',', array_map('intval', $id_pelanggans_to_check));
        $id_pelanggan = $this->senderIdPelanggan() ?: (int) $id_pelanggans[0];
        $namaRow = $pelanggan[array_search($id_pelanggan, $id_pelanggans)] ?? $pelanggan[0];
        $nama_pelanggan = strtoupper(trim((string) ($namaRow['nama_pelanggan'] ?? 'PELANGGAN')));

        $lookup = $this->loadTagihanLookups($db);
        $lines = [];
        $totalTagihan = 0;

        // 1. Sale — semua id_pelanggan terikat nomor WA (bukan hanya satu id)
        $saleRows = $db->query(
            "SELECT id_penjualan, no_ref, id_pelanggan, id_cabang, id_item_group, id_penjualan_jenis, id_durasi, list_layanan, qty, harga, min_order, diskon_qty, diskon_partner, member, insertTime FROM sale WHERE id_pelanggan IN ($ids_bill) AND bin = 0 AND tuntas = 0 ORDER BY no_ref, id_penjualan DESC"
        )->result_array();

        $byRef = [];
        foreach ($saleRows as $row) {
            $byRef[$row['no_ref']][] = $row;
        }

        foreach ($byRef as $noRef => $items) {
            $subTotal = 0;
            $itemLines = [];
            $id_cabang = (int) ($items[0]['id_cabang'] ?? $cabangByPelanggan[(int) ($items[0]['id_pelanggan'] ?? 0)] ?? 0);

            foreach ($items as $s) {
                $line = $this->formatSaleItemForTagihan($s, $lookup);
                if ($line) {
                    $itemLines[] = $line['text'];
                    $subTotal += $line['total'];
                }
            }

            $surcasRows = $db->query(
                "SELECT sc.jumlah, sj.surcas_jenis FROM surcas sc LEFT JOIN surcas_jenis sj ON sc.id_jenis_surcas = sj.id_surcas_jenis WHERE sc.id_cabang = ? AND sc.no_ref = ?",
                [$id_cabang, $noRef]
            )->result_array();
            foreach ($surcasRows as $sc) {
                $j = (int) ($sc['jumlah'] ?? 0);
                $subTotal += $j;
                $itemLines[] = "+ " . ($sc['surcas_jenis'] ?? 'Surcharge') . ": Rp " . number_format($j, 0, ',', '.');
            }

            // Hanya hitung pembayaran sukses (status_mutasi = 3); pending (2) tidak mengurangi sisa tagihan
            $payments = $db->query(
                "SELECT COALESCE(SUM(jumlah), 0) as bayar FROM kas WHERE id_cabang = ? AND jenis_transaksi = 1 AND ref_transaksi = ? AND status_mutasi = 3",
                [$id_cabang, $noRef]
            )->row();
            $bayar = (int) ($payments->bayar ?? 0);
            $sisa = max(0, $subTotal - $bayar);
            $totalTagihan += $sisa;

            $block = "📋 *" . $noRef . "*\n" . implode("\n", $itemLines) . "\n_Subtotal: Rp " . number_format($subTotal, 0, ',', '.') . "_";
            if ($bayar > 0) {
                $block .= "\nSudah bayar: Rp " . number_format($bayar, 0, ',', '.');
            }
            $block .= "\n*Sisa: Rp " . number_format($sisa, 0, ',', '.') . "*";
            $lines[] = $block;
        }

        // 2. Member (lunas=0) — semua id_pelanggan pada nomor ini
        $members = $db->query(
            "SELECT m.id_member, m.id_cabang, m.id_harga, m.harga, m.qty, m.insertTime FROM member m WHERE m.bin = 0 AND m.id_pelanggan IN ($ids_bill) AND m.lunas = 0 ORDER BY m.id_member DESC"
        )->result_array();

        foreach ($members as $mem) {
            $id_member = $mem['id_member'];
            $id_cabang = (int) ($mem['id_cabang'] ?? 0);
            $total = (int) ($mem['harga'] ?? 0);

            // Sama seperti I.php invoice (line 726-727): untuk member hanya hitung status_mutasi = 3
            $payments = $db->query(
                "SELECT COALESCE(SUM(jumlah), 0) as bayar FROM kas WHERE id_cabang = ? AND jenis_transaksi = 3 AND ref_transaksi = ? AND status_mutasi = 3",
                [$id_cabang, $id_member]
            )->row();
            $bayar = (int) ($payments->bayar ?? 0);
            if ($bayar >= $total) {
                continue;
            }
            $sisa = max(0, $total - $bayar);
            $totalTagihan += $sisa;

            $detail = $this->formatMemberItemForTagihan($mem, $lookup);
            $block = "📋 *Member #" . $id_member . "*\n" . $detail . "\nTotal: Rp " . number_format($total, 0, ',', '.');
            if ($bayar > 0) {
                $block .= "\nSudah bayar: Rp " . number_format($bayar, 0, ',', '.');
            }
            $block .= "\n*Sisa: Rp " . number_format($sisa, 0, ',', '.') . "*";
            $lines[] = $block;
        }

        $link = "https://ml.nalju.com/I/" . $id_pelanggan;

        if (empty($lines)) {
            // Sale/member ada di DB tapi rincian kosong (mis. sudah lunas): generik hanya pola tegas
            $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, $link, 'TAGIHAN', $crmManual);
            return;
        }

        // Ada rincian tagihan — selalu kirim (tanpa cek pola tegas)
        $text = "*" . $nama_pelanggan . "*\nRincian Tagihan:\n\n" . implode("\n\n", $lines) . "\n\n*Total Tagihan: Rp " . number_format($totalTagihan, 0, ',', '.') . "*\n" . $link;
        $this->logAutoreplyTrace($waNumber, 'TAGIHAN_SEND', 'rincian blocks=' . count($lines));
        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            if ($crmManual) {
                $this->tagihanSendOutcome = 'sent';
            }
        } elseif ($crmManual) {
            $this->tagihanSendOutcome = 'skipped';
        }
    }

    private function loadTagihanLookups($db)
    {
        $itemGroup = [];
        foreach ($db->query("SELECT id_item_group, item_kategori FROM item_group")->result_array() as $r) {
            $itemGroup[$r['id_item_group']] = $r['item_kategori'] ?? '';
        }
        $penjualan = [];
        foreach ($db->query("SELECT id_penjualan_jenis, id_satuan FROM penjualan_jenis")->result_array() as $r) {
            $penjualan[$r['id_penjualan_jenis']] = (int) ($r['id_satuan'] ?? 0);
        }
        $satuan = [];
        foreach ($db->query("SELECT id_satuan, nama_satuan FROM satuan")->result_array() as $r) {
            $satuan[$r['id_satuan']] = $r['nama_satuan'] ?? '';
        }
        $durasi = [];
        foreach ($db->query("SELECT id_durasi, durasi FROM durasi")->result_array() as $r) {
            $durasi[$r['id_durasi']] = $r['durasi'] ?? '';
        }
        $layanan = [];
        foreach ($db->query("SELECT id_layanan, layanan FROM layanan")->result_array() as $r) {
            $layanan[$r['id_layanan']] = $r['layanan'] ?? '';
        }
        $harga = [];
        foreach ($db->query("SELECT id_harga, id_penjualan_jenis, id_item_group, list_layanan, id_durasi FROM harga")->result_array() as $r) {
            $harga[$r['id_harga']] = $r;
        }
        return compact('itemGroup', 'penjualan', 'satuan', 'durasi', 'layanan', 'harga');
    }

    private function formatSaleItemForTagihan($s, $lookup)
    {
        $member = (int) ($s['member'] ?? 0);
        $kategori = $lookup['itemGroup'][$s['id_item_group']] ?? 'Item';
        $idSatuan = $lookup['penjualan'][$s['id_penjualan_jenis']] ?? 0;
        $satuan = $lookup['satuan'][$idSatuan] ?? '';
        // Gunakan float agar decimal (mis. 11.2kg) tidak terpotong seperti I.php
        $qty = (float) ($s['qty'] ?? 0);
        $minOrder = (float) ($s['min_order'] ?? 1);
        $qtyReal = max($qty, $minOrder);
        $harga = (float) ($s['harga'] ?? 0);
        $total = $harga * $qtyReal;
        $originalTotal = $total;

        $dQty = (float) ($s['diskon_qty'] ?? 0);
        $dPartner = (float) ($s['diskon_partner'] ?? 0);
        if ($member === 1) {
            $total = 0;
        } else {
            if ($dQty > 0) {
                $total = $total - $total * ($dQty / 100);
            }
            if ($dPartner > 0) {
                $total = $total - $total * ($dPartner / 100);
            }
            $total = (int) round($total);
        }

        $layananNames = [];
        $listLayanan = @unserialize($s['list_layanan'] ?? '');
        if (is_array($listLayanan)) {
            foreach ($listLayanan as $lid) {
                if (!empty($lookup['layanan'][$lid])) {
                    $layananNames[] = $lookup['layanan'][$lid];
                }
            }
        }
        $layananStr = !empty($layananNames) ? ' (' . implode(', ', $layananNames) . ')' : '';
        $durasi = $lookup['durasi'][$s['id_durasi']] ?? '';
        $durasiStr = $durasi ? " {$durasi}" : '';

        // Tampilkan qty dengan decimal jika ada (11.2kg bukan 11kg)
        $qtyDisplay = (abs($qty - round($qty)) < 0.001) ? (string) (int) round($qty) : rtrim(rtrim(sprintf('%.2f', $qty), '0'), '.');
        $qtyShow = $qtyDisplay . $satuan;
        if ($minOrder > 0 && $qty < $minOrder) {
            $minDisplay = (abs($minOrder - round($minOrder)) < 0.001) ? (string) (int) round($minOrder) : rtrim(rtrim(sprintf('%.2f', $minOrder), '0'), '.');
            $qtyShow .= " (Min. {$minDisplay}{$satuan})";
        }
        // Sama seperti WAGenerator get_nota: ada diskon → ~harga asli~ harga setelah diskon
        if ($member === 1) {
            $pricePart = 'Member';
        } elseif ($dQty > 0 || $dPartner > 0) {
            $pricePart = '~Rp ' . number_format((int) round($originalTotal), 0, ',', '.') . '~ Rp ' . number_format($total, 0, ',', '.');
        } else {
            $pricePart = 'Rp ' . number_format($total, 0, ',', '.');
        }
        $text = "#{$s['id_penjualan']} - {$kategori} {$qtyShow}{$layananStr}{$durasiStr} | {$pricePart}";
        return ['text' => $text, 'total' => $total];
    }

    private function formatMemberItemForTagihan($mem, $lookup)
    {
        $idHarga = $mem['id_harga'] ?? 0;
        $h = $lookup['harga'][$idHarga] ?? null;
        $kategori = '';
        $layananParts = [];
        $durasi = '';
        $unit = '';
        if ($h) {
            $kategori = $lookup['itemGroup'][$h['id_item_group']] ?? '';
            $durasi = $lookup['durasi'][$h['id_durasi']] ?? '';
            $idPj = $h['id_penjualan_jenis'] ?? 0;
            $idSatuan = $lookup['penjualan'][$idPj] ?? 0;
            $unit = $lookup['satuan'][$idSatuan] ?? '';
            $listL = @unserialize($h['list_layanan'] ?? '');
            if (is_array($listL)) {
                foreach ($listL as $lid) {
                    if (!empty($lookup['layanan'][$lid])) {
                        $layananParts[] = $lookup['layanan'][$lid];
                    }
                }
            }
        }
        $qty = (int) ($mem['qty'] ?? 0);
        $layananStr = !empty($layananParts) ? implode(' * ', $layananParts) : 'Paket';
        $detail = "Topup Paket: " . ($kategori ?: 'Member') . ($layananStr ? " * {$layananStr}" : '') . ($durasi ? " * {$durasi}" : '') . " - {$qty}{$unit}";
        return $detail;
    }

    /**
     * Tanya estimasi kapan order siap / bisa diambil (bukan "sudah siap?", bukan minta kurir).
     * Contoh: kapan siap?, jam berapa siap?, kira jam berapa bisa dijemput kak?
     */
    private function messageLooksLikeEstimasiSelesai(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        // Sudah siap sekarang = STATUS, bukan estimasi
        if (preg_match('/\b(sudah|udah|udh|sdh|dah|dh|sudh)\s+(siap|selesai|jadi)\b/iu', $t)
            && !preg_match('/\b(kapan|kpn|jam|brp|berapa|kira)\b/iu', $t)) {
            return false;
        }
        if (preg_match('/\b(kapan|kpn|jam\s*(brp|brpa|berapa)|brp|berapa)\b.{0,50}?\b(siap|sia+p+|selesai|jadi)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(siap|sia+p+|selesai)(nya)?\b.{0,30}?\b(kapan|kpn|jam|brp|berapa)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(kira|kira[\s\-]*kira)\b.{0,60}?\b(jam|kapan|kpn|brp|berapa)\b.{0,60}?\b(bisa|boleh)?\s*(di\s*)?(ambil|jemput|siap|selesai)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(kapan|kpn|jam\s*(brp|brpa|berapa)|brp|berapa)\b.{0,50}?\b(bisa|boleh)\s*(di\s*)?(ambil|jemput)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(jam\s*)?(brp|brpa|berapa)\s*bisa\s*(di\s*)?(jemput|ambil)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(kapan|kpn)\s+(bisa|boleh)\s*(di\s*)?(ambil|jemput)\b/iu', $t)) {
            return true;
        }
        // "diantar kembali selambatnya hari minggu" = deadline laundry, bukan jam kurir
        if ($this->messageLooksLikeAntarKembaliDeadline($t)) {
            return true;
        }

        return false;
    }


    /**
     * Deadline pengembalian laundry: antar kembali / selambatnya — bukan jam kunjungan kurir jemput/antar.
     */
    private function messageLooksLikeAntarKembaliDeadline(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        if (preg_match('/\b(di\s*)?antar\s*kembali\b/iu', $t)) {
            return true;
        }
        if (preg_match(
            '/\b(selambat\s*2?\s*nya|selambat[\-\s]+lambatnya|paling\s*lambat)\b/iu',
            $t
        )) {
            return true;
        }

        return false;
    }

    /**
     * "Gk bisa sore ini siap?" / "Ndak bisa sore ini jam 6?" = tanya estimasi/grant, bukan STATUS.
     */
    private function messageLooksLikeNegatedBisaRelativeEstimasi(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        $t = $text;
        if (!preg_match(
            '/\b(gk|gak|ga|ngga|nggak|ndak|ndk|tidak|tdk|tak|engga|enggak)\s*(bisa|boleh|bs|bis)\b/iu',
            $t
        )) {
            return false;
        }
        // Waktu relatif hari / bagian hari
        if (preg_match('/\b(pagi|siang|sore|malam)\s*ini\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(hari\s*ini|hr\s*ini|besok|bsk|lusa)\b/iu', $t)
            && preg_match('/\b(siap|selesai|jadi|jam\s*\d{1,2})\b/iu', $t)
        ) {
            return true;
        }
        return false;
    }


    /**
     * Ekstrak jam (+ tanggal opsional) yang DIMINTA customer.
     * @return array{jam:?float,tanggal:?string,ask_ampm?:bool,raw_hour?:int,raw_min?:int}|null
     */
    private function parseEstimasiRequestWaktu(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $t = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", '"', "'"], ' ', (string) $text);
        if (preg_match('/\bjam\s*(brp|brpa|berapa)\b/iu', $t)) {
            return null;
        }
        $h = null;
        $min = 0;
        if (preg_match('/\bjam\s*(\d{1,2})(?:[.:](\d{1,2}))?\b/iu', $t, $m)) {
            $h = (int) $m[1];
            $min = isset($m[2]) ? (int) $m[2] : 0;
        } elseif (preg_match('/\b(\d{1,2})[.:](\d{2})\s*(wib|wit|wita)?\b/iu', $t, $m)
            && preg_match('/\b(siap|selesai|jadi|minta|bisa|tolong)\b/iu', $t)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
        }
        if ($h === null || $h > 23 || $min > 59) {
            return null;
        }
        $tanggal = null;
        if (preg_match('/\b(pagi|siang|sore|malam)\s*ini\b/iu', $t)
            || preg_match('/\b(hari\s*ini|hr\s*ini)\b/iu', $t)) {
            $tanggal = date('Y-m-d');
        } elseif (preg_match('/\b(besok|bsk)\b/iu', $t)) {
            $tanggal = date('Y-m-d', strtotime('+1 day'));
        } elseif (preg_match('/\blusa\b/iu', $t)) {
            $tanggal = date('Y-m-d', strtotime('+2 day'));
        }
        $norm = $this->normalizeLaundryCustomerJam($h, $min, $t);
        if (!empty($norm['ask_ampm'])) {
            return [
                'jam' => null,
                'tanggal' => $tanggal,
                'ask_ampm' => true,
                'raw_hour' => $h,
                'raw_min' => $min,
            ];
        }
        return ['jam' => (float) $norm['jam'], 'tanggal' => $tanggal];
    }

    /**
     * Normalisasi jam bicara customer (bukan format 24 jam eksplisit ≥13).
     * @return array{jam?:float,ask_ampm?:bool}
     */
    private function normalizeLaundryCustomerJam(int $h, int $min, string $text): array
    {
        $jamFloat = static function (int $hour, int $minute): float {
            return (float) sprintf('%d.%02d', $hour, $minute);
        };
        if ($h >= 13 && $h <= 23) {
            return ['jam' => $jamFloat($h, $min)];
        }
        if ($h === 0 || $h === 12) {
            return ['jam' => $jamFloat($h, $min)];
        }
        $hasPagi = (bool) preg_match('/\bpagi\b/iu', $text);
        $hasMalam = (bool) preg_match('/\bmalam\b/iu', $text);
        if ($h >= 1 && $h <= 6) {
            if ($hasPagi) {
                return ['jam' => $jamFloat($h, $min)];
            }
            return ['jam' => $jamFloat($h + 12, $min)];
        }
        if ($h >= 7 && $h <= 9) {
            if ($hasPagi) {
                return ['jam' => $jamFloat($h, $min)];
            }
            if ($hasMalam) {
                return ['jam' => $jamFloat($h + 12, $min)];
            }
            $nowMinutes = ((int) date('G')) * 60 + (int) date('i');
            $reqMinutes = $h * 60 + $min;
            if ($nowMinutes >= $reqMinutes) {
                return ['jam' => $jamFloat($h + 12, $min)];
            }
            return ['ask_ampm' => true];
        }
        if ($h >= 10 && $h <= 11 && $hasMalam) {
            return ['jam' => $jamFloat($h + 12, $min)];
        }
        return ['jam' => $jamFloat($h, $min)];
    }

    private function estimasiWaktuIsResolved(?array $waktu): bool
    {
        return is_array($waktu)
            && empty($waktu['ask_ampm'])
            && isset($waktu['jam'])
            && $waktu['jam'] !== null
            && $waktu['jam'] !== '';
    }

    /** Legacy intent ESTIMASI_SELESAI → PERMINTAAN. */
    private function remapEstimasiSelesaiHandlerToPermintaan(string $handler): string
    {
        return strtoupper($handler) === 'ESTIMASI_SELESAI' ? 'PERMINTAAN' : $handler;
    }

    private function applyPermintaanHandlerMeta(string $handler, array $fullKeywordConfig, &$caseVal, &$notify, &$config): string
    {
        $handler = $this->remapEstimasiSelesaiHandlerToPermintaan($handler);
        if ($handler === 'PERMINTAAN') {
            $caseVal = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
            if ($caseVal === null || (int) $caseVal === 0) {
                $caseVal = 3;
            }
            $notify = true;
            $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
        }
        return $handler;
    }

    /**
     * Customer tanya bisa siap pada hari relatif (tanpa jam angka).
     * pagi/siang/sore/malam ini = hari_ini.
     * @return 'hari_ini'|'besok'|'lusa'|null
     */
    private function parseEstimasiRequestedRelativeDay(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $t = $text;
        // Ada jam angka spesifik (sudah resolved) → grant path, bukan relative-day
        $waktu = $this->parseEstimasiRequestWaktu($t);
        if ($this->estimasiWaktuIsResolved($waktu)) {
            return null;
        }
        // ask_ampm saja masih bisa relative? biasanya sudah sebut jam → bukan relative
        if ($waktu !== null && !empty($waktu['ask_ampm'])) {
            return null;
        }

        $hasSiapContext = (bool) preg_match(
            '/\b(bisa|boleh|bs|mungkin)\b.{0,40}\b(siap|selesai|jadi|ambil|diambil|dijemput|jemput)\b'
            . '|\b(siap|selesai|jadi)\b.{0,40}\b(bisa|boleh|bs|gak|ga|ngga|tidak|tdk)\b'
            . '|\b(siap|selesai)\s*(nya)?\b/iu',
            $t
        );
        // "gk/ndak bisa sore ini (siap)?" — konteks tanya bisa selesai di bagian hari
        if (!$hasSiapContext && $this->messageLooksLikeNegatedBisaRelativeEstimasi($t)) {
            $hasSiapContext = true;
        }
        if (!$hasSiapContext) {
            return null;
        }

        // pagi/siang/sore/malam ini = hari ini
        if (preg_match('/\b(pagi|siang|sore|malam)\s*ini\b/iu', $t)) {
            return 'hari_ini';
        }
        if (preg_match('/\bhari\s*ini\b/iu', $t) || preg_match('/\bhr\s*ini\b/iu', $t)) {
            return 'hari_ini';
        }
        if (preg_match('/\bbesok\b/iu', $t) || preg_match('/\bbsk\b/iu', $t)) {
            return 'besok';
        }
        if (preg_match('/\blusa\b/iu', $t)) {
            return 'lusa';
        }
        // Typo umum: "bisa siap hari gak?" / "siap hari?" (= hari ini, "ini" terlewat)
        if (preg_match('/\b(hari|hr)\b/iu', $t)
            && !preg_match('/\b(besok|bsk|lusa|kemarin)\b/iu', $t)) {
            return 'hari_ini';
        }

        return null;
    }

    /**
     * Ada sale aktif (tuntas=0, bin=0) untuk nomor WA ini? Mirip cek data di handleStatus (tanpa notif pending).
     */
    private function pelangganHasActiveSale(string $phoneIn, string $waNumber): bool
    {
        $db1 = DB::getInstance(1);
        $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        $idPelanggans = array_column($pelanggan, 'id_pelanggan');
        if (empty($idPelanggans)) {
            return false;
        }
        $idsIn = implode(',', array_map('intval', $idPelanggans));
        $sales = $db1->query(
            "SELECT no_ref FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($idsIn) LIMIT 1"
        )->result_array();

        return !empty($sales);
    }

    private function handleStatus($phoneIn, $waNumber, $textBody = '', bool $crmManual = false)
    {
        $waService = $this->getWaService();

        $db1 = DB::getInstance(1);
        // Jalur pelengkap: kirim nota (notif tipe=1) yang belum ada. Diisolasi try/catch —
        // jangan sampai lock wait timeout / API wa_nota lambat menggagalkan balasan STATUS utama.
        try {
            $this->trySendMissingNotaNotifsForStatus($phoneIn, $waNumber, $waService, $db1);
        } catch (\Throwable $e) {
            if (class_exists('\Log', false)) {
                \Log::write('[STATUS] trySendMissingNotaNotifsForStatus skipped: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }

        $limitTime = date('Y-m-d H:i:s', strtotime('-72 hours'));

        $pelangganRows = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        $id_pelanggans = array_values(array_filter(array_map('intval', array_column($pelangganRows, 'id_pelanggan'))));
        if ($id_pelanggans === []) {
            return;
        }
        $ids_in = implode(',', $id_pelanggans);

        $sql = "SELECT * FROM notif 
                WHERE tipe = 2 AND state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND id_pelanggan IN ($ids_in)
                ORDER BY insertTime ASC";

        $pendingNotifs = $db1->query($sql)->result_array();
        
        // Track which id_penjualan already have pending notifs
        $pendingNotifIds = [];
        $pendingSent = false;
        // --- Ada data: notif status pending — kirim tanpa cek pola tegas ---
        if (!empty($pendingNotifs)) {
            foreach ($pendingNotifs as $notif) {
                $idNotif = $notif['id_notif'];
                
                // Collect no_ref from pending notifs (no_ref = id_penjualan)
                if (!empty($notif['no_ref'])) {
                    $pendingNotifIds[] = $notif['no_ref'];
                }

                // 🔒 LOCK: Update state to 'sending' first to prevent race condition
                $success = $db1->update(
                    'notif',
                    ['state' => 'sending'],
                    ['id_notif' => $idNotif, 'state' => 'pending'] // Only update if still pending
                );

                // If lock failed (already being sent by another process), skip
                if (!$success || $db1->affected_rows() <= 0) {
                    continue;
                }

                // Send message (Free text is allowed now since customer just messaged us)
                $res = $this->sendQuotedFreeText($waNumber, $notif['text']);

                $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null);
                $wamid = $res['data']['wamid'] ?? null;

                $updateData = ['state' => $status];
                if ($msgId) {
                    $updateData['id_api'] = $msgId;
                }

                $updated = $db1->update('notif', $updateData, ['id_notif' => $notif['id_notif']]);
                if (!$updated) {
                    \Log::write("FAILED to update Notif #$idNotif - " . $db1->conn()->error, 'wa_error', 'Notif');
                }

                // Broadcast to WebSocket with future timestamp
                if ($res['success']) {
                    $pendingSent = true;
                    // Add 1 second to ensure auto-reply appears after customer message
                    $timestamp = date('Y-m-d H:i:s', strtotime('+1 second'));
                    $payload = $this->buildWsPayload($waNumber, $notif['text'], $msgId, $wamid, $timestamp);
                    $this->pushToWebSocket($payload);
                }
            }
            if ($crmManual && $pendingSent) {
                $this->statusSendOutcome = 'sent';
            }
            $this->logAutoreplyTrace($waNumber, 'STATUS_SEND', 'pending_notif count=' . count($pendingNotifs));
        }
        
        // Always check sale status, even if there are pending notifs
        // This ensures items without pending notifs are also reported
        $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan, nama_pelanggan');
        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
        $nama_pelanggan = strtoupper(trim((string) ($nama_pelanggans[0] ?? ''))); // fix index 0 if empty

        if (empty($id_pelanggans)) {
            $this->logAutoreplyTrace($waNumber, 'STATUS', 'no_pelanggan_skip_sale_status');
            if ($crmManual) {
                $this->statusSendOutcome = 'no_pelanggan';
            }
        } else {
            $ids_in = implode(',', $id_pelanggans);
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
            $noRefs = array_column($sales, 'no_ref');
            if (empty($noRefs)) {
                if (!($crmManual && $this->statusSendOutcome === 'sent')) {
                    $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, null, 'STATUS', $crmManual);
                }
            } else {
                // --- Ada data: sale aktif — kirim status tanpa cek pola tegas ---
                $queuedItems = [];      // Dalam Antrian (deadline > today)
                $inProgressItems = [];  // Dalam Pengerjaan (deadline <= today)
                $completedItems = [];   // Selesai (notif tipe 2 + letak)
                $today = date('Y-m-d');
                // Hanya id_pelanggan yang punya item status (jangan kirim link kosong / sudah tuntas semua)
                $pelangganIdsForLink = [];

                foreach ($noRefs as $noRef) {
                    $get_penjualan = $db1->query(
                        "SELECT id_penjualan, id_pelanggan, letak, insertTime, hari, jam
                         FROM sale
                         WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND no_ref = '$noRef'"
                    )->result_array();
                    $id_penjualans = array_column($get_penjualan, 'id_penjualan');

                    $quotedIds = array_map(function ($id) {
                        return "'$id'";
                    }, $id_penjualans);
                    $id_penjualans_in = implode(',', $quotedIds);

                    $existingNotifIds = !empty($id_penjualans)
                        ? array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($id_penjualans_in)")->result_array(), 'no_ref')
                        : [];

                    foreach ($get_penjualan as $sale) {
                        $id_penjualan = $sale['id_penjualan'];
                        $idPelangganSale = (int) ($sale['id_pelanggan'] ?? 0);
                        $letak = $sale['letak'] ?? '';

                        // Skip if this id_penjualan already has pending notif (already sent above)
                        if (in_array($id_penjualan, $pendingNotifIds)) {
                            continue;
                        }

                        $hasNotif = in_array($id_penjualan, $existingNotifIds);
                        $hasLocation = !empty(trim($letak));

                        if ($hasNotif && $hasLocation) {
                            $completedItems[] = [
                                'id' => $id_penjualan,
                                'id_pelanggan' => $idPelangganSale,
                            ];
                            if ($idPelangganSale > 0) {
                                $pelangganIdsForLink[$idPelangganSale] = true;
                            }
                            continue;
                        }

                        $hari = (int) ($sale['hari'] ?? 0);
                        $jam = (int) ($sale['jam'] ?? 0);
                        $insertTime = $sale['insertTime'] ?? date('Y-m-d H:i:s');
                        $deadlineTs = strtotime($insertTime . ' +' . $hari . ' days +' . $jam . ' hours');
                        if ($deadlineTs === false) {
                            $deadlineTs = time();
                        }
                        $deadlineDate = date('Y-m-d', $deadlineTs);
                        $estimasi = ($hari !== 0)
                            ? date('j/n/Y', $deadlineTs)
                            : date('j/n/Y H:i', $deadlineTs);
                        $isPrioritas = ($hari < 2);

                        $entry = [
                            'id' => $id_penjualan,
                            'id_pelanggan' => $idPelangganSale,
                            'estimasi' => $estimasi,
                            'prioritas' => $isPrioritas,
                        ];

                        if ($deadlineDate <= $today) {
                            $inProgressItems[] = $entry;
                        } else {
                            $queuedItems[] = $entry;
                        }
                        if ($idPelangganSale > 0) {
                            $pelangganIdsForLink[$idPelangganSale] = true;
                        }
                    }
                }

                $hasAnyStatus = count($queuedItems) > 0 || count($inProgressItems) > 0 || count($completedItems) > 0;
                if ($hasAnyStatus) {
                    $list_link = "";
                    foreach (array_keys($pelangganIdsForLink) as $id_pelanggan) {
                        $list_link .= "https://ml.nalju.com/I/" . (int) $id_pelanggan . "\n";
                    }

                    $statusBlocks = [];
                    foreach ($queuedItems as $item) {
                        $block = "#" . $item['id'] . " Dalam Antrian\nEst. Selesai " . $item['estimasi'];
                        if (!empty($item['prioritas'])) {
                            $block .= " *(Prioritas)*";
                        }
                        $statusBlocks[] = $block;
                    }
                    foreach ($inProgressItems as $item) {
                        $block = "#" . $item['id'] . " Dalam Pengerjaan\nEst. Selesai " . $item['estimasi'];
                        if (!empty($item['prioritas'])) {
                            $block .= " *(Prioritas)*";
                        }
                        $statusBlocks[] = $block;
                    }
                    // Selesai digabung satu blok (tanpa baris kosong antar item)
                    if (count($completedItems) > 0) {
                        $selesaiLines = [];
                        foreach ($completedItems as $item) {
                            $selesaiLines[] = "#" . $item['id'] . " Selesai";
                        }
                        $statusBlocks[] = implode("\n", $selesaiLines);
                    }

                    $statusText = implode("\n\n", $statusBlocks);
                    $text = "*" . $nama_pelanggan . ",*\n" . rtrim($list_link) . "\n\n" . $statusText;
                    $res = $this->sendQuotedFreeText($waNumber, $text);
                    if ($res['success']) {
                        $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                        if ($crmManual) {
                            $this->statusSendOutcome = 'sent';
                        }
                    } elseif ($crmManual) {
                        $this->statusSendOutcome = 'skipped';
                    }
                    $this->logAutoreplyTrace(
                        $waNumber,
                        'STATUS_SEND',
                        'status_list antrian=' . count($queuedItems)
                            . ' pengerjaan=' . count($inProgressItems)
                            . ' selesai=' . count($completedItems)
                    );
                }
            }
        }
    }

    /**
     * @return bool true jika balasan terkirim; false jika dilewati cooldown (jam tutup)
     */
    function handleJam_operasional($phoneIn, $waNumber, $textBody = '', $forceKonfirmasiIntro = false, $skipJamTutupCooldown = false)
    {
        $t = strtolower(trim($textBody ?? ''));
        $konfirmasiIntro = null;
        $konfirmasiPattern1 = '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis|b\s*s)(?:\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?)?\s*\??\s*$/i';
        $konfirmasiPattern2 = '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i';
        $konfirmasiPattern3 = '/\b(kapan\s*)?(jadwal\s*)?(terakhir|batas)\s*(terima|penerimaan)/i';  // "kapan jadwal terakhir penerimaan?"
        if ($forceKonfirmasiIntro || preg_match($konfirmasiPattern1, $t) || preg_match($konfirmasiPattern2, $t) || preg_match($konfirmasiPattern3, $t)) {
            $sapaan = $this->getSapaanForGreeting($waNumber);
            $konfirmasiReplies = [
                "Tunggu ya {$sapaan}, kami konfirmasi ke petugas dulu ya {$sapaan} 😊",
                "Tunggu ya {$sapaan}, kami tanyakan ke crew dulu ya {$sapaan} 😊",
                "Tunggu ya {$sapaan}, kami konfirmasi ke kasir dulu ya {$sapaan} 😊",
                "Tunggu ya {$sapaan}, kami cek ke petugas dulu ya {$sapaan} 😊",
            ];
            $konfirmasiIntro = $konfirmasiReplies[array_rand($konfirmasiReplies)];
        }

        $isOpen = $this->isOperatingHours();
        if ($isOpen) {
            $this->handleJam_buka($phoneIn, $waNumber, $textBody, $konfirmasiIntro);
            return true;
        }

        // Jam tutup: jawaban baku saja (tanpa konfirmasi intro)
        return $this->handleJam_tutup($phoneIn, $waNumber, $textBody, null, $skipJamTutupCooldown);
    }

    function handleJam_buka($phoneIn, $waNumber, $textBody = '', $customIntro = null)
    {
        try {
            $config = require __DIR__ . '/../Config/OperatingHours.php';
        } catch (\Throwable $e) {
            $this->sendAutoreplyText($waNumber, "Madinah Laundry buka setiap hari, pukul *07.00 s.d. 21.00*. Terima kasih 😊");
            return;
        }
        $openHour = str_pad($config['open_hour'], 2, '0', STR_PAD_LEFT);
        $openMin = str_pad($config['open_minute'], 2, '0', STR_PAD_LEFT);
        $closeHour = str_pad($config['close_hour'], 2, '0', STR_PAD_LEFT);
        $closeMin = str_pad($config['close_minute'], 2, '0', STR_PAD_LEFT);

        $openTime = "{$openHour}.{$openMin}";
        $closeTime = "{$closeHour}.{$closeMin}";

        // Working days string
        $workingDays = $config['working_days'];
        if (count($workingDays) == 7) {
            $daysStr = "setiap hari";
        } elseif (count($workingDays) == 6 && !in_array(7, $workingDays)) {
            $daysStr = "Senin-Sabtu";
        } else {
            $daysStr = "setiap hari";
        }

        // Check if today is a holiday (cek dulu untuk tentukan format pesan)
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $currentDate = $now->format('Y-m-d');
        $isHoliday = in_array($currentDate, $config['holidays']);

        // Jika "setiap hari" dan ada libur 10 hari ke depan, tambahkan pengecualian (JANGAN jika hari ini libur)
        $upcomingHolidays = '';
        try {
            $upcomingHolidays = $this->getUpcomingHolidaysMessage($config);
            if ($upcomingHolidays !== '' && $daysStr === 'setiap hari' && !$isHoliday) {
                $daysStr = 'setiap hari kecuali pada hari libur khusus';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Hari libur: format khusus (tanpa "kecuali hari libur khusus", dengan Catatan + Buka kembali + Terima kasih)
        if ($isHoliday) {
            $holidayMessage = $this->getHolidayFullMessage($config, $textBody);
            if ($holidayMessage !== '') {
                $textBaku = $holidayMessage;
            } else {
                // Fallback jika tidak ada range
                $timeBold = "*{$openTime} s.d. {$closeTime}*";
                $textBaku = $this->shouldSkipHolidayApologyIntro($textBody)
                    ? "Jam operasional {$timeBold}, {$daysStr}. 🙏"
                    : "Mohon maaf, hari ini kami libur. Jam operasional {$timeBold}, {$daysStr}. 🙏";
            }
        } else {
            // Bukan libur: format normal
            $timeBold = "*{$openTime} s.d. {$closeTime}*";
            $variations = [
                "Jam operasional {$timeBold}, {$daysStr}. 🕐😊",
                "Kami buka jam {$timeBold} ({$daysStr}). ⏰🙏",
                "Buka jam {$timeBold}, {$daysStr}. 📍😊",
                "Operasional jam {$timeBold}, {$daysStr}. 😊🙏",
                "Madinah Laundry buka jam {$timeBold}, {$daysStr}. 👍😊",
                "Jam buka {$timeBold}, {$daysStr}. 😊👋"
            ];
            $textBaku = $variations[array_rand($variations)];
            if ($upcomingHolidays !== '') {
                $textBaku .= $upcomingHolidays;
            }
        }

        // Custom intro (konfirmasi ke petugas) atau AI intro HANYA jika user menyapa dulu (sapaan + tanya jam)
        if (!empty($customIntro)) {
            $textBaku = $customIntro . "\n\n" . $textBaku;
        } elseif (!$isHoliday && $this->hasGreetingInMessage($textBody) && !$this->isBesokOrLiburDateQuestion($textBody) && ($isQuestion = $this->isJamOperasionalQuestion($textBody)) && class_exists('\\App\\Config\\AI') && \App\Config\AI::isEnabled()) {
            $ctx = $this->getGreetingContext($waNumber);
            $contactName = $ctx['contactName'];
            $sapaan = $ctx['sapaan'];
            try {
                $messages = [
                    ['role' => 'system', 'content' => "Kamu customer service Madinah Laundry. Customer bertanya 'masih buka?' / 'buka ga?' (bukan 'masih bisa terima kain').\n\nTugas: buat SATU kalimat pembuka singkat (max 8 kata) yang menjawab 'masih buka', diakhiri koma.\n\nWAJIB: Gunakan HANYA kata sapaan yang diberikan user (bukan kak secara default). Format: \"Masih buka {sapaan},\" atau \"Iya {sapaan}, masih buka,\"\n\nContoh (ganti {sapaan} dengan nilai yang diberikan user, jangan pakai kak jika user minta bg):\n- sapaan=kak: \"Masih buka kak,\"\n- sapaan=bg: \"Masih buka bg,\" atau \"Iya bg, masih buka,\"\n- sapaan=bang: \"Masih buka bang,\"\n\nPENTING: Pakai PERSIS sapaan dari pesan user. Jangan mengganti bg menjadi kak. Hanya output kalimat pembuka, diakhiri koma. JANGAN tambah info jam. JANGAN pakai tanda seru (!). JANGAN sebut nama customer."],
                    ['role' => 'user', 'content' => "Sapaan WAJIB: {$sapaan}\nNama (konteks, jangan sebut): \"{$contactName}\"\nPesan customer: \"{$textBody}\"\n\nBalasan baku yang mengikuti: \"{$textBaku}\"\n\nBuat HANYA kalimat pembuka singkat (diakhiri koma). Wajib memakai sapaan \"{$sapaan}\"."],
                ];
                $intro = trim($this->executeOpenAIRequestWithMessages($messages, 50));
                if (!empty($intro) && mb_strlen($intro) > 2) {
                    $intro = preg_replace('/!+$/', '', $intro);
                    $intro = $this->sanitizePembukaSapaanAiOutput($intro, $sapaan);
                    if (mb_substr($intro, -1) !== ',') {
                        $intro .= ',';
                    }
                    $textBaku = $intro . "\n\n" . $textBaku;
                }
            } catch (\Exception $e) {
                // Fallback: tetap pakai balasan baku saja
            }
        }

        $this->sendAutoreplyText($waNumber, $textBaku);
    }

    /**
     * Cek apakah pertanyaan tentang besok/libur/tanggal (intro "masih buka" tidak nyambung).
     * Contoh: "Soalnya besok libur kan?", "besok buka jam berapa?", "libur tgl berapa?"
     */
    private function isBesokOrLiburDateQuestion($textBody)
    {
        $t = strtolower(trim($textBody ?? ''));
        if ($t === '') return false;
        return preg_match('/\bbesok\b/i', $t)
            || preg_match('/\blibur\s*(tgl|tanggal|tgl\.|brp|berapa|kapan|hari)/i', $t)
            || preg_match('/\b(tgl|tanggal)\s*(brp|berapa)\s*(tutup|libur)/i', $t);
    }

    /**
     * Cek apakah pesan mengandung pertanyaan jam operasional (masih buka, buka ga, dll)
     */
    private function isJamOperasionalQuestion($textBody)
    {
        $t = strtolower(trim($textBody ?? ''));
        if ($t === '') return false;
        if (strpos($textBody ?? '', '?') !== false) return true;
        return preg_match('/\b(masih|masi|msih|apa|apakah|kapan|jam\s*br?p?|berapa)\s*(buka|buat)/i', $t)
            || preg_match('/\b(buka|buat)\s*(ga|gak|g\?|tidak|nggak|nya)\b/i', $t)
            || preg_match('/\b(masih|masi|msih)\s*(buka|buat|laundry|loundry)/i', $t);
    }

    /**
     * Balasan di luar jam operasional / tutup.
     * Cooldown handler JAM_TUTUP: 60 menit (sama JAM_OPERASIONAL) — satu pesan jenis ini per nomor
     * agar tidak dobel saat user kirim sapaan lalu tanya jam dalam waktu berdekatan.
     *
     * @return bool true jika ada pengiriman (atau fallback config), false jika dilewati karena cooldown
     */
    function handleJam_tutup($phoneIn, $waNumber, $textBody = '', $customIntro = null, $skipCooldown = false)
    {
        if (!$skipCooldown && !$this->shouldHandle($waNumber, 'JAM_TUTUP')) {
            return false;
        }

        try {
            $config = require __DIR__ . '/../Config/OperatingHours.php';
        } catch (\Throwable $e) {
            $this->sendAutoreplyText($waNumber, "Mohon maaf, kami sedang tutup. Buka setiap hari pukul *07.00 s.d. 21.00*. Terima kasih 🙏");
            return true;
        }
        $openHour = str_pad($config['open_hour'], 2, '0', STR_PAD_LEFT);
        $openMin = str_pad($config['open_minute'], 2, '0', STR_PAD_LEFT);
        $closeHour = str_pad($config['close_hour'], 2, '0', STR_PAD_LEFT);
        $closeMin = str_pad($config['close_minute'], 2, '0', STR_PAD_LEFT);

        $openTime = "{$openHour}.{$openMin}";
        $closeTime = "{$closeHour}.{$closeMin}";

        // Working days string
        $workingDays = $config['working_days'];
        if (count($workingDays) == 7) {
            $daysStr = "setiap hari";
        } elseif (count($workingDays) == 6 && !in_array(7, $workingDays)) {
            $daysStr = "Senin-Sabtu";
        } else {
            $daysStr = "setiap hari";
        }

        // Cek apakah hari ini libur (untuk pesan khusus)
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $currentDate = $now->format('Y-m-d');
        $isHoliday = in_array($currentDate, $config['holidays']);

        // Jika "setiap hari" dan ada libur 10 hari ke depan, tambahkan pengecualian (JANGAN jika hari ini libur)
        $upcomingHolidays = '';
        try {
            $upcomingHolidays = $this->getUpcomingHolidaysMessage($config);
            if ($upcomingHolidays !== '' && $daysStr === 'setiap hari' && !$isHoliday) {
                $daysStr = 'setiap hari kecuali pada hari libur khusus';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Hari libur: format khusus (tanpa "kecuali hari libur khusus", dengan Catatan + Buka kembali + Terima kasih)
        if ($isHoliday) {
            $holidayMessage = $this->getHolidayFullMessage($config, $textBody);
            if ($holidayMessage !== '') {
                $text = $holidayMessage;
            } else {
                $timeBold = "*{$openTime} s.d. {$closeTime}*";
                $text = $this->shouldSkipHolidayApologyIntro($textBody)
                    ? "Jam operasional {$timeBold}, {$daysStr}. 🙏"
                    : "Mohon maaf, hari ini kami libur. Jam operasional {$timeBold}, {$daysStr}. 🙏";
            }
        } else {
            $timeBold = "*{$openTime} s.d. {$closeTime}*";
            $variations = [
                "Mohon maaf, kami sedang tutup. Jam operasional {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, kami sedang di luar jam operasional. Buka jam {$timeBold}, {$daysStr}. 😊",
                "Maaf, saat ini kami sedang tutup. Jam buka {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, kami tutup. Buka jam {$timeBold}, {$daysStr}. 😊"
            ];
            $text = $variations[array_rand($variations)];
            if ($upcomingHolidays !== '') {
                $text .= $upcomingHolidays;
            }
        }
        if (!empty($customIntro)) {
            $text = $customIntro . "\n\n" . $text;
        }
        $this->sendAutoreplyText($waNumber, $text);

        return true;
    }

    function handleReminder($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'REMINDER')) {
            return;
        }
        try {
            $waService = $this->getWaService();

            $sql = 'SELECT * FROM reminder';

            try {
                $queryResult = DB::getInstance(0)->query($sql);
                $data = $queryResult ? $queryResult->result_array() : [];
            } catch (\Throwable $qe) {
                // Keep error log for critical failures
                \Log::write("handleReminder - Query ERROR: " . $qe->getMessage(), 'wa_error', 'Reminder');
                $text = "Tidak ada reminder yang ditemukan.";
                $this->sendQuotedFreeText($waNumber, $text);
                return;
            }

            // Collect all matching reminders
            $reminders = [];

            foreach ($data as $d) {
                $t1 = date_create($d['next_date']);
                $t2 = date_create(date("Y-m-d"));
                $diff = date_diff($t2, $t1);
                $selisih_hari = $diff->format('%R%a') + 0;

                $rentang = $d['range'];

                if ($selisih_hari <= $rentang) {
                    if ($selisih_hari > 0) {
                        $text_count = $selisih_hari . " Hari Lagi";
                    } elseif ($selisih_hari < 0) {
                        $text_count = "Terlewat " . $selisih_hari * -1 . " Hari";
                    } else {
                        $text_count = "Hari Ini";
                    }

                    $note = "";
                    if ($d['note'] <> "") {
                        $note = "\n" . $d['note'];
                    }

                    $ops_link = "https://api.nalju.com/Laundry/R/" . $d['id'];
                    $text = "*" . $d['name'] . "*" . $note . "\n" . $text_count . "\n" . $ops_link;

                    $reminders[] = $text;
                }
            }

            // Send all reminders to the requesting user
            if (!empty($reminders)) {
                $combined_text = implode("\n\n", $reminders);
                $res = $this->sendQuotedFreeText($waNumber, $combined_text);
            } else {
                // No reminders found
                $text = "Tidak ada reminder dalam rentang waktu.";
                $res = $this->sendQuotedFreeText($waNumber, $text);
            }
        } catch (\Exception $e) {
            \Log::write("handleReminder ERROR: " . $e->getMessage(), 'wa_error', 'Reminder');
            // Still try to send error message to user
            try {
                $waService = $this->getWaService();
                $this->sendQuotedFreeText($waNumber, "Maaf, terjadi kesalahan saat mengambil data reminder.");
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }

    /**
     * Access Key per user (tabel user.access_key).
     * - "key" → kirim key existing; jika null auto-generate 4 digit
     * - "key new" → generate key baru
     */
    function handleKey($phoneIn, $waNumber, $textBody = '')
    {
        try {
            $waService = $this->getWaService();
            $db = DB::getInstance(1); // laundry

            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));
            $phoneInStr = "'" . implode("','", array_map('addslashes', $phones)) . "'";

            $user = $db->query(
                "SELECT id_user, access_key, nama_user FROM user WHERE no_user IN ($phoneInStr) AND en = 1 LIMIT 1"
            )->row_array();

            if (empty($user['id_user'])) {
                $this->logAutoreplyTrace($waNumber, 'KEY', 'no_karyawan');
                return;
            }

            $forceNew = (bool) preg_match('/^\s*key\s+new\s*$/i', trim((string) $textBody));
            $current = isset($user['access_key']) ? trim((string) $user['access_key']) : '';
            $needGenerate = $forceNew || $current === '' || !preg_match('/^\d{4}$/', $current);

            if ($needGenerate) {
                $newKey = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                $idUser = (int) $user['id_user'];
                $escaped = addslashes($newKey);
                $db->query("UPDATE user SET access_key = '$escaped' WHERE id_user = $idUser AND en = 1 LIMIT 1");
                $current = $newKey;
            }

            $text = "*Access Key* Anda: *" . $current . "*\n\n";
            $text .= "Jika Anda mencurigai key diketahui orang lain, ketik *key new* untuk generate key baru.";

            $this->sendQuotedFreeText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write("handleKey ERROR: " . $e->getMessage(), 'wa_error', 'AccessKey');
        }
    }

    function handleKas_laundry($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'KAS_LAUNDRY')) {
            return;
        }
        try {
            $db1 = DB::getInstance(1);
            $cabangs = $db1->query("SELECT * FROM cabang")->result_array();

            $data = [];
            foreach ($cabangs as $a) {
                $id_cabang = $a['id_cabang'];
                $kode_cabang = $a['kode_cabang'];

                $where_kredit = "id_cabang = $id_cabang AND jenis_transaksi IN (1,3,6,7) AND jenis_mutasi = 1 AND metode_mutasi = 1 AND status_mutasi <> 4";
                $kredit_result = $db1->query("SELECT SUM(jumlah) as jumlah FROM kas WHERE $where_kredit")->row_array();
                $jumlah_kredit = $kredit_result['jumlah'] ?? 0;

                $where_debit = "id_cabang = $id_cabang AND jenis_transaksi IN (2,4,5) AND jenis_mutasi = 2 AND metode_mutasi = 1 AND status_mutasi <> 4";
                $debit_result = $db1->query("SELECT SUM(jumlah) as jumlah FROM kas WHERE $where_debit")->row_array();
                $jumlah_debit = $debit_result['jumlah'] ?? 0;

                $saldo = $jumlah_kredit - $jumlah_debit;
                $data[] = ['kode' => $kode_cabang, 'saldo' => $saldo];
            }

            $text = "";
            foreach ($data as $item) {
                if ($item['saldo'] >= 1000000) {
                    if (strlen($text) == 0) {
                        $text = "*" . $item['kode'] . "* Rp" . number_format($item['saldo']);
                    } else {
                        $text .= "\n*" . $item['kode'] . "* Rp" . number_format($item['saldo']);
                    }
                }
            }

            $waService = $this->getWaService();
            if (strlen($text) > 0) {
                $this->sendQuotedFreeText($waNumber, $text);
            } else {
                $this->sendQuotedFreeText($waNumber, "Semua kas cabang di bawah Rp1.000.000");
            }
        } catch (\Throwable $e) {
            \Log::write("handleKas_laundry ERROR: " . $e->getMessage(), 'wa_error', 'Kas');
        }
    }

    /**
     * Handle intent KARYAWAN - cari data karyawan dari tabel user (en=1) db(1).
     * Alur: regex exact match nama_user → jika tidak ketemu, AI fuzzy match → jika gagal, balas maaf.
     */
    function handleKaryawan($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'KARYAWAN')) {
            return;
        }
        if (!preg_match('/^\s*(karyawan|crew|staf+|staff)\s+(.+)\s*$/i', $textBody, $m)) {
            return;
        }
        $namaKaryawan = trim($m[2]);
        if ($namaKaryawan === '') {
            return;
        }

        $db1 = DB::getInstance(1);
        $db0 = DB::getInstance(0);
        $users = $db1->query("SELECT id_user, no_user, nama_user, bank_code, bank_account_number, bank_account_name FROM user WHERE en = 1")->result_array();

        // 1. Regex: exact match (case insensitive)
        $found = null;
        foreach ($users as $u) {
            if (strcasecmp(trim($u['nama_user'] ?? ''), $namaKaryawan) === 0) {
                $found = $u;
                break;
            }
        }

        // 2. Jika tidak ketemu: AI cari nama yang mirip
        if (!$found && class_exists('\\App\\Config\\AI') && \App\Config\AI::isEnabled()) {
            $namaList = array_map(function ($u) { return trim($u['nama_user'] ?? ''); }, $users);
            $namaListStr = implode(', ', array_filter($namaList));
            $messages = [
                ['role' => 'system', 'content' => "Kamu pencari nama. User mencari karyawan: \"{$namaKaryawan}\". Daftar nama yang tersedia: {$namaListStr}.\n\nTugas: pilih SATU nama dari daftar yang PALING MIRIP dengan yang dicari (typo, singkatan, ejaan mirip). Jika tidak ada yang mirip sama sekali, jawab: TIDAK_KETEMU.\n\nJawab HANYA nama yang dipilih (persis dari daftar) atau TIDAK_KETEMU."],
                ['role' => 'user', 'content' => "Nama yang dicari: \"{$namaKaryawan}\""],
            ];
            try {
                $answer = trim($this->executeOpenAIRequestWithMessages($messages, 20));
                if (strtoupper($answer) !== 'TIDAK_KETEMU' && $answer !== '') {
                    foreach ($users as $u) {
                        if (strcasecmp(trim($u['nama_user'] ?? ''), trim((string) $answer)) === 0) {
                            $found = $u;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // AI gagal, tetap pakai found = null
            }
        }

        $waService = $this->getWaService();
        if (!$found) {
            $this->sendQuotedFreeText($waNumber, "Maaf, data karyawan tidak ditemukan.");
            return;
        }

        $text = $this->formatKaryawanReply($found, $db0);
        $this->sendQuotedFreeText($waNumber, $text);
    }

    /**
     * Handle intent SKEMA_GAJI untuk karyawan sendiri, atau admin: "skema {id_karyawan}".
     * Nilai Cuci/Malam adalah minimum efektif: max(clamp minimum global, fee khusus karyawan).
     */
    function handleSkema_gaji($phoneIn, $waNumber, $textBody = '')
    {
        $ctx = $this->ensureSenderContext($waNumber);
        $idUser = (int) ($ctx['id_karyawan'] ?? 0);
        $isAdmin = !empty($ctx['is_admin']);
        if (!$this->intentLabMode && (!$isAdmin && (empty($ctx['is_karyawan']) || $idUser < 1))) {
            $this->logAutoreplyTrace($waNumber, 'SKEMA_GAJI', 'require_karyawan_or_admin');
            return;
        }

        if ($isAdmin && preg_match('/^\s*skema\s+(\d+)\s*$/iu', (string) $textBody, $m)) {
            $idUser = (int) $m[1];
        }
        if ($idUser < 1) {
            $this->logAutoreplyTrace($waNumber, 'SKEMA_GAJI', 'missing_target_user');
            return;
        }

        try {
            $db = DB::getInstance(1); // database laundry
            $user = $idUser > 0
                ? $db->query('SELECT id_user, nama_user FROM user WHERE id_user = ? AND en = 1 LIMIT 1', [$idUser])->row_array()
                : null;
            if (empty($user['id_user'])) {
                $this->logAutoreplyTrace($waNumber, 'SKEMA_GAJI', 'user_not_found');
                return;
            }

            $serviceRows = $db->query(
                "SELECT r.gaji_laundry, r.target, r.max_target, r.bonus_target,
                        COALESCE(p.penjualan_jenis, CONCAT('Jenis ', r.jenis_penjualan)) AS penjualan,
                        COALESCE(l.layanan, CONCAT('Layanan ', r.id_layanan)) AS layanan,
                        COALESCE(s.nama_satuan, 'qty') AS satuan
                 FROM gaji_laundry_ref r
                 LEFT JOIN penjualan_jenis p ON p.id_penjualan_jenis = r.jenis_penjualan
                 LEFT JOIN layanan l ON l.id_layanan = r.id_layanan
                 LEFT JOIN satuan s ON s.id_satuan = p.id_satuan
                 ORDER BY r.jenis_penjualan ASC, r.id_layanan ASC"
            )->result_array();

            $globalRef = [1 => 0, 2 => 0];
            foreach ($db->query('SELECT id_pengali, gaji_pengali FROM gaji_pengali_ref WHERE id_pengali IN (1, 2)')->result_array() as $row) {
                $globalRef[(int) ($row['id_pengali'] ?? 0)] = (int) ($row['gaji_pengali'] ?? 0);
            }

            $feeKaryawan = [3 => 0, 4 => 0, 5 => 0, 6 => 0];
            foreach ($db->query(
                'SELECT id_pengali, gaji_pengali FROM gaji_pengali WHERE id_karyawan = ? AND id_pengali IN (3, 4, 5, 6)',
                [$idUser]
            )->result_array() as $row) {
                $feeKaryawan[(int) ($row['id_pengali'] ?? 0)] = (int) ($row['gaji_pengali'] ?? 0);
            }

            $rupiah = static function ($amount): string {
                return 'Rp' . number_format((int) $amount, 0, ',', '.');
            };
            $nama = trim((string) ($user['nama_user'] ?? 'Karyawan'));
            $lines = ['*SKEMA PEMBAYARAN KERJA*', 'NAMA: *' . mb_strtoupper($nama, 'UTF-8') . '*', '', '*FEE LAYANAN*'];

            if ($serviceRows === []) {
                $lines[] = '- Belum ada fee layanan yang diatur.';
            } else {
                foreach ($serviceRows as $row) {
                    $unit = trim((string) ($row['satuan'] ?? ''));
                    $unit = $unit !== '' ? $unit : 'qty';
                    $target = (int) ($row['target'] ?? 0);
                    $maxTarget = (int) ($row['max_target'] ?? 0);
                    $bonusTarget = (int) ($row['bonus_target'] ?? 0);
                    $lines[] = '';
                    $lines[] = mb_strtoupper(trim((string) ($row['penjualan'] ?? '')), 'UTF-8')
                        . ' — ' . trim((string) ($row['layanan'] ?? ''));
                    $lines[] = 'Fee: ' . $rupiah($row['gaji_laundry'] ?? 0) . '/' . $unit;
                    if ($target > 0) {
                        $lines[] = 'Target: ' . number_format($target, 0, ',', '.') . ' ' . $unit;
                    }
                    if ($bonusTarget > 0) {
                        $lines[] = 'Bonus: ' . $rupiah($bonusTarget) . '/target';
                    }
                    if ($target > 0 && $bonusTarget > 0 && $maxTarget > 0) {
                        $lines[] = 'Maks. target bonus: ' . number_format($maxTarget, 0, ',', '.') . ' ' . $unit;
                    }
                }
            }

            $lines = array_merge($lines, [
                '', '*FEE LAUNDRY*',
                'Terima: ' . $rupiah($globalRef[1]) . '/nota',
                'Kembali: ' . $rupiah($globalRef[2]) . '/nota',
                '', '*TUNJANGAN BULANAN*',
                $rupiah($feeKaryawan[4]) . '/bulan',
                '', '*POTONGAN*',
                'Kasbon akan dikurangi dari gaji periode terkait.',
                '', '*FEE ABSENSI*',
                'Harian: ' . $rupiah($feeKaryawan[3]) . '/hari',
                '', '*Cuci*',
                'Ketik: _Fee Cuci {KODE_CABANG}_',
                '', '*Jaga Malam*',
                'Ketik: _Fee Malam {KODE_CABANG}_',
            ]);

            $this->sendQuotedFreeText($waNumber, implode("\n", $lines));
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'SKEMA_GAJI', 'error=' . mb_substr($e->getMessage(), 0, 120));
            if (class_exists('\Log')) {
                \Log::write('handleSkema_gaji ERROR: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Handle FEE CUCI|MALAM {KODE_CABANG}; admin dapat menambahkan ID karyawan setelah FEE.
     * Contoh admin: FEE 123 CUCI KNO.
     */
    function handleFee($phoneIn, $waNumber, $textBody = '')
    {
        $ctx = $this->ensureSenderContext($waNumber);
        $isAdmin = !empty($ctx['is_admin']);
        $ownUserId = (int) ($ctx['id_karyawan'] ?? 0);
        $textBody = trim((string) $textBody);
        $help = $isAdmin
            ? "Format: *Fee {ID_KARYAWAN} Cuci {KODE_CABANG}*\nContoh: *Fee 123 Cuci KNO*"
            : "Format: *Fee Cuci {KODE_CABANG}*\nContoh: *Fee Cuci KNO*";

        if (!$this->intentLabMode && !$isAdmin && (empty($ctx['is_karyawan']) || $ownUserId < 1)) {
            $this->logAutoreplyTrace($waNumber, 'FEE', 'require_karyawan_or_admin');
            return;
        }
        if (!preg_match('/^\s*fee\s+(?:(\d+)\s+)?(cuci|malam)\s+([a-z0-9_-]+)\s*$/iu', $textBody, $m)) {
            $this->sendQuotedFreeText($waNumber, $help);
            return;
        }

        $targetId = !empty($m[1]) ? (int) $m[1] : $ownUserId;
        if (!$isAdmin && !empty($m[1])) {
            $this->sendQuotedFreeText($waNumber, $help);
            return;
        }
        if ($targetId < 1) {
            $this->sendQuotedFreeText($waNumber, $help);
            return;
        }

        $jenis = mb_strtolower((string) $m[2], 'UTF-8');
        $kodeCabang = mb_strtoupper(trim((string) $m[3]), 'UTF-8');
        $idPengali = $jenis === 'cuci' ? 6 : 5;
        $kodeFormula = $jenis === 'cuci' ? 'cuci' : 'malam';
        $label = $jenis === 'cuci' ? 'CUCI' : 'JAGA MALAM';
        $satuan = $jenis === 'cuci' ? 'hari' : 'malam';
        $defaults = $jenis === 'cuci'
            ? ['pengali' => 4.0, 'min' => 65000, 'max' => 85000]
            : ['pengali' => 1.0, 'min' => 14000, 'max' => 32000];

        try {
            $db = DB::getInstance(1);
            $user = $db->query('SELECT id_user, nama_user FROM user WHERE id_user = ? AND en = 1 LIMIT 1', [$targetId])->row_array();
            $cabang = $db->query('SELECT id_cabang, kode_cabang FROM cabang WHERE UPPER(kode_cabang) = ? LIMIT 1', [$kodeCabang])->row_array();
            if (empty($user['id_user']) || empty($cabang['id_cabang'])) {
                $this->sendQuotedFreeText($waNumber, empty($user['id_user']) ? 'Karyawan tidak ditemukan.' : 'Kode cabang tidak ditemukan.');
                return;
            }

            $formula = $db->query('SELECT pengali, clamp_min, clamp_max FROM gaji_fee_formula WHERE kode = ? LIMIT 1', [$kodeFormula])->row_array();
            $pengali = isset($formula['pengali']) && (float) $formula['pengali'] > 0 ? (float) $formula['pengali'] : $defaults['pengali'];
            $min = isset($formula['clamp_min']) ? (int) $formula['clamp_min'] : $defaults['min'];
            $max = isset($formula['clamp_max']) ? (int) $formula['clamp_max'] : $defaults['max'];
            if ($min > $max) { $min = $defaults['min']; $max = $defaults['max']; }

            $period = date('Y-m', strtotime('first day of last month'));
            $snapshot = $db->query(
                'SELECT total_pendapatan FROM rekap_snapshot WHERE periode = ? AND mode = 2 AND id_cabang = ? LIMIT 1',
                [$period, (int) $cabang['id_cabang']]
            )->row_array();
            $pendapatan = isset($snapshot['total_pendapatan']) ? (int) $snapshot['total_pendapatan'] : null;
            $feeCabang = $pendapatan === null ? $min : (int) round(($pendapatan / 1000) * $pengali);
            $feeCabang = max($min, min($max, $feeCabang));
            $personal = $db->query(
                'SELECT gaji_pengali FROM gaji_pengali WHERE id_karyawan = ? AND id_pengali = ? LIMIT 1',
                [$targetId, $idPengali]
            )->row_array();
            $minimumPribadi = max($min, (int) ($personal['gaji_pengali'] ?? 0));
            $feeBerlaku = max($feeCabang, $minimumPribadi);
            $rp = static function ($v): string { return 'Rp' . number_format((int) $v, 0, ',', '.'); };

            $lines = [
                '*FEE ' . $label . '*',
                'NAMA: *' . mb_strtoupper(trim((string) $user['nama_user']), 'UTF-8') . '* #' . (int) $user['id_user'],
                'CABANG: *' . mb_strtoupper((string) $cabang['kode_cabang'], 'UTF-8') . '*',
                '',
                'Fee cabang: ' . $rp($feeCabang) . '/' . $satuan,
                'Minimum pribadi: ' . $rp($minimumPribadi) . '/' . $satuan,
                '',
                '*FEE BERLAKU: ' . $rp($feeBerlaku) . '/' . $satuan . '*',
                '',
                '_Crew dapat mengajukan kenaikan fee di cabang tertentu untuk keperluan transportasi._',
            ];
            $this->sendQuotedFreeText($waNumber, implode("\n", $lines));
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'FEE', 'error=' . mb_substr($e->getMessage(), 0, 120));
            if (class_exists('\Log')) {
                \Log::write('handleFee ERROR: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Format data karyawan untuk balasan WA (no_user, nama_user, bank_code, bank_account_number, bank_account_name)
     */
    private function formatKaryawanReply($row, $db0)
    {
        $id_user = (int) ($row['id_user'] ?? 0);
        $no_user = $row['no_user'] ?? '';
        $nama_user = trim((string) ($row['nama_user'] ?? ''));
        $bank_code = trim($row['bank_code'] ?? '');
        $bank_account_number = trim($row['bank_account_number'] ?? '');
        $bank_account_name = trim($row['bank_account_name'] ?? '');
        if ($bank_account_name !== '') {
            $bank_account_name = ucwords(mb_strtolower($bank_account_name));
        }

        $bankName = '';
        if (!empty($bank_code)) {
            try {
                $banks = $db0->query("SELECT name FROM banks WHERE bank_code = ? LIMIT 1", [$bank_code])->result_array();
                if (!empty($banks)) {
                    $bankName = ' (' . trim($banks[0]['name'] ?? '') . ')';
                }
            } catch (\Exception $e) {
                // ignore
            }
        }

        $lines = [
            "*" . mb_strtoupper($nama_user, 'UTF-8') . "* #{$id_user}",
            "No. HP: {$no_user}",
            "Bank: " . ($bank_code ?: '-') . $bankName,
            "No. Rek: " . ($bank_account_number ?: '-'),
            "A/N: " . ($bank_account_name ?: '-'),
        ];
        return implode("\n", $lines);
    }

    function handleSlip_gaji($phoneIn, $waNumber, $textBody = '')
    {
        $parts = preg_split('/\s+/', $textBody);
        $id_user = isset($parts[1]) ? intval($parts[1]) : null;

        if($id_user != null) {
            // slip {id} = lihat gaji orang lain — hanya admin (gerbang intent bisa is_karyawan untuk slip sendiri)
            if (empty($this->senderContext['is_admin'])) {
                return;
            }
            $skipPhoneCheck = true;
        } else {
            $skipPhoneCheck = false;
        }

        $waService = null;
        $sendErrorToWa = function ($msg) use (&$waService, $waNumber) {
            try {
                if (!$waService) {
                    $waService = $this->getWaService();
                }
                if ($waService) {
                    $this->sendQuotedFreeText($waNumber, $msg);
                }
            } catch (\Throwable $ex) {
                \Log::write("handleSlip_gaji: Failed to send error message - " . $ex->getMessage(), 'wa_error', 'SlipGaji');
            }
        };

        try {
            $waService = $this->getWaService();
        } catch (\Throwable $e) {
            \Log::write("handleSlip_gaji: getWaService failed - " . $e->getMessage(), 'wa_error', 'SlipGaji');
        }

        try {
            // Parse phone numbers
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));
            $phoneInStr = "'" . implode("','", array_map(function($p) {
                return addslashes($p);
            }, $phones)) . "'";

            // Tentukan periode berdasarkan tanggal hari ini (harus di awal, dipakai query payroll & gaji_result)
            $hariIni = (int)date('d');
            if ($hariIni >= 1 && $hariIni <= 5) {
                $date = date('Y-m', strtotime('-1 month'));
            } else {
                $date = date('Y-m');
            }
            $dateOn = $date;

            // db(0) = mdl_main (central) → payroll
            // db(1) = mdl_laundry → user, cabang, gaji_result, banks
            $dbMain = DB::getInstance(0);   // db(0) untuk payroll saja
            $dbLaundry = DB::getInstance(1); // db(1) untuk tabel lainnya
            
            $user = null;
            $id_cabang = 0;
            
            try {
                if ($skipPhoneCheck && $id_user !== null) {
                    // Admin request dengan ID user spesifik - ambil langsung by ID
                    $users = $dbLaundry->query("SELECT id_user, nama_user, id_cabang, bank_code, bank_account_number, bank_account_name FROM user WHERE id_user = ?", [$id_user])->result_array();
                } else {
                    // User biasa - cari by nomor HP
                    $users = $dbLaundry->query("SELECT id_user, nama_user, id_cabang, bank_code, bank_account_number, bank_account_name FROM user WHERE no_user IN ($phoneInStr) LIMIT 1")->result_array();
                }
                
                if (empty($users)) {
                    $this->logAutoreplyTrace($waNumber, 'SLIP_GAJI', 'no_karyawan');
                    return;
                }
                $user = $users[0];
                $id_cabang = $user['id_cabang'] ?? 0;
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query user failed - " . $e->getMessage() . " | SQL: SELECT id_user, nama_user, id_cabang FROM user WHERE no_user IN ($phoneInStr) LIMIT 1", 'wa_error', 'SlipGaji');
                throw $e;
            }
            
            if (!$user || !isset($user['id_user'])) {
                $this->logAutoreplyTrace($waNumber, 'SLIP_GAJI', 'no_karyawan');
                return;
            }
            
            $id_user = (int)$user['id_user'];
            $nama_user = trim((string) ($user['nama_user'] ?? 'Karyawan'));
            
            // Ambil data rekening pencairan dari tabel payroll (db(0))
            $payroll = $dbMain->query("SELECT id, state, bank_code, bank_acc_number, bank_acc_name FROM payroll WHERE employee_id = ? AND period = ? AND business = 'laundry' LIMIT 1", [$id_user, $date])->row_array();
            $p_id = $payroll['id'] ?? '-';
            $p_state = strtoupper($payroll['state'] ?? 'PENDING');
            
            if ($payroll) {
                $bank_code = trim($payroll['bank_code'] ?? '');
                $bank_account_number = trim($payroll['bank_acc_number'] ?? '');
                $bank_account_name = trim($payroll['bank_acc_name'] ?? '');
            } else {
                // Fallback ke data user (db(1))
                $bank_code = trim($user['bank_code'] ?? '');
                $bank_account_number = trim($user['bank_account_number'] ?? '');
                $bank_account_name = trim($user['bank_account_name'] ?? '');
            }
            
            // Cek apakah data rekening lengkap (untuk menentukan Cash atau Bank)
            $rekeningLengkap = !empty($bank_code) && !empty($bank_account_number) && !empty($bank_account_name);
            
            // Ambil nama bank dari tabel banks di db(0) jika bank_code ada
            $nama_bank = '';
            if ($rekeningLengkap && !empty($bank_code)) {
                try {
                    $banks = $dbMain->query("SELECT name FROM banks WHERE bank_code = ? LIMIT 1", [$bank_code])->result_array();
                    if (!empty($banks)) {
                        $nama_bank = $banks[0]['name'] ?? '';
                    }
                } catch (\Throwable $e) {
                    \Log::write("handleSlip_gaji: Query banks failed - " . $e->getMessage(), 'wa_error', 'SlipGaji');
                    // Continue tanpa nama bank
                }
            }

            // Ambil data cabang untuk nama cabang (dari db(1) - laundry database)
            $nama_cabang = 'Cabang';
            $kode_cabang = '';
            if ($id_cabang > 0) {
                try {
                    $cabangs = $dbLaundry->query("SELECT nama, kode_cabang FROM cabang WHERE id_cabang = " . (int)$id_cabang)->result_array();
                    if (!empty($cabangs)) {
                        $cabang = $cabangs[0];
                        $nama_cabang = $cabang['nama'] ?? 'Cabang';
                        $kode_cabang = $cabang['kode_cabang'] ?? '';
                    }
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query cabang failed - " . $e->getMessage(), 'wa_error', 'SlipGaji');
                    // Continue dengan default values
                }
            }

            // Query data gaji_result dari db(1) - database laundry (bukan db(0))
            try {
                $gajiQuery = "SELECT * FROM gaji_result WHERE tgl = ? AND id_karyawan = ? ORDER BY tipe ASC";
                $gajiResults = $dbLaundry->query($gajiQuery, [$date, $id_user])->result_array();
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query gaji_result failed - " . $e->getMessage() . " | Query: " . $gajiQuery . " | Date: $date | ID User: $id_user", 'wa_error', 'SlipGaji');
                throw $e;
            }

            if (empty($gajiResults)) {
                $this->sendQuotedFreeText($waNumber, "Belum ada data untuk periode " . $date . ".\nSilakan tunggu penetapan gaji.");
                return;
            }

            // Format slip gaji
            $text = "*" . strtoupper($nama_cabang) . " - " . $kode_cabang . "*\n";
            $text .= "*-- SALARY SLIP [".$p_state."] --*\n";
            $text .= "\n";
            $text .= "*" . strtoupper($nama_user) . "* #" . $id_user . "\n";
            $text .= "Periode: *" . $dateOn . "*\n";
            $text .= "ID Payroll: *#" . $p_id . "*\n";
            $text .= "────────────────\n\n";

            $totalGaji = 0;
            $totalPot = 0;

            foreach ($gajiResults as $gf) {
                $jGaji = (float)($gf['jumlah'] ?? 0);
                $ref = $gf['ref'] ?? '';
                $deskripsi = $gf['deskripsi'] ?? '';
                $qty = (int)($gf['qty'] ?? 0);

                if ((int)($gf['tipe'] ?? 0) == 1) {
                    $totalGaji += $jGaji;
                    $vGaji = "Rp" . number_format($jGaji, 0, ',', '.');
                } else {
                    $totalPot += $jGaji;
                    $vGaji = "-Rp" . number_format($jGaji, 0, ',', '.');
                }

                $text .= $deskripsi . "\n";
                $text .= $qty . "x " . $vGaji . "\n";
                $text .= "\n";
            }

            $totalTer = $totalGaji - $totalPot;

            $text .= "────────────────\n";
            $text .= "*Total: Rp" . number_format($totalGaji, 0, ',', '.') . "*\n";
            $text .= "Potongan: -Rp" . number_format($totalPot, 0, ',', '.') . "\n";
            $text .= "Diterima: Rp" . number_format($totalTer, 0, ',', '.')."\n";
            $text .= "\n";
            
            // Tambahkan informasi rekening pencairan
            $text .= "────────────────\n";
            $text .= "Pencairan:\n";
            if ($rekeningLengkap) {
                // Data rekening lengkap - tampilkan informasi bank
                $text .= "*" . $nama_bank . "* \n";
                $text .= "*" . $bank_account_number . "* \n";
                $text .= "*" . strtoupper($bank_account_name) . "*";
            } else {
                // Data rekening tidak lengkap - tampilkan Cash
                $text .= "*Cash*";
            }

            // Kirim pesan
            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Throwable $e) {
            $errorMsg = "handleSlip_gaji ERROR: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine();
            if (method_exists($e, 'getTraceAsString')) {
                $errorMsg .= "\nStack: " . $e->getTraceAsString();
            }
            \Log::write($errorMsg, 'wa_error', 'SlipGaji');
            \Log::write("handleSlip_gaji: phoneIn=$phoneIn, waNumber=$waNumber", 'wa_error', 'SlipGaji');
            $sendErrorToWa("Maaf, terjadi kesalahan saat mengambil data slip gaji.\nSilakan hubungi admin.");
        }
    }

    function handleGaji_cash($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'GAJI_CASH')) {
            return;
        }
        $waService = null;
        $sendErrorToWa = function ($msg) use (&$waService, $waNumber) {
            try {
                if (!$waService) {
                    $waService = $this->getWaService();
                }
                if ($waService) {
                    $this->sendQuotedFreeText($waNumber, $msg);
                }
            } catch (\Throwable $ex) {
                \Log::write("handleGaji_cash: Failed to send error message - " . $ex->getMessage(), 'wa_error', 'GajiCash');
            }
        };

        try {
            $waService = $this->getWaService();
        } catch (\Throwable $e) {
            \Log::write("handleGaji_cash: getWaService failed - " . $e->getMessage(), 'wa_error', 'GajiCash');
        }

        try {
            // Tentukan periode berdasarkan tanggal hari ini (sama dengan handleSlip_gaji)
            $hariIni = (int)date('d');
            if ($hariIni >= 1 && $hariIni <= 5) {
                // Jika tanggal 1-5, gunakan bulan lalu
                $period = date('Y-m', strtotime('-1 month'));
            } else {
                // Jika tanggal > 5, gunakan bulan ini
                $period = date('Y-m');
            }

            $dbMain = DB::getInstance(0);   // db(0) untuk payroll
            $dbLaundry = DB::getInstance(1); // db(1) untuk tabel lainnya

            // Query payroll dari db(0) - draft & approved dengan bank_code kosong (Cash)
            $cashPayrolls = $dbMain->query(
                "SELECT id, employee_id, amount, state FROM payroll WHERE period = ? AND business = 'laundry' AND state IN ('approved', 'draft') AND (bank_code = '' OR bank_code IS NULL)",
                [$period]
            )->result_array();

            if (empty($cashPayrolls)) {
                $this->sendQuotedFreeText($waNumber, "Tidak ada data gaji cash untuk periode " . $period);
                return;
            }

            $draftCount = 0;
            foreach ($cashPayrolls as $p) {
                if (strtolower($p['state'] ?? '') === 'draft') $draftCount++;
            }
            $statusLabel = $draftCount === 0 ? "Status: APPROVED ✅" : ($draftCount === count($cashPayrolls) ? "Status: DRAFT 🟨" : "Status: DRAFT & APPROVED 🟡");

            // Build message
            $text = "*GAJI CASH - " . strtoupper($period) . "*\n";
            $text .= "────────────────\n";
            $text .= $statusLabel . "\n\n";

            $total = 0;
            $count = 0;

            foreach ($cashPayrolls as $p) {
                $employeeId = (int)$p['employee_id'];
                $amount = (float)$p['amount'];
                $payrollId = (int)$p['id'];
                $pState = strtoupper($p['state'] ?? '');

                $simState = $pState == 'APPROVED' ? '✅' : ($pState == 'DRAFT' ? '🟨' : '🟡');

                // Ambil nama karyawan dari db(1) - tabel user
                $user = $dbLaundry->query("SELECT nama_user FROM user WHERE id_user = ? LIMIT 1", [$employeeId])->row_array();
                $namaUser = trim((string) ($user['nama_user'] ?? 'Unknown'));

                $count++;
                $total += $amount;

                $text .= $simState . " *" . strtoupper($namaUser) . "* #" . $employeeId . "\n";
                $text .= "Rp" . number_format($amount, 0, ',', '.') . "\n\n";
            }

            $text .= "────────────────\n";
            $text .= "*Total Cash:* Rp" . number_format($total, 0, ',', '.') . "\n";
            $text .= "*Jumlah:* " . $count . " orang";

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }

        } catch (\Throwable $e) {
            \Log::write("handleGaji_cash ERROR: " . $e->getMessage(), 'wa_error', 'GajiCash');
            $sendErrorToWa("Maaf, terjadi kesalahan saat mengambil data gaji cash.\nSilakan hubungi admin.");
        }
    }

    /**
     * Daftar gaji transfer (non-cash): payroll dengan bank_code terisi — sama periode & akses admin dengan gaji cash.
     */
    function handleGaji_tf($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'GAJI_TF')) {
            return;
        }
        $waService = null;
        $sendErrorToWa = function ($msg) use (&$waService, $waNumber) {
            try {
                if (!$waService) {
                    $waService = $this->getWaService();
                }
                if ($waService) {
                    $this->sendQuotedFreeText($waNumber, $msg);
                }
            } catch (\Throwable $ex) {
                \Log::write("handleGaji_tf: Failed to send error message - " . $ex->getMessage(), 'wa_error', 'GajiTf');
            }
        };

        try {
            $waService = $this->getWaService();
        } catch (\Throwable $e) {
            \Log::write("handleGaji_tf: getWaService failed - " . $e->getMessage(), 'wa_error', 'GajiTf');
        }

        try {
            $hariIni = (int)date('d');
            if ($hariIni >= 1 && $hariIni <= 5) {
                $period = date('Y-m', strtotime('-1 month'));
            } else {
                $period = date('Y-m');
            }

            $dbMain = DB::getInstance(0);
            $dbLaundry = DB::getInstance(1);

            $tfPayrolls = $dbMain->query(
                "SELECT id, employee_id, amount, state, bank_code, bank_acc_number, bank_acc_name FROM payroll WHERE period = ? AND business = 'laundry' AND state IN ('approved', 'draft') AND bank_code IS NOT NULL AND TRIM(bank_code) != ''",
                [$period]
            )->result_array();

            if (empty($tfPayrolls)) {
                $this->sendQuotedFreeText($waNumber, "Tidak ada data gaji transfer untuk periode " . $period);
                return;
            }

            $draftCount = 0;
            foreach ($tfPayrolls as $p) {
                if (strtolower($p['state'] ?? '') === 'draft') {
                    $draftCount++;
                }
            }
            $statusLabel = $draftCount === 0 ? "Status: APPROVED ✅" : ($draftCount === count($tfPayrolls) ? "Status: DRAFT 🟨" : "Status: DRAFT & APPROVED 🟡");

            $text = "*GAJI TF - " . strtoupper($period) . "*\n";
            $text .= "────────────────\n";
            $text .= $statusLabel . "\n\n";

            $total = 0;
            $count = 0;

            foreach ($tfPayrolls as $p) {
                $employeeId = (int)$p['employee_id'];
                $amount = (float)$p['amount'];
                $pState = strtoupper($p['state'] ?? '');
                $simState = $pState == 'APPROVED' ? '✅' : ($pState == 'DRAFT' ? '🟨' : '🟡');

                $user = $dbLaundry->query("SELECT nama_user FROM user WHERE id_user = ? LIMIT 1", [$employeeId])->row_array();
                $namaUser = trim((string) ($user['nama_user'] ?? 'Unknown'));

                $bc = strtoupper(trim($p['bank_code'] ?? ''));
                $ban = trim($p['bank_acc_number'] ?? '');
                $banm = trim($p['bank_acc_name'] ?? '');

                $count++;
                $total += $amount;

                $text .= $simState . " *" . strtoupper($namaUser) . "* #" . $employeeId . "\n";
                $text .= "Rp" . number_format($amount, 0, ',', '.') . "\n";
                $text .= "🏦 " . $bc . ($ban !== '' ? " · " . $ban : '') . "\n";
                if ($banm !== '') {
                    $text .= "a.n. " . strtoupper($banm) . "\n";
                }
                $text .= "\n";
            }

            $text .= "────────────────\n";
            $text .= "*Total Transfer:* Rp" . number_format($total, 0, ',', '.') . "\n";
            $text .= "*Jumlah:* " . $count . " orang";

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Throwable $e) {
            \Log::write("handleGaji_tf ERROR: " . $e->getMessage(), 'wa_error', 'GajiTf');
            $sendErrorToWa("Maaf, terjadi kesalahan saat mengambil data gaji transfer.\nSilakan hubungi admin.");
        }
    }


    function handleCek_token($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        
        try {
            //tentukan DB berdasarkan textBody
            $bisnis = explode(" ", $textBody)[2] ?? null;
            
            if (isset($bisnis)) {
                // Regex untuk match variasi kata laundry (case-insensitive)
                if (preg_match('/laundry|laundri|londri|loundry|loundri/i', $bisnis)) {
                    $bisnis = "laundry";
                    $db = DB::getInstance(1);
                } else if (preg_match('/resto/i', $bisnis)) {
                    $bisnis = "resto";
                    $db = DB::getInstance(2);
                } else {
                    $bisnis = "laundry";
                    $db = DB::getInstance(1);
                }
        } else {
            $this->sendQuotedFreeText($waNumber, "Bisnis tidak ditemukan.");
            return;
        }

        $user = $db->query("SELECT id_cabang, id_privilege FROM user WHERE no_user IN ($phoneIn)")->row_array();
        $id_cabang = $user['id_cabang'] ?? null;
        $id_privilege = $user['id_privilege'] ?? null;

        if ($id_cabang) {
            $db0 = DB::getInstance(0);

            // Get prepaid_list - TODO: $pre_id perlu didefinisikan (dari parameter atau parsing message)
            if ($id_privilege == 100) {
                $pre_list = $db0->query(
                    "SELECT * FROM prepaid_list WHERE bisnis = '$bisnis'")->result_array();
            } else {
                $pre_list = $db0->query(
                    "SELECT * FROM prepaid_list WHERE bisnis = '$bisnis' AND id_cabang = '$id_cabang'")->result_array();
            }

            if (!$pre_list || count($pre_list) == 0) {
                $this->sendQuotedFreeText($waNumber, "Data token untuk $bisnis tidak ditemukan.");
                return;
            }

            $text = "";
            foreach ($pre_list as $item) {
                $pakai_result = $db0->query(
                    "SELECT SUM(price) as total FROM prepaid WHERE bisnis = '$bisnis' AND product_code = '$item[product_code]' AND id_cabang = '$item[id_cabang]' AND MONTH(insertTime) = MONTH(NOW()) AND YEAR(insertTime) = YEAR(NOW()) AND tr_status = 1"
                )->row_array();

                if (isset($pakai_result['total'])) {
                    $pakai_bulan_ini = $pakai_result['total'];
                } else {
                    $pakai_bulan_ini = 0;
                }
                $sisalimit = $item['monthly_limit'] - $pakai_bulan_ini;
                $text .= "ID: *" . $item['pre_id'] . "* - " . $item['bisnis'] . "\n" . $item['description'] . " " . number_format($item['nominal']) . "\nSisa Limit: " . number_format($sisalimit) . "\n\n";
            }

            $text = $text . "Ketik _Token {bisnis} {id}_ untuk beli. Contoh: *_Token ".$item['bisnis']. " " . $item['pre_id']. "_*";
            $this->sendQuotedFreeText($waNumber, $text);
        } else {
            $this->logAutoreplyTrace($waNumber, 'CEK_TOKEN', 'no_karyawan bisnis=' . $bisnis);
        }
        
        } catch (\Throwable $e) {
            \Log::write("handleCek_token ERROR: " . $e->getMessage(), 'wa_error', 'Token');
            $this->sendQuotedFreeText($waNumber, "Terjadi kesalahan sistem.");
        }
    }

    function handleBeli_token($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        $bisnis = explode(" ", $textBody)[1] ?? null;
        $pre_id = explode(" ", $textBody)[2] ?? null;
        
        if (isset($bisnis)) {
            // Regex untuk match variasi kata laundry (case-insensitive)
            if (preg_match('/laundry|laundri|londri|loundry|loundri/i', $bisnis)) {
                $bisnis = "laundry";
                $db = DB::getInstance(1);
            } else if (preg_match('/resto/i', $bisnis)) {
                $bisnis = "resto";
                $db = DB::getInstance(2);
            } else {
                $bisnis = "laundry";
                $db = DB::getInstance(1);
            }
        } else {
            $this->sendQuotedFreeText($waNumber, "Bisnis tidak ditemukan.");
            return;
        }

        $no_user = $db->query("SELECT no_user FROM user WHERE no_user IN ($phoneIn)")->row_array()['no_user'] ?? null;

        if ($no_user) {
            $db0 = DB::getInstance(0);

            // Get prepaid_list - TODO: $pre_id perlu didefinisikan (dari parameter atau parsing message)
            $pre_id_int = (int) $pre_id;
            $pre_list = $db0->query(
                "SELECT * FROM prepaid_list WHERE pre_id = ? AND bisnis = ?",
                [$pre_id_int, $bisnis]
            )->row_array();

            if (!$pre_list) {
                $this->sendQuotedFreeText($waNumber, "Token id: $pre_id tidak ditemukan.");
                return;
            }

            $id_cabang = $pre_list['id_cabang'];
            $product_code = $pre_list['product_code'];
            $customer_id_prepaid = $pre_list['customer_id'];
            $akan_dipakai = $pre_list['nominal'];
            $limit = $pre_list['monthly_limit'];

            // Get usage this month — filter sama dengan handleCek_token (per cabang + bisnis)
            $pakai_result = $db0->query(
                "SELECT SUM(price) as total FROM prepaid WHERE bisnis = ? AND product_code = ? AND id_cabang = ? AND MONTH(insertTime) = MONTH(NOW()) AND YEAR(insertTime) = YEAR(NOW()) AND tr_status = 1",
                [$bisnis, $product_code, $id_cabang]
            )->row_array();
            $pakai_bulan_ini = $pakai_result['total'] ?? 0;
            $total_pakai = $akan_dipakai + $pakai_bulan_ini;

            if ($total_pakai > $limit) {
                $this->sendQuotedFreeText($waNumber, "GAGAL - SUDAH MENCAPAI LIMIT BULANAN");
                return;
            }

            // Cegah duplikasi: token id + hari yang sama + tr_status yang sama (cek sukses sebelum proses)
            $dupBefore = $db0->query(
                "SELECT id, ref_id, tr_status, insertTime, sn, message, price, product_code, customer_id, id_cabang
                 FROM prepaid
                 WHERE product_code = ? AND customer_id = ? AND id_cabang = ?
                 AND DATE(insertTime) = CURDATE()
                 AND tr_status = 1
                 LIMIT 1",
                [$product_code, $customer_id_prepaid, $id_cabang]
            )->row_array();
            if ($dupBefore) {
                $d = $dupBefore;
                $msg = "*Transaksi tidak dapat dilanjutkan*\n\n"
                    . "Sudah ada data transaksi hari ini untuk *token id yang sama* dengan *status transaksi (tr_status) yang sama*.\n"
                    . "Tidak dapat melakukan transaksi 2 kali dalam sehari dengan id token yang sama bila hasilnya sama (sukses).\n\n"
                    . "*Data yang sudah ada:*\n"
                    . "Token ID (pre_id): *{$pre_id_int}*\n"
                    . "Product code: *{$d['product_code']}*\n"
                    . "Customer ID: *{$d['customer_id']}*\n"
                    . "ID cabang: *{$d['id_cabang']}*\n"
                    . "Ref ID: *{$d['ref_id']}*\n"
                    . "tr_status: *{$d['tr_status']}* (sukses)\n"
                    . "Waktu: *{$d['insertTime']}*\n"
                    . "SN: " . ($d['sn'] ?? '-') . "\n"
                    . "Harga: *" . ($d['price'] ?? '-') . "*\n"
                    . "Pesan: " . ($d['message'] ?? '-');
                $this->sendQuotedFreeText($waNumber, $msg);
                return;
            }

            // Bersihkan waNumber dari karakter non-digit (seperti +)
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $ref_id = "wa-" . $cleanWaNumber . "-" . date('YmdHi') . "-" . $id_cabang;

            $col = [
                'id_cabang' => $id_cabang,
                'ref_id' => $ref_id,
                'product_code' => $product_code,
                'customer_id' => $customer_id_prepaid
            ];
            $insertId = $db0->insert("prepaid", $col);

            if ($insertId) {
                $a = $db0->get_where('prepaid', ['ref_id' => $ref_id])->row_array();

                // Use IAK model
                $iak = new \App\Models\IAK();
                $proses = $iak->pre_pay($ref_id, $customer_id_prepaid, $product_code);

                if (isset($proses['data'])) {
                    $d = $proses['data'];

                    $tr_status = (int) ($d['status'] ?? ($a['tr_status'] ?? 0));
                    $price = $d['price'] ?? ($a['price'] ?? 0);
                    $message = $d['message'] ?? ($a['message'] ?? '');
                    $balance = $d['balance'] ?? ($a['balance'] ?? 0);
                    $tr_id = $d['tr_id'] ?? ($a['tr_id'] ?? 0);
                    $rc = $d['rc'] ?? ($a['rc'] ?? '');
                    $sn = $d['sn'] ?? ($a['sn'] ?? '');

                    // Duplikasi: hari yang sama + token yang sama + tr_status yang sama (baris lain, mis. race atau gagal 2x)
                    $dupOther = $db0->query(
                        "SELECT id, ref_id, tr_status, insertTime, sn, message, price, product_code, customer_id, id_cabang
                         FROM prepaid
                         WHERE product_code = ? AND customer_id = ? AND id_cabang = ?
                         AND DATE(insertTime) = CURDATE()
                         AND tr_status = ?
                         AND ref_id <> ?
                         LIMIT 1",
                        [$product_code, $customer_id_prepaid, $id_cabang, $tr_status, $ref_id]
                    )->row_array();
                    if ($dupOther) {
                        $du = $dupOther;
                        $msgDup = "*Transaksi tidak dapat dilanjutkan*\n\n"
                            . "Sudah ada data transaksi *hari ini* untuk token id yang sama dengan *tr_status* yang sama. "
                            . "Tidak dapat melakukan transaksi 2 kali dalam sehari dengan id yang sama bila status hasilnya sama.\n\n"
                            . "*Data yang sudah ada:*\n"
                            . "Token ID (pre_id): *{$pre_id_int}*\n"
                            . "Product code: *{$du['product_code']}*\n"
                            . "Customer ID: *{$du['customer_id']}*\n"
                            . "ID cabang: *{$du['id_cabang']}*\n"
                            . "Ref ID: *{$du['ref_id']}*\n"
                            . "tr_status: *{$du['tr_status']}*\n"
                            . "Waktu: *{$du['insertTime']}*\n"
                            . "SN: " . ($du['sn'] ?? '-') . "\n"
                            . "Harga: *" . ($du['price'] ?? '-') . "*\n"
                            . "Pesan: " . ($du['message'] ?? '-');
                        $db0->delete('prepaid', ['ref_id' => $ref_id]);
                        $this->sendQuotedFreeText($waNumber, $msgDup);
                        return;
                    }

                    $set = [
                        'sn' => $sn,
                        'tr_status' => $tr_status,
                        'price' => $price,
                        'message' => $message,
                        'balance' => $balance,
                        'tr_id' => $tr_id,
                        'rc' => $rc
                    ];
                    $update = $db0->update('prepaid', $set, ['ref_id' => $ref_id]);

                    if ($update) {
                        $text = "PROCESS";
                    } else {
                        $text = "ERROR: Gagal update database";
                    }
                } else {
                    $text = "SERVER GANGGUAN, SILAKAN COBA LAGI";
                }
            } else {
                $text = "ERROR: Gagal insert ke database";
            }

            $this->sendQuotedFreeText($waNumber, $text);
        } else {
            $this->logAutoreplyTrace($waNumber, 'BELI_TOKEN', 'no_karyawan');
        }
    }

    function handleSaldo($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'SALDO')) {
            return;
        }
        $which = $this->saldoProviderFromText($textBody);
        if ($which === null) {
            $this->logAutoreplyTrace($waNumber, 'SALDO', 'need_provider');
            $this->sendSaldoAdminText(
                $waNumber,
                "Format:\nsaldo iak\nsaldo tokopay\nsaldo ycloud\nsaldo deepseek\nsaldo fonnte"
            );
            return;
        }
        $this->logAutoreplyTrace($waNumber, 'SALDO', 'provider=' . $which);
        if ($this->intentLabMode) {
            $this->sendSaldoAdminText($waNumber, '[lab] saldo ' . $which);
            return;
        }
        if ($which === 'iak') {
            $this->replySaldoIak($waNumber);
            return;
        }
        if ($which === 'tokopay') {
            $this->replySaldoTokopay($waNumber);
            return;
        }
        if ($which === 'deepseek') {
            $this->replySaldoDeepseek($waNumber);
            return;
        }
        if ($which === 'fonnte') {
            $this->replySaldoFonnte($waNumber);
            return;
        }
        $this->replySaldoYcloud($waNumber);
    }

    /** @return 'iak'|'tokopay'|'ycloud'|'deepseek'|'fonnte'|null */
    private function saldoProviderFromText(?string $text): ?string
    {
        $t = strtolower(trim((string) $text));
        if ($t === '' || preg_match('/\btarik\b/u', $t)) {
            return null;
        }
        if (preg_match('/\biak\b/u', $t)) {
            return 'iak';
        }
        if (preg_match('/\btoko\s*pay\b|\btokopay\b/u', $t)) {
            return 'tokopay';
        }
        if (preg_match('/\bdeep\s*seek\b/u', $t)) {
            return 'deepseek';
        }
        if (preg_match('/\bfonnte\b/u', $t)) {
            return 'fonnte';
        }
        if (preg_match('/\by\s*cloud\b|\bycloud\b/u', $t)) {
            return 'ycloud';
        }

        return null;
    }

    private function sendSaldoAdminText($waNumber, string $text): void
    {
        if ($this->intentLabMode) {
            $this->sendAutoreplyText($waNumber, $text);
            return;
        }
        $this->sendQuotedFreeText($waNumber, $text);
    }

    private function saldoCheckedAtLabel(): string
    {
        try {
            return (new \DateTime('now', new \DateTimeZone('Asia/Jakarta')))->format('d/m/y H:i');
        } catch (\Throwable $e) {
            return date('d/m/y H:i');
        }
    }

    function handleSaldo_iak($phoneIn, $waNumber, $textBody = '')
    {
        $msg = trim((string) $textBody);
        $this->handleSaldo($phoneIn, $waNumber, $msg !== '' ? $msg : 'saldo iak');
    }

    private function replySaldoIak($waNumber): void
    {
        try {
            $iak = new \App\Models\IAK();
            $response = $iak->check_balance();

            if (isset($response['data']['balance'])) {
                $balance = $response['data']['balance'];
                $text = "*Saldo IAK*\n"
                    . "Saldo: Rp " . number_format((float) $balance, 0, ',', '.') . "\n"
                    . "Cek: " . $this->saldoCheckedAtLabel();
            } else {
                $message = $response['data']['message'] ?? 'Unknown error';
                $text = "Gagal mengambil saldo IAK: " . $message;
            }

            $this->sendSaldoAdminText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write("handleSaldo_iak ERROR: " . $e->getMessage(), 'wa_error', 'IAK');
            $this->sendSaldoAdminText($waNumber, "Error: " . $e->getMessage());
        }
    }

    private function cekQrisFormatHelpMessage()
    {
        return "Format cek QRIS:\n"
            . "cek qris MM.YY jumlah\n\n"
            . "• MM.YY = bulan.tahun (2 digit), contoh 06.26 = Juni 2026\n"
            . "• jumlah = nominal total bayar dari Tokopay (angka), contoh 900\n\n"
            . "Contoh benar:\ncek qris 06.26 900";
    }

    function handleCek_qris($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'CEK_QRIS')) {
            return;
        }
        try {
            if (!preg_match('/^\s*cek\s+qris\s+(\d{2})\.(\d{2})\s+(\d+)\s*$/i', trim($textBody), $m)) {
                $this->sendQuotedFreeText($waNumber, $this->cekQrisFormatHelpMessage());
                return;
            }

            $period = $m[1] . '.' . $m[2];
            $jumlah = (int) $m[3];
            if ($jumlah <= 0) {
                $this->sendQuotedFreeText($waNumber, "Nominal tidak valid.\n\n" . $this->cekQrisFormatHelpMessage());
                return;
            }

            $bulan = (int) $m[1];
            if ($bulan < 1 || $bulan > 12) {
                $this->sendQuotedFreeText($waNumber, "Bulan tidak valid (gunakan 01–12).\n\n" . $this->cekQrisFormatHelpMessage());
                return;
            }

            $db = DB::getInstance(1);
            $rows = $db->query(
                "SELECT ref_finance, state, jumlah, `date`, id_client
                 FROM kas_qris_cleanup_log
                 WHERE jumlah = ? AND DATE_FORMAT(`date`, '%m.%y') = ?
                 ORDER BY `date` DESC
                 LIMIT 30",
                [$jumlah, $period]
            )->result_array();

            $waService = $this->getWaService();

            if (empty($rows)) {
                $this->sendQuotedFreeText(
                    $waNumber,
                    "Tidak ada data QRIS untuk periode {$period} dengan nominal Rp" . number_format($jumlah, 0, ',', '.') . "."
                );
                return;
            }

            $lines = [];
            foreach ($rows as $row) {
                $ref = $row['ref_finance'] ?? '';
                $state = strtoupper($row['state'] ?? '-');
                $tgl = !empty($row['date']) ? date('d/m/y H:i', strtotime($row['date'])) : '-';
                $nominal = number_format((int) ($row['jumlah'] ?? 0), 0, ',', '.');
                $link = 'https://api.nalju.com/Laundry/QRIS_State/' . rawurlencode($ref);
                $lines[] = "{$tgl}\nRp{$nominal} ({$state})\n{$link}";
            }

            $this->sendQuotedFreeText($waNumber, implode("\n\n", $lines));

        } catch (\Throwable $e) {
            \Log::write("handleCek_qris ERROR: " . $e->getMessage(), 'wa_error', 'CekQris');
            try {
                $this->sendQuotedFreeText($waNumber, "Maaf, terjadi kesalahan saat mengambil data QRIS.");
            } catch (\Throwable $e2) {
                // ignore
            }
        }
    }

    function handleSaldo_tokopay($phoneIn, $waNumber, $textBody = '')
    {
        $msg = trim((string) $textBody);
        $this->handleSaldo($phoneIn, $waNumber, $msg !== '' ? $msg : 'saldo tokopay');
    }

    private function replySaldoTokopay($waNumber): void
    {
        try {
            $apiUrl = 'https://api.nalju.com/Laundry/QRIS/balance';

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $this->sendSaldoAdminText($waNumber, "Error: Gagal menghubungi API QRIS. " . $curlError);
                return;
            }

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
                if (isset($data['data']['data']['saldo_tersedia'])) {
                    $d = $data['data']['data'];
                    $text = "Tersedia: " . number_format($d['saldo_tersedia'], 0, ',', '.') . "\n";
                    $text .= "Tertahan: " . number_format($d['saldo_tertahan'] ?? 0, 0, ',', '.');
                } else {
                    $balance = null;
                    if (isset($data['data']['balance'])) {
                        $balance = $data['data']['balance'];
                    } elseif (isset($data['data']['saldo'])) {
                        $balance = $data['data']['saldo'];
                    } elseif (isset($data['data']['data']['balance'])) {
                        $balance = $data['data']['data']['balance'];
                    } elseif (isset($data['balance'])) {
                        $balance = $data['balance'];
                    }

                    if ($balance !== null) {
                        $text = "Saldo TokoPay: Rp " . number_format($balance, 0, ',', '.');
                    } else {
                        $text = "Saldo TokoPay: Data tidak ditemukan.\n" . json_encode($data);
                    }
                }
            } else {
                $message = $data['message'] ?? ($data['data']['message'] ?? 'Unknown error');
                $text = "Gagal mengambil saldo TokoPay: " . $message;
            }

            $this->sendSaldoAdminText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write("handleSaldo_tokopay ERROR: " . $e->getMessage(), 'wa_error', 'Tokopay');
            $this->sendSaldoAdminText($waNumber, "Error: " . $e->getMessage());
        }
    }

    function handleSaldo_ycloud($phoneIn, $waNumber, $textBody = '')
    {
        $msg = trim((string) $textBody);
        $this->handleSaldo($phoneIn, $waNumber, $msg !== '' ? $msg : 'saldo ycloud');
    }

    private function replySaldoYcloud($waNumber): void
    {
        try {
            $apiKey = \App\Config\WhatsApp::getApiKey();
            $baseUrl = rtrim(\App\Config\WhatsApp::getBaseUrl(), '/');

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $baseUrl . '/balance',
                CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $apiKey,
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $this->sendSaldoAdminText($waNumber, "Error: Gagal menghubungi API YCloud. " . $curlError);
                return;
            }

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['amount'])) {
                $amount = (float) $data['amount'];
                $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD')));
                $text = "*Saldo YCloud*\n"
                    . "Saldo: " . number_format($amount, 2, ',', '.') . " {$currency}\n"
                    . "Cek: " . $this->saldoCheckedAtLabel();
            } else {
                $message = $data['message'] ?? ($data['error']['message'] ?? 'Unknown error');
                $text = "Gagal mengambil saldo YCloud: " . $message;
            }

            $this->sendSaldoAdminText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write("handleSaldo_ycloud ERROR: " . $e->getMessage(), 'wa_error', 'YCloud');
            $this->sendSaldoAdminText($waNumber, "Error: " . $e->getMessage());
        }
    }

    private function replySaldoDeepseek($waNumber): void
    {
        try {
            if (!class_exists('\\App\\Config\\AI')) {
                require_once __DIR__ . '/../Config/AI.php';
            }
            $apiKey = trim(\App\Config\AI::getDeepseekApiKey());
            if ($apiKey === '') {
                $this->sendSaldoAdminText($waNumber, 'DeepSeek API key belum diisi.');
                return;
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.deepseek.com/user/balance',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $apiKey,
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $this->sendSaldoAdminText($waNumber, 'Error: Gagal menghubungi API DeepSeek. ' . $curlError);
                return;
            }

            $data = json_decode((string) $response, true);
            $infos = is_array($data) ? ($data['balance_infos'] ?? null) : null;
            if ($httpCode === 200 && is_array($infos) && $infos !== []) {
                $lines = [];
                foreach ($infos as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $currency = trim((string) ($row['currency'] ?? 'USD'));
                    $total = (float) ($row['total_balance'] ?? 0);
                    $granted = (float) ($row['granted_balance'] ?? 0);
                    $topup = (float) ($row['topped_up_balance'] ?? 0);
                    $line = number_format($total, 2, ',', '.') . ' ' . $currency;
                    if ($granted > 0 || $topup > 0) {
                        $line .= "\nGranted " . number_format($granted, 2, ',', '.')
                            . ' · Topup ' . number_format($topup, 2, ',', '.');
                    }
                    $lines[] = $line;
                }
                $text = $lines !== [] ? implode("\n\n", $lines) : 'Saldo DeepSeek: data kosong.';
                if (isset($data['is_available']) && $data['is_available'] === false) {
                    $text .= "\n(tidak cukup untuk API call)";
                }
            } else {
                $message = is_array($data)
                    ? ($data['message'] ?? ($data['error']['message'] ?? ($data['error'] ?? 'Unknown error')))
                    : 'Unknown error';
                if (is_array($message)) {
                    $message = json_encode($message);
                }
                $text = 'Gagal mengambil saldo DeepSeek (HTTP ' . $httpCode . '): ' . $message;
            }

            $this->sendSaldoAdminText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write('handleSaldo_deepseek ERROR: ' . $e->getMessage(), 'wa_error', 'DeepSeek');
            $this->sendSaldoAdminText($waNumber, 'Error: ' . $e->getMessage());
        }
    }

    function handleInfo_fonnte($phoneIn, $waNumber, $textBody = '')
    {
        $msg = trim((string) $textBody);
        $this->handleSaldo($phoneIn, $waNumber, $msg !== '' ? $msg : 'saldo fonnte');
    }

    private function replySaldoFonnte($waNumber): void
    {
        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte')) {
                require_once __DIR__ . '/../Config/Fonnte.php';
            }

            $fonnte = new \App\Helpers\CRM\FonnteService();
            $health = $fonnte->getGatewayHealth();
            $device = $fonnte->getGatewayDevice();

            if (!$health['success'] && !$device['success']) {
                $err = $health['error'] ?? $device['error'] ?? 'Gateway tidak dapat dihubungi';
                $this->sendSaldoAdminText(
                    $waNumber,
                    "Gagal cek Fonnte Server: {$err}\nGateway: " . \App\Config\Fonnte::getBaseUrl()
                );
                return;
            }

            $healthData = is_array($health['data'] ?? null) ? $health['data'] : [];
            $wa = is_array($healthData['whatsapp'] ?? null) ? $healthData['whatsapp'] : [];
            $dev = is_array($device['data'] ?? null) ? $device['data'] : [];
            $connected = !empty($wa['connected']) || !empty($dev['connected']) || !empty($dev['status']);

            $state = $wa['state'] ?? ($dev['state'] ?? 'unknown');
            $deviceNum = trim((string) ($dev['device'] ?? ($healthData['device'] ?? '')));
            $package = $dev['package'] ?? 'self-hosted-baileys';
            $webhook = !empty($healthData['webhook']);
            $hasQr = !empty($wa['has_qr']) || !empty($healthData['qr']);
            $service = $healthData['service'] ?? 'fonnte_server';
            $messages = $dev['messages'] ?? ($connected ? 'online' : 'offline');
            $quota = $dev['quota'] ?? 'unlimited';

            $lines = [
                '*Fonnte Server*',
                'Service: ' . $service,
                'Gateway: ' . \App\Config\Fonnte::getBaseUrl(),
                'Koneksi: ' . ($connected ? 'Terhubung' : 'Putus'),
                'State: ' . $state,
                'Device: ' . ($deviceNum !== '' ? $deviceNum : '-'),
                'Paket: ' . $package,
                'Kuota: ' . $quota,
                'Pesan: ' . $messages,
                'Webhook: ' . ($webhook ? 'aktif' : 'belum'),
            ];

            if (!$connected) {
                $lines[] = 'QR: ' . ($hasQr ? 'tersedia (scan via panel)' : 'belum tersedia');
                if ($state === 'connecting') {
                    $lines[] = 'Hint: tunggu QR atau logout/scan ulang';
                } else {
                    $lines[] = 'Hint: logout/scan ulang atau restart fonnte_server';
                }
            }

            $lines[] = 'Cek: ' . $this->saldoCheckedAtLabel();

            $this->sendSaldoAdminText($waNumber, implode("\n", $lines));
        } catch (\Throwable $e) {
            \Log::write('replySaldoFonnte ERROR: ' . $e->getMessage(), 'wa_error', 'Fonnte');
            $this->sendSaldoAdminText($waNumber, 'Error: ' . $e->getMessage());
        }
    }

     function handleTarik_tokopay($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->requireAdminSender($waNumber, 'TARIK_TOKOPAY')) {
            return;
        }
        try {
            $waService = $this->getWaService();
            
            // Extract amount from text body
            // Format expected: "tarik tokopay 50000" or "wd tokopay 50000"
            $parts = preg_split('/\s+/', $textBody);
            $amount = isset($parts[2]) ? intval($parts[2]) : 0;
            
            // Validate amount
            if ($amount < 10000) {
                $text = "Gagal: Minimal penarikan Rp 10.000";
                $this->sendQuotedFreeText($waNumber, $text);
                return;
            }

            // Call QRIS withdraw endpoint
            $apiUrl = 'https://api.nalju.com/Laundry/QRIS/withdraw';
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode(['nominal' => $amount]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                $text = "Error: Gagal menghubungi API QRIS. " . $curlError;
                $this->sendQuotedFreeText($waNumber, $text);
                return;
            }

            $data = json_decode($response, true);

            // Format Tokopay: Sukses = status 1, rc 200, "message". Gagal = status 0, "error_msg"
            $isSuccess = isset($data['status']) && (int) $data['status'] === 1;
            $replyText = '';

            if ($data === null || $data === false) {
                $replyText = "❌ *Gagal Penarikan Saldo*\n\nRespon API tidak valid.";
            } elseif ($isSuccess) {
                $amountFormatted = number_format($amount, 0, ',', '.');
                $message = isset($data['message']) && trim((string) $data['message']) !== ''
                    ? trim($data['message'])
                    : 'Penarikan berhasil diproses. Silakan hubungi customer service jika perlu.';
                $replyText = "✅ *Penarikan Saldo TokoPay*\n\n";
                $replyText .= "Nominal: *Rp " . $amountFormatted . "*\n";
                $replyText .= "Tujuan: *SEABANK*\n\n";
                $replyText .= $message;
            } else {
                $errorMsg = null;
                if (isset($data['error_msg']) && trim((string) $data['error_msg']) !== '') {
                    $errorMsg = trim($data['error_msg']);
                } elseif (isset($data['message']) && trim((string) $data['message']) !== '') {
                    $errorMsg = trim($data['message']);
                } elseif (isset($data['error']) && trim((string) $data['error']) !== '') {
                    $errorMsg = trim($data['error']);
                }
                $replyText = "❌ *Gagal Penarikan Saldo*\n\n" . ($errorMsg ?: 'Terjadi kesalahan. Silakan coba lagi atau hubungi customer service.');
            }

            $this->sendQuotedFreeText($waNumber, $replyText);

        } catch (\Throwable $e) {
            \Log::write("handleTarik_saldo_tokopay ERROR: " . $e->getMessage(), 'wa_error', 'Tokopay');
            $waService = $this->getWaService();
            $this->sendQuotedFreeText($waNumber, "Error: " . $e->getMessage());
        }
    }

    private function isOperatingHours()
    {
        // Load operating hours config
        $config = require __DIR__ . '/../Config/OperatingHours.php';

        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $dayOfWeek = (int) $now->format('N'); // 1 (Monday) to 7 (Sunday)
        $currentDate = $now->format('Y-m-d');
        $hour = (int) $now->format('G'); // 0-23
        $minute = (int) $now->format('i'); // 0-59

        // Check if today is a holiday
        if (in_array($currentDate, $config['holidays'])) {
            return false; // Holiday - closed
        }

        // Check if today is a working day
        if (!in_array($dayOfWeek, $config['working_days'])) {
            return false; // Not a working day (e.g., Sunday)
        }

        // Check time
        $currentTimeInMinutes = ($hour * 60) + $minute;
        $openTime = ($config['open_hour'] * 60) + $config['open_minute'];
        $closeTime = ($config['close_hour'] * 60) + $config['close_minute'];

        if ($currentTimeInMinutes < $openTime || $currentTimeInMinutes >= $closeTime) {
            return false; // Outside operating hours
        }

        return true; // Within operating hours
    }

    /**
     * Pertanyaan tipe "kapan buka lagi?" → balasan libur tanpa intro maaf, langsung Catatan + jadwal.
     */
    private function shouldSkipHolidayApologyIntro(?string $textBody): bool
    {
        $t = strtolower(trim(preg_replace('/[*_~`]/', '', $textBody ?? '')));
        if ($t === '') {
            return false;
        }
        return (bool) preg_match(
            '/\b(bukak|buka)\s+lagi\s+(kapan|jam|brp|berapa|tgl|tanggal)\b/i',
            $t
        )
        // Pertanyaan jam buka besok: "besok/bsk buka jam berapa (ya)?"
        || preg_match('/\b(besok|bsk)\b.*\b(bukak|buka)\b.*\b(jam\s*)?(brp|brpa|berapa)\b/i', $t)
        || preg_match('/\b(jam\s*)?(brp|brpa|berapa)\b.*\b(besok|bsk)\b.*\b(bukak|buka)\b/i', $t)
        // Pertanyaan umum: "kapan laundry buka?" (tanpa kata "lagi")
        || preg_match('/\bkapan\b.*\b(laundry|loundry)?\b.*\b(bukak|buka)\b/i', $t)
        // Pertanyaan hari operasional: "buka hari apa?" / "hari apa buka?"
        || preg_match('/\b(bukak|buka)\b.*\bhari\s*apa\b/i', $t)
        || preg_match('/\bhari\s*apa\b.*\b(bukak|buka)\b/i', $t)
        || preg_match('/\bkapan\s+(bukak|buka)\s+lagi\b/i', $t)
        || preg_match('/\bkapan\s+(buka\s+)?kembali\b/i', $t)
        || preg_match('/\b(bukak|buka)\s+lagi\s*[?？]\b/i', $t)
        || preg_match('/\b(bukak|buka)\s+lagi\b.*\bkapan\b/i', $t)
        || preg_match('/\bkapan\s+.*\b(bukak|buka)\s+lagi\b/i', $t);
    }

    /**
     * Pesan lengkap saat hari ini libur: (opsional) Mohon maaf + Catatan tanggal libur + Buka kembali + Terima kasih
     * Return string kosong jika hari ini bukan libur atau tidak ada range
     */
    private function getHolidayFullMessage(array $config, ?string $textBody = null): string
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $currentDate = $now->format('Y-m-d');
        if (!in_array($currentDate, $config['holidays'])) {
            return '';
        }

        $openTime = str_pad($config['open_hour'], 2, '0', STR_PAD_LEFT) . '.' . str_pad($config['open_minute'], 2, '0', STR_PAD_LEFT);
        $closeTime = str_pad($config['close_hour'], 2, '0', STR_PAD_LEFT) . '.' . str_pad($config['close_minute'], 2, '0', STR_PAD_LEFT);
        $timeStr = "{$openTime} s.d. {$closeTime}";

        $formatted = [];
        $reopenDt = null;
        $holidayRanges = $config['holiday_ranges'] ?? [];
        foreach ($holidayRanges as $range) {
            $start = $range['start'] ?? $range[0] ?? null;
            $end = $range['end'] ?? $range[1] ?? null;
            if (!$start || !$end) continue;
            try {
                $startDt = new \DateTime($start);
                $endDt = new \DateTime($end);
                $today = new \DateTime($currentDate);
                if ($today >= $startDt && $today <= $endDt) {
                    $formatted[] = $this->formatHolidayRange($startDt, $endDt, $monthNames);
                    $nextReopen = clone $endDt;
                    $nextReopen->modify('+1 day');
                    if ($reopenDt === null || $nextReopen > $reopenDt) {
                        $reopenDt = $nextReopen;
                    }
                }
            } catch (\Exception $e) {}
        }

        if (empty($formatted)) {
            return '';
        }

        $listItems = array_map(function ($item) {
            return '• *' . $item . '*';
        }, $formatted);
        $dateList = "\n" . implode("\n", $listItems);

        $reopenStr = $reopenDt ? (int)$reopenDt->format('d') . ' ' . $monthNames[(int)$reopenDt->format('n')] . ' ' . $reopenDt->format('Y') : '';
        $tomorrow = clone $now;
        $tomorrow->modify('+1 day');
        $isReopenTomorrow = ($reopenDt && $reopenDt->format('Y-m-d') === $tomorrow->format('Y-m-d'));
        $besokPrefix = $isReopenTomorrow ? 'besok, ' : '';

        $skipMaaf = $this->shouldSkipHolidayApologyIntro($textBody);

        $openings = [
            "Mohon maaf, hari ini kami libur",
            "Maaf, hari ini kami libur",
            "Mohon maaf, saat ini kami libur",
        ];

        $catatanVariations = [
            "Info: Kami tutup pada tanggal berikut:{$dateList}",
        ];

        $bukaKembaliVariations = [
            "\n\nBuka kembali {$besokPrefix}tanggal {$reopenStr}, jam *{$timeStr}*, setiap hari 😊",
            "\n\nKami buka kembali {$besokPrefix}tanggal {$reopenStr}, jam *{$timeStr}*, setiap hari 😊",
            "\n\nBuka lagi {$besokPrefix}tanggal {$reopenStr}, jam *{$timeStr}*, setiap hari 😊",
        ];

        $closings = [
            "\n\nTerima kasih 😊",
            "\n\nTerima kasih 🙏",
        ];

        $introPart = $skipMaaf ? '' : ($openings[array_rand($openings)] . "\n\n");

        return $introPart
            . $catatanVariations[array_rand($catatanVariations)]
            . $bukaKembaliVariations[array_rand($bukaKembaliVariations)]
            . $closings[array_rand($closings)];
    }

    /**
     * Cek libur dalam 10 hari ke depan, return teks info untuk customer (atau string kosong jika tidak ada)
     * Rentang tanggal diformat ringkas (17 Maret - 25 April 2026), rentang beda bulan ditampilkan lengkap
     */
    private function getUpcomingHolidaysMessage(array $config): string
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $windowEnd = clone $now;
        $windowEnd->modify('+10 days');
        $formatted = [];

        // Hanya gunakan holiday_ranges (rentang libur)
        $holidayRanges = $config['holiday_ranges'] ?? [];
        foreach ($holidayRanges as $range) {
            $start = $range['start'] ?? $range[0] ?? null;
            $end = $range['end'] ?? $range[1] ?? null;
            if (!$start || !$end) continue;
            try {
                $startDt = new \DateTime($start);
                $endDt = new \DateTime($end);
                // Overlap jika: rentang berpotongan dengan [besok, today+10]
                $tomorrow = clone $now;
                $tomorrow->modify('+1 day');
                if ($endDt < $tomorrow || $startDt > $windowEnd) continue;
                $formatted[] = $this->formatHolidayRange($startDt, $endDt, $monthNames);
            } catch (\Exception $e) {}
        }

        if (empty($formatted)) {
            return '';
        }

        // Tampilkan dalam format list (setiap tanggal libur per baris)
        $listItems = array_map(function ($item) {
            return '• *' . $item . '*';
        }, $formatted);
        $dateList = "\n" . implode("\n", $listItems);
        // Penutup dengan emoji agar terlihat rapi
        $variations = [
            "\n\nInfo: Kami libur pada tanggal berikut:{$dateList}\n\nTerima kasih 🙏",
            "\n\nCatatan: Kami tutup pada tanggal berikut:{$dateList}\n\nTerima kasih 😊",
            "\n\nMohon dicatat, kami libur pada tanggal berikut:{$dateList}\n\nTerima kasih 🙏",
        ];
        return $variations[array_rand($variations)];
    }

    /**
     * Format rentang tanggal libur (handle beda bulan/tahun dengan benar)
     * Jika start = end (tanggal sama), tampilkan format tunggal: "1 Januari 2026"
     */
    private function formatHolidayRange(\DateTime $startDt, \DateTime $endDt, array $monthNames): string
    {
        $sD = (int) $startDt->format('d');
        $eD = (int) $endDt->format('d');
        $sM = (int) $startDt->format('n');
        $eM = (int) $endDt->format('n');
        $sY = $startDt->format('Y');
        $eY = $endDt->format('Y');
        // Tanggal sama: "1 Januari 2026"
        if ($startDt->format('Y-m-d') === $endDt->format('Y-m-d')) {
            return "{$sD} " . $monthNames[$sM] . " {$sY}";
        }
        if ($sM === $eM && $sY === $eY) {
            return "{$sD}-{$eD} " . $monthNames[$sM] . " {$sY}";
        }
        if ($sY === $eY) {
            return "{$sD} " . $monthNames[$sM] . " - {$eD} " . $monthNames[$eM] . " {$eY}";
        }
        return "{$sD} " . $monthNames[$sM] . " {$sY} - {$eD} " . $monthNames[$eM] . " {$eY}";
    }

    private function buildWsPayload($waNumber, $text, $msgId = null, $wamid = null, $timestamp = null)
    {
        // Use provided timestamp or add 3 seconds to current time to ensure auto-reply appears AFTER customer message
        $time = $timestamp ?: date('Y-m-d H:i:s', strtotime('+3 seconds'));

        return [
            'type' => 'agent_message_sent',
            'phone' => $waNumber,
            'conversation_id' => 0,
            'target_id' => '0',
            'sender_id' => 0,
            'message' => [
                'id' => $msgId,
                'wamid' => $wamid,
                'text' => $text,
                'type' => 'text',
                'sender' => 'me',
                'time' => $time,
                'status' => 'sent',
            ],
            'contact_name' => '',
            'phone' => $waNumber,
        ];
    }

    private function pushToWebSocket($data)
    {
        $url = \App\Helpers\CRM\WaServer::incomingUrl();



        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            if (class_exists('\Log')) {
                \Log::write('WS PUSH ERROR: json_encode failed - ' . json_last_error_msg(), 'wa_error', 'WebSocket');
            }
            return null;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Increased from 2 to 5 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // DNS resolution timeout
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1); // Prevent signals causing timeouts
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (class_exists('\Log') && (curl_errno($ch) || $httpCode >= 400)) {
            $err = curl_errno($ch) ? curl_error($ch) : "HTTP $httpCode: " . substr((string) $result, 0, 300);
            \Log::write('WS PUSH ERROR: ' . $err . ' | url=' . $url, 'wa_error', 'WebSocket');
        }

        curl_close($ch);
        return $result;
    }

    /**
     * Klarifikasi AI dimatikan (spam). Abaikan saja jika teks tidak jelas.
     */
    private function sendClarifyConfirmation(string $waNumber, string $suggested): void
    {
        $this->logAutoreplyTrace(
            $waNumber,
            'CLARIFY',
            'ignored_no_reply suggested=' . mb_substr(trim($suggested), 0, 120)
        );
    }

    private function savePendingClarify(string $waNumber, string $suggested): void
    {
        $suggested = mb_substr(trim($suggested), 0, 160);
        $marker = 'pending_clarify=<<' . str_replace(['<<', '>>'], '', $suggested) . '>>';

        $kurir = $this->getKurirSession($waNumber);
        if ($kurir !== null) {
            $sum = preg_replace('/\s*\|\s*pending_clarify=<<.*?>>/u', '', (string) ($kurir['summary'] ?? ''));
            $this->saveKurirSession($waNumber, [
                'summary' => mb_substr(trim($sum . ($sum !== '' ? ' | ' : '') . $marker), 0, 800),
            ]);
            return;
        }

        // Estimasi session dihapus — clarify hanya via session kurir
        $this->logAutoreplyTrace($waNumber, 'CLARIFY', 'pending_clarify_no_session');
    }

    private function loadPendingClarify(string $waNumber): ?string
    {
        foreach ([$this->getKurirSession($waNumber)] as $sess) {
            if (!is_array($sess)) {
                continue;
            }
            $sum = (string) ($sess['summary'] ?? '');
            if (preg_match('/pending_clarify=<<(.*?)>>/u', $sum, $m)) {
                $s = trim($m[1]);
                return $s !== '' ? $s : null;
            }
        }
        return null;
    }

    private function clearPendingClarify(string $waNumber): void
    {
        $kurir = $this->getKurirSession($waNumber);
        if ($kurir !== null) {
            $sum = preg_replace('/\s*\|\s*pending_clarify=<<.*?>>/u', '', (string) ($kurir['summary'] ?? ''));
            $this->saveKurirSession($waNumber, ['summary' => trim($sum)]);
        }
    }

    /**
     * @return string|null rewritten message, '__abort__' if handled/cancelled, null if no pending
     */
    private function consumePendingClarifyIfAny(string $waNumber, string $textBodyToCheck)
    {
        $pending = $this->loadPendingClarify($waNumber);
        if ($pending === null) {
            return null;
        }

        $sapaan = $this->getSapaanForGreeting($waNumber);
        if ($this->kurirLooksAgree($textBodyToCheck) || preg_match('/\b(betul|benar|iyo|yoi|yang\s*itu)\b/iu', $textBodyToCheck)) {
            $this->clearPendingClarify($waNumber);
            return $pending;
        }
        if ($this->kurirLooksRefuse($textBodyToCheck) || $this->kurirLooksCancel($textBodyToCheck)) {
            $this->clearPendingClarify($waNumber);
            $this->logAutoreplyTrace($waNumber, 'CLARIFY', 'pending_refused_silent');
            return '__abort__';
        }

        // Pesan baru yang jelas (bukan sekadar ya/tidak) → buang pending, proses pesan baru
        if (mb_strlen(trim($textBodyToCheck)) >= 12
            || $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
            || $this->parseEstimasiRequestedRelativeDay($textBodyToCheck) !== null) {
            $this->clearPendingClarify($waNumber);
            return null;
        }

        // Masih ambigu → jangan spam tanya ulang; buang pending & biarkan pesan diproses biasa / diabaikan
        $this->clearPendingClarify($waNumber);
        $this->logAutoreplyTrace($waNumber, 'CLARIFY', 'pending_cleared_no_reask');
        return null;
    }

    /**
     * Soft clarify AI dimatikan — jangan spam "kurang dapat saya pahami".
     * @return bool always false
     */
    private function tryAiClarifyAmbiguous(string $waNumber, string $msg): bool
    {
        $this->logAutoreplyTrace($waNumber, 'CLARIFY', 'tryAiClarify_disabled');
        return false;
    }

    /**
     * Nama blok === INTENT === yang instruksi AI-nya dipakai untuk menentukan intent.
     */
    private function buildAiIntentClassifierPrompt(string $textBody, array $keywordConfig): string
    {
        $prompt = "Kamu adalah AI classifier untuk WhatsApp bot laundry. Klasifikasikan pesan ke SATU kategori.\n\n";
        $prompt .= "ATURAN TRUE/FALSE (WAJIB — BACA SEBELUM MEMILIH INTENT):\n";
        $prompt .= "1. Definisi intent HANYA dari teks di blok === NAMA === (field ai_prompt database). Jangan infer dari nama blok (KURIR, dll.).\n";
        $prompt .= "2. PRIORITAS FALSE (PALING TINGGI): Sebelum menetapkan intent apapun, periksa SEMUA blok. Jika pesan memenuhi syarat FALSE di blok manapun, intent WAJIB \"FALSE\" — meskipun pesan juga cocok dengan TRUE di blok lain.\n";
        $prompt .= "3. FALSE menang atas TRUE. Jika FALSE dan TRUE keduanya bisa dibaca, pilih FALSE.\n";
        $prompt .= "4. TRUE hanya jika syarat TRUE di blok terpenuhi DAN tidak ada FALSE di blok manapun yang match.\n";
        $prompt .= "5. Blok ideal memakai format: \"TRUE jika:\" (syarat masuk intent) dan \"FALSE jika:\" (syarat tolak). Ikuti contoh/frasa di FALSE secara harfiah.\n";
        $prompt .= "6. Jika tidak ada TRUE yang jelas dan tidak ada FALSE spesifik yang match → intent=FALSE.\n";
        $prompt .= "7. Jangan memperluas kategori; jangan mengarang label di luar daftar blok.\n\n";

        foreach ($keywordConfig as $category => $config) {
            $aiPrompt = trim((string) ($config['ai_prompt'] ?? ''));
            if ($aiPrompt === '') {
                continue;
            }
            $prompt .= "=== {$category} ===\n{$aiPrompt}\n\n";
        }

        $prompt .= "=== FALSE ===\nTidak termasuk kategori di atas (termasuk jika ada FALSE match di blok manapun).\n\n";
        $prompt .= "ATURAN WAJIB OUTPUT:\n";
        $prompt .= "- field \"intent\" HANYA boleh berisi nama kategori yang PERSIS ada di daftar blok di atas, atau FALSE. Jangan mengarang label (PERTANYAAN, PERTANYAAN_UMUM, dll.).\n";
        $prompt .= "- Field \"ask\" (boolean, wajib): true jika pesan pertanyaan/permintaan butuh respon CS; false jika info/ack/cerita tanpa minta aksi.\n";
        $prompt .= "- ask=true: bertanya (ada/tidak ada tanda ?), minta bantuan/cek/info/kabari/tolong/komplain.\n";
        $prompt .= "- ask=false: info saja (otw, daftar item tanpa minta aksi), ack singkat (ok/siap/baik), cerita sosial.\n";
        $prompt .= "- Field \"from_block\" (wajib): blok === NAMA === yang aturan TRUE/FALSE-nya Anda pakai.\n";
        $prompt .= "- Jika intent=FALSE karena FALSE di blok === X === → from_block=X (bukan FALSE kecuali tidak ada blok spesifik).\n";
        $prompt .= "- Jika intent=HARGA karena blok === BONEKA === menulis \"tanya harga boneka → HARGA\" → from_block=BONEKA.\n";
        $prompt .= "- from_block harus PERSIS nama blok di daftar, atau FALSE.\n\n";
        $prompt .= "Pesan: \"{$textBody}\"\n";
        $prompt .= "JAWAB HANYA JSON SATU OBJEK (tanpa markdown, tanpa teks lain).\n";
        $prompt .= "reason maksimal 12 kata, tanpa tanda kutip ganda.\n";
        $prompt .= "{\"intent\": \"NAMA_KATEGORI\", \"ask\": true, \"from_block\": \"NAMA_BLOK\", \"reason\": \"alasan singkat\"}\n";
        $prompt .= "Kategori harus salah satu dari daftar di atas atau FALSE. ask harus true atau false. from_block wajib diisi.";

        return $prompt;
    }

    /**
     * PHP enforcement: FALSE priority — scan contoh/frasa di bagian FALSE tiap ai_prompt.
     * @return array{from_block:string,reason:string}|null
     */
    private function applyAiFalsePriorityOverride(string $textBody, string $intent, array $keywordConfig): ?array
    {
        if ($intent === '' || $intent === 'FALSE') {
            return null;
        }
        $textNorm = mb_strtolower(trim($textBody), 'UTF-8');
        if ($textNorm === '') {
            return null;
        }
        foreach ($keywordConfig as $code => $config) {
            $aiPrompt = trim((string) ($config['ai_prompt'] ?? ''));
            if ($aiPrompt === '') {
                continue;
            }
            foreach ($this->extractFalseMatchPhrasesFromAiPrompt($aiPrompt) as $phrase) {
                $phraseNorm = mb_strtolower(trim($phrase), 'UTF-8');
                if ($phraseNorm === '' || mb_strlen($phraseNorm) < 4) {
                    continue;
                }
                if (mb_stripos($textNorm, $phraseNorm) !== false) {
                    return [
                        'from_block' => (string) $code,
                        'reason' => 'false_priority ' . $code . ' phrase=' . mb_substr($phrase, 0, 48),
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Ambil frasa contoh dari bagian FALSE di ai_prompt (kutip, pipe, atau baris FALSE jika).
     * @return list<string>
     */
    private function extractFalseMatchPhrasesFromAiPrompt(string $aiPrompt): array
    {
        $phrases = [];
        $lines = preg_split('/\r\n|\r|\n/', $aiPrompt) ?: [];
        $inFalse = false;
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                continue;
            }
            if (preg_match('/^(TRUE\s*:|TRUE\s+jika)/iu', $trim)) {
                $inFalse = false;
                continue;
            }
            if (preg_match('/^(FALSE\s*:|FALSE\s+jika|Contoh\s+FALSE)/iu', $trim)) {
                $inFalse = true;
            } elseif (preg_match('/\bFALSE\s+jika\b/iu', $trim)) {
                $inFalse = true;
            }
            if (!$inFalse) {
                continue;
            }
            if (preg_match_all('/["\']([^"\']{3,120})["\']/', $trim, $quoted)) {
                foreach ($quoted[1] as $p) {
                    $phrases[] = trim($p);
                }
            }
            if (preg_match_all('/\|\s*([^|]{3,120}?)\s*\|/u', $trim, $piped)) {
                foreach ($piped[1] as $p) {
                    $p = trim($p);
                    if ($p !== '' && !preg_match('/^(FALSE|TRUE)$/iu', $p)) {
                        $phrases[] = $p;
                    }
                }
            }
        }
        return array_values(array_unique(array_filter($phrases, static fn ($p) => trim($p) !== '')));
    }

    private function normalizeAiFromBlock($raw, array $keywordConfig, string $fallbackIntent): string
    {
        $block = trim(strtoupper((string) $raw));
        $block = preg_replace('/^===?\s*|\s*===?$/', '', $block) ?? $block;
        $block = trim($block, " \t\"'");
        if ($block === 'FALSE') {
            return 'FALSE';
        }
        if ($block !== '' && isset($keywordConfig[$block])) {
            return $block;
        }

        $fallback = trim(strtoupper($fallbackIntent));
        if ($fallback === 'FALSE' || isset($keywordConfig[$fallback])) {
            return $fallback !== '' ? $fallback : 'FALSE';
        }

        return $block !== '' ? $block : 'FALSE';
    }

    private function formatAiParseTrace(
        string $intent,
        bool $ask,
        string $reason,
        string $fromBlock,
        string $aiIntentRaw = '',
        string $aiReason = ''
    ): string {
        $parts = [
            'intent=' . $intent,
            'ask=' . ($ask ? '1' : '0'),
            'from_block=' . ($fromBlock !== '' ? $fromBlock : '-'),
        ];
        $aiRaw = trim(strtoupper($aiIntentRaw));
        if ($aiRaw !== '' && $aiRaw !== strtoupper($intent)) {
            $parts[] = 'ai_intent=' . $aiRaw;
        }
        $aiReason = trim(preg_replace('/\s+/', ' ', $aiReason) ?? $aiReason);
        if ($aiReason !== '') {
            if (mb_strlen($aiReason) > 160) {
                $aiReason = mb_substr($aiReason, 0, 160) . '…';
            }
            $parts[] = 'ai_reason=' . $aiReason;
        }
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? $reason);
        if ($reason !== '' && $reason !== $aiReason) {
            $parts[] = 'reason=' . $reason;
        }

        return implode(' ', $parts);
    }

    /**
     * Parse JSON klasifikasi AI. Tahan markdown, trailing comma, reason terpotong.
     *
     * @return array{json:?array,repaired:bool}
     */
    private function decodeAiJsonObject(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['json' => null, 'repaired' => false];
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $strict = $this->tryJsonDecodeAssoc($raw);
        if (is_array($strict) && isset($strict['intent'])) {
            return ['json' => $strict, 'repaired' => false];
        }

        $candidate = $raw;
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $m)) {
            $candidate = trim($m[1]);
        } elseif (preg_match('/\{.*\}/s', $raw, $m)) {
            $candidate = $m[0];
        }

        $decoded = $this->tryJsonDecodeAssoc($candidate);
        if (is_array($decoded) && isset($decoded['intent'])) {
            return ['json' => $decoded, 'repaired' => $candidate !== $raw];
        }

        $repaired = $this->repairTruncatedAiJson($candidate);
        $decoded = $this->tryJsonDecodeAssoc($repaired);
        if (is_array($decoded) && isset($decoded['intent'])) {
            return ['json' => $decoded, 'repaired' => true];
        }

        $salvaged = $this->salvageAiIntentFields($raw);
        if (is_array($salvaged)) {
            return ['json' => $salvaged, 'repaired' => true];
        }

        return ['json' => is_array($decoded) ? $decoded : null, 'repaired' => false];
    }

    private function tryJsonDecodeAssoc(string $s): ?array
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        $json = json_decode($s, true);
        if (is_array($json)) {
            return $json;
        }
        $fixed = str_replace(["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"], ['"', '"', "'", "'"], $s);
        $fixed = preg_replace('/,\s*([}\]])/', '$1', $fixed) ?? $fixed;
        $json = json_decode($fixed, true);

        return is_array($json) ? $json : null;
    }

    private function repairTruncatedAiJson(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return $s;
        }
        if ($this->jsonHasUnclosedString($s)) {
            $s .= '"';
        }
        $s = rtrim($s, ", \t\n\r");
        $nBrack = substr_count($s, '[') - substr_count($s, ']');
        if ($nBrack > 0) {
            $s .= str_repeat(']', $nBrack);
        }
        $nBrace = substr_count($s, '{') - substr_count($s, '}');
        if ($nBrace > 0) {
            $s .= str_repeat('}', $nBrace);
        }

        return $s;
    }

    private function jsonHasUnclosedString(string $s): bool
    {
        $in = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '\\' && $in) {
                $i++;
                continue;
            }
            if ($c === '"') {
                $in = !$in;
            }
        }

        return $in;
    }

    /**
     * Ambil intent/ask/from_block dari JSON yang terpotong di field reason.
     */
    private function salvageAiIntentFields(string $raw): ?array
    {
        $out = [];
        if (preg_match('/"intent"\s*:\s*"([^"]+)"/i', $raw, $m)
            || preg_match('/"intent"\s*:\s*([A-Za-z0-9_]+)/i', $raw, $m)
        ) {
            $out['intent'] = trim($m[1]);
        }
        if (preg_match('/"ask"\s*:\s*(true|false|1|0)/i', $raw, $m)) {
            $v = strtolower($m[1]);
            $out['ask'] = ($v === 'true' || $v === '1');
        }
        if (preg_match('/"from_block"\s*:\s*"([^"]+)"/i', $raw, $m)
            || preg_match('/"from_block"\s*:\s*([A-Za-z0-9_]+)/i', $raw, $m)
        ) {
            $out['from_block'] = trim($m[1]);
        }
        if (preg_match('/"reason"\s*:\s*"([^"]*)/i', $raw, $m)) {
            $out['reason'] = trim($m[1]);
        }
        if (!isset($out['intent']) || $out['intent'] === '') {
            return null;
        }

        return $out;
    }

    private function handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig = null)
    {
        try {
            // Check if AI Config class exists
            if (!class_exists('\\App\\Config\\AI')) {
                $configFile = __DIR__ . '/../Config/AI.php';
                if (!file_exists($configFile)) {
                    $this->logAutoreplyTrace($waNumber, 'AI_SKIP', 'file missing: Config/AI.php');
                    return false;
                }
                require_once $configFile;
            }

            // Check if AI is enabled
            if (!\App\Config\AI::isEnabled()) {
                $this->logAutoreplyTrace($waNumber, 'AI_SKIP', 'disabled or no API key (OpenAI/DeepSeek)');
                return false;
            }
        } catch (\Exception $e) {
            $this->logAutoreplyTrace($waNumber, 'AI_SKIP', 'config: ' . $e->getMessage());
            return false;
        }

        try {
            // Use provided keywordConfig (already filtered) or load full config
            // Jika keywordConfig tidak diberikan, load full config (backward compatibility)
            if ($keywordConfig === null) {
                $keywordConfig = $this->loadAutoreplyKeywordConfig();
            }
            $keywordConfig = $this->filterKeywordConfigBySenderGate($keywordConfig);
            $textBodyNorm = preg_replace('/[*_~`]/', '', (string) $textBody);
            $textBodyNorm = preg_replace('/^>\s*/m', '', $textBodyNorm ?? '');
            $messageLength = mb_strlen(strtolower(trim($textBodyNorm ?? '')));
            $keywordConfig = $this->filterKeywordConfigByChatMaxlength($keywordConfig, $messageLength, $textBody);

            $prompt = $this->buildAiIntentClassifierPrompt($textBody, $keywordConfig);

            $this->logAutoreplyTrace($waNumber, 'AI_REQUEST', \App\Config\AI::describePriority());
            $response = $this->callOpenAI($prompt, $waNumber);

            $parsed = $this->decodeAiJsonObject((string) $response);
            $json = $parsed['json'];
            if (!is_array($json)) {
                $this->logAutoreplyTrace($waNumber, 'AI_REJECT', 'unparseable JSON raw=' . mb_substr((string) $response, 0, 200));
                return false;
            }
            if (($parsed['repaired'] ?? false) === true) {
                $this->logAutoreplyTrace(
                    $waNumber,
                    'AI_PARSE',
                    'repaired JSON intent=' . (string) ($json['intent'] ?? '-')
                );
            }

            $intent = $json['intent'] ?? 'FALSE';
            $aiReason = trim(preg_replace('/\s+/', ' ', (string) ($json['reason'] ?? '')) ?? '');
            $reason = $aiReason;
            $ask = false;
            if (array_key_exists('ask', $json)) {
                $rawAsk = $json['ask'];
                if (is_bool($rawAsk)) {
                    $ask = $rawAsk;
                } elseif (is_numeric($rawAsk)) {
                    $ask = ((int) $rawAsk) === 1;
                } else {
                    $ask = in_array(strtolower(trim((string) $rawAsk)), ['true', '1', 'yes'], true);
                }
            }

            $intent = trim(strtoupper((string) $intent));
            $fromBlock = $this->normalizeAiFromBlock(
                $json['from_block'] ?? ($json['fromBlock'] ?? ($json['source_block'] ?? '')),
                $keywordConfig,
                $intent
            );
            $aiIntentRaw = $intent;

            $falseOverride = $this->applyAiFalsePriorityOverride($textBody, $intent, $keywordConfig);
            if ($falseOverride !== null) {
                $this->logAutoreplyTrace(
                    $waNumber,
                    'AI_FALSE_PRIORITY',
                    $falseOverride['reason'] . ' was=' . $aiIntentRaw
                );
                $intent = 'FALSE';
                $fromBlock = $falseOverride['from_block'];
                $reason = $falseOverride['reason'];
            }

            if (in_array($intent, ['SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD', 'INFO_FONNTE'], true)
                && isset($keywordConfig['SALDO'])) {
                $intent = 'SALDO';
                $reason = ($reason !== '' ? $reason . '; ' : '') . 'remap ' . $aiIntentRaw . ' → SALDO';
            }
            if ($intent === 'SALDO' && preg_match('/\btarik\b/i', $textBody)
                && isset($keywordConfig['TARIK_TOKOPAY'])) {
                $intent = 'TARIK_TOKOPAY';
                $reason = ($reason !== '' ? $reason . '; ' : '') . 'remap SALDO+tarik → TARIK_TOKOPAY';
            }

            // Model kadang mengembalikan label bukan daftar (mis. PERTANYAAN) — sering dari teks prompt. Samakan ke STATUS jika jelas tanya siap laundry/cucian.
            if (!isset($keywordConfig[$intent]) && in_array($intent, ['PERTANYAAN', 'QUESTION', 'TANYA', 'PERTANYAAN_UMUM'], true)) {
                if (preg_match('/\b(sudah|udah|sudh|udh|dah|dh)\s+siap\b.{0,120}?\b(laundry|loundry|laundri|londri|cucian)\b/iu', $textBody)
                    || preg_match('/\b(apakah)\b.{0,120}?\bsiap\b.{0,120}?\b(laundry|loundry|laundri|londri|cucian)\b/iu', $textBody)) {
                    $intent = 'STATUS';
                    $reason = 'remap tanya siap laundry → STATUS (ai label bukan)';
                }
            }

            // FALSE / STATUS / MINTA padahal tanya kapan/jam berapa siap = PERMINTAAN
            if (in_array($intent, ['FALSE', 'STATUS', 'KURIR', 'JAM_OPERASIONAL', 'ESTIMASI_SELESAI'], true)
                && $this->messageLooksLikeEstimasiSelesai($textBody)
                && isset($keywordConfig['PERMINTAAN'])
            ) {
                $intent = 'PERMINTAAN';
                $reason = 'remap kapan/jam berapa siap → PERMINTAAN';
            }

            // FALSE padahal konfirmasi sudah bayar/lunas → PENUTUP
            if (($intent === 'FALSE' || $intent === 'REKENING')
                && $this->messageLooksLikePaymentConfirmationPenutup($textBody)
                && isset($keywordConfig['PENUTUP'])
            ) {
                $intent = 'PENUTUP';
                $reason = 'remap konfirmasi sudah bayar → PENUTUP';
            }

            // FALSE padahal ucapan terima kasih (makasi, makasih, thanks, ...) → PENUTUP
            if ($intent === 'FALSE'
                && $this->messageLooksLikeThanksPenutup($textBody)
                && !$this->messageLooksLikeQuestion($textBody)
                && isset($keywordConfig['PENUTUP'])
            ) {
                $intent = 'PENUTUP';
                $reason = 'remap ucapan terima kasih → PENUTUP';
            }

            // FALSE padahal jelas tanya berat order (berapa/brp kilo atau kg) — samakan ke TAGIHAN (bukan tanya harga per kg)
            if ($intent === 'FALSE'
                && preg_match('/\b(brp|brpa|brapa|berapa)\s*(kilo|kg)\b/i', $textBody)
                && !preg_match('/\b(harga|biaya|tarif)\b.{0,50}?\b(per\s*)?(kilo|kg)\b/i', $textBody)) {
                $intent = 'TAGIHAN';
                $reason = 'remap tanya berapa kilo/kg order → TAGIHAN';
            }

            // FALSE padahal brp/berapa + laundry (typo londry) + ku/saya/aku — tanya tagihan order
            if ($intent === 'FALSE' && preg_match('/\b(brp|brpa|brapa|berapa)\b/i', $textBody)
                && preg_match('/\b(laundry|loundry|londri|londry|cucian)\b/i', $textBody)
                && preg_match('/\b(ku|saya|aku)\b/i', $textBody)
                && !preg_match('/\b(bl?m|belum|belom)\s+di\s+(wa|whatsapp)\b/i', $textBody)
                && !preg_match('/\b(harga|tarif)\b.{0,40}?\b(per\s*)?(item|pcs|buah|potong|kg|kilo)\b/i', $textBody)) {
                $intent = 'TAGIHAN';
                $reason = 'remap brp/berapa + laundry + ku/saya/aku → TAGIHAN';
            }

            // FALSE padahal minta info transfer/tf (bukan konfirmasi sudah kirim) — REKENING
            if ($intent === 'FALSE'
                && preg_match('/\b(bisa|boleh|mau|minta)\s+(tf|transfer)\b/i', $textBody)
                && !preg_match('/(telah berhasil mengirimkan|sudah\s+transfer|sudah\s+bayar|sudah\s+kirim|sudah\s+mengirim)\b/i', $textBody)) {
                $intent = 'REKENING';
                $reason = 'remap minta info tf/transfer → REKENING';
            }

            // PENUTUP yang bukan closing ketat → FALSE (info/otw/item/jadwal/janji bayar, bukan ack)
            if ($intent === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap PENUTUP → FALSE (bukan allowlist ketat: thanks/bayar/ack murni)';
            }

            // FALSE padahal follow-up nota: infonya + laundry + antar + waktu (sama pola regex NOTA)
            if ($intent === 'FALSE' && isset($keywordConfig['NOTA'])
                && preg_match('/\b(info|infonya|informasi)\b.{0,280}?\b(laundry|loundry|londri|laondri|cucian)\b.{0,220}?\b(antar|nyerahkan|nitip)\b.{0,120}?\b(pagi|siang|sore|malam)\b.{0,40}?\b(tadi|td|kemarin|kmrn)\b/iu', $textBody)) {
                $intent = 'NOTA';
                $reason = 'remap infonya + laundry + antar + waktu → NOTA';
            }

            // FALSE padahal konfirmasi layanan untuk cucian yang baru diantar/masukkan = NOTA
            if ($intent === 'FALSE' && isset($keywordConfig['NOTA'])
                && $this->messageLooksLikeNotaLayananSetelahAntar($textBody)) {
                $intent = 'NOTA';
                $reason = 'remap antar/masukkan + pilihan layanan → NOTA';
            }

            // FALSE padahal minta pricelist / daftar harga = HARGA
            if ($intent === 'FALSE' && isset($keywordConfig['HARGA']) && $this->messageLooksLikePricelistRequest($textBody)) {
                $intent = 'HARGA';
                $reason = 'remap pricelist/daftar harga → HARGA';
            }

            // KURIR / FALSE padahal tanya tarif ongkos by durasi atau jenis layanan = HARGA
            if (($intent === 'KURIR' || $intent === 'FALSE') && isset($keywordConfig['HARGA'])
                && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBody)) {
                $intent = 'HARGA';
                $reason = 'remap ongkos + durasi/tier layanan → HARGA';
            }

            // MINTA padahal "kalau/klo ... antar/jemput" = hipotetis, bukan minta kurir
            if ($intent === 'KURIR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap kalau+antar/jemput → FALSE (hipotetis, bukan minta kurir)';
            }

            // MINTA padahal customer sendiri yang antar/jemput ("kami aja yang antar")
            if ($intent === 'KURIR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap customer self antar/jemput → FALSE (bukan minta kurir)';
            }

            // MINTA padahal hanya tanya ongkir/ongkos antar-jemput (belum minta kurir)
            if ($intent === 'KURIR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap pertanyaan ongkir/ongkos → FALSE (bukan minta kurir)';
            }

            // Legacy HARGA_PAKET(_D) dari AI lama → unified HARGA
            if (in_array($intent, ['HARGA_PAKET', 'HARGA_PAKET_D'], true)) {
                $legacyIntent = $intent;
                $intent = 'HARGA';
                $reason = 'remap legacy ' . $legacyIntent . ' → HARGA unified';
            }

            $textBodyForPaketCheck = mb_strtolower(preg_replace('/[*_~`]/', '', (string) $textBody), 'UTF-8');

            // MINTA + tanya harga paket/antar → HARGA
            if ($intent === 'KURIR' && $this->messageIsHargaDeliveryQuestion($textBodyForPaketCheck)) {
                $intent = 'HARGA';
                $reason = 'remap minta + harga delivery → HARGA';
            }

            // Log: text | intent | ask | from_block | reason
            $parseDetail = $this->formatAiParseTrace($intent, $ask, $reason, $fromBlock, $aiIntentRaw, $aiReason);
            if (class_exists('\Log')) {
                \Log::write("{$textBody} | {$parseDetail}", 'wa', 'intent');
            }

            // Check if this is a valid intent from config
            if (isset($keywordConfig[$intent])) {
                $this->logAutoreplyTrace($waNumber, 'AI_PARSE', $parseDetail);
                // Return intent (case will be taken from config in process())
                // Ensure returning ARRAY as expected by process()
                return [
                    'intent' => $intent,
                    'ask' => $ask,
                    'reason' => $reason,
                    'from_block' => $fromBlock,
                ];
            }

            if ($intent === 'FALSE') {
                $this->logAutoreplyTrace($waNumber, 'AI_PARSE', $parseDetail);
                return [
                    'intent' => 'FALSE',
                    'ask' => $ask,
                    'reason' => $reason,
                    'from_block' => $fromBlock,
                ];
            }

            $this->logAutoreplyTrace($waNumber, 'AI_REJECT', 'intent not in config: ' . $intent);
            // Intent not in config, return false
            return false;
        } catch (\Exception $e) {
            $this->logAutoreplyTrace($waNumber, 'AI_ERROR', $e->getMessage());
            if (class_exists('\Log')) {
                \Log::write("AI ERROR: " . $e->getMessage(), 'wa_error', 'AI');
            }
            return false;
        }
    }

    /**
     * POST chat/completions (format OpenAI). Dipakai OpenAI dan DeepSeek (endpoint kompatibel).
     *
     * @param string $url      Mis. https://api.openai.com/v1/chat/completions
     * @param array  $data     Body JSON (model, messages, …)
     * @param string $label    Untuk pesan error (OpenAI / DeepSeek)
     */
    private function executeOpenAiCompatibleChat(string $url, string $apiKey, array $data, string $label, int $timeout): string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $connectTimeout = min(30, max(15, (int) $timeout));
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \Exception("{$label} API cURL error: {$curlError}");
        }
        if ($httpCode !== 200) {
            $errorMsg = "{$label} API error: HTTP {$httpCode}";
            if ($result) {
                $errorData = json_decode($result, true);
                if (isset($errorData['error']['message'])) {
                    $errorMsg .= ' - ' . $errorData['error']['message'];
                }
            }
            throw new \Exception($errorMsg);
        }

        $response = json_decode($result, true);
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        throw new \Exception("{$label} API: Invalid response structure");
    }

    /**
     * @param string|null $waNumber Untuk log AI_FALLBACK ke wa_autoreply
     */
    private function callOpenAI($prompt, $waNumber = null)
    {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../Config/AI.php';
        }

        return $this->executeOpenAIRequest($prompt, 'gpt-4o-mini', $waNumber);
    }

    /**
     * Intent classifier: urutan sesuai Env::AI_PRIORITY (deepseek|openai).
     * Provider kedua dipakai jika yang pertama gagal (timeout, HTTP, dll.).
     *
     * @param string|null $waNumber Untuk log trace
     */
    private function executeOpenAIRequest($prompt, $model, $waNumber = null)
    {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../Config/AI.php';
        }

        $providers = \App\Config\AI::getProvidersInOrder();
        $temperature = \App\Config\AI::getTemperature();
        $timeout = \App\Config\AI::getTimeout();
        if ($providers === []) {
            throw new \Exception('No OpenAI or DeepSeek API key configured');
        }

        $lastError = null;
        foreach ($providers as $i => $p) {
            $data = [
                'model' => $p['id'] === 'openai' && is_string($model) && $model !== ''
                    ? $model
                    : $p['model'],
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $temperature,
            ];
            $maxTokens = (int) \App\Config\AI::getMaxTokens();
            if ($maxTokens < 80) {
                $maxTokens = 180;
            }
            if ($p['id'] === 'openai') {
                $data['max_completion_tokens'] = $maxTokens;
            } else {
                $data['max_tokens'] = $maxTokens;
            }
            try {
                $withJson = $data;
                $withJson['response_format'] = ['type' => 'json_object'];
                try {
                    return $this->executeOpenAiCompatibleChat(
                        $p['url'],
                        $p['key'],
                        $withJson,
                        $p['label'],
                        $timeout
                    );
                } catch (\Exception $formatErr) {
                    $em = strtolower($formatErr->getMessage());
                    $formatIssue = strpos($em, 'response_format') !== false
                        || strpos($em, 'json_object') !== false
                        || strpos($em, 'http 400') !== false;
                    if (!$formatIssue) {
                        throw $formatErr;
                    }
                    return $this->executeOpenAiCompatibleChat(
                        $p['url'],
                        $p['key'],
                        $data,
                        $p['label'],
                        $timeout
                    );
                }
            } catch (\Exception $e) {
                $lastError = $e;
                $next = $providers[$i + 1] ?? null;
                if ($next === null) {
                    throw $e;
                }
                if ($waNumber !== null) {
                    $this->logAutoreplyTrace(
                        $waNumber,
                        'AI_FALLBACK',
                        $next['label'] . ' after ' . $p['label'] . ' failed: ' . mb_substr($e->getMessage(), 0, 240)
                    );
                }
            }
        }

        throw $lastError ?? new \Exception('AI request failed');
    }

    /**
     * Chat completions: urutan sesuai Env::AI_PRIORITY (deepseek|openai).
     *
     * @param array       $messages [['role'=>'system','content'=>...], ['role'=>'user','content'=>...]]
     * @param int         $maxTokens
     * @param string|null $waNumber  Opsional: log AI_FALLBACK
     * @return string
     */
    private function executeOpenAIRequestWithMessages($messages, $maxTokens = 400, $waNumber = null)
    {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../Config/AI.php';
        }
        $providers = \App\Config\AI::getProvidersInOrder();
        $temperature = \App\Config\AI::getTemperature();
        $timeout = \App\Config\AI::getTimeout();
        if ($providers === []) {
            throw new \Exception('No OpenAI or DeepSeek API key configured');
        }

        $lastError = null;
        foreach ($providers as $i => $p) {
            $data = [
                'model' => $p['model'],
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ];
            try {
                return $this->executeOpenAiCompatibleChat(
                    $p['url'],
                    $p['key'],
                    $data,
                    $p['label'],
                    $timeout
                );
            } catch (\Exception $e) {
                $lastError = $e;
                $next = $providers[$i + 1] ?? null;
                if ($next === null) {
                    throw $e;
                }
                if ($waNumber !== null) {
                    $this->logAutoreplyTrace(
                        $waNumber,
                        'AI_FALLBACK',
                        $next['label'] . ' after ' . $p['label'] . ' failed (messages): ' . mb_substr($e->getMessage(), 0, 240)
                    );
                }
            }
        }

        throw $lastError ?? new \Exception('AI request failed');
    }
    
    /**
     * Get or create conversation with case management.
     * Public so webhook can open CSW + push WS before slow intent/AI.
     */
    /**
     * Append/open case di conv_case tanpa mengubah last_message (untuk Fonnte skipPersist).
     */
    private function appendOpenConvCase($db, $conv, int $case): void
    {
        if ($case === 0 || empty($conv->wa_number)) {
            return;
        }
        if (!class_exists('\\App\\Helpers\\CRM\\CrmCaseHelper')) {
            require_once __DIR__ . '/../Helpers/CRM/CrmCaseHelper.php';
        }

        $caseList = \App\Helpers\CRM\CrmCaseHelper::decodeList($conv->conv_case ?? null);
        $merged = \App\Helpers\CRM\CrmCaseHelper::mergeOpenCase($caseList, $case);
        if (empty($merged['changed'])) {
            return;
        }

        $db->update('wa_conversations', [
            'conv_case' => \App\Helpers\CRM\CrmCaseHelper::encodeList($merged['list']),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['wa_number' => $conv->wa_number]);
    }

    /** Hapus assignment CSW jika pengirim adalah karyawan (SQL NULL). */
    private function clearAssignedUserIdIfKaryawan($db, $convId): void
    {
        if (empty($this->senderContext['is_karyawan'])) {
            return;
        }
        $convId = (int) $convId;
        if ($convId <= 0) {
            return;
        }
        try {
            $db->query('UPDATE wa_conversations SET assigned_user_id = NULL WHERE id = ? LIMIT 1', [$convId]);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('clearAssignedUserIdIfKaryawan: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    public function getOrCreateConversationWithCase($db, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $cust_id = null, $lastMessage = null, $case = null)
    {
        if ($this->intentLabMode) {
            return 0;
        }
        if ($contactName !== null) {
            $contactName = trim((string) $contactName);
        }
        if ($this->skipConversationPersist) {
            $conv = $this->findExistingWaConversationRow($db, $waNumber);
            if ($conv) {
                $convId = (int) ($conv->id ?? 0);
                $this->clearAssignedUserIdIfKaryawan($db, $convId);
                // Fonnte: jangan rewrite last_message/status, tapi case CRM (simbol kuning) tetap boleh
                if ($convId > 0 && $case !== null && (int) $case !== 0) {
                    try {
                        $this->appendOpenConvCase($db, $conv, (int) $case);
                    } catch (\Throwable $e) {
                        if (class_exists('\Log')) {
                            \Log::write('skipPersist appendOpenConvCase: ' . $e->getMessage(), 'wa_error', 'Autoreply');
                        }
                    }
                }
                return $convId;
            }

            return 0;
        }

        // Try to find existing conversation (same logical ID can be stored as +628…, 628…, 08…)
        $conv = $this->findExistingWaConversationRow($db, $waNumber);
        
        if ($conv) {           
            $updateData = [
                'status' => 'open',
                'last_in_at' => date('Y-m-d H:i:s'),
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'last_message' => $lastMessage,
            ];
            // Jangan overwrite field penting dengan null (hot-path push sebelum getUserData)
            if ($contactName !== null && trim((string) $contactName) !== '') {
                $updateData['contact_name'] = $contactName;
            }
            if (!empty($this->senderContext['is_karyawan'])) {
                $updateData['assigned_user_id'] = null;
                $updateData['code'] = null;
                $updateData['cust_id'] = null;
            } else {
                if ($assigned_user_id !== null && $assigned_user_id !== '') {
                    $updateData['assigned_user_id'] = $assigned_user_id;
                }
                if ($code !== null && $code !== '') {
                    $updateData['code'] = $code;
                }
                if ($cust_id !== null && $cust_id !== '') {
                    $updateData['cust_id'] = $cust_id;
                }
            }
            
            // Only update case if not null and not 0 (Append to existing list)
            if ($case !== null && (int)$case !== 0) {
                if (!class_exists('\\App\\Helpers\\CRM\\CrmCaseHelper')) {
                    require_once __DIR__ . '/../Helpers/CRM/CrmCaseHelper.php';
                }
                $caseList = \App\Helpers\CRM\CrmCaseHelper::decodeList($conv->conv_case ?? null);
                $merged = \App\Helpers\CRM\CrmCaseHelper::mergeOpenCase($caseList, (int) $case);
                if (!empty($merged['changed'])) {
                    $updateData['conv_case'] = \App\Helpers\CRM\CrmCaseHelper::encodeList($merged['list']);
                }
            }

            $db->update('wa_conversations', $updateData, ['wa_number' => $conv->wa_number]);
            $convId = (int) ($conv->id ?? 0);
            $this->clearAssignedUserIdIfKaryawan($db, $convId);
            return $convId;
        }

        // Create new conversation
        $convData = [
            'assigned_user_id' => $assigned_user_id,
            'wa_number' => $waNumber,
            'contact_name' => $contactName,
            'code' => $code,
            'cust_id' => $cust_id,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
            'last_in_at' => date('Y-m-d H:i:s'),
            'last_message_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'last_message' => $lastMessage,
        ];
        if (!empty($this->senderContext['is_karyawan'])) {
            unset($convData['assigned_user_id']);
            unset($convData['code']);
            unset($convData['cust_id']);
        }
        
        // Only set case if not null and not 0
        if ($case !== null && (int)$case !== 0) {
            $convData['conv_case'] = json_encode([[
                'case' => $case,
                'status' => 'open',
                'timestamp' => date('Y-m-d H:i:s')
            ]]);
        }

        if($db->insert('wa_conversations', $convData)) {
            return $db->insert_id();
        }
        return 0;
    }

    /**
     * Hanya digit 0–9 dari string nomor (kedua sisi perbandingan memakai ini di PHP).
     */
    private function normalizePhoneDigitsOnlyPhp(?string $s): string
    {
        if ($s === null || $s === '') {
            return '';
        }

        return preg_replace('/[^0-9]/u', '', $s);
    }

    /**
     * Variasi format nomor WA untuk lookup exact di kolom wa_number (+62… / 62… / 08… / 8…).
     *
     * @return string[]
     */
    private function waNumberLookupVariants(string $waNumber): array
    {
        $trimmed = trim($waNumber);
        $d = $this->normalizePhoneDigitsOnlyPhp($waNumber);
        $out = [$trimmed];
        if (strlen($d) < 9) {
            return array_values(array_unique(array_filter($out)));
        }
        if (strpos($d, '62') === 0 && strlen($d) >= 11) {
            $rest = substr($d, 2);
            $out[] = '+' . $d;
            $out[] = $d;
            $out[] = '0' . $rest;
            if (strpos($rest, '8') === 0 || strlen($rest) >= 9) {
                $out[] = $rest;
            }
        } elseif (strpos($d, '0') === 0 && strlen($d) >= 10) {
            $out[] = $d;
            $out[] = '62' . substr($d, 1);
            $out[] = '+62' . substr($d, 1);
        } elseif (strpos($d, '8') === 0 && strlen($d) >= 9) {
            $out[] = $d;
            $out[] = '0' . $d;
            $out[] = '62' . $d;
            $out[] = '+62' . $d;
        }
        return array_values(array_unique(array_filter($out)));
    }

    /**
     * MySQL 5.7: tebakan digit dari kolom nomor dengan rantai REPLACE (tanpa REGEXP_REPLACE).
     * Karakter di luar daftar bisa tetap ada; sisi WA sudah dibersihkan penuh di PHP.
     *
     * @param string $column Nama kolom (mis. nomor_pelanggan, wa_number)
     */
    private function sqlPhoneColumnDigitsOnlyExpr(string $column): string
    {
        $expr = "TRIM({$column})";
        foreach (['+', '-', ' ', '(', ')', '.', '/', "'", '"', ':', ';', ',', '_', '*', '#', '@', '&', "\t"] as $ch) {
            $q = $ch === "'" ? "''" : $ch;
            $expr = "REPLACE({$expr},'{$q}','')";
        }

        return $expr;
    }

    /**
     * Cari pelanggan: id dari sender context, atau LIKE '%852…' setelah buang +62/62/0.
     * Nomor WA dinormalisasi ke digit saja di PHP; nomor_pelanggan di DB dinormalisasi sebisa mungkin di SQL (MySQL 5.7).
     *
     * @param \App\Core\DB $db DB laundry (biasanya getInstance(1))
     * @param string $selectColumns kolom SELECT tanpa kata SELECT
     * @return array
     */
    private function queryPelangganRowsByWaNumber($db, string $phoneIn, string $waNumber, string $selectColumns = 'id_pelanggan, nama_pelanggan'): array
    {
        $ids = $this->senderIdsPelanggan();
        if ($ids !== []) {
            $idsIn = implode(',', $ids);
            $sql = 'SELECT ' . $selectColumns . ' FROM pelanggan WHERE id_pelanggan IN (' . $idsIn . ') ORDER BY id_pelanggan ASC';

            return $db->query($sql)->result_array() ?: [];
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }
        $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber);
        if ($nomor === null || strlen($nomor) < 8) {
            return [];
        }
        $digits = \App\Helpers\CRM\WaSenderContext::sqlDigitsExpr('nomor_pelanggan');
        $digits2 = \App\Helpers\CRM\WaSenderContext::sqlDigitsExpr('nomor_pelanggan_2');
        $sql = 'SELECT ' . $selectColumns . ' FROM pelanggan WHERE ' . $digits . ' LIKE ? OR ' . $digits2 . ' LIKE ? ORDER BY id_pelanggan ASC';

        return $db->query($sql, ['%' . $nomor, '%' . $nomor])->result_array() ?: [];
    }

    /**
     * True jika baris wa_conversations untuk nomor ini punya partner = 1 (flag mitra dari CRM).
     */
    private function isWaPartnerChannel($db, string $waNumber): bool
    {
        $conv = $this->findExistingWaConversationRow($db, $waNumber);
        if (!$conv) {
            return false;
        }

        return isset($conv->partner) && (int) $conv->partner === 1;
    }

    /**
     * Cari baris wa_conversations: exact dulu (variasi format), lalu suffix nasional 852… (MySQL 5.7).
     *
     * @return object|null row from wa_conversations
     */
    private function findExistingWaConversationRow($db, string $waNumber): ?object
    {
        if (!class_exists('\\App\\Helpers\\CRM\\WaConversationAlias')) {
            require_once __DIR__ . '/../Helpers/CRM/WaConversationAlias.php';
        }
        $hints = ['phone' => $waNumber];
        if (\App\Helpers\CRM\WaConversationAlias::looksLikeLidFallback($waNumber)) {
            $hints = ['lid' => $waNumber];
        }
        $byAlias = \App\Helpers\CRM\WaConversationAlias::findConversationRow($db, $hints);
        if ($byAlias) {
            return $byAlias;
        }

        foreach ($this->waNumberLookupVariants($waNumber) as $variant) {
            $existing = $db->get_where('wa_conversations', ['wa_number' => $variant], 1);
            if ($existing->num_rows() > 0) {
                return $existing->row();
            }
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WaSenderContext')) {
            require_once __DIR__ . '/../Helpers/CRM/WaSenderContext.php';
        }
        $nomor = \App\Helpers\CRM\WaSenderContext::toNomorNasional($waNumber);
        if ($nomor === null) {
            return null;
        }

        $digits = $this->sqlPhoneColumnDigitsOnlyExpr('wa_number');
        $sql = 'SELECT * FROM wa_conversations WHERE ' . $digits . ' LIKE ? ORDER BY id DESC LIMIT 1';

        try {
            $db->query($sql, ['%' . $nomor]);
            if ($db->num_rows() > 0) {
                return $db->row();
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('findExistingWaConversationRow: ' . $e->getMessage(), 'wa_error', 'WAReplies');
            }
        }

        return null;
    }

    /**
     * Normalize phone number to +62 format
     */
    private function normalizePhoneNumber($phone)
    {
        if (!$phone) return null;
        
        // Remove non-numeric except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Handle 08... -> +628...
        if (substr($phone, 0, 1) === '0') {
            return '+62' . substr($phone, 1);
        }
        
        // Handle 628... -> +628...
        if (substr($phone, 0, 2) === '62') {
            return '+' . $phone;
        }
        
        // Handle 8... -> +628... (just in case)
        if (substr($phone, 0, 1) === '8') {
            return '+62' . $phone;
        }

        // If starts with +, return it
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // Default: assume it's already +62...
        return $phone;
    }
}
