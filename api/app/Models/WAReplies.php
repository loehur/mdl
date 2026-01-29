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
     
     * @param string $waNumber Phone number
     * @param string $handler Handler name (bon, status, buka, etc)
     * @param int $cooldownMinutes Cooldown period in minutes (default: 10)
     * @return bool True if can send reply
     */
    private function shouldHandle($waNumber, $handler, $cooldownMinutes = 3)
    {
        $db = DB::getInstance(0);

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
    public function process($phoneIn, $textBody, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $lastMessage = null)
    {
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
        
        $matchPattern = [];
        // Check each handler's patterns
        foreach ($keywordConfig as $handler => $config) {
            $patterns = $config['patterns'] ?? [];
            // Check regex patterns
            foreach ($patterns as $patternIndex => $pattern) {
                if (preg_match($pattern, $textBodyToCheck)) {
                    // Get case from config
                    $caseVal = $config['case'] ?? null;
                    $notify = $config['notify'] ?? false;
                    $matchPattern[] = $handler;
                    
                    // Unset matched keyword from config to optimize AI detection
                    // AI tidak perlu cek keyword yang sudah match di regex
                    unset($keywordConfig[$handler]);
                    
                    //cek rate limit
                    if (!$this->shouldHandle($waNumber, $handler)) {
                        $conversationId = $this->getOrCreateConversationWithCase(
                            $db, $waNumber, $contactName, $assigned_user_id, $code, $lastMessage, null
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
                        $lastMessage,
                        $caseVal
                    );

                    // Dynamically call handler method (will send auto-reply)
                    $handlerName = ucwords(strtolower($handler), '_');
                    $methodName = 'handle' . $handlerName;

                    if (method_exists($this, $methodName)) {
                        $this->$methodName($phoneIn, $waNumber, $textBody);
                        return (object) [
                            'case' => $caseVal,
                            'notify' => $notify,
                            'conversation_id' => $conversationId
                        ];
                    }
                }
            }
        }

        // Short message (likely not a real query) - still create conversation!
        if ($messageLength >= 0 && $messageLength <= 7) {
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $lastMessage, null
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
                    $db, $waNumber, $contactName, $assigned_user_id, $code, $lastMessage, null
                );
                
                return (object) [
                    'case' => $aiCase,
                    'notify' => $aiNotify,
                    'conversation_id' => $conversationId
                ];
            }
            
            // Rate limit passed - create conversation with AI case
            $conversationId = $this->getOrCreateConversationWithCase(
                $db, $waNumber, $contactName, $assigned_user_id, $code, $lastMessage, $aiCase
            );
            
            // Call handler method
            $handlerName = ucwords(strtolower($aiIntent), '_');
            $methodName = 'handle' . $handlerName;
            if (method_exists($this, $methodName)) {
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
            $db, $waNumber, $contactName, $assigned_user_id, $code, $lastMessage, 4
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
                $text = "Pak/Bu *" . $nama_pelanggan . "*, belum ada Nota/Bon. Terima kasih 😊";
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
            $res = $waService->sendFreeText($waNumber, $this->noRegisterText);
            if ($res['success']) {
                $this->pushToWebSocket($this->buildWsPayload($waNumber, $this->noRegisterText, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
            }
        } else {
            $ids_in = implode(',', $id_pelanggans);
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
            $noRefs = array_column($sales, 'no_ref');
            if (empty($noRefs)) {
                $text = 'Pak/Bu *' . $nama_pelanggan . '*, belum ada Nota/Bon terbuka. Terima kasih';
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
            "Madinah Laundry buka {$daysStr}, dari pukul {$openTime} - {$closeTime}. 🕐😊",
            "Kami buka {$daysStr} pukul {$openTime} - {$closeTime}. ⏰🙏",
            "Jam operasional: {$openTime} - {$closeTime} ({$daysStr}) 📍😊",
            "Buka {$daysStr} jam {$openTime} sampai {$closeTime} ya! 😊🙏",
            "Kami melayani dari jam {$openTime} sampai {$closeTime} 🕐😊",
            "Operasional {$daysStr} pukul {$openTime} - {$closeTime} 😊👋",
            "Buka {$daysStr}, jam {$openTime} sampai {$closeTime} 👍😊"
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

    function handleSlip_gaji($phoneIn, $waNumber, $textBody = '')
    {
        $waService = $this->getWaService();
        
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

            // Cari user di db(1) berdasarkan nomor HP, jika tidak ada coba db(0)
            // db(1) = mdl_laundry (database laundry)
            // db(0) = mdl_main (database central)
            $dbLaundry = DB::getInstance(1); // Database laundry
            $dbMain = DB::getInstance(0); // Database main/central
            
            $user = null;
            $id_cabang = 0;
            
            try {
                // Coba cari di db(1) dulu (laundry database) - ambil juga data bank
                $users = $dbLaundry->query("SELECT id_user, nama_user, id_cabang, bank_code, bank_account_number, bank_account_name FROM user WHERE no_user IN ($phoneInStr) LIMIT 1")->result_array();
                if (!empty($users)) {
                    $user = $users[0];
                    $id_cabang = $user['id_cabang'] ?? 0;
                } else {
                    // Jika tidak ada di db(1), coba cari di db(0) (central database)
                    $users = $dbMain->query("SELECT id_user, nama_user FROM user WHERE no_user IN ($phoneInStr) LIMIT 1")->result_array();
                    if (!empty($users)) {
                        $user = $users[0];
                    }
                }
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query user failed - " . $e->getMessage() . " | SQL: SELECT id_user, nama_user, id_cabang FROM user WHERE no_user IN ($phoneInStr) LIMIT 1", 'wa_error', 'SlipGaji');
                throw $e;
            }
            
            if (!$user || !isset($user['id_user'])) {
                $waService->sendFreeText($waNumber, "Nomor Anda tidak terdaftar sebagai karyawan.");
                return;
            }
            
            $id_user = (int)$user['id_user'];
            $nama_user = $user['nama_user'] ?? 'Karyawan';
            
            // Ambil data rekening bank dari user (db(1))
            $bank_code = trim($user['bank_code'] ?? '');
            $bank_account_number = trim($user['bank_account_number'] ?? '');
            $bank_account_name = trim($user['bank_account_name'] ?? '');
            
            // Cek apakah data rekening lengkap
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

            // Tentukan periode berdasarkan tanggal hari ini
            $hariIni = (int)date('d');
            if ($hariIni >= 1 && $hariIni <= 5) {
                // Jika tanggal 1-5, gunakan bulan lalu
                $date = date('Y-m', strtotime('-1 month'));
            } else {
                // Jika tanggal > 5, gunakan bulan ini
                $date = date('Y-m');
            }
            $dateOn = $date;

            // Query data gaji_result dari db(1) - database laundry (bukan db(0))
            try {
                $gajiQuery = "SELECT * FROM gaji_result WHERE tgl = ? AND id_karyawan = ? ORDER BY tipe ASC";
                $gajiResults = $dbLaundry->query($gajiQuery, [$date, $id_user])->result_array();
            } catch (\Throwable $e) {
                \Log::write("handleSlip_gaji: Query gaji_result failed - " . $e->getMessage() . " | Query: " . $gajiQuery . " | Date: $date | ID User: $id_user", 'wa_error', 'SlipGaji');
                throw $e;
            }

            if (empty($gajiResults)) {
                $waService->sendFreeText($waNumber, "Belum ada data gaji untuk periode " . $date . ".\nSilakan tunggu penetapan gaji.");
                return;
            }

            // Format slip gaji
            $text = "*" . strtoupper($nama_cabang) . " - " . $kode_cabang . "*\n";
            $text .= "*-- SALARY SLIP --*\n";
            $text .= "\n";
            $text .= "*" . strtoupper($nama_user) . "*\n";
            $text .= "Periode: *" . $dateOn . "*\n";
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
            $text .= "Total: Rp" . number_format($totalGaji, 0, ',', '.') . "\n";
            $text .= "Potongan: -Rp" . number_format($totalPot, 0, ',', '.') . "\n";
            $text .= "*Diterima: Rp" . number_format($totalTer, 0, ',', '.')."*\n";
            $text .= "\n";
            
            // Tambahkan informasi rekening pencairan
            $text .= "────────────────\n";
            $text .= "Pencairan:\n";
            if ($rekeningLengkap) {
                // Data rekening lengkap - tampilkan informasi bank
                $text .= "*" . $nama_bank . "* \n";
                $text .= "*" . $bank_account_number . "* \n";
                $text .= "*" . $bank_account_name . "*";
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
            // Log dengan detail lengkap
            \Log::write($errorMsg, 'wa_error', 'SlipGaji');
            
            // Log juga phone number untuk debugging
            \Log::write("handleSlip_gaji: phoneIn=$phoneIn, waNumber=$waNumber", 'wa_error', 'SlipGaji');
            
            try {
                $waService = $this->getWaService();
                $waService->sendFreeText($waNumber, "Maaf, terjadi kesalahan saat mengambil data slip gaji.\nSilakan hubungi admin.");
            } catch (\Throwable $e2) {
                \Log::write("handleSlip_gaji: Failed to send error message - " . $e2->getMessage(), 'wa_error', 'SlipGaji');
            }
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

     function handleTarik_saldo_tokopay($phoneIn, $waNumber, $textBody = '')
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

            // Check Tokopay response format
            // Success: {"status": 1, "rc": 200, "message": "Penarikan berhasil di teruskan ke operator..."}
            // Error: {"status": 0, "rc": 500, "error_msg": "Saldo tidak cukup"}
            
            if (isset($data['status']) && $data['status'] == 1 && isset($data['rc']) && $data['rc'] == 200) {
                // Success response
                $amountFormatted = number_format($amount, 0, ',', '.');
                $message = $data['message'] ?? 'Penarikan berhasil diproses';
                
                $text = "✅ *Penarikan Saldo TokoPay*\n\n";
                $text .= "Nominal: *Rp " . $amountFormatted . "*\n";
                $text .= "Tujuan: *SEABANK*\n\n";
                $text .= $message;
            } else {
                // Error response
                $errorMsg = $data['error_msg'] ?? ($data['message'] ?? 'Terjadi kesalahan');
                $text = "❌ *Gagal Penarikan Saldo*\n\n";
                $text .= $errorMsg;
            }

            $waService->sendFreeText($waNumber, $text);

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
                \Log::write("{$textBody} | {$intent} | {$reason}", 'ai', 'intent');
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
    
    /**
     * Get or create conversation with case management
     * Moved from Webhook controller for better architecture
     */
    private function getOrCreateConversationWithCase($db, $waNumber, $contactName = null, $assigned_user_id = null, $code = null, $lastMessage = null, $case = null)
    {
        // Try to find existing conversation
        $existing = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
        
        if ($existing->num_rows() > 0) {
            $conv = $existing->row();           
            $updateData = [
                'contact_name' => $contactName,
                'assigned_user_id' => $assigned_user_id,
                'code' => $code,
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
