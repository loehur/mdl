<?php

namespace App\Models;

use App\Core\DB;

class WAReplies
{
    private $waService = null;
    private $noRegisterText = 'Mohon Maaf, nomor Anda belum terdaftar di Madinah Laundry. Terima kasih';

    /**
     * Get WhatsApp Service instance (lazy loading)
     */
    private function getWaService()
    {
        if ($this->waService === null) {
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../Helpers/WhatsAppService.php';
            }
            $this->waService = new \App\Helpers\WhatsAppService();
        }
        return $this->waService;
    }
    /**
     * Check if auto-reply should be sent (rate limiting / cooldown)
     * @param string $waNumber Phone number
     * @param string $handler Handler name (bon, status, buka, etc)
     * @param int $cooldownMinutes Cooldown period in minutes (default: 10)
     * @return bool True if can send reply
     */
    private function shouldReply($waNumber, $handler, $cooldownMinutes = 3)
    {
        $db = DB::getInstance(0);

        // Query last auto-reply for this number + handler
        $sql = "SELECT created_at FROM wa_auto_reply_log 
                WHERE phone = ? AND handler = ? 
                ORDER BY created_at DESC LIMIT 1";

        $result = $db->query($sql, [$waNumber, $handler]);

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
            'handler' => $handler
        ])->row();

        if ($existing) {
            // Update created_at jika record sudah ada
            $db->update(
                'wa_auto_reply_log',
                ['created_at' => date('Y-m-d H:i:s')],
                ['phone' => $waNumber, 'handler' => $handler]
            );
        } else {
            // Insert baru jika belum ada
            $db->insert('wa_auto_reply_log', [
                'phone' => $waNumber,
                'handler' => $handler,
                'created_at' => date('Y-m-d H:i:s')
            ]);
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
    public function process($phoneIn, $textBody, $waNumber)
    {
        $textBodyToCheck = strtolower(trim($textBody ?? ''));
        $messageLength = mb_strlen($textBodyToCheck);

        // Load keyword configuration
        $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';
        $matchPatterns = [];
        // Check each handler's patterns
        foreach ($keywordConfig as $handler => $config) {
            $maxLength = $config['max_length'] ?? 0;
            $patterns = $config['patterns'] ?? [];

            // Skip if message is longer than max_length (0 = unlimited)
            if ($maxLength > 0 && $messageLength > $maxLength) {
                continue;
            }

            // Check regex patterns
            foreach ($patterns as $patternIndex => $pattern) {
                if (preg_match($pattern, $textBodyToCheck)) {
                    // Get case from config, default to null (don't update) if not set or explicitly null
                    if (isset($config['case'])) {
                        $caseVal = $config['case'];
                    } else {
                        \Log::write("Case not set for $handler", 'ai_intent', 'error');
                        $caseVal = 4;
                    }

                    // Check if auto_reply is enabled for this handler
                    $autoReply = $config['auto_reply'] ?? false;

                    // If auto_reply is false, skip handler but still return priority
                    if ($autoReply) {
                        // RATE LIMITING: Check if can send reply (cooldown)
                        if (!$this->shouldReply($waNumber, $handler)) {
                            $matchPatterns[] = $handler;
                            continue 2; // Skip to next handler (this handler is in cooldown)
                        }

                        // Dynamically call handler method
                        $handlerName = ucwords(strtolower($handler), '_');
                        $methodName = 'handle' . $handlerName;

                        if (method_exists($this, $methodName)) {
                            $this->$methodName($phoneIn, $waNumber, $textBody);

                            return (object) [
                                'case' => $caseVal
                            ];
                        }
                    }
                }
            }
        }

        if ($messageLength >= 0 && $messageLength <= 7) {
            return (object) [
                'case' => null
            ];
        }

        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber);

        // Check if AI successfully detected a valid intent (not FALSE and not boolean false)
        // Check if AI successfully detected a valid intent (array with intent key)
        if ($aiResult && is_array($aiResult) && isset($aiResult['intent']) && strtoupper($aiResult['intent']) !== 'FALSE') {
            $aiIntent = strtoupper($aiResult['intent']);

            // Rate limiting check (using matchedPatterns logic logic from original code if needed, but simplifying here)
            // Original code checked if intent was in $matchPatterns (which were skipped due to rate limit)
            if (in_array($aiIntent, $matchPatterns)) {
                return (object) [
                    'case' => null
                ];
            }

            // AI successfully detected intent, get case from config
            // Get case from config, respecting null values (null = don't update case)
            if (isset($keywordConfig[$aiIntent]) && array_key_exists('case', $keywordConfig[$aiIntent])) {
                $aiCase = $keywordConfig[$aiIntent]['case'];
                // \Log::write("DEBUG MAPPING SUCCESS: Intent='$aiIntent' -> Case=$aiCase", 'wa_case_debug');
            } else {
                // If intent found but configuration missing, fallback to 4
                \Log::write("DEBUG MAPPING FAILED: Intent='$aiIntent' not found in config or no case key. Keys available: " . implode(',', array_keys($keywordConfig)), 'wa_case_debug', 'error');
                $aiCase = 4;
            }

            return (object) [
                'case' => $aiCase
            ];
        }

        // AI failed or returned FALSE (unknown intent) - needs manual attention
        return (object) [
            'case' => 4
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
                    \Log::write("FAILED to update DB for Notif #$idNotif (Error: " . $db1->conn()->error . ")", 'wa_replies', 'PendingNotifs');
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
                $res = $waService->sendFreeText($waNumber, $this->noRegisterText);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $this->noRegisterText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
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

                                    // Try to get last query if available in wrapper
                                    $lastQuery = method_exists($db1, 'last_query') ? $db1->last_query() : 'N/A';
                                    \Log::write("Insert Data: " . json_encode($insertData), 'webhook', 'WhatsApp');
                                }
                            } else {
                                \Log::write("⚠️ API response has no text | Response: " . $apiResponse, 'wa_nota_debug');
                            }
                        } else {
                            \Log::write("❌ API call failed | Ref: $ref", 'wa_nota_debug');
                        }
                    }
                } else {
                    // All notifs already exist - they were sent before
                    $list_link = "";
                    // Remove duplicates - same customer may have multiple transactions
                    $unique_pelanggans_active = array_unique($id_pelanggans_active);
                    foreach ($unique_pelanggans_active as $id_pelanggan_active) {
                        $list_link .= "https://ml.nalju.com/I/i/" . $id_pelanggan_active . "\n";
                    }

                    $text = "Yth. *" . $nama_pelanggan . "*,\nNota/Bon sudah kami kirimkan sebelumnya. Terima kasih 😊\n" . $list_link;
                    $res = $waService->sendFreeText($waNumber, $text);
                    if ($res['success']) {
                        $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                    }
                }
            } else {
                $text = "Yth. *" . $nama_pelanggan . "*, belum ada Nota/Bon. Terima kasih 😊";
                $res = $waService->sendFreeText($waNumber, $text);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
            }
        }
    }

    private function handleStatus($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        $db1 = DB::getInstance(1);
        $limitTime = date('Y-m-d H:i:s', strtotime('-48 hours'));

        $sql = "SELECT * FROM notif 
                WHERE tipe = 2 AND state = 'pending' 
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
                    \Log::write("FAILED to update DB for Notif #$idNotif (Error: " . $db1->conn()->error . ")", 'wa_replies', 'PendingNotifs');
                }

                // Broadcast to WebSocket with future timestamp
                if ($res['success']) {
                    // Add 1 second to ensure auto-reply appears after customer message
                    $timestamp = date('Y-m-d H:i:s', strtotime('+1 second'));
                    $payload = $this->buildWsPayload($waNumber, $notif['text'], $msgId, $wamid, $timestamp);
                    $this->pushToWebSocket($payload);
                }
            }
        } else {
            $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanPhone, 2);

            $where = "nomor_pelanggan IN ($phoneIn)";
            $pelanggan = $db1->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();
            $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
            $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
            $nama_pelanggan = strtoupper($nama_pelanggans[0] ?? ''); // fix index 0 if empty

            if (empty($id_pelanggans)) {
                $res = $waService->sendFreeText($waNumber, $this->noRegisterText);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $this->noRegisterText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
            } else {
                $ids_in = implode(',', $id_pelanggans);
                $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
                $noRefs = array_column($sales, 'no_ref');
                if (empty($noRefs)) {
                    $text = 'Yth. *' . $nama_pelanggan . '*, belum ada transaksi terbuka. Terima kasih';
                    $res = $waService->sendFreeText($waNumber, $text);
                    if ($res['success']) {
                        $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                    }
                } else {
                    $listIdPenjualan = []; // Items still in progress (belum ada notif selesai)
                    $listIdSelesai = [];   // Items already completed (sudah ada notif selesai)
                    foreach ($noRefs as $noRef) {
                        $get_penjualan = $db1->query("SELECT id_penjualan, id_pelanggan FROM sale WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND no_ref = '$noRef'")->result_array();
                        $id_penjualans = array_column($get_penjualan, 'id_penjualan');
                        $id_pelanggans = array_column($get_penjualan, 'id_pelanggan');

                        // Fix for VARCHAR IDs: Quote them
                        $quotedIds = array_map(function ($id) {
                            return "'$id'";
                        }, $id_penjualans);
                        $id_penjualans_in = implode(',', $quotedIds);

                        // Get id_penjualan that already have notif tipe 2
                        $existingNotifIds = !empty($id_penjualans) ? array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($id_penjualans_in)")->result_array(), 'no_ref') : [];
                        // Items still in progress (belum ada notif)
                        $sisaIDPenjualan = array_diff($id_penjualans, $existingNotifIds);
                        if (count($sisaIDPenjualan) > 0) {
                            array_push($listIdPenjualan, $sisaIDPenjualan);
                        }

                        // Items already completed (sudah ada notif)
                        if (count($existingNotifIds) > 0) {
                            array_push($listIdSelesai, $existingNotifIds);
                        }
                    }

                    $list_link = "";
                    // Remove duplicates - same customer may have multiple transactions
                    $unique_pelanggans = array_unique($id_pelanggans);
                    foreach ($unique_pelanggans as $id_pelanggan) {
                        $list_link .= "https://ml.nalju.com/I/i/" . $id_pelanggan . "\n";
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
                        $text = "Yth. *" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n" . $list_link;
                        $res = $waService->sendFreeText($waNumber, $text);
                        if ($res['success']) {
                            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                        }
                    } else {
                        $text = "Yth. *" . $nama_pelanggan . "*, Status Laundry sudah selesai. Terima kasih\n" . $list_link;
                        $res = $waService->sendFreeText($waNumber, $text);
                        if ($res['success']) {
                            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                        }
                    }
                }
            }
        }
    }

    function handleJam_operasional($phoneIn, $waNumber, $textBody = '')
    {
        // Cek apakah sedang buka atau tutup
        if ($this->isOperatingHours()) {
            // Sedang buka, kasih tahu jam operasional
            $this->handleJam_buka($phoneIn, $waNumber);
        } else {
            // Sedang tutup, kasih tahu bahwa sudah tutup
            $this->handleJam_tutup($phoneIn, $waNumber);
        }
    }

    function handleJam_buka($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        // Load operating hours config untuk dynamic response
        $config = require __DIR__ . '/../Config/OperatingHours.php';
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

        $variations = [
            "Madinah Laundry buka {$daysStr}, dari pukul {$openTime} - {$closeTime}. 🕐\nAda yang bisa kami bantu? 😊",
            "Kami buka {$daysStr} pukul {$openTime} - {$closeTime}. ⏰\nAda yang ingin ditanyakan? 🙏",
            "Jam operasional: {$openTime} - {$closeTime} ({$daysStr}) 📍\nAda yang bisa dibantu? 😊",
            "Buka {$daysStr} jam {$openTime} sampai {$closeTime} ya! 😊\nSilakan, ada yang perlu dibantu? 🙏",
            "Kami melayani dari jam {$openTime} sampai {$closeTime} 🕐\nAda yang bisa kami bantu hari ini? 😊",
            "Operasional {$daysStr} pukul {$openTime} - {$closeTime} 😊\nSilakan, ada yang ditanyakan? 👋",
            "Buka {$daysStr}, jam {$openTime} sampai {$closeTime} 👍\nAda yang bisa dibantu? 😊"
        ];

        $text = $holidayPrefix . $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handleJam_tutup($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();

        // Load operating hours config untuk dynamic response
        $config = require __DIR__ . '/../Config/OperatingHours.php';
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

        $variations = [
            "Mohon maaf, kami sedang tutup. Kami buka {$daysStr} pukul {$openTime}-{$closeTime}. Silakan tinggalkan pesan, nanti akan kami balas saat jam kerja. Terima kasih 🙏",
            "Mohon Maaf, kami sedang di luar jam operasional. Kami buka {$daysStr} jam {$openTime}-{$closeTime}. Tinggalkan pesan saja ya, nanti kami respon saat buka 😊",
            "Halo! Saat ini kami sudah tutup. Jam buka kami: {$daysStr} {$openTime}-{$closeTime}. Silakan tinggalkan pesan, kami akan membalas saat jam kerja. Terima kasih 🙏",
            "Mohon maaf, kami di luar jam operasional. Buka lagi: {$daysStr} pukul {$openTime}-{$closeTime}. Silakan tinggalkan pesan Anda, nanti akan kami balas saat jam kerja. Terima kasih 😊"
        ];

        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
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

            \Log::write("handleReminder - phones: " . json_encode($phones), 'reminder_debug');

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
                \Log::write("handleReminder - no conditions, returning", 'reminder_debug');
                $text = "Tidak ada reminder yang ditemukan.";
                $waService->sendFreeText($waNumber, $text);
                return;
            }

            $whereClause = implode(' OR ', $conditions);
            $sql = "SELECT * FROM reminder WHERE $whereClause";

            \Log::write("handleReminder - SQL: $sql", 'reminder_debug');

            try {
                $queryResult = DB::getInstance(0)->query($sql);
                $data = $queryResult ? $queryResult->result_array() : [];
            } catch (\Throwable $qe) {
                \Log::write("handleReminder - Query ERROR: " . $qe->getMessage(), 'reminder_debug', 'error');
                $text = "Tidak ada reminder yang ditemukan.";
                $waService->sendFreeText($waNumber, $text);
                return;
            }

            \Log::write("handleReminder - data count: " . count($data), 'reminder_debug');

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

                    $ops_link = "https://api.nalju.com/Reminder/r/" . $d['id'];
                    $text = "*" . $d['name'] . "*" . $note . "\n" . $text_count . "\n" . $ops_link;

                    $reminders[] = $text;
                }
            }

            \Log::write("handleReminder - reminders count: " . count($reminders), 'reminder_debug');

            // Send all reminders to the requesting user
            if (!empty($reminders)) {
                $combined_text = implode("\n\n", $reminders);
                $res = $waService->sendFreeText($waNumber, $combined_text);
                \Log::write("handleReminder - sent reminders, result: " . json_encode($res), 'reminder_debug');
            } else {
                // No reminders found
                $text = "Tidak ada reminder yang ditemukan untuk nomor Anda.";
                $res = $waService->sendFreeText($waNumber, $text);
                \Log::write("handleReminder - sent no-reminder msg, result: " . json_encode($res), 'reminder_debug');
            }
        } catch (\Exception $e) {
            \Log::write("handleReminder ERROR: " . $e->getMessage(), 'reminder_debug', 'error');
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
            $hp = ['081268098300', '085278114125'];

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            \Log::write("handleKas_laundry - phones: " . json_encode($phones), 'kas_debug');

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                \Log::write("handleKas_laundry - NOT authorized", 'kas_debug');
                return;
            }

            $db1 = DB::getInstance(1);
            $cabangs = $db1->query("SELECT * FROM cabang")->result_array();

            \Log::write("handleKas_laundry - cabang count: " . count($cabangs), 'kas_debug');

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

            \Log::write("handleKas_laundry - text: " . $text, 'kas_debug');

            $waService = $this->getWaService();
            if (strlen($text) > 0) {
                $waService->sendFreeText($waNumber, $text);
            } else {
                $waService->sendFreeText($waNumber, "Semua kas cabang di bawah Rp1.000.000");
            }
        } catch (\Throwable $e) {
            \Log::write("handleKas_laundry ERROR: " . $e->getMessage(), 'kas_debug', 'error');
        }
    }

    function handleCek_token($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        
        try {
            \Log::write("handleCek_token START - textBody: $textBody", 'token_debug');
            
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

            \Log::write("handleCek_token - bisnis: $bisnis, DB connected", 'token_debug');

            $user = $db->query("SELECT id_cabang, id_privilege FROM user WHERE no_user IN ($phoneIn)")->row_array();
            $id_cabang = $user['id_cabang'] ?? null;
            $id_privilege = $user['id_privilege'] ?? null;

            \Log::write("handleCek_token - bisnis: $bisnis, phoneIn: $phoneIn, id_cabang: $id_cabang, id_privilege: $id_privilege", 'token_debug');

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

            \Log::write("handleCek_token - pre_list count: " . count($pre_list), 'token_debug');

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
            \Log::write("handleCek_token - user not found in $bisnis DB for phone: $phoneIn", 'token_debug');
            $waService->sendFreeText($waNumber, "Nomor Anda tidak terdaftar di sistem $bisnis.");
        }
        
        } catch (\Throwable $e) {
            \Log::write("handleCek_token ERROR: " . $e->getMessage(), 'token_debug', 'error');
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
            $hp = ['081268098300', '085278114125'];

            // Parse phone numbers and check authorization
            $phones = array_map(function ($p) {
                return trim($p, "' ");
            }, explode(',', $phoneIn));
            $cleanWaNumber = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanWaNumber, 2);
            $phones[] = $phone0;
            $phones[] = $cleanWaNumber;
            $phones = array_unique(array_filter($phones));

            \Log::write("handleSaldo_iak - phones: " . json_encode($phones), 'iak_debug');

            // Only allowed phones can access this
            $intersect = array_intersect($phones, $hp);
            if (empty($intersect)) {
                \Log::write("handleSaldo_iak - NOT authorized", 'iak_debug');
                return;
            }

            // Cek saldo IAK
            $iak = new \App\Models\IAK();
            $response = $iak->check_balance();

            \Log::write("handleSaldo_iak - response: " . json_encode($response), 'iak_debug');

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
            \Log::write("handleSaldo_iak ERROR: " . $e->getMessage(), 'iak_debug', 'error');
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

    private function handleWithAI($phoneIn, $textBody, $waNumber)
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
            // Load keyword configuration to get ai_prompt for each category
            $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';

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
                \Log::write("{$textBody} | {$intent} | {$reason}", 'ai', 'intent');
            }

            // Rate limiting moved inside auto_reply check
            // if (!$this->shouldReply($waNumber, $intent)) { return false; }

            // Check if this is a valid intent from config
            if (isset($keywordConfig[$intent])) {
                $config = $keywordConfig[$intent];
                $autoReply = $config['auto_reply'] ?? false;

                // Only call handler if auto_reply is enabled
                if ($autoReply) {
                    // Check rate limiting only if we are going to reply
                    if ($this->shouldReply($waNumber, $intent)) {
                        $handlerName = ucwords(strtolower($intent), '_');
                        $methodName = 'handle' . $handlerName;

                        if (method_exists($this, $methodName)) {
                            $this->$methodName($phoneIn, $waNumber, $textBody);
                        }
                    }
                }

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
                \Log::write("AI ERROR: " . $e->getMessage(), 'ai', 'error');
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
}
