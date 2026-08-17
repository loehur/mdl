<?php

namespace App\Models;

use App\Core\DB;

// Pastikan trait ter-load (jangan andalkan autoload saja saat require_once WAReplies.php dari webhook)
require_once __DIR__ . '/WARepliesKurirTrait.php';
require_once __DIR__ . '/WARepliesLokasiTrait.php';
require_once __DIR__ . '/WARepliesPermintaanTrait.php';

class WAReplies
{
    use WARepliesKurirTrait;
    use WARepliesLokasiTrait;
    use WARepliesPermintaanTrait;

    private $waService = null;
    private $noRegisterTextVariations = [
        "Maaf, nomor kakak belum terdaftar di sistem kami.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 🙏",
        "Mohon maaf, nomor kakak tidak ditemukan di sistem.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 😊",
        "Maaf, nomor kakak belum terdaftar di data kami.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 🙏",
        "Mohon maaf, nomor kakak belum ada di sistem Madinah Laundry.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 😊",
        "Maaf, nomor kakak belum terdaftar di database kami.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 🙏",
        "Mohon maaf, nomor kakak tidak terdaftar di sistem.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 🙏",
        "Maaf, nomor kakak belum terdaftar di sistem Madinah Laundry.\n\nBoleh bantu kirim bukti nota di sini ya, agar kami bantu pengecekan. Terima kasih 😊",
    ];

    private function getNoRegisterText()
    {
        return $this->noRegisterTextVariations[array_rand($this->noRegisterTextVariations)];
    }
    /** @var string|null Nama handler saat ini (untuk log saat send gagal) */
    private $currentHandler = null;
    /** @var string|null Nama contact dari process() untuk sapaan AI (pak/bu/kak) */
    private $currentContactName = null;
    /** @var array Cache sapaan AI per nama (nama => kak/bang) untuk hindari panggilan berulang */
    private $sapaanAiCache = [];
    /** @var object|null Custom sender (FonnteReplyAdapter) - bila set, pakai ini instead of YCloud */
    private $customSender = null;

    /**
     * ID pesan masuk yang sedang dijawab (yCloud wamid / Fonnte inboxid) untuk quote-reply.
     * @var string|null
     */
    private $inboundReplyToId = null;

    /**
     * Jika true: tidak INSERT/UPDATE wa_conversations (webhook Fonnte — CSW Fonnte di wa_fonnte_csw saja).
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

    /** Idle agent manusia (menit) sebelum AI kembali boleh balas intent sosial */
    private const HUMAN_ACTIVE_IDLE_MINUTES = 60;

    /** PEMBUKA: cooldown handler + jeda sapaan jika ada outbound terakhir */
    private const PEMBUKA_RECENT_CHAT_MINUTES = 30;

    /** TTL session ESTIMASI_SELESAI (menit) */
    private const ESTIMASI_SESSION_TTL_MINUTES = 60;

    /** @var array|null Cache keyword config (DB/loader) untuk cek rate limit per handler */
    private $autoreplyKeywordConfig = null;

    /**
     * Set custom sender untuk Fonnte (bila webhook dari Fonnte, bukan YCloud)
     * @param object $adapter Instance FonnteReplyAdapter
     */
    public function setCustomSender($adapter)
    {
        $this->customSender = $adapter;
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
     * Get WhatsApp Service instance (lazy loading)
     * Bila setCustomSender() dipanggil, return adapter tersebut
     */
    private function getWaService()
    {
        if ($this->customSender !== null) {
            return $this->customSender;
        }
        if ($this->waService === null) {
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../Helpers/CRM/WhatsAppService.php';
            }
            $this->waService = new \App\Helpers\CRM\WhatsAppService();
        }
        return $this->waService;
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
            $code
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
            // Jangan push WS di sini untuk yCloud: WhatsAppService::saveOutboundMessage
            // sudah broadcast agent_message_sent (id = DB). Push kedua pakai id provider
            // membuat CRM tampil double sampai refresh.
            // Fonnte (customSender): pushToWebSocket memang no-op.
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

    /**
     * Handler di AutoReplyKeywords tanpa ai_prompt = regex-only / perintah admin, tanpa cooldown.
     */
    private function handlerSkipsAutoreplyRateLimit(string $handler): bool
    {
        $h = strtoupper($handler);
        if (in_array($h, ['SALDO', 'SALDO_IAK', 'SALDO_TOKOPAY', 'SALDO_YCLOUD', 'INFO_FONNTE'], true)) {
            return true;
        }
        if ($this->autoreplyKeywordConfig === null) {
            $this->autoreplyKeywordConfig = $this->loadAutoreplyKeywordConfig();
        }
        $config = $this->autoreplyKeywordConfig[$handler] ?? null;
        if ($config === null) {
            return false;
        }

        return !isset($config['ai_prompt']);
    }

    /**
     * Durasi cooldown per handler (menit). Default 1; jam operasional/tutup = 60;
     * MINTA_JEMPUT_ANTAR = 1440 (24 jam, sama seperti DEFAULT fallback CS/Admin menunggu).
     */
    private function getAutoreplyCooldownMinutes(string $handler): int
    {
        $h = strtoupper($handler);
        if ($h === 'PEMBUKA') {
            return self::PEMBUKA_RECENT_CHAT_MINUTES;
        }
        if ($h === 'JAM_OPERASIONAL' || $h === 'JAM_TUTUP') {
            return 60;
        }
        if ($h === 'MINTA_JEMPUT_ANTAR' || $h === 'LOKASI' || $h === 'PERMINTAAN') {
            // Multi-turn session aktif: jangan blok 24 jam
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
        $tables = ['wa_messages_out', 'wa_fonnte_messages_out'];
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
        return $this->hasRecentOutboundMessage($waNumber, self::PEMBUKA_RECENT_CHAT_MINUTES);
    }

    /**
     * Intent yang tetap boleh autoreply saat agent manusia baru aktif.
     * Data/self-service + perintah admin (tanpa ai_prompt).
     */
    private function isIntentAllowedDuringHumanActive(string $handler): bool
    {
        $h = strtoupper($handler);
        $allow = [
            'STATUS',
            'ESTIMASI_SELESAI',
            'TAGIHAN',
            'NOTA',
            'HARGA',
            'HARGA_PAKET',
            'HARGA_PAKET_D',
            'JAM_OPERASIONAL',
            'JAM_TUTUP',
            'JAM_BUKA',
        ];
        if (in_array($h, $allow, true)) {
            return true;
        }

        return $this->handlerSkipsAutoreplyRateLimit($handler);
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
     * Fungsi terpusat untuk handler yang butuh keduanya (PEMBUKA, PENUTUP, PEMBERITAHUAN, JAM_OPERASIONAL).
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
     * Penutup / pemberitahuan singkat tetap (tanpa AI): Baik / Oke / Ok + sapaan + emote soft.
     */
    private function getRandomSiapReply($sapaan)
    {
        return $this->pickRandomFromList($this->expandPenutupRepliesWithSoftSmiles([
            'Baik {sapaan}',
            'Oke {sapaan}',
            'Ok {sapaan}',
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
        if ($this->messageLooksLikePaymentConfirmationPenutup($textBody)) {
            return false;
        }
        if ($this->messageLooksLikeThanksPenutup($textBody)) {
            return false;
        }

        return true;
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
     * PENUTUP hanya untuk ack/kalimat sangat pendek. Lebih dari 50 karakter (trim) = bukan penutup.
     */
    private function messageExceedsPenutupMaxLength(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        return mb_strlen(trim($text)) > 50;
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
     * Hipotetis/kondisional: "kalau/klo/kalo" lalu antar/jemput — BUKAN permintaan kurir.
     * Contoh: "Kalau express di antar sekarang", "klo jemput brp?"
     */
    private function messageLooksLikeKalauAntarJemputBukanMinta(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
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

        // (1) Terima kasih (termasuk typo: trima ksih, dll.)
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
     * PENUTUP yang bukan closing ketat: info/otw/daftar item/jadwal/janji bayar → kandidat PEMBERITAHUAN.
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
        if ($this->messageExceedsPenutupMaxLength($text)
            && !$this->messageLooksLikePaymentConfirmationPenutup($text)
            && !$this->messageLooksLikeThanksPenutup($text)
        ) {
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
     * PEMBUKA/PENUTUP/PEMBERITAHUAN tidak boleh jika ini true.
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
    private function trySendBelumAdaTagihanAutoreply($waService, string $waNumber, ?string $textBody, string $namaPelanggan, ?string $linkSuffix = null, string $logPrefix = 'SKIP'): void
    {
        if (!$this->shouldSendGenericNoDataAutoreply($textBody)) {
            $this->logAutoreplyTrace($waNumber, $logPrefix, 'belum_ada_tagihan_skipped_not_strict_keyword');
            return;
        }
        $text = 'Pak/Bu *' . $namaPelanggan . '*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih 😊';
        if ($linkSuffix !== null && $linkSuffix !== '') {
            $text .= "\n" . $linkSuffix;
        }
        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
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
            if ($assigned_user_id === null || $assigned_user_id === '') {
                $assigned_user_id = $ctx['assigned_user_id'] ?? $assigned_user_id;
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
            && !$this->messageBreaksPermintaanSession($textBodyToCheck, $fullKeywordConfig)) {
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

        // Session ESTIMASI aktif (TTL 1 jam): follow-up ke petugas HANYA jika masih relevan;
        // pesan tidak terkait → jangan spam ack, biarkan intent lain / diam.
        $estSessionEarly = $this->getEstimasiSession($waNumber);
        if ($estSessionEarly !== null) {
            $pendingClarifyJemput = (bool) preg_match(
                '/pending_clarify_jemput_vs_selesai=1/',
                (string) ($estSessionEarly['summary'] ?? '')
            );
            // Saat klarifikasi jemput vs selesai, jangan putus session karena regex MINTA ("jemput")
            if ($pendingClarifyJemput
                || !$this->messageBreaksEstimasiSession($textBodyToCheck, $fullKeywordConfig)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'estimasi_session_followup→ESTIMASI_SELESAI case=4');
                $this->currentHandler = 'ESTIMASI_SELESAI';
                $consumed = $this->handleEstimasi_Selesai($phoneIn, $waNumber, $textBody);
                if ($consumed !== false) {
                    $conversationId = $this->getOrCreateConversationWithCase(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
                    );
                    $this->logAutoreplyTrace($waNumber, 'DONE', 'estimasi_session_followup_ok');

                    return (object) [
                        'case' => 4,
                        'notify' => true,
                        'conversation_id' => $conversationId,
                    ];
                }
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'estimasi_session_unrelated→continue_routing');
                // fall through ke regex/AI intent lain
            }
        }

        // Session LOKASI aktif: lengkapi alamat (kecuali intent jelas lain / jemput-antar)
        try {
            if ($this->getLokasiSession($waNumber) !== null
                && !$this->messageLooksLikeMintaJemputAntar($textBodyToCheck, $fullKeywordConfig)
                && !$this->messageBreaksLokasiSession($textBodyToCheck, $fullKeywordConfig)
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

        // Session KURIR aktif: follow-up MINTA_JEMPUT_ANTAR
        if ($this->getKurirSession($waNumber) !== null) {
            $kurirSessEarly = $this->getKurirSession($waNumber);
            // Kurir menunggu LOKASI selesai → serahkan ke lokasi bila session ada
            try {
                if ($kurirSessEarly && (string) ($kurirSessEarly['step'] ?? '') === 'wait_lokasi') {
                    if ($this->getLokasiSession($waNumber) !== null
                        && !$this->messageLooksLikeMintaJemputAntar($textBodyToCheck, $fullKeywordConfig)
                        && !$this->messageBreaksLokasiSession($textBodyToCheck, $fullKeywordConfig)
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
                // fall through ke routing ESTIMASI
            } elseif ($this->messageBreaksKurirSession($textBodyToCheck, $fullKeywordConfig, $hasActiveSale)) {
                // Topik pindah (status/bill/harga/ingat/…) → clear agar tidak tiba-tiba tanya shareloc lagi
                $this->clearKurirSession($waNumber);
                $this->clearLokasiSession($waNumber);
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_cleared→other_intent');
            } else {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_followup→MINTA_JEMPUT_ANTAR case=2');
                $this->currentHandler = 'MINTA_JEMPUT_ANTAR';
                // Bypass cooldown for active session
                $kurirConsumed = $this->handleMinta_Jemput_Antar($phoneIn, $waNumber, $textBody);
                if ($kurirConsumed !== false) {
                    $conversationId = $this->getOrCreateConversationWithCase(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 2
                    );
                    $this->logAutoreplyTrace($waNumber, 'DONE', 'kurir_session_followup_ok');

                    return (object) [
                        'case' => 2,
                        'notify' => true,
                        'conversation_id' => $conversationId,
                    ];
                }
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'kurir_session_unrelated→continue_routing');
                // fall through ke regex/AI intent lain
            }
        }
        } // end !$intentLabMode session followups

        // "masih/msh/msih bisa/bs terima kain?" atau "masih nerima ga klo gosok aj?" -> konfirmasi ke petugas + jam operasional (PRIORITAS)
        // BEDA dengan "masih buka?" yang jawab "masih buka kak/bang"
        $masihBisaTerimaPattern = '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis|b\s*s)(?:\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?)?\s*\??\s*$/i';
        $masihTerimaGosokPattern = '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i';
        if (preg_match($masihBisaTerimaPattern, $textBodyToCheck) || preg_match($masihTerimaGosokPattern, $textBodyToCheck)) {
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
                    if ($handler !== 'PENUTUP' && $this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', $handler . '→PENUTUP payment_confirm');
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
                    // Pertanyaan (termasuk tanpa tanda ?) TIDAK boleh masuk PEMBUKA, PENUTUP, atau PEMBERITAHUAN
                    if (($handler === 'PEMBUKA' || $handler === 'PENUTUP' || $handler === 'PEMBERITAHUAN') && $this->messageLooksLikeQuestion($textBody)) {
                        continue;
                    }
                    // waalaikumsalam = balasan salam, bukan PEMBUKA (beda dari assalamualaikum)
                    if ($handler === 'PEMBUKA' && $this->messageIsWalaikumsalamReply($textBodyToCheck)) {
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: "saya/aku ambil" / "kami aja yang antar" = pemberitahuan, bukan minta kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBodyToCheck)) {
                        if (isset($fullKeywordConfig['PEMBERITAHUAN']) && !$this->messageLooksLikeQuestion($textBody)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'MINTA_JEMPUT_ANTAR→PEMBERITAHUAN customer_self');
                            $handler = 'PEMBERITAHUAN';
                            $config = $fullKeywordConfig['PEMBERITAHUAN'] ?? $config;
                        } else {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'MINTA_JEMPUT_ANTAR→customer_self_antar_jemput');
                            continue;
                        }
                    }
                    // LOKASI tapi jelas minta jemput/antar → MINTA_JEMPUT_ANTAR
                    if ($handler === 'LOKASI' && $this->messageLooksLikeMintaJemputAntar($textBodyToCheck, $fullKeywordConfig)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'LOKASI→MINTA_JEMPUT_ANTAR');
                        $handler = 'MINTA_JEMPUT_ANTAR';
                    }
                    // MINTA_JEMPUT_ANTAR: udh/sdh/sudah + bisa dijemput/diambil = tanya STATUS order, bukan minta kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && preg_match('/\b(udah|sudah|udh|sdh|dah|dh)\s+bisa\s*(di\s*)?(jemput|ambil)\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // STATUS: tanya kapan/jam berapa siap = ESTIMASI, bukan cek status sekarang
                    if ($handler === 'STATUS'
                        && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'STATUS→ESTIMASI_SELESAI perkiraan');
                        $handler = 'ESTIMASI_SELESAI';
                    }
                    // Ambigu "bisa jemput … sore/pagi ini": ada order aktif → ESTIMASI; tidak ada → tetap MINTA kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)) {
                        if ($this->pelangganHasActiveSale($phoneIn, $waNumber)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'MINTA_JEMPUT_ANTAR→ESTIMASI_SELESAI has_sale');
                            $handler = 'ESTIMASI_SELESAI';
                        } else {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_KEEP', 'MINTA_JEMPUT_ANTAR keep (no active sale)');
                        }
                    }
                    // "diantar kembali selambatnya hari minggu" tanpa sale = PERMINTAAN, bukan jam kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeAntarKembaliDeadline($textBodyToCheck)
                        && !$this->pelangganHasActiveSale($phoneIn, $waNumber)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'MINTA_JEMPUT_ANTAR→PERMINTAAN antar_kembali_deadline');
                        $handler = 'PERMINTAAN';
                        $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                    }
                    // MINTA_JEMPUT_ANTAR: tanya harga paket/member + antar/jemput (paket -D) = HARGA_PAKET_D lewat AI, bukan minta kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyToCheck)) {
                        continue;
                    }
                    // HARGA_PAKET regex: paket + antar/jemput = HARGA_PAKET_D (dicek lebih dulu di config)
                    if ($handler === 'HARGA_PAKET' && $this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyToCheck)) {
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: "kalau/klo ... antar/jemput" = hipotetis, bukan minta kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'MINTA_JEMPUT_ANTAR→kalau_hypothetical');
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: ongkos + durasi hari / tipe layanan = HARGA (regex HARGA dicek lebih dulu; ini cadangan)
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBodyToCheck)) {
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: pertanyaan ongkir/ongkos saja = bukan minta kurir
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'MINTA_JEMPUT_ANTAR→ongkir_inquiry_only');
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: "masih bisa/bs jemput" = tanya availabilitas layanan = JAM_OPERASIONAL (regex MINTA lewat sub-bisa jemput)
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)\b/i', $textBodyToCheck)) {
                        continue;
                    }
                    // MINTA_JEMPUT_ANTAR: minta satu pakaian/item diambil/dulukan dulu dari order = PERMINTAAN, bukan kurir ke alamat
                    if ($handler === 'MINTA_JEMPUT_ANTAR' && $this->messageIsPermintaanAmbilPakaianDulu($textBodyToCheck)) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'MINTA_JEMPUT_ANTAR→PERMINTAAN ambil_pakaian_dulu');
                        $handler = 'PERMINTAAN';
                        $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                    }
                    // MINTA_JEMPUT_ANTAR: jenis jemput + order aktif (tuntas=0,bin=0,id_user_ambil=0) = abaikan
                    if ($handler === 'MINTA_JEMPUT_ANTAR'
                        && $this->kurirJemputBlockedByActiveSale($phoneIn, $waNumber, $textBodyToCheck)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'MINTA_JEMPUT_ANTAR→jemput_has_antarable_sale');
                        continue;
                    }
                    // PENUTUP yang bukan closing ketat → PEMBERITAHUAN (info/otw/item/jadwal/janji bayar)
                    if ($handler === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBodyToCheck)) {
                        if (isset($fullKeywordConfig['PEMBERITAHUAN']) && !$this->messageLooksLikeQuestion($textBody)) {
                            $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'PENUTUP→PEMBERITAHUAN');
                            $handler = 'PEMBERITAHUAN';
                            $config = $fullKeywordConfig['PEMBERITAHUAN'] ?? $config;
                        } else {
                            continue;
                        }
                    }
                    // "jam berapa bisa jemput/diambil?" / "bisa jemput sore ini":
                    // ada order → biarkan ESTIMASI; tanpa order → kurir MINTA
                    if ($handler === 'JAM_OPERASIONAL' && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)) {
                        if ($this->pelangganHasActiveSale($phoneIn, $waNumber)) {
                            continue;
                        }
                        if ($this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                            continue;
                        }
                        $this->logAutoreplyTrace($waNumber, 'REGEX_REMAP', 'JAM_OPERASIONAL→MINTA_JEMPUT_ANTAR no_sale');
                        $handler = 'MINTA_JEMPUT_ANTAR';
                    }
                    // "bs jmpt?" tanpa estimasi = MINTA_JEMPUT_ANTAR (minta jemput), bukan JAM_OPERASIONAL
                    if ($handler === 'JAM_OPERASIONAL' && preg_match('/\b(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)\b/i', $textBodyToCheck) && !preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)/i', $textBodyToCheck)) {
                        continue;
                    }
                    // Get case from config (pakai handler final — bisa sudah di-remap MINTA→ESTIMASI)
                    $caseVal = $fullKeywordConfig[$handler]['case'] ?? ($config['case'] ?? null);
                    $notify = $fullKeywordConfig[$handler]['notify'] ?? ($config['notify'] ?? false);
                    if ($handler === 'PERMINTAAN') {
                        if ($caseVal === null || (int) $caseVal === 0) {
                            $caseVal = 3;
                        }
                        $notify = true;
                    }
                    $matchPattern[] = $handler;

                    // ESTIMASI tanpa order aktif → PERMINTAAN (bukan minta kurir)
                    if ($handler === 'ESTIMASI_SELESAI') {
                        $resolved = $this->resolveEstimasiSelesaiByActiveSale($handler, $phoneIn, $waNumber);
                        if ($resolved !== $handler) {
                            $handler = $resolved;
                            $caseVal = $fullKeywordConfig[$handler]['case'] ?? null;
                            $notify = $fullKeywordConfig[$handler]['notify'] ?? false;
                            if ($handler === 'PERMINTAAN') {
                                if ($caseVal === null || (int) $caseVal === 0) {
                                    $caseVal = 3;
                                }
                                $notify = true;
                                $config = $fullKeywordConfig['PERMINTAAN'] ?? $config;
                            }
                        }
                    }
                    // Request waktu selesai bukan ESTIMASI (nanti PERMINTAAN)
                    if ($handler === 'ESTIMASI_SELESAI'
                        && $this->estimasiMessageIsRequestWaktuSelesai($textBodyToCheck)
                    ) {
                        $this->logAutoreplyTrace($waNumber, 'REGEX_SKIP', 'ESTIMASI_SELESAI→request_waktu_selesai');
                        continue;
                    }

                    // Human agent aktif: hanya intent data/self-service (dan admin) yang boleh balas
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
                        // PEMBERITAHUAN: ack singkat saja, jangan dobel sapaan
                        if ($handler !== 'PEMBUKA' && $handler !== 'PERMINTAAN' && $handler !== 'PEMBERITAHUAN') {
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
        if ($messageLength >= 0 && $messageLength <= 7) {
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
        // Pass filtered keywordConfig to AI (keywords yang sudah match di regex sudah di-unset)
        // Ini mengoptimalkan AI detection karena AI tidak perlu cek keyword yang sudah match
        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig);

        if ($this->messageLooksLikePaymentConfirmationPenutup($textBodyToCheck)) {
            if (!is_array($aiResult)) {
                $aiResult = [];
            }
            $prevAi = strtoupper((string) ($aiResult['intent'] ?? 'FALSE'));
            if ($prevAi !== 'PENUTUP') {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'force_PENUTUP payment_confirm was=' . $prevAi);
            }
            $aiResult['intent'] = 'PENUTUP';
        }

        // Check if AI successfully detected a valid intent
        if ($aiResult && is_array($aiResult) && isset($aiResult['intent']) && strtoupper($aiResult['intent']) !== 'FALSE') {
            $aiIntent = strtoupper($aiResult['intent']);
            // Pertanyaan (termasuk tanpa tanda ?) TIDAK boleh masuk PEMBUKA, PENUTUP, atau PEMBERITAHUAN
            $isQuestion = $this->messageLooksLikeQuestion($textBody);
            if ($isQuestion && in_array($aiIntent, ['PEMBUKA', 'PENUTUP', 'PEMBERITAHUAN'])) {
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

            // AI salah: tanya harga paket + antar/jemput (paket -D) = HARGA_PAKET_D, bukan minta kurir
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→HARGA_PAKET_D');
                $aiIntent = 'HARGA_PAKET_D';
                $aiCase = $fullKeywordConfig['HARGA_PAKET_D']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA_PAKET_D']['notify'] ?? false;
            }

            // AI salah: HARGA_PAKET padahal tanya paket + antar/jemput
            if ($aiIntent === 'HARGA_PAKET' && $this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_harga_paket→HARGA_PAKET_D');
                $aiIntent = 'HARGA_PAKET_D';
                $aiCase = $fullKeywordConfig['HARGA_PAKET_D']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA_PAKET_D']['notify'] ?? false;
            }

            // AI salah: HARGA_PAKET_D padahal tanpa antar/jemput
            if ($aiIntent === 'HARGA_PAKET_D' && !$this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_harga_paket_d→HARGA_PAKET');
                $aiIntent = 'HARGA_PAKET';
                $aiCase = $fullKeywordConfig['HARGA_PAKET']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA_PAKET']['notify'] ?? false;
            }

            // AI salah: "kalau/klo ... antar/jemput" = hipotetis (bukan minta kurir) → biarkan CS (FALSE + ask)
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_minta_kalau_hypothetical');
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_reject_minta_kalau_hypothetical'
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

            // AI salah: "kami aja yang antar" / "saya yang jemput" = pemberitahuan (bukan minta kurir)
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBodyToCheck)) {
                if (isset($fullKeywordConfig['PEMBERITAHUAN']) && !$this->messageLooksLikeQuestion($textBody)) {
                    $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→PEMBERITAHUAN customer_self');
                    $aiIntent = 'PEMBERITAHUAN';
                    $aiCase = $fullKeywordConfig['PEMBERITAHUAN']['case'] ?? null;
                    $aiNotify = $fullKeywordConfig['PEMBERITAHUAN']['notify'] ?? false;
                } else {
                    $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_minta_customer_self_antar_jemput');
                    if ($this->isHumanAgentRecentlyActive($waNumber)) {
                        return $this->silentExitHumanActive(
                            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                            'ai_reject_minta_customer_self'
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
            }

            // AI salah: ongkos + durasi (hari) / jenis layanan = HARGA, bukan minta kurir
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→HARGA ongkos_durasi_tier');
                $aiIntent = 'HARGA';
                $aiCase = $fullKeywordConfig['HARGA']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['HARGA']['notify'] ?? false;
            }

            // AI salah: pertanyaan ongkir/ongkos antar-jemput saja = FALSE (CS), bukan minta kurir
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→FALSE ongkir_inquiry_only');
                $aiIntent = 'FALSE';
                $aiCase = 4;
                $aiNotify = false;
            }

            // AI salah: minta satu pakaian/item diambil/dulukan dulu dari cucian/order = PERMINTAAN, bukan minta kurir jemput/antar
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR' && $this->messageIsPermintaanAmbilPakaianDulu($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→PERMINTAAN ambil_pakaian_dulu');
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

            // STATUS / MINTA / JAM → ESTIMASI_SELESAI (tanya kapan/jam berapa siap) jika ada order aktif
            if (in_array($aiIntent, ['STATUS', 'MINTA_JEMPUT_ANTAR', 'JAM_OPERASIONAL'], true)
                && $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                && $this->pelangganHasActiveSale($phoneIn, $waNumber)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_' . $aiIntent . '→ESTIMASI_SELESAI has_sale');
                $aiIntent = 'ESTIMASI_SELESAI';
                $aiCase = $fullKeywordConfig['ESTIMASI_SELESAI']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['ESTIMASI_SELESAI']['notify'] ?? false;
            }

            if ($aiIntent === 'MINTA_JEMPUT_ANTAR'
                && $this->messageLooksLikeAntarKembaliDeadline($textBodyToCheck)
                && !$this->pelangganHasActiveSale($phoneIn, $waNumber)
            ) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_minta_jemput→PERMINTAAN antar_kembali_deadline');
                $aiIntent = 'PERMINTAAN';
                $aiCase = $fullKeywordConfig['PERMINTAAN']['case'] ?? 3;
                $aiNotify = $fullKeywordConfig['PERMINTAAN']['notify'] ?? true;
            }

            // ESTIMASI tanpa order aktif → PERMINTAAN (bukan minta kurir)
            if ($aiIntent === 'ESTIMASI_SELESAI') {
                $resolved = $this->resolveEstimasiSelesaiByActiveSale($aiIntent, $phoneIn, $waNumber);
                if ($resolved !== $aiIntent) {
                    $aiIntent = $resolved;
                    $aiCase = $fullKeywordConfig[$aiIntent]['case'] ?? null;
                    $aiNotify = $fullKeywordConfig[$aiIntent]['notify'] ?? false;
                    if ($aiIntent === 'PERMINTAAN') {
                        if ($aiCase === null || (int) $aiCase === 0) {
                            $aiCase = 3;
                        }
                        $aiNotify = true;
                    }
                }
            }

            // JAM_OPERASIONAL AI salah: "bs jmpt baju?" / "bisa jemput?" tanpa "masih" = MINTA_JEMPUT_ANTAR (bukan tanya jam buka)
            // Kecuali "kalau/klo ... jemput/antar" (hipotetis)
            if ($aiIntent === 'JAM_OPERASIONAL' && preg_match('/\b(bisa|bs|bis|boleh)\s*(jmpt|jemput|antar)\b/i', $textBodyToCheck)
                && !preg_match('/\b(masih|msh|mash|masi|msih)\s+(bisa|bs|bis|boleh)\s*(jemput|jmpt|antar)/i', $textBodyToCheck)
                && !$this->messageLooksLikeKalauAntarJemputBukanMinta($textBodyToCheck)
                && !(
                    $this->messageLooksLikeEstimasiSelesai($textBodyToCheck)
                    && $this->pelangganHasActiveSale($phoneIn, $waNumber)
                )) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_override_jam_operasional→MINTA_JEMPUT_ANTAR bs_jmput_tanpa_masih');
                $aiIntent = 'MINTA_JEMPUT_ANTAR';
                $aiCase = $fullKeywordConfig['MINTA_JEMPUT_ANTAR']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['MINTA_JEMPUT_ANTAR']['notify'] ?? false;
            }

            // PENUTUP yang bukan closing ketat → PEMBERITAHUAN (info/otw/item/jadwal/janji bayar)
            if ($aiIntent === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBodyToCheck)
                && isset($fullKeywordConfig['PEMBERITAHUAN']) && !$this->messageLooksLikeQuestion($textBody)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_remap PENUTUP→PEMBERITAHUAN');
                $aiIntent = 'PEMBERITAHUAN';
                $aiCase = $fullKeywordConfig['PEMBERITAHUAN']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['PEMBERITAHUAN']['notify'] ?? false;
            }

            // FALSE hasil remap lain + ask false → PEMBERITAHUAN
            if ($aiIntent === 'FALSE' && empty($aiResult['ask'])
                && isset($fullKeywordConfig['PEMBERITAHUAN']) && !$this->messageLooksLikeQuestion($textBody)) {
                $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ai_remap FALSE ask=0→PEMBERITAHUAN');
                $aiIntent = 'PEMBERITAHUAN';
                $aiCase = $fullKeywordConfig['PEMBERITAHUAN']['case'] ?? null;
                $aiNotify = $fullKeywordConfig['PEMBERITAHUAN']['notify'] ?? false;
            }

            // Human agent aktif: setelah remap, hanya intent data/self-service yang boleh balas
            if ($this->isHumanAgentRecentlyActive($waNumber)
                && !$this->isIntentAllowedDuringHumanActive($aiIntent)) {
                return $this->silentExitHumanActive(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                    'ai intent=' . $aiIntent
                );
            }

            // "kabari ya kak" / "infokan ya" = minta CS update, bukan sapaan/pemberitahuan
            if (in_array($aiIntent, ['PEMBUKA', 'PEMBERITAHUAN'], true)
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

            // PENUTUP sisa yang bukan closing ketat (intent PEMBERITAHUAN belum di DB) → biarkan CS
            if ($aiIntent === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBodyToCheck)) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_penutup_non_strict_no_pemberitahuan');
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
            if ($aiIntent === 'LOKASI' && $this->messageLooksLikeMintaJemputAntar($textBodyToCheck, $fullKeywordConfig)) {
                $this->logAutoreplyTrace($waNumber, 'AI_REMAP', 'LOKASI→MINTA_JEMPUT_ANTAR');
                $aiIntent = 'MINTA_JEMPUT_ANTAR';
                $aiCase = $fullKeywordConfig['MINTA_JEMPUT_ANTAR']['case'] ?? 2;
                $aiNotify = $fullKeywordConfig['MINTA_JEMPUT_ANTAR']['notify'] ?? true;
            }

            // Jenis jemput + order aktif (tuntas=0,bin=0,id_user_ambil=0) = bukan MINTA jemput
            if ($aiIntent === 'MINTA_JEMPUT_ANTAR'
                && $this->kurirJemputBlockedByActiveSale($phoneIn, $waNumber, $textBodyToCheck)
            ) {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'ai_reject_minta_jemput_has_antarable_sale');
                if ($this->isHumanAgentRecentlyActive($waNumber)) {
                    return $this->silentExitHumanActive(
                        $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage,
                        'ai_reject_minta_jemput_has_antarable_sale'
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
                // PEMBERITAHUAN: ack singkat saja, jangan dobel sapaan
                if ($aiIntent !== 'PEMBUKA' && $aiIntent !== 'PERMINTAAN' && $aiIntent !== 'PEMBERITAHUAN') {
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
                        'phone' => $phone0,
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

        $sql = "SELECT * FROM notif 
                WHERE tipe = 1 AND state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND phone IN ($phoneIn)
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
     * Sumber: laundry GET /Get/rekening (URL::NON_TUNAI_GUIDE + QRIS)
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
     * Ambil teks rekening terformat dari laundry public endpoint.
     */
    private function fetchLaundryRekeningMessage(): string
    {
        $fallback =
            "QRIS\nhttps://ml.nalju.com/I/q\n\n" .
            "BCA (BANK CENTRAL ASIA)\n8455103793\n\n" .
            "BRI (BANK RAKYAT INDONESIA)\n327901031534535\n\n" .
            "an. LUHUR GUNAWAN";

        $url = 'https://ml.nalju.com/Get/rekening';
        try {
            if (!function_exists('curl_init')) {
                return $fallback;
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false || $code < 200 || $code >= 300) {
                return $fallback;
            }
            $json = json_decode($raw, true);
            if (!empty($json['ok']) && !empty($json['message']) && is_string($json['message'])) {
                return trim($json['message']);
            }
        } catch (\Throwable $e) {
            // keep fallback
        }
        return $fallback;
    }

    /**
     * Intent MINTA_JEMPUT_ANTAR — logic di WARepliesKurirTrait.
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
        $keywordConfig = $this->loadAutoreplyKeywordConfig();
        unset($keywordConfig['PEMBUKA']);
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
        $inHours = $this->isOperatingHours();
        if (!$inHours) {
            if ($this->intentLabMode) {
                $this->logAutoreplyTrace($waNumber, 'LAB_NOTE', 'live would skip: di luar jam operasional');
            } else {
                $this->logAutoreplyTrace($waNumber, 'EXIT', 'penutup_skip_outside_hours');
                return;
            }
        }

        $textTrimmed = trim($textBody ?? '');

        $ctx = $this->getGreetingContext($waNumber);
        $sapaan = $ctx['sapaan'];

        // (2) Sudah bayar/lunas — prioritas di atas thanks (pesan campur sering ada makasih)
        if ($this->messageLooksLikePaymentConfirmationPenutup($textBody)) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'penutup_subtype=payment');
            $this->sendAutoreplyText($waNumber, $this->pickPenutupPaymentReply($sapaan));
            return;
        }

        // (1) Ucapan terima kasih
        if ($this->messageLooksLikeThanksPenutup($textBody)) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'penutup_subtype=thanks');
            $this->sendAutoreplyText($waNumber, $this->pickPenutupThanksReply($sapaan));
            return;
        }

        // (3) Lainnya: ok/siap/sticker/emoji/ack — tidak balas
        $this->logAutoreplyTrace($waNumber, 'EXIT', 'penutup_subtype=other_no_reply len=' . mb_strlen($textTrimmed));
    }

    /**
     * Handle intent PEMBERITAHUAN — ack singkat info tanpa pertanyaan/permintaan.
     * Variasi hanya emote senyum soft (sama pola PENUTUP): Baik/Ok/Oke + sapaan + emote.
     * Di luar jam operasional → tidak balas.
     */
    private function handlePemberitahuan($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->isOperatingHours()) {
            $this->logAutoreplyTrace($waNumber, 'EXIT', 'pemberitahuan_skip_outside_hours');
            return;
        }

        $ctx = $this->getGreetingContext($waNumber);
        $sapaan = $ctx['sapaan'];
        $this->logAutoreplyTrace($waNumber, 'BRANCH', 'pemberitahuan_ack');
        $this->sendAutoreplyText($waNumber, $this->getRandomSiapReply($sapaan));
    }

    /**
     * Handle intent TAGIHAN - balas rincian tagihan dengan item detail (seperti I.php view)
     * Menggunakan db(1) = mdl_laundry
     */
    /**
     * Handle intent HARGA_PAKET - harga paket/member/langganan (tanpa varian antar-jemput).
     */
    private function handleHarga_Paket($phoneIn, $waNumber, $textBody = '')
    {
        $this->runHargaPaketAutoreply($phoneIn, $waNumber, $textBody, false);
    }

    /**
     * Handle intent HARGA_PAKET_D - harga paket/member yang include antar-jemput (data -D/-d saja).
     */
    private function handleHarga_Paket_D($phoneIn, $waNumber, $textBody = '')
    {
        $this->runHargaPaketAutoreply($phoneIn, $waNumber, $textBody, true);
    }

    /**
     * Autoreply harga paket via AI. $deliveryOnlyPakets: false = tanpa -D/-d, true = hanya -D/-d.
     */
    private function runHargaPaketAutoreply($phoneIn, $waNumber, $textBody, $deliveryOnlyPakets)
    {
        $waService = $this->getWaService();
        $logTag = $deliveryOnlyPakets ? 'HargaPaketD' : 'HargaPaket';

        $priceDataText = $this->loadHargaPaketDataForAI($deliveryOnlyPakets);
        if (empty($priceDataText)) {
            return;
        }

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                return;
            }

            if ($deliveryOnlyPakets) {
                $systemExtra = "\n\nPENTING - PAKET ANTAR/JEMPUT (DELIVERY):\n- Customer menanyakan harga paket yang INCLUDE antar-jemput/delivery.\n- Data di bawah HANYA berisi paket varian antar-jemput. Setiap judul sudah berformat 'Paket ... + Antar Jemput'.\n- Tampilkan SEMUA paket di data sesuai urutan. JANGAN menawarkan atau menampilkan paket tanpa antar/jemput.\n- Pertahankan judul '+ Antar Jemput' saat menjawab.";
                $dataLabel = 'DATA HARGA PAKET/MEMBER + ANTAR JEMPUT (hanya varian include antar-jemput)';
            } else {
                $systemExtra = "\n\nPENTING - TANPA ANTAR/JEMPUT:\n- Data di bawah adalah paket standar (tanpa layanan antar-jemput). JANGAN menawarkan atau menyebut harga paket include antar/jemput kecuali customer minta.";
                $dataLabel = 'DATA HARGA PAKET/MEMBER LAUNDRY (paket bulanan = paket member = paket kuota - tanpa antar/jemput)';
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu adalah asisten harga paket/member laundry. Jawab HANYA berdasarkan data yang diberikan.\n\nPENTING - PAKET BULANAN = PAKET MEMBER = HARGA PAKET (SAMA):\n- 'Paket bulanan', 'paket member', 'harga paket', 'ada paket?', 'langganan' = SEMUA merujuk ke data yang sama. Data di bawah adalah paket kuota/deposit.\n- JANGAN PERNAH jawab 'kami tidak punya paket bulanan' atau 'tidak ada paket bulanan'. SELALU tampilkan data paket yang ada.\n\nPENTING - FILTER LAYANAN:\n- 'Cuci + Setrika' = cuci DAN setrika, 'Setrika' = setrika saja.\n- Jika customer tanya 'cuci setrika': TAMPILKAN HANYA paket 'Cuci + Setrika'. JANGAN tampilkan 'Setrika' saja.\n- Jika customer tanya 'setrika saja': tampilkan HANYA paket 'Setrika' saja.\n- Jika customer tanya 'paket bulanan?', 'ada paket?', 'harga paket?', 'harga member?' (tanpa spesifikasi layanan): tampilkan SEMUA data namun ringkas." . $systemExtra . "\n\nURUTAN & FORMAT: Data SUDAH diurutkan. JANGAN ubah urutan. Format: *bold*, _italic_, emoji secukupnya, line break, tutup ramah. Jangan pakai tanda === atau garis pemisah serupa di sekitar judul paket."
                ],
                [
                    'role' => 'user',
                    'content' => "{$dataLabel}:\n\n" . $priceDataText . "\n\n---\n\nPertanyaan customer: " . $textBody . "\n\nJawab berdasarkan data. JANGAN bilang tidak punya paket bulanan - tampilkan data paket. Jika tanya layanan spesifik (cuci setrika, setrika saja), filter paket yang match saja."
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 600);
            $text = trim($answer);
            if (empty($text)) {
                return;
            }

            $catatan = "\n\n_Catatan:_\n- Pembayaran dimuka/deposit\n- Kuota berlaku selamanya\n- Kuota tidak dapat direfund";
            $text .= $catatan;

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("runHargaPaketAutoreply ERROR: " . $e->getMessage(), 'wa_error', $logTag);
            }
        }
    }

    /**
     * Pertanyaan harga paket/member/langganan sekaligus antar-jemput/delivery → hanya data paket -D/-d.
     * Dipakai agar regex MINTA_JEMPUT_ANTAR tidak mengalahkan jalur AI HARGA_PAKET_D.
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
        // Instruksi kurir saja (bukan tanya harga paket)
        if (preg_match('/\b(tolong|minta|bantu)\s+(di)?(jemput|antar)\b/i', $t)
            && !preg_match('/\b(harga|berapa|biaya|daftar|tarif|rate|brp|brpa|paket|member)\b/u', $t)) {
            return false;
        }
        if (preg_match('/\b(harga|berapa|biaya|daftar|tarif|rate|brp|brpa)\b/u', $t)) {
            return true;
        }
        // Tanpa kata harga eksplisit: "paket member antar jemput", "paket laundry antar jemput"
        if (preg_match('/\b(paket|member|langganan|deposit)\b/u', $t)
            && preg_match('/\b(antar|jemput|antar\s*jemput|jemput\s*antar|delivery|include)\b/u', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Nama paket (kategori | layanan | durasi) memakai penanda delivery -D/-d di durasi.
     */
    private function hargaPaketNamaIsDeliveryVariant($nama)
    {
        return (bool) preg_match('/-(?:D|d)\b/i', (string) $nama);
    }

    /**
     * Judul tampilan untuk paket delivery: "Paket {kategori} | {layanan} | {durasi} + Antar Jemput"
     */
    private function formatHargaPaketDeliveryDisplayTitle($nama)
    {
        $nama = trim((string) $nama);
        $parts = array_map('trim', explode('|', $nama));
        if (count($parts) >= 3) {
            $durasi = preg_replace('/-(?:D|d)\b/i', '', $parts[2]);
            $durasi = trim($durasi);

            return 'Paket ' . $parts[0] . ' | ' . $parts[1] . ' | ' . $durasi . ' + Antar Jemput';
        }

        $base = preg_replace('/-(?:D|d)\b/i', '', $nama);

        return 'Paket ' . trim($base) . ' + Antar Jemput';
    }

    /**
     * Tanya ongkos/ongkir sekaligus durasi proses (hari) atau jenis layanan (regular/ekspres/kilat) → HARGA, bukan minta kurir.
     *
     * @param string $text asli atau lowercase
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

    /**
     * Pertanyaan ongkir/ongkos antar-jemput saja — belum minta kurir (→ FALSE/CS, bukan MINTA_JEMPUT_ANTAR).
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
     * Minta satu jenis pakaian/item diambil/dulukan dulu dari order — PERMINTAAN, bukan kurir jemput ke kamar/alamat.
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

    /**
     * Load harga paket dari db(1) - format untuk AI
     * Urut by id_harga (nama paket), qty.
     *
     * @param bool $deliveryOnlyPakets true = HANYA paket -D/-d (antar/jemput), judul + Antar Jemput
     */
    private function loadHargaPaketDataForAI($deliveryOnlyPakets = false)
    {
        $db = DB::getInstance(1);

        $itemGroup = [];
        foreach ($db->query("SELECT id_item_group, item_kategori FROM item_group")->result_array() as $r) {
            $itemGroup[$r['id_item_group']] = $r['item_kategori'] ?? '';
        }
        $penjualan = [];
        foreach ($db->query("SELECT id_penjualan_jenis, penjualan_jenis, id_satuan FROM penjualan_jenis")->result_array() as $r) {
            $penjualan[$r['id_penjualan_jenis']] = ['nama' => $r['penjualan_jenis'] ?? '', 'id_satuan' => (int) ($r['id_satuan'] ?? 0)];
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

        $rows = $db->query(
            "SELECT hp.id_harga, hp.qty, hp.harga 
             FROM harga_paket hp 
             INNER JOIN harga h ON hp.id_harga = h.id_harga 
             ORDER BY hp.id_harga ASC, hp.qty ASC"
        )->result_array();

        if (empty($rows)) {
            return '';
        }

        $hargaCache = [];
        /** @var array<string, array{unit: string, rows: string[], has_d: bool}> $groups */
        $groups = [];
        $order = [];

        foreach ($rows as $r) {
            $idHarga = (int) ($r['id_harga'] ?? 0);
            $qty = (int) ($r['qty'] ?? 0);
            $harga = (int) ($r['harga'] ?? 0);

            if (!isset($hargaCache[$idHarga])) {
                $hRows = $db->query("SELECT id_item_group, id_penjualan_jenis, list_layanan, id_durasi FROM harga WHERE id_harga = " . (int) $idHarga)->result_array();
                if (empty($hRows)) {
                    continue;
                }
                $h = $hRows[0];
                $kategori = $itemGroup[$h['id_item_group'] ?? 0] ?? 'Item';
                $listL = @unserialize($h['list_layanan'] ?? '');
                $layananParts = [];
                if (is_array($listL)) {
                    foreach ($listL as $lid) {
                        if (!empty($layanan[$lid])) {
                            $layananParts[] = $layanan[$lid];
                        }
                    }
                }
                $layananStr = !empty($layananParts) ? implode(' + ', $layananParts) : '-';
                $durasiStr = $durasi[$h['id_durasi'] ?? 0] ?? '';
                $pj = $penjualan[$h['id_penjualan_jenis'] ?? 0] ?? null;
                $idSatuan = $pj['id_satuan'] ?? 0;
                $unit = $satuan[$idSatuan] ?? '';
                $hargaCache[$idHarga] = ['nama' => "{$kategori} | {$layananStr} | {$durasiStr}", 'unit' => $unit];
            }

            $cache = $hargaCache[$idHarga];
            $nama = $cache['nama'];
            $unit = $cache['unit'];

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
            $groups[$nama]['rows'][] = "  {$qtyUnit}: Rp " . number_format($harga, 0, ',', '.');
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
     * Handle intent HARGA - ambil list harga dari db(1), AI jawab pertanyaan harga berdasarkan data
     * Sumber data: sama dengan view SetHarga (harga, item_group, layanan, durasi, penjualan_jenis, satuan)
     */
    private function handleHarga($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $priceDataText = $this->loadHargaDataForAI($textBody, 20);
        if (empty($priceDataText)) {
            return; // Tidak ada data, CS yang akan membalas manual
        }

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                return; // AI tidak aktif, CS yang akan membalas manual
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu adalah asisten harga laundry. Jawab HANYA berdasarkan data harga yang diberikan.\n\nPENTING - URUTAN: Item dalam data SUDAH diurutkan by sort (paling sering dipakai). Baris PERTAMA = nomor 1, baris kedua = 2, dst. JANGAN ubah urutan, JANGAN sort ulang by harga. Tampilkan sesuai urutan yang diberikan.\n\n- Jika pertanyaan JELAS/SPESIFIK: jawab fokus pada yang ditanya.\n- Jika pertanyaan BELUM JELAS: tampilkan 3 harga teratas = BARIS PERTAMA dari data (jangan urutkan ulang by harga).\n\nFORMAT WHATSAPP agar menarik:\n- Gunakan *teks* untuk bold (judul, nominal)\n- Gunakan _teks_ untuk italic (penekanan)\n- Boleh gunakan emoji secukupnya (📋 ✨ 💰) untuk mempercantik\n- Beri line break antar item agar mudah dibaca\n- WAKTU: Data sudah berformat 'X Hari' atau 'Y Jam' atau 'X Hari Y Jam'. Tampilkan persis seperti di data (jangan ubah ke format 1h 0j)\n- Tutup dengan kalimat ramah dan ajakan bertanya lebih lanjut"
                ],
                [
                    'role' => 'user',
                    'content' => "DATA HARGA LAUNDRY (urutan SUDAH BENAR - baris pertama = sort tertinggi/paling populer, JANGAN sort ulang by harga):\n\n" . $priceDataText . "\n\n---\n\nPertanyaan customer: " . $textBody . "\n\nJawab berdasarkan data di atas. Jika tidak spesifik, tampilkan 3 BARIS PERTAMA sesuai urutan data (jangan ubah urutan)."
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 400);
            $text = trim($answer);
            if (empty($text)) {
                $fallback = "Mohon maaf, saya belum bisa menampilkan harga saat ini.\nBoleh sebutkan itemnya (mis. pakaian harian, bedcover, sepatu, gorden) agar saya bantu cek harga?";
                $res = $this->sendQuotedFreeText($waNumber, $fallback);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $fallback, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
                return;
            }

            $res = $this->sendQuotedFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handleHarga ERROR: " . $e->getMessage(), 'wa_error', 'Harga');
            }
            $fallback = "Mohon maaf, sistem sedang sibuk saat cek harga.\nSilakan kirim lagi dengan item yang dicari (contoh: setrika, bedcover, sepatu, gorden).";
            $res = $this->sendQuotedFreeText($waNumber, $fallback);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $fallback, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            return;
        }
    }

    /**
     * Load harga data dari db(1) - format sama SetHarga view
     * Return: text untuk context AI
     */
    private function loadHargaDataForAI(string $questionText = '', int $maxRows = 20)
    {
        $db = DB::getInstance(1);

        $itemGroup = [];
        foreach ($db->query("SELECT id_item_group, item_kategori FROM item_group")->result_array() as $r) {
            $itemGroup[$r['id_item_group']] = $r['item_kategori'] ?? '';
        }
        $penjualan = [];
        foreach ($db->query("SELECT id_penjualan_jenis, penjualan_jenis, id_satuan FROM penjualan_jenis")->result_array() as $r) {
            $penjualan[$r['id_penjualan_jenis']] = ['nama' => $r['penjualan_jenis'] ?? '', 'id_satuan' => (int) ($r['id_satuan'] ?? 0)];
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

        $rows = $db->query(
            "SELECT h.id_penjualan_jenis, h.id_item_group, h.list_layanan, h.id_durasi, h.harga, h.min_order, h.hari, h.jam, h.sort 
             FROM harga h 
             INNER JOIN durasi d ON h.id_durasi = d.id_durasi 
             WHERE h.is_active = 1 AND d.durasi IN ('Reguler', 'Ekspres', 'Kilat') 
             ORDER BY h.sort DESC, h.id_penjualan_jenis, h.id_item_group, h.list_layanan, h.id_durasi"
        )->result_array();

        if (empty($rows)) {
            return '';
        }

        $questionLower = mb_strtolower(trim($questionText));
        $specialItemPattern = '/\b(gorden|gor?d?en|bed\s*cover|bedcover|selimut|karpet|sepatu|tas|boneka|jaket|sprei|kemeja|gaun|jas|hoodie|sweater|mukena|jilbab|kerudung)\b/iu';
        $mentionsSpecialItem = (bool) preg_match($specialItemPattern, $questionLower);
        $keywords = [];
        if ($questionLower !== '') {
            if (preg_match('/\b(reguler\s*[-]?\s*d)\b/iu', $questionLower)) {
                $keywords[] = 'reguler-d';
            }
            if (preg_match('/\b(ekspres\s*[-]?\s*d)\b/iu', $questionLower)) {
                $keywords[] = 'ekspres-d';
            }
            if (preg_match('/\b(kilat\s*[-]?\s*d)\b/iu', $questionLower)) {
                $keywords[] = 'kilat-d';
            }
            if (preg_match('/\b(setrika|strika|gosok)\b/iu', $questionLower)) {
                $keywords[] = 'setrika';
                $keywords[] = 'strika';
                $keywords[] = 'gosok';
            }

            preg_match_all('/[a-z0-9\-]{3,}/iu', $questionLower, $m);
            $tokens = $m[0] ?? [];
            $stopwords = [
                'harga', 'berapa', 'brp', 'kak', 'bang', 'pak', 'bu', 'mau', 'saya', 'aku', 'yang',
                'untuk', 'dan', 'atau', 'ini', 'itu', 'ada', 'bisa', 'tolong', 'info', 'dong', 'ya',
                'laundry', 'cuci'
            ];
            foreach ($tokens as $token) {
                if (!in_array($token, $stopwords, true)) {
                    $keywords[] = $token;
                }
            }
        }
        $keywords = array_values(array_unique($keywords));

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
                    if (!empty($layanan[$lid])) {
                        $layananParts[] = $layanan[$lid];
                    }
                }
            }
            $layananStr = !empty($layananParts) ? implode(' + ', $layananParts) : '-';
            $durasiStr = $durasi[$r['id_durasi']] ?? '';
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
                if ($hari > 0) $waktuParts[] = $hari . ' Hari';
                if ($jam > 0) $waktuParts[] = $jam . ' Jam';
                $line .= ' | Waktu: ' . implode(' ', $waktuParts);
            }

            $searchBlob = mb_strtolower(implode(' ', [
                (string) $kategori,
                (string) $namaJenis,
                (string) $layananStr,
                (string) $durasiStr,
                (string) $line,
            ]));

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

        $defaultRows = $enrichedRows;
        // Jika item khusus tidak disebut, anggap user menanyakan "pakaian harian".
        if (!$mentionsSpecialItem) {
            $pakaianHarianRows = array_values(array_filter($enrichedRows, function ($row) {
                $kategori = mb_strtolower((string) ($row['kategori'] ?? ''));
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

        // Jika tidak ada match keyword, fallback ke data terpopuler (urutan default query).
        $selectedRows = !empty($filtered) ? $filtered : $defaultRows;
        $selectedRows = array_slice($selectedRows, 0, max(1, (int) $maxRows));

        $lines = [];
        $currentJenis = '';
        $lineNum = 0;
        foreach ($selectedRows as $row) {
            $lineNum++;
            if ($row['namaJenis'] !== $currentJenis) {
                $currentJenis = $row['namaJenis'];
                $lines[] = "\n=== " . strtoupper($row['namaJenis']) . " (per " . $row['unit'] . ") ===";
            }
            $prefix = "{$lineNum}. ";
            $lines[] = $prefix . $row['line'];
        }

        return trim(implode("\n", $lines));
    }

    private function handleTagihan($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        $db = DB::getInstance(1);

        $pelanggan = $this->queryPelangganRowsByWaNumber($db, $phoneIn, $waNumber, 'id_pelanggan, nama_pelanggan, id_cabang');

        if (empty($pelanggan)) {
            $this->logAutoreplyTrace($waNumber, 'TAGIHAN', 'no_pelanggan');
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
            $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, 'https://ml.nalju.com/I/' . $id_pelanggan, 'TAGIHAN');
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

            // Sama seperti I.php invoice: hitung pembayaran dengan status_mutasi <> 4 (exclude failed only)
            $payments = $db->query(
                "SELECT COALESCE(SUM(jumlah), 0) as bayar FROM kas WHERE id_cabang = ? AND jenis_transaksi = 1 AND ref_transaksi = ? AND status_mutasi <> 4",
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
            $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, $link, 'TAGIHAN');
            return;
        }

        // Ada rincian tagihan — selalu kirim (tanpa cek pola tegas)
        $text = "*" . $nama_pelanggan . "*\nRincian Tagihan:\n\n" . implode("\n\n", $lines) . "\n\n*Total Tagihan: Rp " . number_format($totalTagihan, 0, ',', '.') . "*\n" . $link;
        $this->logAutoreplyTrace($waNumber, 'TAGIHAN_SEND', 'rincian blocks=' . count($lines));
        $res = $this->sendQuotedFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
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
     * Minta selesai pada waktu tertentu (bukan tanya perkiraan).
     * Tidak di-record ESTIMASI; nanti di intent PERMINTAAN.
     */
    private function estimasiMessageIsRequestWaktuSelesai(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if ($this->messageLooksLikeEstimasiGrantRequest($text)) {
            return true;
        }
        $tanyaJamBerapa = (bool) preg_match('/\b(kapan|kpn|jam\s*(brp|brpa|berapa)|berapa|brp|brpa)\b/iu', $text);
        if ($tanyaJamBerapa) {
            return false;
        }
        if ($this->parseEstimasiRequestedRelativeDay($text) !== null) {
            return true;
        }
        if ($this->messageLooksLikeNegatedBisaRelativeEstimasi($text)) {
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

    /**
     * ESTIMASI_SELESAI hanya jika ada order aktif; jika tidak → PERMINTAAN (CS), bukan minta kurir.
     */
    private function resolveEstimasiSelesaiByActiveSale(string $intent, string $phoneIn, string $waNumber): string
    {
        if (strtoupper($intent) !== 'ESTIMASI_SELESAI') {
            return $intent;
        }
        if ($this->pelangganHasActiveSale($phoneIn, $waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ESTIMASI_SELESAI keep (ada sale aktif)');
            return 'ESTIMASI_SELESAI';
        }
        $this->logAutoreplyTrace($waNumber, 'BRANCH', 'ESTIMASI_SELESAI→PERMINTAAN (tidak ada sale aktif)');
        return 'PERMINTAAN';
    }

    /**
     * Intent ESTIMASI_SELESAI: record session + act (notif laundry + chat group Fonnte).
     * Tidak ada balasan ke customer.
     * Safety: tanpa order aktif alihkan ke PERMINTAAN.
     *
     * @return bool true = pesan dikonsumsi (jangan lanjut routing); false = tidak terkait, lanjut intent lain
     */
    private function handleEstimasi_Selesai($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->pelangganHasActiveSale($phoneIn, $waNumber)) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'handler_fallback→PERMINTAAN no_active_sale');
            $this->clearEstimasiSession($waNumber);
            $this->currentHandler = 'PERMINTAAN';
            $this->handlePermintaan($phoneIn, $waNumber, $textBody);
            return true;
        }

        $session = $this->getEstimasiSession($waNumber);
        if ($session !== null) {
            return $this->handleEstimasiSelesaiFollowUp($phoneIn, $waNumber, $textBody, $session);
        }

        if ($this->estimasiMessageIsRequestWaktuSelesai(trim((string) $textBody))) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'skip_request_waktu_selesai');
            return false;
        }

        $this->handleEstimasiSelesaiFirstHit($phoneIn, $waNumber, $textBody);
        return true;
    }

    /**
     * Keyword tegas yang membatalkan follow-up session ESTIMASI (BON/BILL/TAGIHAN/NOTA/HARGA/STATUS/…).
     */
    private function messageBreaksEstimasiSession(string $text, array $keywordConfig): bool
    {
        if (preg_match('/\b(bon|bill|bil{1,}|tagihan|nota|invoice|pricelist|price\s*list)\b/iu', $text)) {
            return true;
        }
        if ($this->messageLooksLikeThanksPenutup($text) || $this->messageMatchesStrictPenutupAllowlist($text)) {
            return true;
        }

        $breakout = [
            'TAGIHAN',
            'NOTA',
            'STATUS',
            'HARGA',
            'HARGA_PAKET',
            'HARGA_PAKET_D',
            'PEMBUKA',
            'PENUTUP',
            'JAM_OPERASIONAL',
            'JAM_TUTUP',
            'JAM_BUKA',
            'MINTA_JEMPUT_ANTAR',
        ];

        $looksEstimasi = $this->messageLooksLikeEstimasiSelesai($text);
        foreach ($breakout as $handler) {
            // Jangan putus session bila pesan masih tanya estimasi (hindari false match MINTA/JAM)
            if ($looksEstimasi && in_array($handler, ['MINTA_JEMPUT_ANTAR', 'JAM_OPERASIONAL', 'JAM_TUTUP', 'JAM_BUKA', 'STATUS'], true)) {
                continue;
            }
            $patterns = $keywordConfig[$handler]['patterns'] ?? [];
            foreach ($patterns as $pattern) {
                if (@preg_match($pattern, $text)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Ambil session ESTIMASI aktif (belum expire). Expired → hapus & return null.
     * @return array|null
     */
    private function getEstimasiSession(string $waNumber): ?array
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return null;
        }

        $db = DB::getInstance(0);
        try {
            $res = $db->query(
                'SELECT * FROM wa_estimasi_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if (!$res || $res->num_rows() === 0) {
                return null;
            }
            $row = (array) $res->row();
            $expiresAt = $row['expires_at'] ?? null;
            if (!$expiresAt || strtotime($expiresAt) < time()) {
                $this->clearEstimasiSession($waNumber);
                return null;
            }

            return $row;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('getEstimasiSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            return null;
        }
    }

    /**
     * Upsert session ESTIMASI (TTL ESTIMASI_SESSION_TTL_MINUTES).
     */
    private function saveEstimasiSession(string $waNumber, array $data): void
    {
        if ($this->intentLabMode) {
            return;
        }
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', time() + (self::ESTIMASI_SESSION_TTL_MINUTES * 60));

        $existing = null;
        try {
            $ex = DB::getInstance(0)->query(
                'SELECT butuh_estimasi, estimasi_jam, estimasi_tanggal, id_cabang, id_penjualan, fase_proses, summary
                 FROM wa_estimasi_session WHERE phone = ? LIMIT 1',
                [$phone]
            );
            if ($ex && $ex->num_rows() > 0) {
                $existing = (array) $ex->row();
            }
        } catch (\Throwable $e) {
            $existing = null;
        }

        $estimasiJam = array_key_exists('estimasi_jam', $data)
            ? $data['estimasi_jam']
            : ($existing['estimasi_jam'] ?? null);
        $estimasiTanggal = array_key_exists('estimasi_tanggal', $data)
            ? $data['estimasi_tanggal']
            : ($existing['estimasi_tanggal'] ?? null);
        $idCabang = array_key_exists('id_cabang', $data)
            ? $data['id_cabang']
            : ($existing['id_cabang'] ?? null);
        $idPenjualan = array_key_exists('id_penjualan', $data)
            ? $data['id_penjualan']
            : ($existing['id_penjualan'] ?? null);
        $fase = array_key_exists('fase_proses', $data)
            ? ($data['fase_proses'] ?? null)
            : ($existing['fase_proses'] ?? null);
        $butuh = array_key_exists('butuh_estimasi', $data)
            ? (!empty($data['butuh_estimasi']) ? 1 : 0)
            : (!empty($existing['butuh_estimasi']) ? 1 : 0);
        $summary = array_key_exists('summary', $data)
            ? $data['summary']
            : ($existing['summary'] ?? null);

        $db = DB::getInstance(0);
        try {
            $db->query(
                'INSERT INTO wa_estimasi_session
                    (phone, id_penjualan, id_cabang, fase_proses, butuh_estimasi, estimasi_tanggal, estimasi_jam,
                     summary, updated_at, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id_penjualan = VALUES(id_penjualan),
                    id_cabang = VALUES(id_cabang),
                    fase_proses = VALUES(fase_proses),
                    butuh_estimasi = VALUES(butuh_estimasi),
                    estimasi_tanggal = VALUES(estimasi_tanggal),
                    estimasi_jam = VALUES(estimasi_jam),
                    summary = VALUES(summary),
                    updated_at = VALUES(updated_at),
                    expires_at = VALUES(expires_at)',
                [
                    $phone,
                    $idPenjualan,
                    $idCabang,
                    $fase,
                    $butuh,
                    $estimasiTanggal,
                    $estimasiJam,
                    $summary,
                    $now,
                    $expires,
                ]
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('saveEstimasiSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    private function clearEstimasiSession(string $waNumber): void
    {
        $phone = $this->normalizePhoneNumber($waNumber);
        if (!$phone) {
            return;
        }
        $db = DB::getInstance(0);
        try {
            $db->query('DELETE FROM wa_estimasi_session WHERE phone = ?', [$phone]);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('clearEstimasiSession: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
        }
    }

    /**
     * Pilih 1 item aktif dengan deadline terdekat + fase antrian|pengerjaan|selesai.
     * @return array{id:int,id_cabang:int|null,fase:string,deadline_ts:int,deadline_label:string}|null
     */
    private function pickEstimasiFocusItem(string $phoneIn, string $waNumber): ?array
    {
        $db1 = DB::getInstance(1);
        $pelanggan = $this->queryPelangganRowsByWaNumber($db1, $phoneIn, $waNumber, 'id_pelanggan');
        $idPelanggans = array_column($pelanggan, 'id_pelanggan');
        if (empty($idPelanggans)) {
            return null;
        }

        $idsIn = implode(',', array_map('intval', $idPelanggans));
        $sales = $db1->query(
            "SELECT id_penjualan, id_cabang, letak, insertTime, hari, jam
             FROM sale
             WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND id_pelanggan IN ($idsIn)"
        )->result_array();
        if (empty($sales)) {
            return null;
        }

        $idList = array_column($sales, 'id_penjualan');
        $quotedIds = array_map(function ($id) {
            return "'" . (int) $id . "'";
        }, $idList);
        $idsInNotif = implode(',', $quotedIds);
        $existingNotifIds = !empty($idList)
            ? array_column(
                $db1->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($idsInNotif)")->result_array(),
                'no_ref'
            )
            : [];

        $today = date('Y-m-d');
        $candidates = [];
        foreach ($sales as $sale) {
            $idPenjualan = (int) $sale['id_penjualan'];
            $letak = trim((string) ($sale['letak'] ?? ''));
            $hasNotif = in_array((string) $idPenjualan, array_map('strval', $existingNotifIds), true)
                || in_array($idPenjualan, $existingNotifIds, true);
            $hari = (int) ($sale['hari'] ?? 0);
            $jam = (int) ($sale['jam'] ?? 0);
            $insertTime = $sale['insertTime'] ?? date('Y-m-d H:i:s');
            $deadlineTs = strtotime($insertTime . ' +' . $hari . ' days +' . $jam . ' hours');
            if ($deadlineTs === false) {
                $deadlineTs = time();
            }
            $deadlineDate = date('Y-m-d', $deadlineTs);

            if ($hasNotif && $letak !== '') {
                $fase = 'selesai';
            } elseif ($deadlineDate <= $today) {
                $fase = 'pengerjaan';
            } else {
                $fase = 'antrian';
            }

            $idCabangSale = isset($sale['id_cabang']) ? (int) $sale['id_cabang'] : 0;

            $candidates[] = [
                'id' => $idPenjualan,
                'id_cabang' => $idCabangSale > 0 ? $idCabangSale : null,
                'fase' => $fase,
                'deadline_ts' => $deadlineTs,
                'deadline_label' => $this->formatEstimasiDeadlineLabel($deadlineTs, $hari),
                'unfinished' => $fase !== 'selesai' ? 1 : 0,
            ];
        }

        usort($candidates, function ($a, $b) {
            if ($a['unfinished'] !== $b['unfinished']) {
                return $b['unfinished'] <=> $a['unfinished'];
            }
            return $a['deadline_ts'] <=> $b['deadline_ts'];
        });

        $best = $candidates[0] ?? null;
        if ($best === null) {
            return null;
        }

        return [
            'id' => $best['id'],
            'id_cabang' => $best['id_cabang'],
            'fase' => $best['fase'],
            'deadline_ts' => $best['deadline_ts'],
            'deadline_label' => $best['deadline_label'],
        ];
    }

    private function formatEstimasiDeadlineLabel(int $deadlineTs, int $hari = 0): string
    {
        $deadlineDate = date('Y-m-d', $deadlineTs);
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $lusa = date('Y-m-d', strtotime('+2 day'));

        if ($deadlineDate <= $today) {
            return 'hari ini';
        }
        if ($deadlineDate === $tomorrow) {
            return 'besok';
        }
        if ($deadlineDate === $lusa) {
            return 'lusa';
        }
        // > lusa: tetap relatif ke "lusa" tidak pas — pakai tanggal singkat
        return date('j/n/Y', $deadlineTs);
    }

    private function handleEstimasiSelesaiFirstHit(string $phoneIn, string $waNumber, $textBody = ''): void
    {
        $item = $this->pickEstimasiFocusItem($phoneIn, $waNumber);
        if ($item === null) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'first_hit_no_item→MINTA_JEMPUT_ANTAR');
            $this->currentHandler = 'MINTA_JEMPUT_ANTAR';
            $this->handleMinta_Jemput_Antar($phoneIn, $waNumber, $textBody);
            return;
        }

        $id = (int) $item['id'];
        $idCabang = isset($item['id_cabang']) ? (int) $item['id_cabang'] : null;
        if ($idCabang !== null && $idCabang <= 0) {
            $idCabang = null;
        }
        $fase = $item['fase'];
        $msg = trim((string) ($textBody ?? ''));

        if ($this->estimasiMessageIsRequestWaktuSelesai($msg)) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'first_hit_skip_request_waktu');
            return;
        }

        if ($fase === 'selesai') {
            $this->saveEstimasiSession($waNumber, [
                'id_penjualan' => $id,
                'id_cabang' => $idCabang,
                'fase_proses' => $fase,
                'butuh_estimasi' => 0,
                'estimasi_tanggal' => null,
                'estimasi_jam' => null,
                'summary' => "Customer tanya estimasi; #{$id} sudah selesai",
            ]);
            $this->escalateEstimasiToPetugas($waNumber, $idCabang);
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', "first_hit_selesai id={$id}");
            return;
        }

        $this->saveEstimasiSession($waNumber, [
            'id_penjualan' => $id,
            'id_cabang' => $idCabang,
            'fase_proses' => $fase,
            'butuh_estimasi' => 1,
            'estimasi_tanggal' => null,
            'estimasi_jam' => null,
            'summary' => '[pesan] ' . $msg . " | Tanya jam siap; fokus #{$id} fase={$fase}; butuh_estimasi=1",
        ]);
        $this->escalateEstimasiToPetugas($waNumber, $idCabang);
        $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', "first_hit_butuh_estimasi id={$id} fase={$fase}");
    }

    /**
     * Customer minta jam/waktu siap → butuh estimasi petugas (bukan sekadar info fase).
     */
    private function messageNeedsEstimasiJam(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if ($this->messageLooksLikeEstimasiGrantRequest($text)) {
            return false;
        }
        if ($this->messageLooksLikeEstimasiSelesai($text)) {
            return true;
        }
        // Hari relatif tanpa "jam berapa" = request waktu (bukan tanya perkiraan)
        if ($this->parseEstimasiRequestedRelativeDay($text) !== null) {
            return false;
        }
        if (preg_match('/\b(jam|kapan|kpn|estimasi)\b/iu', $text)) {
            return true;
        }
        if (preg_match('/\b(berapa|brp|brpa|kira)\b/iu', $text)
            && preg_match('/\b(siap|selesai|ambil|jemput|diantar|selese|kelar)\b/iu', $text)) {
            return true;
        }

        return false;
    }

    /**
     * Permintaan khusus: customer minta SELESAI pada jam spesifik.
     * Wajib ada jam angka (bukan "jam berapa"). Contoh: "bisa siap jam 10?", "minta selesai jam 14.30 besok"
     */
    private function messageLooksLikeEstimasiGrantRequest(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }
        if ($this->parseEstimasiRequestWaktu($text) === null) {
            return false;
        }
        $t = $text;
        if (preg_match('/\bjam\s*(brp|brpa|berapa)\b/iu', $t)) {
            return false;
        }
        // Konteks permintaan / target selesai
        if (preg_match('/\b(bisa|boleh|minta|tolong|mau|mohon|request|pengen|ingin)\b/iu', $t)) {
            return true;
        }
        if (preg_match('/\b(siap|selesai|jadi)\b.{0,40}\bjam\s*\d{1,2}/iu', $t)) {
            return true;
        }
        if (preg_match('/\bjam\s*\d{1,2}([.:]\d{2})?\b.{0,40}\b(siap|selesai|jadi)\b/iu', $t)) {
            return true;
        }

        return false;
    }

    /**
     * Ekstrak jam (+ tanggal opsional) yang DIMINTA customer.
     * Normalisasi laundry: jam 1–6 → PM (kecuali kata pagi); jam 7–9 ambigu pagi/malam.
     *
     * @return array{jam:?float,tanggal:?string,ask_ampm?:bool,raw_hour?:int,raw_min?:int}|null
     */
    private function parseEstimasiRequestWaktu(?string $text): ?array
    {
        if ($text === null || trim($text) === '') {
            return null;
        }
        $t = $text;
        // Normalisasi kutip aneh WA: jam“ 6 / jam"6
        $t = str_replace(["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x98", "\xE2\x80\x99", '"', "'"], ' ', $t);
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
        // pagi/siang/sore/malam ini = hari ini
        if (preg_match('/\b(pagi|siang|sore|malam)\s*ini\b/iu', $t)
            || preg_match('/\b(hari\s*ini|hr\s*ini)\b/iu', $t)
        ) {
            $tanggal = date('Y-m-d');
        } elseif (preg_match('/\b(besok|bsk)\b/iu', $t)) {
            $tanggal = date('Y-m-d', strtotime('+1 day'));
        } elseif (preg_match('/\b(lusa)\b/iu', $t)) {
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

        return [
            'jam' => (float) $norm['jam'],
            'tanggal' => $tanggal,
        ];
    }

    /**
     * Normalisasi jam bicara customer (bukan format 24 jam eksplisit ≥13).
     * - 1–6: PM (+12) kecuali ada kata "pagi"
     * - 7–9: pagi/malam dari kata; tanpa kata → tanya jika sekarang masih sebelum jam itu, else malam
     *
     * @return array{jam?:float,ask_ampm?:bool}
     */
    private function normalizeLaundryCustomerJam(int $h, int $min, string $text): array
    {
        $jamFloat = static function (int $hour, int $minute): float {
            return (float) sprintf('%d.%02d', $hour, $minute);
        };

        // Sudah 24 jam / siang-sore numerik
        if ($h >= 13 && $h <= 23) {
            return ['jam' => $jamFloat($h, $min)];
        }
        if ($h === 0 || $h === 12) {
            return ['jam' => $jamFloat($h, $min)];
        }

        $hasPagi = (bool) preg_match('/\bpagi\b/iu', $text);
        $hasMalam = (bool) preg_match('/\bmalam\b/iu', $text);
        // siang/sore menguatkan PM untuk 1–6 (default sudah PM)

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
                // Sudah lewat jam pagi itu → maksud malam
                return ['jam' => $jamFloat($h + 12, $min)];
            }
            return ['ask_ampm' => true];
        }

        // 10–11: default pagi; "malam" → +12
        if ($h >= 10 && $h <= 11 && $hasMalam) {
            return ['jam' => $jamFloat($h + 12, $min)];
        }

        return ['jam' => $jamFloat($h, $min)];
    }

    /** Jam sudah siap dipakai escalate (bukan pending tanya pagi/malam). */
    private function estimasiWaktuIsResolved(?array $waktu): bool
    {
        return is_array($waktu)
            && empty($waktu['ask_ampm'])
            && isset($waktu['jam'])
            && $waktu['jam'] !== null
            && $waktu['jam'] !== '';
    }

    /**
     * Follow-up session ESTIMASI: jangan spam "tanyakan petugas" untuk chat yang sudah lepas topik.
     * AI + summary memutuskan escalate / silent / penutup / unrelated.
     *
     * @return bool true = dikonsumsi; false = lanjut routing intent lain
     */
    private function handleEstimasiSelesaiFollowUp(string $phoneIn, string $waNumber, $textBody, array $session): bool
    {
        $msg = trim((string) ($textBody ?? ''));

        // Session hanya untuk pending klarifikasi typo → jangan escalate
        if (preg_match('/clarify_only=1/', (string) ($session['summary'] ?? ''))
            && !preg_match('/pending_clarify=<</', (string) ($session['summary'] ?? ''))) {
            $this->clearEstimasiSession($waNumber);
            return false;
        }

        // Penutup / terima kasih → lepas session, biarkan PENUTUP yang balas
        if ($msg !== '' && $this->estimasiLooksLikePenutup($msg)) {
            $this->clearEstimasiSession($waNumber);
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'followup_penutup→continue_routing');
            return false;
        }

        // Request waktu selesai bukan ESTIMASI
        if ($this->estimasiMessageIsRequestWaktuSelesai($msg)) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'followup_skip_request_waktu→continue_routing');
            return false;
        }

        $needsJam = $this->messageNeedsEstimasiJam($msg);
        $looksEstimasi = $this->messageLooksLikeEstimasiSelesai($msg);

        $forceEscalate = $needsJam || $looksEstimasi;

        $decision = null;
        if (!$forceEscalate && $msg !== '') {
            $decision = $this->estimasiAiDecideFollowUp($waNumber, $session, $msg);
        }

        $action = $forceEscalate ? 'escalate' : ($decision['action'] ?? null);
        if ($action === null) {
            if ($needsJam || $looksEstimasi) {
                $action = 'escalate';
            } elseif ($this->estimasiLooksUnrelatedToEstimasi($msg)) {
                $action = 'unrelated';
            } else {
                $action = 'silent';
            }
        }

        if ($action === 'unrelated') {
            $this->estimasiAppendSummary($waNumber, $session, 'unrelated_skip: ' . mb_substr($msg, 0, 80));
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'followup_unrelated→continue_routing');
            return false;
        }

        if ($action === 'penutup') {
            $this->estimasiAppendSummary($waNumber, $session, 'penutup_ai: ' . mb_substr($msg, 0, 80));
            return true;
        }

        if ($action === 'clarify') {
            $this->estimasiAppendSummary($waNumber, $session, 'clarify_ignored: ' . mb_substr($msg, 0, 60));
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'clarify_ignored');
            return true;
        }

        if ($action === 'silent') {
            $note = $decision['summary_note'] ?? ('silent: ' . mb_substr($msg, 0, 80));
            $this->estimasiAppendSummary($waNumber, $session, $note);
            if (!empty($decision['forward_group'])) {
                $idCabang = isset($session['id_cabang']) ? (int) $session['id_cabang'] : null;
                $this->forwardEstimasiToFonnteGroup($waNumber, $idCabang > 0 ? $idCabang : null);
            }
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'followup_silent');
            return true;
        }

        $butuhEstimasi = !empty($session['butuh_estimasi']);
        if ($needsJam || $looksEstimasi || ($decision['butuh_estimasi'] ?? false)) {
            $butuhEstimasi = true;
        }

        $idPenjualan = isset($session['id_penjualan']) ? (int) $session['id_penjualan'] : null;
        $fase = $session['fase_proses'] ?? null;
        $idCabang = isset($session['id_cabang']) ? (int) $session['id_cabang'] : null;
        $fresh = $this->pickEstimasiFocusItem($phoneIn, $waNumber);
        if ($fresh !== null) {
            $idPenjualan = (int) $fresh['id'];
            $fase = $fresh['fase'];
            if (!empty($fresh['id_cabang'])) {
                $idCabang = (int) $fresh['id_cabang'];
            }
        }
        if ($idCabang !== null && $idCabang <= 0) {
            $idCabang = null;
        }

        $summaryNote = $decision['summary_note'] ?? sprintf(
            'escalate; fokus #%s fase=%s; butuh=%s; pesan=%s',
            $idPenjualan ?? '-',
            $fase ?? '-',
            $butuhEstimasi ? '1' : '0',
            mb_substr($msg !== '' ? $msg : '-', 0, 80)
        );

        $this->saveEstimasiSession($waNumber, [
            'id_penjualan' => $idPenjualan,
            'id_cabang' => $idCabang,
            'fase_proses' => $fase,
            'butuh_estimasi' => $butuhEstimasi ? 1 : 0,
            'estimasi_tanggal' => $session['estimasi_tanggal'] ?? null,
            'estimasi_jam' => $session['estimasi_jam'] ?? null,
            'summary' => $this->estimasiMergeSummaryText((string) ($session['summary'] ?? ''), $summaryNote),
        ]);

        $this->escalateEstimasiToPetugas($waNumber, $idCabang);
        return true;
    }

    private function estimasiLooksLikePenutup(string $msg): bool
    {
        $t = mb_strtolower(trim($msg));
        if ($t === '') {
            return false;
        }
        // Jangan anggap penutup jika masih ada isi operasional
        if (preg_match('/\b(jam|kapan|siap|selesai|estimasi|jemput|antar|plastik|gosok|cuci|setrika|kg|kilo)\b/iu', $t)) {
            return false;
        }
        return (bool) preg_match(
            '/\b(makasih|thanks|thank\s*you|trims|trima*kasih|trimakasih|terima\s*kasih|mksh|mksih|trima\s*ksih|trmksh|sip|oke+y*|ok|baik|noted|sudah)\b/iu',
            $t
        ) && mb_strlen($t) <= 40;
    }

    private function estimasiLooksUnrelatedToEstimasi(string $msg): bool
    {
        // Instruksi layanan/item ke petugas, bukan tanya kapan siap
        return (bool) preg_match(
            '/\b(plastik|gosok|setrika|cuci\s*gosok|cuci\s*setrika|reguler|ekspres|kilat|warna|merah|biru|hijau|semua\s*nya)\b/iu',
            $msg
        ) && !$this->messageLooksLikeEstimasiSelesai($msg) && !$this->messageNeedsEstimasiJam($msg);
    }

    /**
     * @return array{action:string,reply?:string,summary_note?:string,butuh_estimasi?:bool,forward_group?:bool,no_ack?:bool}|null
     */
    private function estimasiAiDecideFollowUp(string $waNumber, array $session, string $msg): ?array
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

        $summary = trim((string) ($session['summary'] ?? ''));
        $fase = $session['fase_proses'] ?? '-';
        $id = $session['id_penjualan'] ?? '-';
        $butuh = !empty($session['butuh_estimasi']) ? '1' : '0';

        $chatLines = [];
        try {
            // Reuse kurir helper if available (same class)
            if (method_exists($this, 'kurirFetchRecentChatTurns')) {
                foreach ($this->kurirFetchRecentChatTurns($waNumber, 8) as $t) {
                    $dir = ($t['dir'] ?? '') === 'out' ? 'BOT' : 'CUST';
                    $chatLines[] = $dir . ': ' . ($t['body'] ?? '');
                }
            }
        } catch (\Throwable $e) {
            $chatLines = [];
        }

        $system = "Kamu gatekeeper session ESTIMASI_SELESAI laundry. "
            . "Session aktif = customer sebelumnya tanya kapan/jam order siap. "
            . "Pilih action untuk PESAN_BARU:\n"
            . "- escalate: masih tentang estimasi/jam/kapan siap/minta selesai jam X → teruskan ke petugas\n"
            . "- silent: info operasional ke petugas (instruksi cuci/gosok/plastik/dll) ATAU chat yang tidak perlu balasan bot; jangan balas customer\n"
            . "- penutup: terima kasih / oke penutup singkat\n"
            . "- unrelated: topik lain (harga, tagihan, jemput kurir, dll) → biarkan intent lain; jangan balas sebagai estimasi\n"
            . "- clarify: HANYA typo/salah ketik yang masih terkait estimasi — isi suggested_text (mis. 'bisa siap hari ini kak?')\n"
            . "Jika pesan jelas dipahami tapi bukan estimasi → unrelated. Jangan clarify hanya agar bot tetap balas.\n"
            . "JANGAN escalate hanya karena session masih aktif. "
            . "Jawab HANYA JSON: {\"action\":\"...\",\"reply\":\"opsional\",\"suggested_text\":\"...\",\"summary_note\":\"...\",\"butuh_estimasi\":false,\"forward_group\":false,\"no_ack\":false}";

        $user = "SESSION: id_penjualan={$id} fase={$fase} butuh_estimasi={$butuh}\n"
            . "SUMMARY: " . ($summary !== '' ? $summary : '(kosong)') . "\n"
            . "RECENT_CHAT:\n" . (empty($chatLines) ? '(tidak ada)' : implode("\n", $chatLines)) . "\n"
            . "PESAN_BARU: " . mb_substr($msg, 0, 400);

        try {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_AI', 'request');
            $raw = $this->executeOpenAIRequestWithMessages(
                [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                180,
                $waNumber
            );
        } catch (\Throwable $e) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_AI', 'error ' . mb_substr($e->getMessage(), 0, 160));
            return null;
        }

        $json = json_decode((string) $raw, true);
        if (!is_array($json) && preg_match('/\{.*\}/s', (string) $raw, $m)) {
            $json = json_decode($m[0], true);
        }
        if (!is_array($json)) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_AI', 'bad_json');
            return null;
        }

        $action = strtolower(trim((string) ($json['action'] ?? '')));
        $allowed = ['escalate', 'silent', 'penutup', 'unrelated', 'clarify'];
        if (!in_array($action, $allowed, true)) {
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_AI', 'bad_action=' . $action);
            return null;
        }

        $this->logAutoreplyTrace($waNumber, 'ESTIMASI_AI', 'action=' . $action);
        return [
            'action' => $action,
            'reply' => isset($json['reply']) ? trim((string) $json['reply']) : '',
            'suggested_text' => isset($json['suggested_text']) ? trim((string) $json['suggested_text']) : '',
            'summary_note' => isset($json['summary_note']) ? trim((string) $json['summary_note']) : '',
            'butuh_estimasi' => !empty($json['butuh_estimasi']),
            'forward_group' => !empty($json['forward_group']),
            'no_ack' => !empty($json['no_ack']),
        ];
    }

    private function estimasiMergeSummaryText(string $existing, string $note): string
    {
        $note = trim($note);
        if ($note === '') {
            return mb_substr($existing, 0, 800);
        }
        $existing = trim($existing);
        return mb_substr(($existing !== '' ? $existing . ' | ' : '') . $note, 0, 800);
    }

    private function estimasiAppendSummary(string $waNumber, array $session, string $note): void
    {
        // Pakai state terbaru di DB — jangan overwrite butuh_estimasi/request dari session stale
        // (mis. escalate baru set butuh=1, lalu ack_ts append pakai session lama butuh=0 → notif petugas hilang)
        $live = $this->getEstimasiSession($waNumber);
        $base = is_array($live) ? $live : $session;
        $summaryBase = array_key_exists('summary', $session)
            ? (string) $session['summary']
            : (string) ($base['summary'] ?? '');
        $merged = $this->estimasiMergeSummaryText($summaryBase, $note);
        $this->saveEstimasiSession($waNumber, [
            'id_penjualan' => $base['id_penjualan'] ?? null,
            'id_cabang' => $base['id_cabang'] ?? null,
            'fase_proses' => $base['fase_proses'] ?? null,
            'butuh_estimasi' => !empty($base['butuh_estimasi']) ? 1 : 0,
            'estimasi_tanggal' => $base['estimasi_tanggal'] ?? null,
            'estimasi_jam' => $base['estimasi_jam'] ?? null,
            'summary' => $merged,
        ]);
    }

    /** Record sudah disimpan; act = chat group Fonnte cabang. Tanpa balasan customer. */
    private function escalateEstimasiToPetugas(string $waNumber, ?int $idCabang = null): void
    {
        $this->forwardEstimasiToFonnteGroup($waNumber, $idCabang);
    }

    private function formatEstimasiGroupNama(string $waNumber): string
    {
        $nama = trim($this->getContactNameForGreeting($waNumber));
        if ($nama === '') {
            $nama = 'Pelanggan';
        }

        return mb_strtoupper($nama);
    }

    private function forwardEstimasiToFonnteGroup(string $waNumber, ?int $idCabang = null): void
    {
        $nama = $this->formatEstimasiGroupNama($waNumber);
        $groupText = "*{$nama}* menanyakan perkiraan selesai";

        try {
            if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }
            if (!class_exists('\\App\\Config\\Fonnte')) {
                require_once __DIR__ . '/../Config/Fonnte.php';
            }
            $groupId = $this->resolveEstimasiFonnteGroupId($idCabang);
            if ($groupId === '') {
                $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'forward_group skip_no_group_id cabang=' . ($idCabang ?? 0));
                return;
            }
            $fonnte = new \App\Helpers\CRM\FonnteService();
            $res = $fonnte->sendToGroup($groupId, $groupText);
            $ok = !empty($res['success']);
            $this->logAutoreplyTrace(
                $waNumber,
                'ESTIMASI_SELESAI',
                'forward_group cabang=' . ($idCabang ?? 0) . ' target=' . $groupId . ' '
                . ($ok ? 'ok' : ('fail=' . ($res['error'] ?? 'unknown')))
            );
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('forwardEstimasiToFonnteGroup: ' . $e->getMessage(), 'wa_error', 'Autoreply');
            }
            $this->logAutoreplyTrace($waNumber, 'ESTIMASI_SELESAI', 'forward_group exception');
        }
    }

    /**
     * Group Fonnte per cabang (mdl_laundry.cabang.id_group_fonnte), fallback config default.
     */
    private function resolveEstimasiFonnteGroupId(?int $idCabang): string
    {
        if ($idCabang !== null && $idCabang > 0) {
            try {
                $rows = DB::getInstance(1)->query(
                    'SELECT id_group_fonnte FROM cabang WHERE id_cabang = ' . (int) $idCabang . ' LIMIT 1'
                )->result_array();
                $fromCabang = trim((string) ($rows[0]['id_group_fonnte'] ?? ''));
                if ($fromCabang !== '' && preg_match('/@g\.us$/i', $fromCabang)) {
                    return $fromCabang;
                }
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write('resolveEstimasiFonnteGroupId: ' . $e->getMessage(), 'wa_error', 'Autoreply');
                }
            }
        }

        return \App\Config\Fonnte::getEstimasiGroupId();
    }

    private function handleStatus($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $db1 = DB::getInstance(1);
        $this->trySendMissingNotaNotifsForStatus($phoneIn, $waNumber, $waService, $db1);

        $limitTime = date('Y-m-d H:i:s', strtotime('-72 hours'));

        $sql = "SELECT * FROM notif 
                WHERE tipe = 2 AND state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND phone IN ($phoneIn)
                ORDER BY insertTime ASC";

        $pendingNotifs = $db1->query($sql)->result_array();
        
        // Track which id_penjualan already have pending notifs
        $pendingNotifIds = [];
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
                    // Add 1 second to ensure auto-reply appears after customer message
                    $timestamp = date('Y-m-d H:i:s', strtotime('+1 second'));
                    $payload = $this->buildWsPayload($waNumber, $notif['text'], $msgId, $wamid, $timestamp);
                    $this->pushToWebSocket($payload);
                }
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
        } else {
            $ids_in = implode(',', $id_pelanggans);
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
            $noRefs = array_column($sales, 'no_ref');
            if (empty($noRefs)) {
                $this->trySendBelumAdaTagihanAutoreply($waService, $waNumber, $textBody, $nama_pelanggan, null, 'STATUS');
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

            // Parse phone numbers from $phoneIn (format: '08123','08456')
            // Remove quotes and split into array
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));

            // Add waNumber (clean format) to the list
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2); // Convert 62xxx to 0xxx
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Build FIND_IN_SET conditions for each phone
            // This handles notif_number containing comma-separated values like "08123,08456"
            $conditions = [];
            foreach ($phones as $phone) {
                if (!empty($phone)) {
                    $escapedPhone = addslashes($phone);
                    $conditions[] = "FIND_IN_SET('$escapedPhone', REPLACE(notif_number, ' ', ''))";
                }
            }

            if (empty($conditions)) {
                $text = "Tidak ada reminder yang ditemukan.";
                $this->sendQuotedFreeText($waNumber, $text);
                return;
            }

            $whereClause = implode(' OR ', $conditions);
            $sql = "SELECT * FROM reminder WHERE $whereClause";

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
                $text = "Tidak ada reminder yang ditemukan untuk nomor Anda.";
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
        $users = $db1->query("SELECT no_user, nama_user, bank_code, bank_account_number, bank_account_name FROM user WHERE en = 1")->result_array();

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
     * Format data karyawan untuk balasan WA (no_user, nama_user, bank_code, bank_account_number, bank_account_name)
     */
    private function formatKaryawanReply($row, $db0)
    {
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
            "DATA KARYAWAN",
            "*{$nama_user}*",
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
            if (!class_exists('\\App\\Helpers\\FonnteService')) {
                require_once __DIR__ . '/../Helpers/CRM/FonnteService.php';
            }

            $fonnte = new \App\Helpers\CRM\FonnteService();
            $result = $fonnte->getDeviceProfile();

            if (!$result['success']) {
                $this->sendSaldoAdminText($waNumber, 'Gagal mengambil profil Fonnte: ' . ($result['error'] ?? 'Unknown error'));
                return;
            }

            $d = $result['data'];
            $deviceStatus = $d['device_status'] ?? '-';
            $messages = isset($d['messages']) ? number_format((int) $d['messages'], 0, ',', '.') : '-';
            $quota = $d['quota'] ?? '-';

            $text = "*Profile Fonnte*\n"
                . "Nama: " . ($d['name'] ?? '-') . "\n"
                . "Device: " . ($d['device'] ?? '-') . "\n"
                . "Status koneksi: " . $deviceStatus . "\n"
                . "Paket: " . ($d['package'] ?? '-') . "\n"
                . "Kuota: " . $quota . "\n"
                . "Total pesan: " . $messages . "\n"
                . "Expired: " . ($d['expired'] ?? '-');

            $this->sendSaldoAdminText($waNumber, $text);
        } catch (\Throwable $e) {
            \Log::write('handleInfo_fonnte ERROR: ' . $e->getMessage(), 'wa_error', 'Fonnte');
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
        // Autoreply Fonnte (FonnteReplyAdapter): tidak push ke waserver — WebSocket hanya untuk yCloud
        if ($this->customSender !== null) {
            return null;
        }

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

        $est = $this->getEstimasiSession($waNumber);
        $sum = $est ? preg_replace('/\s*\|\s*pending_clarify=<<.*?>>/u', '', (string) ($est['summary'] ?? '')) : '';
        $sum = preg_replace('/\s*\|\s*clarify_only=1/u', '', (string) $sum);
        $newSum = mb_substr(
            trim(($sum !== '' ? $sum . ' | ' : '') . 'clarify_only=1 | ' . $marker),
            0,
            800
        );
        $this->saveEstimasiSession($waNumber, [
            'id_penjualan' => $est['id_penjualan'] ?? null,
            'id_cabang' => $est['id_cabang'] ?? null,
            'fase_proses' => $est['fase_proses'] ?? null,
            'butuh_estimasi' => !empty($est['butuh_estimasi']) ? 1 : 0,
            'estimasi_tanggal' => $est['estimasi_tanggal'] ?? null,
            'estimasi_jam' => $est['estimasi_jam'] ?? null,
            'summary' => $newSum,
        ]);
    }

    private function loadPendingClarify(string $waNumber): ?string
    {
        foreach ([$this->getKurirSession($waNumber), $this->getEstimasiSession($waNumber)] as $sess) {
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
        $est = $this->getEstimasiSession($waNumber);
        if ($est !== null) {
            $sum = (string) ($est['summary'] ?? '');
            $sum = preg_replace('/\s*\|\s*pending_clarify=<<.*?>>/u', '', $sum);
            $wasClarifyOnly = (bool) preg_match('/clarify_only=1/', $sum);
            $sum = preg_replace('/\s*\|\s*clarify_only=1/u', '', $sum);
            $sum = trim($sum);
            if ($wasClarifyOnly && $sum === '') {
                $this->clearEstimasiSession($waNumber);
            } else {
                $this->saveEstimasiSession($waNumber, [
                    'id_penjualan' => $est['id_penjualan'] ?? null,
                    'id_cabang' => $est['id_cabang'] ?? null,
                    'fase_proses' => $est['fase_proses'] ?? null,
                    'butuh_estimasi' => !empty($est['butuh_estimasi']) ? 1 : 0,
                    'estimasi_tanggal' => $est['estimasi_tanggal'] ?? null,
                    'estimasi_jam' => $est['estimasi_jam'] ?? null,
                    'summary' => $sum,
                ]);
            }
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

            // Klasifikasi: definisi intent HANYA dari ai_prompt DB. Format JSON + ask tetap di kode.
            $prompt = "Kamu adalah AI classifier untuk WhatsApp bot laundry. Klasifikasikan pesan ke SATU kategori.\n";
            $prompt .= "Definisi tiap kategori HANYA dari teks di blok === NAMA === (field ai_prompt database). Ikuti syarat TRUE/FALSE di situ secara harfiah. Jangan menambah arti, jangan perluas kategori.\n\n";

            foreach ($keywordConfig as $category => $config) {
                $aiPrompt = trim((string) ($config['ai_prompt'] ?? ''));
                if ($aiPrompt === '') {
                    continue;
                }
                $prompt .= "=== {$category} ===\n{$aiPrompt}\n\n";
            }

            $prompt .= "=== FALSE ===\nTidak termasuk kategori di atas.\n\n";
            $prompt .= "ATURAN WAJIB: field \"intent\" HANYA boleh berisi nama kategori yang PERSIS ada di daftar di atas, atau FALSE. Jangan mengarang label seperti PERTANYAAN, PERTANYAAN_UMUM, atau kategori lain yang tidak tercantum.\n";
            $prompt .= "Field \"ask\" (boolean, wajib): true jika pesan adalah PERTANYAAN atau PERMINTAAN yang butuh respon CS; false jika hanya info/pemberitahuan/ack/omongan biasa tanpa minta aksi.\n";
            $prompt .= "ask=true: bertanya (ada/tidak ada tanda ?), minta bantuan/cek/info/kabari/tolong/komplain.\n";
            $prompt .= "ask=false: info saja (otw, daftar item tanpa minta aksi), ack singkat (ok/siap/baik), cerita sosial.\n";
            $prompt .= "Field \"from_block\" (wajib): nama blok === NAMA === yang INSTRUKSINYA kamu pakai untuk menentukan intent.\n";
            $prompt .= "- Jika intent HARGA karena teks di blok === HARGA === → from_block=HARGA.\n";
            $prompt .= "- Jika intent HARGA karena blok lain (mis. === BONEKA ===) menulis \"tanya harga boneka → HARGA\" → from_block=BONEKA (bukan HARGA).\n";
            $prompt .= "- from_block harus PERSIS nama blok di daftar, atau FALSE. Boleh berbeda dari field intent.\n";
            $prompt .= "Pesan: \"{$textBody}\"\n";
            $prompt .= "JAWAB HANYA JSON SATU OBJEK (tanpa markdown, tanpa teks lain).\n";
            $prompt .= "reason maksimal 12 kata, tanpa tanda kutip ganda.\n";
            $prompt .= "{\"intent\": \"NAMA_KATEGORI\", \"ask\": true, \"from_block\": \"NAMA_BLOK\", \"reason\": \"alasan singkat\"}\n";
            $prompt .= "Kategori harus salah satu dari daftar di atas atau FALSE. ask harus true atau false. from_block wajib diisi.";

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

            // FALSE / STATUS / MINTA padahal tanya kapan/jam berapa siap = ESTIMASI_SELESAI
            if (in_array($intent, ['FALSE', 'STATUS', 'MINTA_JEMPUT_ANTAR', 'JAM_OPERASIONAL'], true)
                && $this->messageLooksLikeEstimasiSelesai($textBody)) {
                $intent = 'ESTIMASI_SELESAI';
                $reason = 'remap kapan/jam berapa siap → ESTIMASI_SELESAI';
            }

            // FALSE padahal konfirmasi sudah bayar/lunas → PENUTUP
            if (($intent === 'FALSE' || $intent === 'PEMBERITAHUAN' || $intent === 'REKENING')
                && $this->messageLooksLikePaymentConfirmationPenutup($textBody)
            ) {
                $intent = 'PENUTUP';
                $reason = 'remap konfirmasi sudah bayar → PENUTUP';
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

            // PENUTUP yang bukan closing ketat → PEMBERITAHUAN (info/otw/item/jadwal/janji bayar)
            if ($intent === 'PENUTUP' && $this->messageIsNonStrictPenutup($textBody)) {
                if (isset($keywordConfig['PEMBERITAHUAN'])) {
                    $intent = 'PEMBERITAHUAN';
                    $reason = 'remap PENUTUP → PEMBERITAHUAN (bukan closing ketat)';
                } else {
                    $intent = 'FALSE';
                    $reason = 'remap PENUTUP → FALSE (bukan allowlist ketat: thanks/bayar/ack murni)';
                }
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

            // MINTA_JEMPUT_ANTAR / FALSE padahal tanya tarif ongkos by durasi atau jenis layanan = HARGA
            if (($intent === 'MINTA_JEMPUT_ANTAR' || $intent === 'FALSE') && isset($keywordConfig['HARGA'])
                && $this->messageIsHargaOngkosByDurasiAtauLayanan($textBody)) {
                $intent = 'HARGA';
                $reason = 'remap ongkos + durasi/tier layanan → HARGA';
            }

            // MINTA padahal "kalau/klo ... antar/jemput" = hipotetis, bukan minta kurir
            if ($intent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeKalauAntarJemputBukanMinta($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap kalau+antar/jemput → FALSE (hipotetis, bukan minta kurir)';
            }

            // MINTA padahal customer sendiri yang antar/jemput ("kami aja yang antar")
            if ($intent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeCustomerSelfAntarAtauJemput($textBody)) {
                if (isset($keywordConfig['PEMBERITAHUAN'])) {
                    $intent = 'PEMBERITAHUAN';
                    $reason = 'remap customer self antar/jemput → PEMBERITAHUAN';
                } else {
                    $intent = 'FALSE';
                    $reason = 'remap customer self antar/jemput → FALSE (bukan minta kurir)';
                }
            }

            // MINTA padahal hanya tanya ongkir/ongkos antar-jemput (belum minta kurir)
            if ($intent === 'MINTA_JEMPUT_ANTAR' && $this->messageLooksLikeOngkirOngkosInquiryOnly($textBody)) {
                $intent = 'FALSE';
                $reason = 'remap pertanyaan ongkir/ongkos → FALSE (bukan minta kurir)';
            }

            $textBodyForPaketCheck = mb_strtolower(preg_replace('/[*_~`]/', '', (string) $textBody), 'UTF-8');

            // HARGA_PAKET / HARGA_PAKET_D + antar/jemput ↔ pisah intent delivery
            if ($intent === 'HARGA_PAKET' && isset($keywordConfig['HARGA_PAKET_D'])
                && $this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyForPaketCheck)) {
                $intent = 'HARGA_PAKET_D';
                $reason = 'remap HARGA_PAKET + antar/jemput → HARGA_PAKET_D';
            }
            if ($intent === 'HARGA_PAKET_D' && !$this->messageIsHargaPaketAntarJemputCombinedQuestion($textBodyForPaketCheck)) {
                $intent = 'HARGA_PAKET';
                $reason = 'remap HARGA_PAKET_D tanpa antar/jemput → HARGA_PAKET';
            }

            // AI kadang salah pilih HARGA_PAKET(_D) untuk tanya harga layanan biasa (mis. "cek harga setrika").
            foreach (['HARGA_PAKET', 'HARGA_PAKET_D'] as $paketIntent) {
                if ($intent !== $paketIntent || !isset($keywordConfig['HARGA'])) {
                    continue;
                }
                $hasPaketContext = (bool) preg_match('/\b(paket|member|langganan|deposit|bulanan)\b/iu', $textBody);
                $looksLikeRegularHargaQuestion = (bool) (
                    preg_match('/\b(harga|biaya|tarif|berapa|brp|brapa)\b/iu', $textBody)
                    && preg_match('/\b(setrika|strika|gosok|cuci|laundry|loundry|londri|kilo|kg|reguler|regular|ekspres|kilat)\b/iu', $textBody)
                );
                if (!$hasPaketContext && ($looksLikeRegularHargaQuestion || $this->messageLooksLikePricelistRequest($textBody))) {
                    $intent = 'HARGA';
                    $reason = 'remap ' . $paketIntent . ' tanpa kata paket/member → HARGA';
                }
            }

            // FALSE + ask false (info tanpa minta aksi) → PEMBERITAHUAN
            if ($intent === 'FALSE' && !$ask && isset($keywordConfig['PEMBERITAHUAN'])
                && !$this->messageLooksLikeQuestion($textBody)) {
                $intent = 'PEMBERITAHUAN';
                $reason = ($reason !== '' ? $reason . '; ' : '') . 'remap FALSE ask=false → PEMBERITAHUAN';
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
        $caseList = [];
        if (!empty($conv->conv_case)) {
            $decoded = json_decode($conv->conv_case, true);
            if (is_array($decoded)) {
                $caseList = isset($decoded[0]) ? $decoded : (!empty($decoded) ? [$decoded] : []);
            } elseif (is_numeric($conv->conv_case)) {
                $caseList[] = ['case' => (int) $conv->conv_case, 'status' => 'unknown'];
            }
        }
        $hasOtherOpenCases = false;
        foreach ($caseList as $c) {
            if (isset($c['case']) && (int) $c['case'] !== 4 && ($c['status'] ?? '') === 'open') {
                $hasOtherOpenCases = true;
                break;
            }
        }
        if ($case === 4 && $hasOtherOpenCases) {
            return;
        }
        $caseExists = false;
        foreach ($caseList as &$existingCase) {
            if (isset($existingCase['case']) && (int) $existingCase['case'] === $case) {
                $existingCase['status'] = 'open';
                unset($existingCase['timestamp'], $existingCase['resolved_at'], $existingCase['resolved_by']);
                $caseExists = true;
                break;
            }
        }
        unset($existingCase);
        if (!$caseExists) {
            $caseList[] = ['case' => $case, 'status' => 'open'];
        }
        if ($case !== 4) {
            foreach ($caseList as &$c) {
                if (isset($c['case']) && (int) $c['case'] === 4) {
                    $c['status'] = 'closed';
                    unset($c['timestamp'], $c['resolved_at'], $c['resolved_by']);
                }
            }
            unset($c);
        }
        $db->update('wa_conversations', [
            'conv_case' => json_encode($caseList),
            'updated_at' => date('Y-m-d H:i:s'),
        ], ['wa_number' => $conv->wa_number]);
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
            if ($assigned_user_id !== null && $assigned_user_id !== '') {
                $updateData['assigned_user_id'] = $assigned_user_id;
            }
            if ($code !== null && $code !== '') {
                $updateData['code'] = $code;
            }
            if ($cust_id !== null && $cust_id !== '') {
                $updateData['cust_id'] = $cust_id;
            }
            
            // Only update case if not null and not 0 (Append to existing list)
            if ($case !== null && (int)$case !== 0) {
                $caseList = [];
                
                // 1. Retrieve & Decode existing content
                if (!empty($conv->conv_case)) {
                    $decoded = json_decode($conv->conv_case, true);
                    
                    if (is_array($decoded)) {
                        $isList = isset($decoded[0]);
                        
                        if ($isList) {
                            $caseList = $decoded;
                        } else {
                            if (!empty($decoded)) {
                                $caseList[] = $decoded;
                            }
                        }
                    } elseif (is_numeric($conv->conv_case)) {
                        $caseList[] = ['case' => (int)$conv->conv_case, 'status' => 'unknown'];
                    }
                }
                
                // 2. Check if there are other open cases (for Case 4 logic)
                $caseExists = false;
                $hasOtherOpenCases = false;
                foreach ($caseList as $c) {
                    if (isset($c['case']) && (int)$c['case'] !== 4 && ($c['status'] ?? '') === 'open') {
                        $hasOtherOpenCases = true;
                        break;
                    }
                }
                
                // NEW RULE: If trying to add/open Case 4 but other cases are open, SKIP
                if ((int)$case === 4 && $hasOtherOpenCases) {
                    // Don't add or update Case 4 - just skip case update entirely
                } else {
                    // Normal case processing
                    foreach ($caseList as &$existingCase) {
                        if (isset($existingCase['case']) && (int)$existingCase['case'] === (int)$case) {
                            $existingCase['status'] = 'open';
                            
                            // Clean up extra fields
                            if(isset($existingCase['timestamp'])) unset($existingCase['timestamp']);
                            if(isset($existingCase['resolved_at'])) unset($existingCase['resolved_at']);
                            if(isset($existingCase['resolved_by'])) unset($existingCase['resolved_by']);
                            
                            $caseExists = true;
                            break;
                        }
                    }
                    unset($existingCase); 
                    
                    // 3. Only append if case doesn't exist
                    if (!$caseExists) {
                        $caseList[] = [
                            'case' => $case,
                            'status' => 'open'
                        ];
                    }
                    
                    if ((int)$case !== 4) {
                        foreach ($caseList as &$c) {
                            if (isset($c['case']) && (int)$c['case'] === 4) {
                                $c['status'] = 'closed';
                                if(isset($c['timestamp'])) unset($c['timestamp']);
                                if(isset($c['resolved_at'])) unset($c['resolved_at']);
                                if(isset($c['resolved_by'])) unset($c['resolved_by']);
                            }
                        }
                        unset($c);
                    }
                    
                    $updateData['conv_case'] = json_encode($caseList);
                }
            }

            $db->update('wa_conversations', $updateData, ['wa_number' => $conv->wa_number]);
            return $conv->id ?? 0;
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
        $sql = 'SELECT ' . $selectColumns . ' FROM pelanggan WHERE ' . $digits . ' LIKE ? ORDER BY id_pelanggan ASC';

        return $db->query($sql, ['%' . $nomor])->result_array() ?: [];
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
