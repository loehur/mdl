<?php

namespace App\Models;

use App\Core\DB;

class WAReplies
{
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
     * Set custom sender untuk Fonnte (bila webhook dari Fonnte, bukan YCloud)
     * @param object $adapter Instance FonnteReplyAdapter
     */
    public function setCustomSender($adapter)
    {
        $this->customSender = $adapter;
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
                require_once __DIR__ . '/../Helpers/WhatsAppService.php';
            }
            $this->waService = new \App\Helpers\WhatsAppService();
        }
        return $this->waService;
    }

    /**
     * Kirim autoreply via WA + push WebSocket. Log jika gagal (untuk debug "intent benar tapi balasan tidak ada").
     * @param string $waNumber
     * @param string $text
     * @return array Response dari sendFreeText
     */
    private function sendAutoreplyText($waNumber, $text)
    {
        $res = $this->getWaService()->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
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
     * @param string $waNumber Phone number
     * @param string $handler Handler name (bon, status, buka, etc)
     * @param int $cooldownMinutes Cooldown period in minutes (default: 10)
     * @return bool True if can send reply
     */
    private function shouldHandle($waNumber, $handler, $cooldownMinutes = 1)
    {
        $db = DB::getInstance(0);
        $provider = 'A';

        $sql = "SELECT created_at FROM wa_auto_reply_log 
                WHERE phone = ? AND handler = ? AND provider = ? 
                ORDER BY created_at DESC LIMIT 1";

        $result = $db->query($sql, [$waNumber, $handler, $provider]);

        if ($result && $result->num_rows() > 0) {
            $lastReply = $result->row()->created_at;
            $cooldownEnd = date('Y-m-d H:i:s', strtotime($lastReply) + ($cooldownMinutes * 60));

            // Still in cooldown period
            if (date('Y-m-d H:i:s') < $cooldownEnd) {
                return false;
            }
        }

        // Update jika sudah ada, insert jika belum
        $existing = $db->get_where('wa_auto_reply_log', [
            'phone' => $waNumber,
            'handler' => $handler,
            'provider' => $provider
        ])->row();

        if ($existing) {
            // Update created_at jika record sudah ada
            $db->update(
                'wa_auto_reply_log',
                ['created_at' => date('Y-m-d H:i:s'), 'provider' => $provider],
                ['phone' => $waNumber, 'handler' => $handler, 'provider' => $provider]
            );
        } else {
            // Insert baru jika belum ada
            $db->insert('wa_auto_reply_log', [
                'phone' => $waNumber,
                'handler' => $handler,
                'provider' => $provider,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        return true;
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
        $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber])->row();
        return $conv ? trim($conv->contact_name ?? '') : '';
    }

    /**
     * Ambil context greeting: contactName + sapaan (pak/bu/kak/bang).
     * Fungsi terpusat untuk handler yang butuh keduanya (PEMBUKA, PENUTUP, JAM_OPERASIONAL).
     * @param string $waNumber Nomor WhatsApp
     * @return array{contactName: string, sapaan: string}
     */
    private function getGreetingContext($waNumber)
    {
        $contactName = $this->getContactNameForGreeting($waNumber);
        return [
            'contactName' => $contactName,
            'sapaan' => $this->getSapaanFromName($contactName),
        ];
    }

    /**
     * Ambil sapaan untuk greeting (pak/bu/kak/bang) sesuai gender.
     * Fungsi terpusat: sendGreetingReplyFirst dan handler lain tinggal panggil ini.
     * Alur: regex (ibu/bu, bapak/pak, bg/bang, kak) → jika tidak cocok, AI klasifikasi gender (kak/bang).
     * @param string $waNumber Nomor WhatsApp
     * @return string 'pak'|'bu'|'kak'|'bang'
     */
    private function getSapaanForGreeting($waNumber)
    {
        $ctx = $this->getGreetingContext($waNumber);
        return $ctx['sapaan'];
    }

    /**
     * Ambil sapaan dari nama. PRIORITAS: regex dulu, baru AI jika tidak ketemu.
     * @internal Dipanggil via getSapaanForGreeting() oleh handler.
     */
    private function getSapaanFromName($contactName)
    {
        $n = strtolower(trim($contactName ?? ''));
        if ($n === '') return 'kak';

        // 1. Regex dulu (pakde/bude sebelum pak/bu agar "pak de"/"bu de" terdeteksi benar)
        if (preg_match('/\b(pakde|pak\s*de)\b/i', $n)) return 'pakde';
        if (preg_match('/\b(bude|bukde|bu\s*de|buk\s*de)\b/i', $n)) return 'bude';
        if (preg_match('/\b(ibu|ibuk|bu|buk)\b/', $n)) return 'bu';
        // Nama diawali "B " atau "B." (inisial, misal B DELI) -> bu
        if (preg_match('/^b\s|^b\./i', $n)) return 'bu';
        if (preg_match('/\b(bapak|pak|bpk)\b/', $n)) return 'pak';
        if (preg_match('/\bom\b/', $n)) return 'om';
        if (preg_match('/\bmas\b/', $n)) return 'mas';
        if (preg_match('/\bmbak\b/', $n)) return 'mbak';
        if (preg_match('/\b(bg|bang)\b|^bg/i', $n)) return 'bang';
        if (preg_match('/\b(kak|kakak)\b/', $n)) return 'kak';

        // 2. Tidak ketemu di regex: baru minta AI (kak/bang dari nama)
        $cacheKey = $n;
        if (isset($this->sapaanAiCache[$cacheKey])) {
            return $this->sapaanAiCache[$cacheKey];
        }
        $sapaan = $this->detectSapaanFromNameWithAI($n);
        $this->sapaanAiCache[$cacheKey] = $sapaan;
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
     * Balasan acknowledgment (ok/siap) variatif sesuai sapaan.
     */
    private function getRandomSiapReply($sapaan)
    {
        $replies = [
            "Siap {$sapaan} 😊",
            "Oke {$sapaan} 😊",
            "Baik {$sapaan} 😊",
            "Sip {$sapaan} 😊",
            "Ok {$sapaan} 😊",
        ];
        return $replies[array_rand($replies)];
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
     * Cek apakah pesan mengandung sapaan (assalamualaikum, pagi, halo, dll) di awal.
     * Untuk menentukan apakah perlu intro sapaan di balasan.
     */
    private function hasGreetingInMessage($textBody)
    {
        $t = strtolower(trim($textBody ?? ''));
        if ($t === '') return false;
        return preg_match('/^(assalam+u[a-z]*|asalam+u[a-z]*|salam|halo|hai|pagi|siang|sore|malam)\b/i', $t)
            || preg_match('/\b(pagi|siang|sore|malam)\s*(kak|bang|pak|bu|adek)/i', $t)
            || preg_match('/\b(assalam+u|asalam+u|salam)\s*(kak|bang|pak|bu|adek)/i', $t);
    }

    /**
     * Kirim balasan salam/sapaan dulu jika pesan mengandung sapaan + intent lain.
     * Dipanggil sebelum handler lain (STATUS, dll) agar intent dijalankan satu per satu: PEMBUKA dulu, baru handler lain.
     * @return bool True jika sudah mengirim greeting
     */
    private function sendGreetingReplyFirst($waNumber, $textBody)
    {
        $textLower = strtolower(trim($textBody ?? ''));
        if (mb_strlen($textLower) < 10) {
            return false;
        }
        // Assalamualaikum (assalam/assalamu, termasuk typo assalammualaikum) atau sapaan lain di awal
        $hasGreeting = preg_match('/^(assalam+u[a-z]*|asalam+u[a-z]*|salam|halo|hai|pagi|siang|sore|malam)\b/i', $textLower)
            || preg_match('/\b(pagi|siang|sore|malam)\s*(kak|bang|pak|bu|adek)/i', $textLower)
            || preg_match('/\b(assalam+u|asalam+u|salam)\s*(kak|bang|pak|bu|adek)/i', $textLower);
        $hasOtherIntent = preg_match('/siap|sudah|dah|udah|udh|bisa|jemput|antar|berapa|brp|harga|transfer|bayar|cek|status|laundry|tagihan|kirim|nota|bon|struk|tutup|buka|jam/i', $textLower);
        if (!$hasGreeting || !$hasOtherIntent) {
            return false;
        }
        $sapaan = $this->getSapaanForGreeting($waNumber);
        $isSalam = preg_match('/assalam+u|asalam+u|salam\b/i', $textLower);
        $reply = $isSalam ? "Waalaikumsalam {$sapaan}" : (preg_match('/pagi/i', $textLower) ? "Pagi {$sapaan}" : (preg_match('/siang/i', $textLower) ? "Siang {$sapaan}" : (preg_match('/sore/i', $textLower) ? "Sore {$sapaan}" : (preg_match('/malam/i', $textLower) ? "Malam {$sapaan}" : "Halo {$sapaan}"))));
        $this->sendAutoreplyText($waNumber, $reply);
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
        $this->currentContactName = $contactName;
        // Strip WhatsApp formatters: * (bold), _ (italic), ~ (strikethrough), ` (monospace)
        $textBodyToCheck = preg_replace('/[*_~`]/', '', $textBody ?? '');
        // Strip quote prefix (> at start of line)
        $textBodyToCheck = preg_replace('/^>\s*/m', '', $textBodyToCheck);
        $textBodyToCheck = strtolower(trim($textBodyToCheck));       

        $messageLength = mb_strlen($textBodyToCheck);

        // Get DB instance for conversation management
        $db = DB::getInstance(0);

        // Load keyword configuration
        $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';
        
        // Simpan config lengkap untuk akses case dan notify nanti
        $fullKeywordConfig = $keywordConfig;

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

        // "masih/msh/msih bisa/bs terima kain?" atau "masih nerima ga klo gosok aj?" -> konfirmasi ke petugas + jam operasional (PRIORITAS)
        // BEDA dengan "masih buka?" yang jawab "masih buka kak/bang"
        $masihBisaTerimaPattern = '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis|b\s*s)(?:\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?)?\s*\??\s*$/i';
        $masihTerimaGosokPattern = '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i';
        if (preg_match($masihBisaTerimaPattern, $textBodyToCheck) || preg_match($masihTerimaGosokPattern, $textBodyToCheck)) {
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
                    // REKENING pattern match tapi pesan konfirmasi pembayaran (sudah transfer) = PENUTUP, skip REKENING
                    if ($handler === 'REKENING' && preg_match('/(telah berhasil mengirimkan|sudah transfer|sudah bayar|sudah kirim|sudah mengirim)/i', $textBodyToCheck)) {
                        continue;
                    }
                    // Pertanyaan (mengandung ?) TIDAK boleh masuk PEMBUKA atau PENUTUP
                    if (($handler === 'PEMBUKA' || $handler === 'PENUTUP') && strpos($textBody ?? '', '?') !== false) {
                        continue;
                    }
                    // "jam berapa bisa jemput?" = MINTA_JEMPUT_ANTAR (minta jemput), bukan JAM_OPERASIONAL
                    if ($handler === 'JAM_OPERASIONAL' && preg_match('/\bbisa\s*(jemput|antar)\b/i', $textBodyToCheck) && !preg_match('/\b(masih|masi|msih)\s+bisa\s*(jemput|antar)/i', $textBodyToCheck)) {
                        continue;
                    }
                    // Get case from config
                    $caseVal = $config['case'] ?? null;
                    $notify = $config['notify'] ?? false;
                    $matchPattern[] = $handler;

                    if (class_exists('\Log')) {
                        \Log::write(mb_substr($textBody ?? '', 0, 100) . " | {$handler} | regex", 'wa', 'intent');
                    }
                    
                    // Unset matched keyword from config to optimize AI detection
                    // AI tidak perlu cek keyword yang sudah match di regex
                    unset($keywordConfig[$handler]);
                    
                    //cek rate limit
                    if (!$this->shouldHandle($waNumber, $handler)) {
                        $conversationId = $this->getOrCreateConversationWithCase(
                            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                        );
                        
                        return (object) [
                            'case' => null,
                            'notify' => false,
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
                        // Kalimat PENUTUP ambigu (closed order, dll): tetap intent PENUTUP tapi jangan dibalas AI
                        if ($handler === 'PENUTUP' && $this->isAmbiguousPenutupShortPhrase($textBodyToCheck)) {
                            return (object) [
                                'case' => $caseVal,
                                'notify' => $notify,
                                'conversation_id' => $conversationId
                            ];
                        }
                        // Jika handler BUKAN PEMBUKA dan pesan ada sapaan+intent lain: kirim sapaan dulu, baru handler (satu per satu)
                        if ($handler !== 'PEMBUKA') {
                            $this->sendGreetingReplyFirst($waNumber, $textBody);
                        }
                        $this->$methodName($phoneIn, $waNumber, $textBody);
                        return (object) [
                            'case' => $caseVal,
                            'notify' => $notify,
                            'conversation_id' => $conversationId
                        ];
                    }
                    // Handler match tapi method tidak ada (mis. MINTA_JEMPUT_ANTAR): create conversation, return, biarkan CS
                    return (object) [
                        'case' => $caseVal,
                        'notify' => $notify,
                        'conversation_id' => $conversationId
                    ];
                }
            }
        }

        // Short message (likely not a real query) - still create conversation!
        if ($messageLength >= 0 && $messageLength <= 7) {
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
            );
            
            return (object) [
                'case' => null,
                'notify' => false,
                'conversation_id' => $conversationId
            ];
        }

        // Pass filtered keywordConfig to AI (keywords yang sudah match di regex sudah di-unset)
        // Ini mengoptimalkan AI detection karena AI tidak perlu cek keyword yang sudah match
        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig);

        // Check if AI successfully detected a valid intent
        if ($aiResult && is_array($aiResult) && isset($aiResult['intent']) && strtoupper($aiResult['intent']) !== 'FALSE') {
            $aiIntent = strtoupper($aiResult['intent']);
            // Pertanyaan (mengandung ?) TIDAK boleh masuk PEMBUKA atau PENUTUP - treat sebagai unknown
            $isQuestion = strpos($textBody ?? '', '?') !== false;
            if ($isQuestion && in_array($aiIntent, ['PEMBUKA', 'PENUTUP'])) {
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                return (object) [
                    'case' => null,
                    'notify' => false,
                    'conversation_id' => $conversationId
                ];
            }

            // Gunakan fullKeywordConfig untuk akses case dan notify (config lengkap)
            $aiCase = $fullKeywordConfig[$aiIntent]['case'] ?? null;
            $aiNotify = $fullKeywordConfig[$aiIntent]['notify'] ?? false;
            
            // Note: Tidak perlu cek in_array($aiIntent, $matchPattern) lagi
            // karena keyword yang sudah match di regex sudah di-unset dari $keywordConfig
            // Jadi jika AI detect intent, berarti intent tersebut belum match di regex

            // Rate limit check for AI intent
            // ========================================
            if (!$this->shouldHandle($waNumber, $aiIntent)) {
                // Rate limited - create conversation but don't send auto-reply
                $conversationId = $this->getOrCreateConversationWithCase(
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, null
                );
                
                return (object) [
                    'case' => $aiCase,
                    'notify' => $aiNotify,
                    'conversation_id' => $conversationId
                ];
            }
            
            // Rate limit passed - create conversation with AI case
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, $aiCase
            );

            // Call handler method
            $handlerName = ucwords(strtolower($aiIntent), '_');
            $methodName = 'handle' . $handlerName;
            if (method_exists($this, $methodName)) {
                $this->currentHandler = $aiIntent;
                // Kalimat PENUTUP ambigu (closed order, dll): tetap intent PENUTUP tapi jangan dibalas AI
                if ($aiIntent === 'PENUTUP' && $this->isAmbiguousPenutupShortPhrase($textBodyToCheck)) {
                    return (object) [
                        'case' => $aiCase,
                        'notify' => $aiNotify,
                        'conversation_id' => $conversationId
                    ];
                }
                // Jika handler BUKAN PEMBUKA dan pesan ada sapaan+intent lain: kirim sapaan dulu, baru handler (satu per satu)
                if ($aiIntent !== 'PEMBUKA') {
                    $this->sendGreetingReplyFirst($waNumber, $textBody);
                }
                $this->$methodName($phoneIn, $waNumber, $textBody);
            }

            return (object) [
                'case' => $aiCase,
                'notify' => $aiNotify,
                'conversation_id' => $conversationId
            ];
        }

        // AI failed or unknown intent - still create conversation!
        $conversationId = $this->getOrCreateConversationWithCase(
            $db, $waNumber, $contactName, $assigned_user_id, $code, $cust_id, $lastMessage, 4
        );
        
        return (object) [
            'case' => 4,
            'notify' => true,
            'conversation_id' => $conversationId
        ];
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
                $res = $waService->sendFreeText($waNumber, $notif['text']);

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
        } else {
            // Find customer
            $where = "nomor_pelanggan IN ($phoneIn)";
            $pelanggan = $db1->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();
            $id_pelanggans = array_column($pelanggan, 'id_pelanggan');

            // Check if customer exists BEFORE accessing array
            if (empty($id_pelanggans)) {
                // Customer NOT registered - send message and exit
                $noRegText = $this->getNoRegisterText();
                $res = $waService->sendFreeText($waNumber, $noRegText);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $noRegText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
                return;
            }

            // Customer exists - get first one
            $id_pelanggan = $id_pelanggans[0];
            $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
            $nama_pelanggan = strtoupper($nama_pelanggans[0] ?? 'PELANGGAN');

            $ids_in = implode(',', $id_pelanggans);

            // Find unfinished sales
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan ORDER BY insertTime DESC")->result_array();
            $id_pelanggans_active = array_column($sales, 'id_pelanggan');
            $noRefs = array_column($sales, 'no_ref');

            if (!empty($noRefs)) {
                // Remove refs that already have a notification of tipe 1
                $noRefsIn = "'" . implode("','", $noRefs) . "'";
                $existingRefs = array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 1 AND no_ref IN ($noRefsIn)")->result_array(), 'no_ref');
                $missingRefs = array_diff($noRefs, $existingRefs);

                if (count($missingRefs) > 0) {
                    foreach ($missingRefs as $ref) {
                        // Create context with User-Agent to avoid potential filtering
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
                                // Insert Notif
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
                                    $res = $waService->sendFreeText($waNumber, $responseData['text']);

                                    $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                                    $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null);
                                    $wamid = $res['data']['wamid'] ?? null;

                                    // Update state immediately
                                    $updateData = ['state' => $status];
                                    if ($msgId) {
                                        $updateData['id_api'] = $msgId;
                                    }

                                    $db1->update('notif', $updateData, ['id_notif' => $id_notif]);

                                    // Broadcast to WebSocket
                                    if ($res['success']) {
                                        $payload = $this->buildWsPayload($waNumber, $responseData['text'], $msgId, $wamid);
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
                } else {
                    // All notifs already exist - they were sent before
                    $list_link = "";
                    // Remove duplicates - same customer may have multiple transactions
                    $unique_pelanggans_active = array_unique($id_pelanggans_active);
                    foreach ($unique_pelanggans_active as $id_pelanggan_active) {
                        $list_link .= "https://ml.nalju.com/I/" . $id_pelanggan_active . "\n";
                    }

                    $text = "Pak/Bu *" . $nama_pelanggan . "*,\nNota/Bon sudah kami kirimkan sebelumnya. Terima kasih 😊\n" . $list_link;
                    $res = $waService->sendFreeText($waNumber, $text);
                    if ($res['success']) {
                        $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                    }
                }
            } else {
                $text = "Pak/Bu *" . $nama_pelanggan . "*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih 😊";
                $res = $waService->sendFreeText($waNumber, $text);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
            }
        }
    }

    /**
     * Handle intent REKENING - balas data rekening pembayaran dan QRIS
     */
    private function handleRekening($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $text = "Berikut *Rekening Pembayaran* Madinah Laundry:\n\n" .
            "QRIS\nhttps://ml.nalju.com/I/q\n\n" .
            "BRI 327901031534535\n" .
            "BCA 8455103793\n" .
            "BTNs 7132077419\n" .
            "SEABANK 901799867052\n" .
            "SHOPEE/GOPAY/DANA 081268098300\n\n" .
            "a.n. LUHUR GUNAWAN\n\n" .
            "Terima kasih 😊";

        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    /**
     * Handle intent PEMBUKA - balas sapaan pembuka dengan AI sebagai customer service laundry
     * Jika diluar jam operasional, alihkan ke handleJam_tutup
     */
    private function handlePembuka($phoneIn, $waNumber, $textBody = '')
    {
        if (!$this->isOperatingHours()) {
            $this->handleJam_tutup($phoneIn, $waNumber, $textBody);
            return;
        }

        $textLower = strtolower(trim($textBody ?? ''));
        $textStripped = preg_replace('/[\s\x{200B}-\x{200D}\x{FEFF}]/u', '', $textLower);
        $len = mb_strlen($textStripped);
        $hasOtherIntent = preg_match('/siap|dah|udah|bisa|jemput|antar|berapa|harga|transfer|bayar/i', $textLower) && mb_strlen($textLower) > 15;

        $ctx = $this->getGreetingContext($waNumber);
        $contactName = $ctx['contactName'];
        $sapaan = $ctx['sapaan'];

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
            $isSalam = preg_match('/assalam+u|asalam+u|salam\b/i', $textLower);
            $reply = $isSalam ? "Waalaikumsalam {$sapaan}" : (preg_match('/pagi/i', $textLower) ? "Pagi {$sapaan}" : (preg_match('/siang|sore|malam/i', $textLower) ? "Siang {$sapaan}" : "Halo {$sapaan}"));
            $this->sendAutoreplyText($waNumber, $reply);
            $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';
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
            return;
        }

        $sapaanHint = $contactName !== ''
            ? "Nama customer: \"{$contactName}\". Aturan sapaan: HANYA jika nama mengandung ibu/bu -> pakai bu. HANYA jika nama mengandung pak/bapak/bpk -> pakai pak. Jika TIDAK mengandung itu, pakai kak/kakak/bg/bang."
            : "Nama customer tidak tersedia. Pakai sapaan kak/kakak.";

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
                return;
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu adalah customer service Madinah Laundry. Balas HANYA sapaan pembuka dari customer. JANGAN jawab pertanyaan/permintaan lain.\n\nCRITICAL - SINGKAT & SANTAI:\n- Balas PENDEK (1 kalimat, max 8-10 kata). Jangan formal. Santai tapi ramah.\n- Contoh singkat: \"Halo kak, ada yang bisa dibantu?\" \"Pagi bu 😊\" \"Waalaikumsalam pak\"\n- JANGAN kalimat panjang. JANGAN pakai tanda seru (!).\n\nCRITICAL - JANGAN PERNAH sebut nama customer dalam balasan (gunakan hanya untuk identifikasi sapaan).\n\nCRITICAL - JANGAN JAWAB PERTANYAAN:\n- Jika pesan mengandung sapaan + pertanyaan (misal: 'Assalamualaikum, kain ku dah siap?'), balas CUKUP salam saja. Handler lain yang jawab pertanyaannya.\n- Contoh: \"Assalamualaikum, kain ku dah siap?\" -> \"Waalaikumsalam pak\" (HANYA itu)\n\nPENTING:\n- Assalamualaikum -> Waalaikumsalam + sapaan. Halo/pagi/siang/malam -> sesuaikan + sapaan.\n- Sapaan: HANYA jika nama ada ibu/bu -> bu. HANYA jika nama ada pak/bapak/bpk -> pak. Jika tidak, pakai kak/kakak/bg/bang. JANGAN sebut nama. JANGAN kata 'Anda'.\n- Boleh singkatan umum: siap, oke. JANGAN pakai 'mk'. JANGAN pakai tanda seru (!). Santai, tidak formal, tetap ramah."
                ],
                [
                    'role' => 'user',
                    'content' => "{$sapaanHint}\n\nPesan customer: \"{$textBody}\"\n\nBalas singkat dan santai. Max 1-2 kalimat pendek."
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 120);
            $text = trim(str_replace('!', '', $answer));
            if (empty($text) || mb_strlen($text) <= 2) {
                $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
                return;
            }

            $this->sendAutoreplyText($waNumber, $text);
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handlePembuka ERROR: " . $e->getMessage(), 'wa_error', 'Pembuka');
            }
            $this->sendAutoreplyText($waNumber, "Halo {$sapaan} 😊");
        }
    }

    /**
     * Handle intent PENUTUP - balas penutup/konfirmasi dengan AI sebagai customer service laundry
     * Termasuk konfirmasi pembayaran/transfer
     */
    private function handlePenutup($phoneIn, $waNumber, $textBody = '')
    {
        $textLower = trim(strtolower($textBody ?? ''));
        $textTrimmed = trim($textBody ?? '');

        // Regex quick path: reaction (Reacted: 👍) atau emoji saja (👍 ❤️ 😊) -> balas emoji ramah saja
        if (preg_match('/^reacted\s*:?\s*.+$/i', $textTrimmed)) {
            $this->sendAutoreplyText($waNumber, '😊');
            return;
        }
        if (mb_strlen($textTrimmed) <= 6 && preg_match('/^[^\p{L}\p{N}]+$/u', $textTrimmed) && $textTrimmed !== '') {
            $this->sendAutoreplyText($waNumber, '😊');
            return;
        }

        $ctx = $this->getGreetingContext($waNumber);
        $contactName = $ctx['contactName'];
        $sapaan = $ctx['sapaan'];

        // Regex quick path: terimakasih/makasih (termasuk "oke makasih kak", "ok makasih") -> variatif (sesuai sapaan)
        if (preg_match('/^(terima\s*kasih|terimakasih|makasih|mksh|thanks|thx|tq)(\s+(kak|bang|pak|bu))?\s*[.!]?$/i', $textLower)
            || preg_match('/^(ok|oke)\s*[,.]?\s*(makasih|terimakasih|thanks|thx)(\s+(kak|bang|pak|bu))?\s*[.!]?$/i', $textLower)) {
            $terimakasihReplies = [
                "Sama-sama {$sapaan} 😊",
                "Baik {$sapaan}, sama-sama 😊",
                "Oke {$sapaan}, sama-sama 😊",
                "Sama-sama ya {$sapaan} 😊",
                "Terima kasih juga {$sapaan} 😊",
                "Senang bisa membantu {$sapaan} 😊",
                "Dengan senang hati {$sapaan} 😊",
                "Terima kasih kembali {$sapaan} 😊",
            ];
            $reply = $terimakasihReplies[array_rand($terimakasihReplies)];
            $this->sendAutoreplyText($waNumber, $reply);
            return;
        }

        // Regex quick path: gpp/gak apa-apa (acknowledgment singkat) -> balas emoji ramah saja
        if (preg_match('/^(gpp|gak\s*apa\s*apa|ga\s*apa\s*apa)(\s+(kak|bang|pak|bu))?\s*[.\s]*$/i', $textLower)) {
            $this->sendAutoreplyText($waNumber, '😊');
            return;
        }

        // Regex quick path: ok/baik/siap (acknowledgment) tanpa jemput/antar
        if (preg_match('/^(ok|oke|baik|sip|siap)(\s+deh)?\s*[.!]?$/i', $textLower) && !preg_match('/jemput|antar|ambil/i', $textLower)) {
            $this->sendAutoreplyText($waNumber, $this->getRandomSiapReply($sapaan));
            return;
        }

        $sapaanHint = $contactName !== ''
            ? "Nama customer: \"{$contactName}\". Sapaan yang WAJIB dipakai: {$sapaan}. JANGAN pakai sapaan lain."
            : "Nama customer tidak tersedia. Pakai sapaan: {$sapaan}.";

        try {
            if (!class_exists('\\App\\Config\\AI') || !\App\Config\AI::isEnabled()) {
                $this->sendAutoreplyText($waNumber, $this->getRandomSiapReply($sapaan));
                return;
            }

            $messages = [
                [
                    'role' => 'system',
                    'content' => "Kamu adalah customer service Madinah Laundry. Balas penutup/acknowledgment dari customer.\n\nCRITICAL - SINGKAT & SANTAI:\n- Balas PENDEK (max 1 kalimat, 5-8 kata). Jangan formal. Santai tapi ramah.\n- WAJIB gunakan PERSIS sapaan yang diberikan (bang/bu/kak/pak). JANGAN ganti sapaan.\n- Contoh: jika sapaan=bang -> \"Oke bang\" \"Siap bang\" \"Selamat mudik ya bang\". Jika sapaan=kak -> \"Oke kak\" \"Siap kak\".\n- JANGAN kalimat panjang. JANGAN pakai tanda seru (!). JANGAN pakai singkatan 'mk' atau 'mksh'.\n- JANGAN PERNAH gunakan kata \"ditunggu\" atau \"di tunggu\" atau \"ditunggu ya\" - HILANGKAN dari semua balasan.\n- JANGAN PERNAH sebut nama customer dalam balasan.\n\nJENIS PENUTUP:\n1. Terimakasih/makasih/thanks -> balas sama-sama + sapaan\n2. Ok/baik/siap (acknowledgment) -> balas \"Siap\" atau \"Oke\" + sapaan\n3. Konfirmasi transfer -> \"Siap\" atau \"Oke, terima kasih\"\n4. Pemberitahuan jemput/antar/mudik -> balas \"Siap\" atau \"Oke, selamat mudik ya\" + sapaan\n5. JANGAN balas komplain/keluhan - itu BUKAN PENUTUP\n\nPENTING: Pakai PERSIS sapaan yang diberikan. JANGAN pakai \"ditunggu\". JANGAN pakai tanda seru (!)."
                ],
                [
                    'role' => 'user',
                    'content' => "{$sapaanHint}\n\nPesan customer: \"{$textBody}\"\n\nBalas singkat dan santai. Max 1 kalimat pendek. Gunakan sapaan: {$sapaan}"
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 100);
            $text = trim(str_replace('!', '', $answer));
            if (empty($text) || mb_strlen($text) <= 2) {
                $this->sendAutoreplyText($waNumber, $this->getRandomSiapReply($sapaan));
                return;
            }
            // Hilangkan "ditunggu ya" / "di tunggu" jika AI tetap mengeluarkan
            $text = preg_replace('/,?\s*(di\s*)?tunggu\s*(ya\s*)?(kak|bang|pak|bu)?\s*[.!]?/i', '', $text);
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if ($text === '' || $text === ',') {
                $text = $this->getRandomSiapReply($sapaan);
            }

            $this->sendAutoreplyText($waNumber, $text);
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handlePenutup ERROR: " . $e->getMessage(), 'wa_error', 'Penutup');
            }
            $this->sendAutoreplyText($waNumber, $this->getRandomSiapReply($sapaan));
        }
    }

    /**
     * Handle intent TAGIHAN - balas rincian tagihan dengan item detail (seperti I.php view)
     * Menggunakan db(1) = mdl_laundry
     */
    /**
     * Handle intent HARGA_PAKET - harga paket/member/langganan bulanan laundry
     * Data dari tabel harga_paket (db 1), urut by nama harga dan qty
     */
    private function handleHarga_Paket($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $priceDataText = $this->loadHargaPaketDataForAI();
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
                    'content' => "Kamu adalah asisten harga paket/member laundry. Jawab HANYA berdasarkan data yang diberikan.\n\nPENTING - PAKET BULANAN = PAKET MEMBER = HARGA PAKET (SAMA):\n- 'Paket bulanan', 'paket member', 'harga paket', 'ada paket?', 'langganan' = SEMUA merujuk ke data yang sama. Data di bawah adalah paket kuota/deposit.\n- JANGAN PERNAH jawab 'kami tidak punya paket bulanan' atau 'tidak ada paket bulanan'. SELALU tampilkan data paket yang ada.\n\nPENTING - FILTER LAYANAN:\n- 'Cuci + Setrika' = cuci DAN setrika, 'Setrika' = setrika saja.\n- Jika customer tanya 'cuci setrika': TAMPILKAN HANYA paket 'Cuci + Setrika'. JANGAN tampilkan 'Setrika' saja.\n- Jika customer tanya 'setrika saja': tampilkan HANYA paket 'Setrika' saja.\n- Jika customer tanya 'paket bulanan?', 'ada paket?', 'harga paket?', 'harga member?' (tanpa spesifikasi layanan): tampilkan SEMUA data namun ringkas.\n\nURUTAN & FORMAT: Data SUDAH diurutkan. JANGAN ubah urutan. Format: *bold*, _italic_, emoji secukupnya, line break, tutup ramah."
                ],
                [
                    'role' => 'user',
                    'content' => "DATA HARGA PAKET/MEMBER LAUNDRY (paket bulanan = paket member = paket kuota - ini data yang sama):\n\n" . $priceDataText . "\n\n---\n\nPertanyaan customer: " . $textBody . "\n\nJawab berdasarkan data. JANGAN bilang tidak punya paket bulanan - tampilkan data paket. Jika tanya layanan spesifik (cuci setrika, setrika saja), filter paket yang match saja."
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 600);
            $text = trim($answer);
            if (empty($text)) {
                return;
            }

            $catatan = "\n\n_Catatan:_\n- Pembayaran dimuka/deposit\n- Kuota berlaku selamanya\n- Kuota tidak dapat direfund";
            $text .= $catatan;

            $res = $waService->sendFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handleHarga_Paket ERROR: " . $e->getMessage(), 'wa_error', 'HargaPaket');
            }
            return;
        }
    }

    /**
     * Load harga paket dari db(1) - format untuk AI
     * Urut by id_harga (nama paket), qty
     */
    private function loadHargaPaketDataForAI()
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
        $lines = [];
        $currentNama = '';

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

            // Jangan tampilkan paket yang nama mengandung -D
            if (strpos($nama, '-D') !== false) {
                continue;
            }

            if ($nama !== $currentNama) {
                $currentNama = $nama;
                $lines[] = "\n=== " . strtoupper($nama) . " ({$unit}) ===";
            }

            $qtyUnit = $qty . $unit;
            $lines[] = "  {$qtyUnit}: Rp " . number_format($harga, 0, ',', '.');
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

        $priceDataText = $this->loadHargaDataForAI();
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
                    'content' => "Kamu adalah asisten harga laundry. Jawab HANYA berdasarkan data harga yang diberikan.\n\nPENTING - URUTAN: Item dalam data SUDAH diurutkan by sort (paling sering dipakai). Baris PERTAMA = nomor 1, baris kedua = 2, dst. JANGAN ubah urutan, JANGAN sort ulang by harga. Tampilkan sesuai urutan yang diberikan.\n\n- Jika pertanyaan JELAS/SPESIFIK: jawab fokus pada yang ditanya.\n- Jika pertanyaan BELUM JELAS: tampilkan 5 harga teratas = BARIS PERTAMA dari data (jangan urutkan ulang by harga).\n\nFORMAT WHATSAPP agar menarik:\n- Gunakan *teks* untuk bold (judul, nominal)\n- Gunakan _teks_ untuk italic (penekanan)\n- Boleh gunakan emoji secukupnya (📋 ✨ 💰) untuk mempercantik\n- Beri line break antar item agar mudah dibaca\n- WAKTU: Data sudah berformat 'X Hari' atau 'Y Jam' atau 'X Hari Y Jam'. Tampilkan persis seperti di data (jangan ubah ke format 1h 0j)\n- Tutup dengan kalimat ramah dan ajakan bertanya lebih lanjut"
                ],
                [
                    'role' => 'user',
                    'content' => "DATA HARGA LAUNDRY (urutan SUDAH BENAR - baris pertama = sort tertinggi/paling populer, JANGAN sort ulang by harga):\n\n" . $priceDataText . "\n\n---\n\nPertanyaan customer: " . $textBody . "\n\nJawab berdasarkan data di atas. Jika tidak spesifik, tampilkan 5 BARIS PERTAMA sesuai urutan data (jangan ubah urutan)."
                ]
            ];

            $answer = $this->executeOpenAIRequestWithMessages($messages, 400);
            $text = trim($answer);
            if (empty($text)) {
                return; // AI tidak bisa jawab, CS yang akan membalas manual
            }

            $res = $waService->sendFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("handleHarga ERROR: " . $e->getMessage(), 'wa_error', 'Harga');
            }
            return; // Error, CS yang akan membalas manual
        }
    }

    /**
     * Load harga data dari db(1) - format sama SetHarga view
     * Return: text untuk context AI
     */
    private function loadHargaDataForAI()
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

        $lines = [];
        $currentJenis = '';
        $lineNum = 0;
        foreach ($rows as $r) {
            $lineNum++;
            $idPj = $r['id_penjualan_jenis'];
            $pj = $penjualan[$idPj] ?? null;
            $namaJenis = $pj ? $pj['nama'] : 'Layanan';
            $idSatuan = $pj ? $pj['id_satuan'] : 0;
            $unit = $satuan[$idSatuan] ?? '';

            if ($namaJenis !== $currentJenis) {
                $currentJenis = $namaJenis;
                $lines[] = "\n=== " . strtoupper($namaJenis) . " (per " . $unit . ") ===";
            }

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

            $prefix = $lineNum <= 10 ? "{$lineNum}. " : "- ";
            $line = $prefix . "{$kategori} | {$layananStr} | {$durasiStr} | Rp " . number_format($harga, 0, ',', '.') . "/{$unit}";
            if ($minOrder > 0) {
                $line .= " | Min order: {$minOrder}{$unit}";
            }
            if ($hari > 0 || $jam > 0) {
                $waktuParts = [];
                if ($hari > 0) $waktuParts[] = $hari . ' Hari';
                if ($jam > 0) $waktuParts[] = $jam . ' Jam';
                $line .= ' | Waktu: ' . implode(' ', $waktuParts);
            }
            $lines[] = $line;
        }

        return trim(implode("\n", $lines));
    }

    private function handleTagihan($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        $db = DB::getInstance(1);

        $where = "nomor_pelanggan IN ($phoneIn)";
        $pelanggan = $db->query("SELECT id_pelanggan, nama_pelanggan, id_cabang FROM pelanggan WHERE $where")->result_array();

        if (empty($pelanggan)) {
            $noRegText = $this->getNoRegisterText();
            $res = $waService->sendFreeText($waNumber, $noRegText);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $noRegText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            return;
        }

        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        $ids_in = implode(',', $id_pelanggans);

        // Cari id_pelanggan dari sales yang tuntas=0 dulu (sama seperti handleNota)
        $sales = $db->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan ORDER BY insertTime DESC")->result_array();
        $id_pelanggans_from_sale = array_unique(array_column($sales, 'id_pelanggan'));

        // Juga cek member lunas=0
        $members = $db->query("SELECT id_pelanggan FROM member WHERE bin = 0 AND id_pelanggan IN ($ids_in) AND lunas = 0")->result_array();
        $id_pelanggans_from_member = array_unique(array_column($members, 'id_pelanggan'));

        $id_pelanggans_to_check = array_unique(array_merge($id_pelanggans_from_sale, $id_pelanggans_from_member));
        if (empty($id_pelanggans_to_check)) {
            $id_pelanggan = $id_pelanggans[0];
            $nama_pelanggan = strtoupper($pelanggan[array_search($id_pelanggan, $id_pelanggans)]['nama_pelanggan'] ?? 'PELANGGAN');
            $id_cabang = (int) ($pelanggan[array_search($id_pelanggan, $id_pelanggans)]['id_cabang'] ?? 0);
            $link = "https://ml.nalju.com/I/" . $id_pelanggan;
            $text = "Pak/Bu *" . $nama_pelanggan . "*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih 😊\n" . $link;
            $res = $waService->sendFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
            return;
        }

        $id_pelanggan = array_values($id_pelanggans_to_check)[0];
        $pelangganRow = null;
        foreach ($pelanggan as $p) {
            if ($p['id_pelanggan'] == $id_pelanggan) {
                $pelangganRow = $p;
                break;
            }
        }
        $nama_pelanggan = strtoupper($pelangganRow['nama_pelanggan'] ?? 'PELANGGAN');
        $id_cabang = (int) ($pelangganRow['id_cabang'] ?? 0);

        $lookup = $this->loadTagihanLookups($db);
        $lines = [];
        $totalTagihan = 0;

        // 1. Sale - ambil item per baris (sama seperti I.php)
        // Urutan sama seperti I.php: id_penjualan DESC agar id item yang tampil selaras dengan invoice
        $saleRows = $db->query(
            "SELECT id_penjualan, no_ref, id_item_group, id_penjualan_jenis, id_durasi, list_layanan, qty, harga, min_order, diskon_qty, diskon_partner, member, insertTime FROM sale WHERE id_pelanggan = ? AND bin = 0 AND tuntas = 0 ORDER BY no_ref, id_penjualan DESC",
            [$id_pelanggan]
        )->result_array();

        $byRef = [];
        foreach ($saleRows as $row) {
            $byRef[$row['no_ref']][] = $row;
        }

        foreach ($byRef as $noRef => $items) {
            $subTotal = 0;
            $itemLines = [];

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

        // 2. Member (lunas=0) - dengan rincian paket
        $members = $db->query(
            "SELECT m.id_member, m.id_harga, m.harga, m.qty, m.insertTime FROM member m WHERE m.id_cabang = ? AND m.bin = 0 AND m.id_pelanggan = ? AND m.lunas = 0 ORDER BY m.id_member DESC",
            [$id_cabang, $id_pelanggan]
        )->result_array();

        foreach ($members as $mem) {
            $id_member = $mem['id_member'];
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
            $text = "Pak/Bu *" . $nama_pelanggan . "*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih 😊\n" . $link;
        } else {
            $text = "*" . $nama_pelanggan . "*\nRincian Tagihan:\n\n" . implode("\n\n", $lines) . "\n\n*Total Tagihan: Rp " . number_format($totalTagihan, 0, ',', '.') . "*\n" . $link;
        }

        $res = $waService->sendFreeText($waNumber, $text);
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
        $pricePart = ($member === 1) ? 'Member' : 'Rp ' . number_format($total, 0, ',', '.');
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

    private function handleStatus($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $db1 = DB::getInstance(1);
        $limitTime = date('Y-m-d H:i:s', strtotime('-72 hours'));

        $sql = "SELECT * FROM notif 
                WHERE tipe = 2 AND state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND phone IN ($phoneIn)
                ORDER BY insertTime ASC";

        $pendingNotifs = $db1->query($sql)->result_array();
        
        // Track which id_penjualan already have pending notifs
        $pendingNotifIds = [];
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
                $res = $waService->sendFreeText($waNumber, $notif['text']);

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
        }
        
        // Always check sale status, even if there are pending notifs
        // This ensures items without pending notifs are also reported
        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);

        $where = "nomor_pelanggan IN ($phoneIn)";
        $pelanggan = $db1->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();
        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
        $nama_pelanggan = strtoupper($nama_pelanggans[0] ?? ''); // fix index 0 if empty

        if (empty($id_pelanggans)) {
            $noRegText = $this->getNoRegisterText();
            $res = $waService->sendFreeText($waNumber, $noRegText);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $noRegText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } else {
            $ids_in = implode(',', $id_pelanggans);
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
            $noRefs = array_column($sales, 'no_ref');
            if (empty($noRefs)) {
                $text = 'Pak/Bu *' . $nama_pelanggan . '*, belum ada tagihan dan semua laundry sudah di ambil. Terima kasih';
                $res = $waService->sendFreeText($waNumber, $text);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
            } else {
                $listIdPenjualan = []; // Items still in progress (belum ada notif selesai)
                $listIdSelesai = [];   // Items already completed (sudah ada notif selesai)
                $allIdPenjualan = [];  // All items for fallback display
                foreach ($noRefs as $noRef) {
                    $get_penjualan = $db1->query("SELECT id_penjualan, id_pelanggan, letak FROM sale WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND no_ref = '$noRef'")->result_array();
                    $id_penjualans = array_column($get_penjualan, 'id_penjualan');
                    $id_pelanggans = array_column($get_penjualan, 'id_pelanggan');

                    // Fix for VARCHAR IDs: Quote them
                    $quotedIds = array_map(function ($id) {
                        return "'$id'";
                    }, $id_penjualans);
                    $id_penjualans_in = implode(',', $quotedIds);

                    // Get id_penjualan that already have notif tipe 2
                    $existingNotifIds = !empty($id_penjualans) ? array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($id_penjualans_in)")->result_array(), 'no_ref') : [];
                    
                    // Check each sale item: Selesai = ada notif tipe 2 DAN letak sudah terisi
                    $completedWithLocation = [];
                    $inProgressItems = [];
                    
                    foreach ($get_penjualan as $sale) {
                        $id_penjualan = $sale['id_penjualan'];
                        $letak = $sale['letak'] ?? '';
                        
                        // Skip if this id_penjualan already has pending notif (already sent above)
                        if (in_array($id_penjualan, $pendingNotifIds)) {
                            continue;
                        }
                        
                        // Collect all id_penjualan for fallback
                        $allIdPenjualan[] = $id_penjualan;
                        
                        $hasNotif = in_array($id_penjualan, $existingNotifIds);
                        $hasLocation = !empty(trim($letak));
                        
                        // Selesai: ada notif DAN letak sudah terisi
                        if ($hasNotif && $hasLocation) {
                            $completedWithLocation[] = $id_penjualan;
                        } else {
                            // Dalam Pengerjaan: tidak ada notif ATAU letak masih kosong
                            $inProgressItems[] = $id_penjualan;
                        }
                    }
                    
                    // Items still in progress
                    if (count($inProgressItems) > 0) {
                        array_push($listIdPenjualan, $inProgressItems);
                    }

                    // Items already completed (ada notif DAN letak terisi)
                    if (count($completedWithLocation) > 0) {
                        array_push($listIdSelesai, $completedWithLocation);
                    }
                }

                // Only send status message if there are items that don't have pending notifs
                if (count($listIdPenjualan) > 0 || count($listIdSelesai) > 0 || count($allIdPenjualan) > 0) {
                    $list_link = "";
                    // Remove duplicates - same customer may have multiple transactions
                    // Use $id_pelanggans from the outer scope (line 479), not from inside the loop
                    $unique_pelanggans = array_unique($id_pelanggans);
                    foreach ($unique_pelanggans as $id_pelanggan) {
                        $list_link .= "https://ml.nalju.com/I/" . $id_pelanggan . "\n";
                    }

                    if (count($listIdPenjualan) > 0 || count($listIdSelesai) > 0) {
                        // Build formatted status list
                        $statusList = [];

                        // Flatten in-progress items
                        $flatInProgress = [];
                        foreach ($listIdPenjualan as $subArr) {
                            if (is_array($subArr)) {
                                foreach ($subArr as $v)
                                    $flatInProgress[] = $v;
                            } else {
                                $flatInProgress[] = $subArr;
                            }
                        }

                        // Flatten completed items
                        $flatCompleted = [];
                        foreach ($listIdSelesai as $subArr) {
                            if (is_array($subArr)) {
                                foreach ($subArr as $v)
                                    $flatCompleted[] = $v;
                            } else {
                                $flatCompleted[] = $subArr;
                            }
                        }

                        // Add in-progress items to status list
                        foreach ($flatInProgress as $id) {
                            $statusList[] = "#" . $id . " - Dalam Pengerjaan";
                        }

                        // Add completed items to status list
                        foreach ($flatCompleted as $id) {
                            $statusList[] = "#" . $id . " - Selesai";
                        }

                        $statusText = implode("\n", $statusList);
                        $text = "*" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n" . $list_link;
                        $res = $waService->sendFreeText($waNumber, $text);
                        if ($res['success']) {
                            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                        }
                    } else {
                        // Jika semua selesai, tetap tampilkan list dengan format yang sama
                        $statusList = [];
                        foreach ($allIdPenjualan as $id) {
                            $statusList[] = "#" . $id . " - Selesai";
                        }
                        $statusText = implode("\n", $statusList);
                        $text = "*" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n" . $list_link;
                        $res = $waService->sendFreeText($waNumber, $text);
                        if ($res['success']) {
                            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                        }
                    }
                }
            }
        }
    }

    function handleJam_operasional($phoneIn, $waNumber, $textBody = '', $forceKonfirmasiIntro = false)
    {
        $t = strtolower(trim($textBody ?? ''));
        $konfirmasiIntro = null;
        $konfirmasiPattern1 = '/\b(masih|msh|mash|masi|msih)\s*(bisa|bs|bis|b\s*s)(?:\s*(terima|trima|nerima|antar|masukin|masuk)\s*(kain|baju|laundry|cuci|gosok|setrika|strika)?\s*(aja|aj)?)?\s*\??\s*$/i';
        $konfirmasiPattern2 = '/\b(masih|msh|mash|masi|msih)\s*(nerima|terima|trima).*(gosok|setrika|strika)\s*(aja|aj)?/i';
        if ($forceKonfirmasiIntro || preg_match($konfirmasiPattern1, $t) || preg_match($konfirmasiPattern2, $t)) {
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
        } else {
            // Jam tutup: jawaban baku saja (tanpa konfirmasi intro)
            $this->handleJam_tutup($phoneIn, $waNumber, $textBody, null);
        }
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

        // Jika "setiap hari" dan ada libur 10 hari ke depan, tambahkan pengecualian
        $upcomingHolidays = '';
        try {
            $upcomingHolidays = $this->getUpcomingHolidaysMessage($config);
            if ($upcomingHolidays !== '' && $daysStr === 'setiap hari') {
                $daysStr = 'setiap hari kecuali pada hari libur khusus';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Check if today is a holiday
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $currentDate = $now->format('Y-m-d');
        $isHoliday = in_array($currentDate, $config['holidays']);

        // Prefix untuk holiday
        $holidayPrefix = "";
        if ($isHoliday) {
            $holidayPrefixes = [
                "Mohon maaf, hari ini kami libur. ",
            ];
            $holidayPrefix = $holidayPrefixes[array_rand($holidayPrefixes)];
        }

        // Jam operasional di depan, tebal di WhatsApp
        $timeBold = "*{$openTime} s.d. {$closeTime}*";
        $variations = [
            "Jam operasional {$timeBold}, {$daysStr}. 🕐😊",
            "Kami buka jam {$timeBold} ({$daysStr}). ⏰🙏",
            "Buka jam {$timeBold}, {$daysStr}. 📍😊",
            "Operasional jam {$timeBold}, {$daysStr}. 😊🙏",
            "Madinah Laundry buka jam {$timeBold}, {$daysStr}. 👍😊",
            "Jam buka {$timeBold}, {$daysStr}. 😊👋"
        ];

        $textBaku = $holidayPrefix . $variations[array_rand($variations)];
        if ($upcomingHolidays !== '') {
            $textBaku .= $upcomingHolidays;
        }

        // Custom intro (konfirmasi ke petugas) atau AI intro HANYA jika user menyapa dulu (sapaan + tanya jam)
        if (!empty($customIntro)) {
            $textBaku = $customIntro . "\n\n" . $textBaku;
        } elseif ($this->hasGreetingInMessage($textBody) && !$this->isBesokOrLiburDateQuestion($textBody) && ($isQuestion = $this->isJamOperasionalQuestion($textBody)) && class_exists('\\App\\Config\\AI') && \App\Config\AI::isEnabled()) {
            $ctx = $this->getGreetingContext($waNumber);
            $contactName = $ctx['contactName'];
            $sapaan = $ctx['sapaan'];
            try {
                $messages = [
                    ['role' => 'system', 'content' => "Kamu customer service Madinah Laundry. Customer bertanya 'masih buka?' / 'buka ga?' (bukan 'masih bisa terima kain').\n\nTugas: buat SATU kalimat pembuka singkat (max 8 kata) yang menjawab 'masih buka', diakhiri koma.\n\nWAJIB: Gunakan HANYA sapaan yang diberikan. Format: \"Masih buka {sapaan},\" atau \"Iya {sapaan}, masih buka,\"\n\nContoh jika sapaan=kak: \"Masih buka kak,\" atau \"Iya kak, masih buka,\"\nContoh jika sapaan=bang: \"Masih buka bang,\" atau \"Iya bang, masih buka,\"\nContoh jika sapaan=bu: \"Masih buka bu,\" atau \"Iya bu, masih buka,\"\n\nPENTING: Pakai PERSIS sapaan yang diberikan. Hanya output kalimat pembuka, diakhiri koma. JANGAN tambah info jam. JANGAN pakai tanda seru (!). JANGAN sebut nama customer."],
                    ['role' => 'user', 'content' => "Sapaan yang WAJIB dipakai: {$sapaan}\nNama customer: \"{$contactName}\"\nPesan customer: \"{$textBody}\"\n\nBalasan baku yang mengikuti: \"{$textBaku}\"\n\nBuat HANYA kalimat pembuka singkat (diakhiri koma). Gunakan sapaan: {$sapaan}"],
                ];
                $intro = trim($this->executeOpenAIRequestWithMessages($messages, 50));
                if (!empty($intro) && mb_strlen($intro) > 2) {
                    $intro = preg_replace('/!+$/', '', $intro);
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

    function handleJam_tutup($phoneIn, $waNumber, $textBody = '', $customIntro = null)
    {
        try {
            $config = require __DIR__ . '/../Config/OperatingHours.php';
        } catch (\Throwable $e) {
            $this->sendAutoreplyText($waNumber, "Mohon maaf, kami sedang tutup. Buka setiap hari pukul *07.00 s.d. 21.00*. Terima kasih 🙏");
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

        // Jika "setiap hari" dan ada libur 10 hari ke depan, tambahkan pengecualian
        $upcomingHolidays = '';
        try {
            $upcomingHolidays = $this->getUpcomingHolidaysMessage($config);
            if ($upcomingHolidays !== '' && $daysStr === 'setiap hari') {
                $daysStr = 'setiap hari kecuali pada hari libur khusus';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Cek apakah hari ini libur (untuk pesan khusus)
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $currentDate = $now->format('Y-m-d');
        $isHoliday = in_array($currentDate, $config['holidays']);

        // Jam operasional di depan, tebal di WhatsApp
        $timeBold = "*{$openTime} s.d. {$closeTime}*";
        if ($isHoliday) {
            $variations = [
                "Mohon maaf, hari ini kami libur. Jam operasional {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, hari ini kami tutup (libur khusus). Buka jam {$timeBold}, {$daysStr}. 😊",
                "Maaf, hari ini kami libur. Jam buka {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, hari ini kami libur. Buka jam {$timeBold}, {$daysStr}. 😊"
            ];
        } else {
            $variations = [
                "Mohon maaf, kami sedang tutup. Jam operasional {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, kami di luar jam operasional. Buka jam {$timeBold}, {$daysStr}. 😊",
                "Maaf, saat ini kami sedang tutup. Jam buka {$timeBold}, {$daysStr}. 🙏",
                "Mohon maaf, kami tutup. Buka jam {$timeBold}, {$daysStr}. 😊"
            ];
        }

        $text = $variations[array_rand($variations)];
        if ($upcomingHolidays !== '') {
            $text .= $upcomingHolidays;
        }
        if (!empty($customIntro)) {
            $text = $customIntro . "\n\n" . $text;
        }
        $this->sendAutoreplyText($waNumber, $text);
    }

    function handleReminder($phoneIn, $waNumber, $textBody = '')
    {
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
                $waService->sendFreeText($waNumber, $text);
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
                $waService->sendFreeText($waNumber, $text);
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

                    $ops_link = "https://api.nalju.com/R/" . $d['id'];
                    $text = "*" . $d['name'] . "*" . $note . "\n" . $text_count . "\n" . $ops_link;

                    $reminders[] = $text;
                }
            }

            // Send all reminders to the requesting user
            if (!empty($reminders)) {
                $combined_text = implode("\n\n", $reminders);
                $res = $waService->sendFreeText($waNumber, $combined_text);
            } else {
                // No reminders found
                $text = "Tidak ada reminder yang ditemukan untuk nomor Anda.";
                $res = $waService->sendFreeText($waNumber, $text);
            }
        } catch (\Exception $e) {
            \Log::write("handleReminder ERROR: " . $e->getMessage(), 'wa_error', 'Reminder');
            // Still try to send error message to user
            try {
                $waService = $this->getWaService();
                $waService->sendFreeText($waNumber, "Maaf, terjadi kesalahan saat mengambil data reminder.");
            } catch (\Exception $e2) {
                // Ignore
            }
        }
    }

    function handleKas_laundry($phoneIn, $waNumber, $textBody = '')
    {
        try {
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }

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
                $waService->sendFreeText($waNumber, $text);
            } else {
                $waService->sendFreeText($waNumber, "Semua kas cabang di bawah Rp1.000.000");
            }
        } catch (\Throwable $e) {
            \Log::write("handleKas_laundry ERROR: " . $e->getMessage(), 'wa_error', 'Kas');
        }
    }

    /**
     * Handle intent KARYAWAN - cari data karyawan dari tabel user (en=1) db(1).
     * Hanya bisa diakses ADMIN_NUMBERS.
     * Alur: regex exact match nama_user → jika tidak ketemu, AI fuzzy match → jika gagal, balas maaf.
     */
    function handleKaryawan($phoneIn, $waNumber, $textBody = '')
    {
        $hp = \Env::ADMIN_NUMBERS;
        $phones = array_map(function ($p) { return trim($p, "' "); }, explode(',', $phoneIn));
        $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanWaNumber, 2);
        $phones[] = $phone0;
        $phones[] = $cleanWaNumber;
        $phones = array_unique(array_filter($phones));
        $intersect = array_intersect($phones, $hp);
        if (empty($intersect)) {
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
                        if (strcasecmp(trim($u['nama_user'] ?? ''), $answer) === 0) {
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
            $waService->sendFreeText($waNumber, "Maaf, data karyawan tidak ditemukan.");
            return;
        }

        $text = $this->formatKaryawanReply($found, $db0);
        $waService->sendFreeText($waNumber, $text);
    }

    /**
     * Format data karyawan untuk balasan WA (no_user, nama_user, bank_code, bank_account_number, bank_account_name)
     */
    private function formatKaryawanReply($row, $db0)
    {
        $no_user = $row['no_user'] ?? '';
        $nama_user = $row['nama_user'] ?? '';
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
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }
            
            // Admin dengan ID user spesifik - langsung ambil data user by ID, tidak pakai nomor
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
                    $waService->sendFreeText($waNumber, $msg);
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
                    $sendErrorToWa("Maaf, nomor tidak terdaftar sebagai karyawan Madinah Laundry.");
                    return;
                }
                $user = $users[0];
                $id_cabang = $user['id_cabang'] ?? 0;
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query user failed - " . $e->getMessage() . " | SQL: SELECT id_user, nama_user, id_cabang FROM user WHERE no_user IN ($phoneInStr) LIMIT 1", 'wa_error', 'SlipGaji');
                throw $e;
            }
            
            if (!$user || !isset($user['id_user'])) {
                $sendErrorToWa("Maaf, nomor tidak terdaftar sebagai karyawan Madinah Laundry.");
                return;
            }
            
            $id_user = (int)$user['id_user'];
            $nama_user = $user['nama_user'] ?? 'Karyawan';
            
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
                $waService->sendFreeText($waNumber, "Belum ada data untuk periode " . $date . ".\nSilakan tunggu penetapan gaji.");
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
            $res = $waService->sendFreeText($waNumber, $text);
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
        $waService = null;
        $sendErrorToWa = function ($msg) use (&$waService, $waNumber) {
            try {
                if (!$waService) {
                    $waService = $this->getWaService();
                }
                if ($waService) {
                    $waService->sendFreeText($waNumber, $msg);
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
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }

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
                $waService->sendFreeText($waNumber, "Tidak ada data gaji cash untuk periode " . $period);
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
                $namaUser = $user['nama_user'] ?? 'Unknown';

                $count++;
                $total += $amount;

                $text .= $simState . " *" . strtoupper($namaUser) . "* #" . $employeeId . "\n";
                $text .= "Rp" . number_format($amount, 0, ',', '.') . "\n\n";
            }

            $text .= "────────────────\n";
            $text .= "*Total Cash:* Rp" . number_format($total, 0, ',', '.') . "\n";
            $text .= "*Jumlah:* " . $count . " orang";

            $res = $waService->sendFreeText($waNumber, $text);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }

        } catch (\Throwable $e) {
            \Log::write("handleGaji_cash ERROR: " . $e->getMessage(), 'wa_error', 'GajiCash');
            $sendErrorToWa("Maaf, terjadi kesalahan saat mengambil data gaji cash.\nSilakan hubungi admin.");
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
            $waService->sendFreeText($waNumber, "Bisnis tidak ditemukan.");
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
                $waService->sendFreeText($waNumber, "Data token untuk $bisnis tidak ditemukan.");
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
            $waService->sendFreeText($waNumber, $text);
        } else {
            $waService->sendFreeText($waNumber, "Nomor Anda tidak terdaftar di sistem $bisnis.");
        }
        
        } catch (\Throwable $e) {
            \Log::write("handleCek_token ERROR: " . $e->getMessage(), 'wa_error', 'Token');
            $waService->sendFreeText($waNumber, "Terjadi kesalahan sistem.");
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
            $waService->sendFreeText($waNumber, "Bisnis tidak ditemukan.");
            return;
        }

        $no_user = $db->query("SELECT no_user FROM user WHERE no_user IN ($phoneIn)")->row_array()['no_user'] ?? null;

        if ($no_user) {
            $db0 = DB::getInstance(0);

            // Get prepaid_list - TODO: $pre_id perlu didefinisikan (dari parameter atau parsing message)
            $pre_list = $db0->query(
                "SELECT * FROM prepaid_list WHERE pre_id = $pre_id AND bisnis = '$bisnis'"
            )->row_array();

            if (!$pre_list) {
                $waService->sendFreeText($waNumber, "Token id: $pre_id tidak ditemukan.");
                return;
            }

            $id_cabang = $pre_list['id_cabang'];
            $product_code = $pre_list['product_code'];
            $customer_id_prepaid = $pre_list['customer_id'];
            $akan_dipakai = $pre_list['nominal'];
            $limit = $pre_list['monthly_limit'];

            // Get usage this month (pengganti helper('Pre')->bulan_ini)
            $pakai_result = $db0->query(
                "SELECT SUM(price) as total FROM prepaid WHERE product_code = '$product_code' AND MONTH(insertTime) = MONTH(NOW()) AND YEAR(insertTime) = YEAR(NOW()) AND tr_status = 1"
            )->row_array();
            $pakai_bulan_ini = $pakai_result['total'] ?? 0;
            $total_pakai = $akan_dipakai + $pakai_bulan_ini;

            if ($total_pakai > $limit) {
                $waService->sendFreeText($waNumber, "GAGAL - SUDAH MENCAPAI LIMIT BULANAN");
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

                    $tr_status = $d['status'] ?? ($a['tr_status'] ?? 0);
                    $price = $d['price'] ?? ($a['price'] ?? 0);
                    $message = $d['message'] ?? ($a['message'] ?? '');
                    $balance = $d['balance'] ?? ($a['balance'] ?? 0);
                    $tr_id = $d['tr_id'] ?? ($a['tr_id'] ?? 0);
                    $rc = $d['rc'] ?? ($a['rc'] ?? '');
                    $sn = $d['sn'] ?? ($a['sn'] ?? '');

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

            $waService->sendFreeText($waNumber, $text);
        } else {
            $waService->sendFreeText($waNumber, "Nomor anda tidak terdaftar sebagai karyawan.");
        }
    }

    function handleSaldo_iak($phoneIn, $waNumber, $textBody = '')
    {
        try {
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }

            // Cek saldo IAK
            $iak = new \App\Models\IAK();
            $response = $iak->check_balance();

            $waService = $this->getWaService();

            if (isset($response['data']['balance'])) {
                $balance = $response['data']['balance'];
                $text = number_format($balance, 0, ',', '.');
            } else {
                $message = $response['data']['message'] ?? 'Unknown error';
                $text = "Gagal: " . $message;
            }

            $waService->sendFreeText($waNumber, $text);

        } catch (\Throwable $e) {
            \Log::write("handleSaldo_iak ERROR: " . $e->getMessage(), 'wa_error', 'IAK');
            $waService = $this->getWaService();
            $waService->sendFreeText($waNumber, "Error: " . $e->getMessage());
        }
    }

    function handleSaldo_tokopay($phoneIn, $waNumber, $textBody = '')
    {
        try {
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }

            // Cek saldo TokoPay menggunakan endpoint QRIS
            $apiUrl = 'https://api.nalju.com/QRIS/balance';
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
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

            $waService = $this->getWaService();

            if ($curlError) {
                $text = "Error: Gagal menghubungi API QRIS. " . $curlError;
                $waService->sendFreeText($waNumber, $text);
                return;
            }

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['status']) && $data['status'] === true) {
                // Handle complex structure with available and held balance
                if (isset($data['data']['data']['saldo_tersedia'])) {
                    $d = $data['data']['data'];
                    $text = "Tersedia: " . number_format($d['saldo_tersedia'], 0, ',', '.') . "\n";
                    $text .= "Tertahan: " . number_format($d['saldo_tertahan'] ?? 0, 0, ',', '.');
                } else {
                    // Fallback to simpler balance structures
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
                        // If still not found, show minimal info or raw for debugging
                        $text = "Saldo TokoPay: Data tidak ditemukan.\n" . json_encode($data);
                    }
                }
            } else {
                $message = $data['message'] ?? ($data['data']['message'] ?? 'Unknown error');
                $text = "Gagal mengambil saldo TokoPay: " . $message;
            }

            $waService->sendFreeText($waNumber, $text);

        } catch (\Throwable $e) {
            \Log::write("handleSaldo_tokopay ERROR: " . $e->getMessage(), 'wa_error', 'Tokopay');
            $waService = $this->getWaService();
            $waService->sendFreeText($waNumber, "Error: " . $e->getMessage());
        }
    }

     function handleTarik_tokopay($phoneIn, $waNumber, $textBody = '')
    {
        try {
            $hp = \Env::ADMIN_NUMBERS;

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                return;
            }

            $waService = $this->getWaService();
            
            // Extract amount from text body
            // Format expected: "tarik tokopay 50000" or "wd tokopay 50000"
            $parts = preg_split('/\s+/', $textBody);
            $amount = isset($parts[2]) ? intval($parts[2]) : 0;
            
            // Validate amount
            if ($amount < 10000) {
                $text = "Gagal: Minimal penarikan Rp 10.000";
                $waService->sendFreeText($waNumber, $text);
                return;
            }

            // Call QRIS withdraw endpoint
            $apiUrl = 'https://api.nalju.com/QRIS/withdraw';
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
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
                $waService->sendFreeText($waNumber, $text);
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

            $waService->sendFreeText($waNumber, $replyText);

        } catch (\Throwable $e) {
            \Log::write("handleTarik_saldo_tokopay ERROR: " . $e->getMessage(), 'wa_error', 'Tokopay');
            $waService = $this->getWaService();
            $waService->sendFreeText($waNumber, "Error: " . $e->getMessage());
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
        $url = 'https://waserver.nalju.com/incoming';



        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Increased from 2 to 5 seconds
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // DNS resolution timeout
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1); // Prevent signals causing timeouts
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);

        // Ignore errors silently to prevent blocking auto-reply

        curl_close($ch);
        return $result;
    }

    private function handleWithAI($phoneIn, $textBody, $waNumber, $keywordConfig = null)
    {
        try {
            // Check if AI Config class exists
            if (!class_exists('\\App\\Config\\AI')) {
                $configFile = __DIR__ . '/../Config/AI.php';
                if (!file_exists($configFile)) {
                    return false;
                }
                require_once $configFile;
            }

            // Check if AI is enabled
            if (!\App\Config\AI::isEnabled()) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        try {
            // Use provided keywordConfig (already filtered) or load full config
            // Jika keywordConfig tidak diberikan, load full config (backward compatibility)
            if ($keywordConfig === null) {
                $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';
            }

            // Prepare AI prompt for intent classification
            $prompt = "Kamu adalah AI classifier untuk WhatsApp bot laundry. Klasifikasikan pesan berikut ke dalam SATU kategori saja:\n";
            $prompt .= "Kategori:\n";

            // Build categories dynamically from config
            foreach ($keywordConfig as $category => $config) {
                if (isset($config['ai_prompt'])) {
                    $prompt .= "- {$category}: {$config['ai_prompt']}\n";
                }
            }

            $prompt .= "- FALSE: Tidak termasuk kategori di atas\n";
            $prompt .= "Pesan: \"{$textBody}\"\n";
            $prompt .= "JAWAB HANYA DENGAN FORMAT JSON SEPERTI INI:\n";
            $prompt .= "{\"intent\": \"NAMA_KATEGORI\", \"reason\": \"Alasan singkat memilih kategori ini\"}\n";
            $prompt .= "Kategori harus salah satu dari daftar di atas atau FALSE.";

            // Call OpenAI API
            $response = $this->callOpenAI($prompt);

            // Parse JSON Response
            $json = json_decode($response, true);

            // Handle markdown code blocks if AI adds them
            if (!$json) {
                $cleanMatches = [];
                if (preg_match('/\{.*\}/s', $response, $cleanMatches)) {
                    $json = json_decode($cleanMatches[0], true);
                }
            }

            $intent = $json['intent'] ?? 'FALSE';
            $reason = $json['reason'] ?? '';

            $intent = trim(strtoupper($intent));

            // Log: text | intent | reason
            if (class_exists('\Log')) {
                \Log::write("{$textBody} | {$intent} | {$reason}", 'wa', 'intent');
            }

            // Check if this is a valid intent from config
            if (isset($keywordConfig[$intent])) {
                // Return intent (case will be taken from config in process())
                // Ensure returning ARRAY as expected by process()
                return [
                    'intent' => $intent,
                    'reason' => $reason
                ];
            }

            // Intent not in config, return false
            return false;
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("AI ERROR: " . $e->getMessage(), 'wa_error', 'AI');
            }
            return false;
        }
    }

    private function callOpenAI($prompt)
    {
        // Load AI config
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../Config/AI.php';
        }

        $model = 'gpt-4o-mini';

        try {
            return $this->executeOpenAIRequest($prompt, $model);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function executeOpenAIRequest($prompt, $model)
    {
        // Prioritize getOpenAIApiKey if exists, otherwise fallback to getApiKey
        $apiKey = (method_exists('\\App\\Config\\AI', 'getOpenAIApiKey')) ? \App\Config\AI::getOpenAIApiKey() : ((method_exists('\\App\\Config\\AI', 'getApiKey')) ? \App\Config\AI::getApiKey() : '');

        $temperature = \App\Config\AI::getTemperature();
        $timeout = \App\Config\AI::getTimeout();

        // OpenAI API URL
        $url = 'https://api.openai.com/v1/chat/completions';

        // Prepare request body for OpenAI
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => $temperature,
            'max_completion_tokens' => 50, // Limit output for efficiency
        ];

        // cURL request
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($result === false) {
            throw new \Exception("OpenAI API cURL error: {$curlError}");
        }

        // Check HTTP status
        if ($httpCode !== 200) {
            $errorMsg = "OpenAI API error: HTTP {$httpCode}";
            if ($result) {
                $errorData = json_decode($result, true);
                if (isset($errorData['error']['message'])) {
                    $errorMsg .= " - " . $errorData['error']['message'];
                }
            }
            throw new \Exception($errorMsg);
        }

        // Parse response
        $response = json_decode($result, true);

        // Extract text from OpenAI response structure
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }

        throw new \Exception("OpenAI API: Invalid response structure");
    }

    /**
     * Call OpenAI with messages array (system + user) and custom max_tokens
     * @param array $messages [['role'=>'system','content'=>...], ['role'=>'user','content'=>...]]
     * @param int $maxTokens
     * @return string
     */
    private function executeOpenAIRequestWithMessages($messages, $maxTokens = 400)
    {
        if (!class_exists('\\App\\Config\\AI')) {
            require_once __DIR__ . '/../Config/AI.php';
        }
        $apiKey = (method_exists('\\App\\Config\\AI', 'getOpenAIApiKey')) ? \App\Config\AI::getOpenAIApiKey() : ((method_exists('\\App\\Config\\AI', 'getApiKey')) ? \App\Config\AI::getApiKey() : '');
        $temperature = \App\Config\AI::getTemperature();
        $timeout = \App\Config\AI::getTimeout();
        $model = 'gpt-4o-mini';

        $data = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \Exception("OpenAI API cURL error: {$curlError}");
        }
        if ($httpCode !== 200) {
            $errorMsg = "OpenAI API error: HTTP {$httpCode}";
            if ($result) {
                $errorData = json_decode($result, true);
                if (isset($errorData['error']['message'])) {
                    $errorMsg .= " - " . $errorData['error']['message'];
                }
            }
            throw new \Exception($errorMsg);
        }

        $response = json_decode($result, true);
        if (isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        throw new \Exception("OpenAI API: Invalid response structure");
    }
    
    /**
     * Get or create conversation with case management
     * Moved from Webhook controller for better architecture
     */
    private function getOrCreateConversationWithCase($db, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $cust_id = null, $lastMessage = null, $case = null)
    {
        // Try to find existing conversation
        $existing = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        
        if ($existing->num_rows() > 0) {
            $conv = $existing->row();           
            $updateData = [
                'contact_name' => $contactName,
                'assigned_user_id' => $assigned_user_id,
                'code' => $code,
                'cust_id' => $cust_id,
                'status' => 'open',
                'last_in_at' => date('Y-m-d H:i:s'),
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'last_message' => $lastMessage,
            ];
            
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

            $db->update('wa_conversations', $updateData, ['wa_number' => $waNumber]);
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
