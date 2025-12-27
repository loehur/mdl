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
    private function shouldReply($waNumber, $handler, $cooldownMinutes = 5)
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
            $db->update('wa_auto_reply_log', 
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
                    // RATE LIMITING: Check if can send reply (cooldown)
                    if (!$this->shouldReply($waNumber, $handler)) {
                        $matchPatterns[] = $handler;
                        continue 2; // Skip to next handler (this handler is in cooldown)
                    }
                    
                    // Dynamically call handler method
                    $handlerName = ucwords(strtolower($handler), '_');
                    $methodName = 'handle' . $handlerName;
                    
                    if (method_exists($this, $methodName)) {
                        $this->$methodName($phoneIn, $waNumber);
                        
                        // Get priority from config, default to 0 if not set
                        $priority = $config['priority'] ?? 0;
                        
                        return (object) [
                            'status' => null,
                            'ai' => false,
                            'priority' => $priority
                        ];
                    }
                }
            }
        }

        // Ambiguous short messages -> Use AI to classify as PEMBUKA or PENUTUP
        if ($messageLength >= 1 && $messageLength <= 8) {
            // Rate limiting for AI ambiguous check
            if (!$this->shouldReply($waNumber, 'AI_AMBIGUOUS')) {
                // Still in cooldown, don't handle anything (user just got a reply)
                return (object) [
                    'status' => null,
                    'ai' => false,
                    'priority' => null
                ];
            }
            
            // Use AI to determine if it's PEMBUKA, PENUTUP, or EMOTE
            $aiIntent = $this->classifyAmbiguous($phoneIn, $textBody, $waNumber);
            if (strtoupper($aiIntent) !== 'FALSE') { // selain false
                // Get priority from config
                if (in_array($aiIntent, $matchPatterns)) {
                    return (object) [
                        'status' => null,
                        'ai' => false,
                        'priority' => null
                    ];
                }

                $matchPatterns[] = $aiIntent;

                $priority = isset($keywordConfig[$aiIntent]['priority']) 
                    ? $keywordConfig[$aiIntent]['priority'] 
                    : null;
                
                return (object) [
                    'status' => null,
                    'ai' => true,
                    'priority' => $priority
                ];
            }
        }

        if ($messageLength == 0) {
            return (object) [
                'status' => null,
                'ai' => false,
                'priority' => null
            ];
        }
        
        // Rate limiting: Prevent AI from being called too frequently
        if (!$this->shouldReply($waNumber, 'AI_FALLBACK')) {
            // AI is in cooldown, skip - don't update priority
            return (object) [
                'status' => null,
                'ai' => false,
                'priority' => null  // null = don't update priority
            ];
        }
        
        $aiResult = $this->handleWithAI($phoneIn, $textBody, $waNumber);
        
        // Check if AI successfully detected a valid intent (not FALSE)
        if (strtoupper($aiResult) !== 'FALSE') {
            if (in_array($aiResult, $matchPatterns)) {
                return (object) [
                    'status' => null,
                    'ai' => false,
                    'priority' => null
                ];
            }
            // AI successfully detected intent, get priority from config
            $aiIntent = strtoupper($aiResult);
            // Get priority from config, respecting null values (null = don't update priority)
            $aiPriority = isset($keywordConfig[$aiIntent]['priority']) 
                ? $keywordConfig[$aiIntent]['priority']  // Could be null, which is intentional
                : 4;  // Default if intent not in config
            
            return (object) [
                'status' => null,
                'ai' => true,
                'priority' => $aiPriority
            ];
        }

        // AI failed or returned FALSE (unknown intent) - needs manual attention
        return (object) [
            'status' => null,
            'ai' => false,
            'priority' => 4
        ];
    }
    
    private function handleStatus($phoneIn, $waNumber)
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
         }else{
            //cek dulu ada tidak nya nota terbuka
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
                        $quotedIds = array_map(function($id) { return "'$id'"; }, $id_penjualans);
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
                        foreach($listIdPenjualan as $subArr) {
                            if(is_array($subArr)) {
                                foreach($subArr as $v) $flatInProgress[] = $v;
                            } else {
                                $flatInProgress[] = $subArr;
                            }
                        }
                        
                        // Flatten completed items
                        $flatCompleted = [];
                        foreach($listIdSelesai as $subArr) {
                            if(is_array($subArr)) {
                                foreach($subArr as $v) $flatCompleted[] = $v;
                            } else {
                                $flatCompleted[] = $subArr;
                            }
                        }
                        
                        // Add in-progress items to status list
                        foreach($flatInProgress as $id) {
                            $statusList[] = "#" . $id . " - Dalam Pengerjaan";
                        }
                        
                        // Add completed items to status list
                        foreach($flatCompleted as $id) {
                            $statusList[] = "#" . $id . " - Selesai";
                        }
                        
                        $statusText = implode("\n", $statusList);
                        
                        if (count($flatInProgress) > 0) {
                            $text = "Yth. *" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n\nTerima kasih sudah *CEK*.\n" . $list_link;
                        } else {
                            // Adjust message based on number of items
                            $completedMsg = count($flatCompleted) > 1 ? "Semua sudah selesai" : "Laundry sudah selesai";
                            $text = "Yth. *" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n\n" . $completedMsg . ". Terima kasih.\n" . $list_link;
                        }
                        
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

    private function handleNota($phoneIn, $waNumber)
    {
        $waService = $this->getWaService();

        // Use DB(1)
        $db1 = DB::getInstance(1);

        // Derive phon Terima kasihom waNumber (+628... or 628...)
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
            
            // FIX: Check if customer exists BEFORE accessing array
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
                        
                        $apiResponse = @file_get_contents("https://ml.nalju.com/Get/wa_nota/" . urlencode($ref), false, $context);
                        if ($apiResponse) {
                            $responseData = json_decode($apiResponse, true);
                            if (!empty($responseData['text'])) {
                                // Insert Notif
                                $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
                                $insertData = [
                                'id_notif'   => $id_notif,
                                'id_cabang'  => $sales[array_search($ref, $noRefs)]['id_cabang'],
                                'tipe'       => 1,
                                'no_ref'     => $ref,
                                'text'       => $responseData['text'],
                                'phone'      => $phone0,
                                'state'      => 'pending',
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
                        $list_link .= "https://ml.nalju.com/I/i/" . $id_pelanggan_active . "\n";
                    }

                    $text = "Yth. *" . $nama_pelanggan . "*,\nNota/Bon sudah kami kirimkan sebelumnya. Terima kasih 😊\n\n" . $list_link;
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

    /**
     * Handler untuk pertanyaan jam buka/tutup
     * Smart handler: cek jam operasional lalu kasih response yang sesuai
     */
    function handleCek_buka($phoneIn, $waNumber){
        // Cek apakah sedang buka atau tutup
        if ($this->isOperatingHours()) {
            // Sedang buka, kasih tahu jam operasional
            $this->handleJam_buka($phoneIn, $waNumber);
        } else {
            // Sedang tutup, kasih tahu bahwa sudah tutup
            $this->handleJam_tutup($phoneIn, $waNumber);
        }
    }

    /**
     * Handler untuk kasih tahu jam operasional (dipanggil saat buka)
     */
    function handleJam_buka($phoneIn, $waNumber){
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
            "Madinah Laundry buka {$daysStr}, dari pukul {$openTime} - {$closeTime}. 🕐\n\nAda yang bisa kami bantu? 😊",
            "Kami buka {$daysStr} pukul {$openTime} - {$closeTime}. ⏰\n\nAda yang ingin ditanyakan? 🙏",
            "Jam operasional: {$openTime} - {$closeTime} ({$daysStr}) 📍\n\nAda yang bisa dibantu? 😊",
            "Buka {$daysStr} jam {$openTime} sampai {$closeTime} ya! 😊\n\nSilakan, ada yang perlu dibantu? 🙏",
            "Kami melayani dari jam {$openTime} sampai {$closeTime} 🕐\n\nAda yang bisa kami bantu hari ini? 😊",
            "Operasional {$daysStr} pukul {$openTime} - {$closeTime} 😊\n\nSilakan, ada yang ditanyakan? 👋",
            "Buka {$daysStr}, jam {$openTime} sampai {$closeTime} 👍\n\nAda yang bisa dibantu? 😊"
        ];
        
        $text = $holidayPrefix . $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handlePembuka($phoneIn, $waNumber){
        $waService = $this->getWaService();
        
        $variations = [
            "Iya, ada yang bisa saya bantu? 😊",
            "Halo! Ada yang bisa dibantu? 👋",
            "Hai! Silahkan, ada yang ditanyakan? 😊",
            "Ya, ada yang bisa kami bantu? 🙏",
            "Halo! Dengan Madinah Laundry, ada yang bisa dibantu? 😊",
            "Hai! Ada yang bisa kami bantu? 👋",
            "Iya, silahkan 😊",
            "Halo, ada yang ditanyakan? 😊",
            "Hai! Ada yang perlu dibantu? 👋",
            "Ya, silahkan 🙏",
            "Iya kak, ada yang bisa dibantu? 😊",
            "Halo kak! Silahkan 👋",
            "Hai, dengan Madinah Laundry 😊",
            "Ya, ada yang perlu dibantu? 🙏",
            "Selamat datang! Ada yang bisa dibantu? 😊",
            "Dengan Madinah Laundry, silahkan 👋",
            "Halo! Silahkan, ada yang bisa kami bantu? 😊",
            "Iya kak, silahkan 🙏",
            "Hai! Madinah Laundry siap membantu 😊",
            "Ya kak, ada yang ditanyakan? 👋"
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handlePenutup($phoneIn, $waNumber){
        $waService = $this->getWaService();
        
        // Random variations untuk terlihat lebih natural
        $variations = [
            "Baik 👌",
            "Siap! 😊",
            "Oke 😊",
            "Okee 😊",
            "Sip! 👍",
            "Siap 🙏",
            "Ok siap 😊",
            "Oke siap! 😊",
            "Siapp 👍",
            "Ok! 😊",
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handleEmote($phoneIn, $waNumber){
        $waService = $this->getWaService();
        
        // Random emoji-based responses (keep it simple and friendly)
        $variations = [
            "👍",
            "😊",
            "🙏",
            "👌",
            "😁",
            "✨",
            "🎉",
            "❤️",
            "🤗",
            "💪",
            "🙌",
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handleMinta_jemput_antar($phoneIn, $waNumber){
        $waService = $this->getWaService();
        
        // Cek jam operasional - layanan jemput/antar hanya saat jam kerja
        if (!$this->isOperatingHours()) {
            // Diluar jam kerja, kasih tahu tutup dan tidak bisa jemput/antar
            $this->handleJam_tutup($phoneIn, $waNumber);
            return;
        }
        
        // Random variations untuk konfirmasi request jemput/antar
        $variations = [
            "Baik, kami konfirmasi ke abang driver dulu ya. Ditunggu.. 🚗",
            "Siap! kami cek schedule bg driver dulu ya, tunggu sebentar. 😊",
            "Oke, kami hubungi driver dulu ya. Mohon ditunggu sebentar 🙏",
            "Baik, kami cek jadwal abang driver dulu. Ditunggu ya 😊",
            "Siap, kami konfirmasi ke team driver dulu. Tunggu sebentar ya 🚗",
            "Oke, kami cek ketersediaan driver dulu ya. Mohon ditunggu 👍",
            "Baik, kami koordinasi dengan driver dulu. Sebentar ya 😊",
            "Siap! kami tanyakan ke abang driver dulu. Ditunggu 🚗",
            "Oke, kami cek schedule abang driver dulu ya. Tunggu sebentar 🙏",
            "Baik, kami konfirmasi ketersediaan driver dulu. Mohon ditunggu ya 😊",
            "Siap! kami hubungi team driver dulu. Sebentar ya 🚗",
            "Oke, kami cek jadwal driver terdekat dulu. Ditunggu 👍",
            "Baik, kami tanyakan ke driver dulu ya. Tunggu sebentar 😊",
            "Siap! kami koordinasi sama abang driver dulu. Mohon ditunggu 🚗",
            "Oke, kami cek driver yang available dulu ya. Sebentar 🙏",
            "Baik, kami konfirmasi sama driver dulu. Ditunggu ya 😊",
            "Siap! kami hubungi abang driver terdekat dulu. Tunggu ya 🚗",
            "Oke, kami cek schedule team driver dulu. Mohon ditunggu 👍",
            "Baik, kami tanya ke driver area sana dulu ya. Sebentar 😊",
            "Siap! kami koordinasi ke driver dulu. Ditunggu ya 🚗"
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    /**
     * Handler untuk auto-reply diluar jam kerja
     */
    function handleJam_tutup($phoneIn, $waNumber){
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
            "Maaf ya, sedang di luar jam operasional. Kami buka {$daysStr} jam {$openTime}-{$closeTime}. Tinggalkan pesan saja ya, nanti kami respon saat buka 😊",
            "Halo! Saat ini kami sudah tutup. Jam buka kami: {$daysStr} {$openTime}-{$closeTime}. Silakan tinggalkan pesan, kami akan membalas saat jam kerja. Terima kasih 🙏",
            "Mohon maaf, kami di luar jam operasional. Buka lagi: {$daysStr} pukul {$openTime}-{$closeTime}. Silakan tinggalkan pesan Anda, nanti akan kami balas saat jam kerja. Terima kasih 😊"
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    /**
     * Check if current time is within operating hours
     * Operating hours configurable in Config/OperatingHours.php
     * @return bool
     */
    private function isOperatingHours()
    {
        // Load operating hours config
        $config = require __DIR__ . '/../Config/OperatingHours.php';
        
        $now = new \DateTime('now', new \DateTimeZone($config['timezone']));
        $dayOfWeek = (int)$now->format('N'); // 1 (Monday) to 7 (Sunday)
        $currentDate = $now->format('Y-m-d');
        $hour = (int)$now->format('G'); // 0-23
        $minute = (int)$now->format('i'); // 0-59
        
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
     * Build WebSocket payload for broadcasting auto-reply messages
     */
    private function buildWsPayload($waNumber, $text, $msgId = null, $wamid = null, $timestamp = null)
    {
        // Use provided timestamp or add 3 seconds to current time to ensure auto-reply appears AFTER customer message
        $time = $timestamp ?: date('Y-m-d H:i:s', strtotime('+3 seconds'));
        
        return [
            'type'           => 'agent_message_sent',
            'phone'          => $waNumber,
            'conversation_id'=> 0,
            'target_id'      => '0',
            'sender_id'      => 0,
            'message' => [
                'id'     => $msgId,
                'wamid'  => $wamid,
                'text'   => $text,
                'type'   => 'text',
                'sender' => 'me',
                'time'   => $time,
                'status' => 'sent',
            ],
            'contact_name'   => '',
            'phone'          => $waNumber,
        ];
    }
    
    /**
     * Push message to WebSocket server for real-time notifications
     * Made non-blocking to prevent delays in auto-reply execution
     */
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
    
    /**
     * AI-Powered Intent Detection
     * 
     * @param string $phoneIn CSV string of phone numbers
     * @param string $textBody Original message text
     * @param string $waNumber Sender's WhatsApp number
     * @return string|false Intent name if handled successfully, false otherwise
     */
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

            
            // Prepare AI prompt for intent classification
            $prompt = "Kamu adalah AI classifier untuk WhatsApp bot laundry. Klasifikasikan pesan berikut ke dalam SATU kategori saja:\\n\\n";
            $prompt .= "Kategori:\\n";
            $prompt .= "- NOTA: User minta bon/struk/nota/tagihan/bukti pembayaran\\n";
            $prompt .= "- STATUS: User cek status/progress laundry (sudah selesai? bisa diambil? kapan siap?)\\n";
            $prompt .= "- CEK_BUKA: User tanya jam buka/tutup, masih kah buka atau sudah kah tutup laundry/operasional\\n";
            $prompt .= "- MINTA_JEMPUT_ANTAR: User melakukan permintaan jemput/antar laundry (harus ada kata2 permintaan, seperti minta, tolong, bisa, dll)\\n";
            $prompt .= "- PEMBUKA: Salam pembuka (halo, hai, ping, pagi, siang, malam, sore)\\n";
            $prompt .= "- PENUTUP: Penutup percakapan (terima kasih, oke lah, sip lah, iya lah, dll)\\n";
            $prompt .= "- PENUTUP: Hanya memberitahu kalau sudah dibayar, sudah lunas, atau sudah diambil, akan menjemput, akan mengantarkan\\n";
            $prompt .= "- EMOTE: cuma emote dan candaan tawa seperti hehe haha wkwk\\n";
            $prompt .= "- FALSE: Tidak termasuk kategori di atas\\n\\n";
            $prompt .= "Pesan: \"{$textBody}\"\\n\\n";
            $prompt .= "JAWAB HANYA DENGAN NAMA KATEGORI (huruf kapital). Contoh: NOTA";
            
            // Log AI checking input
            if (class_exists('\Log')) {
                \Log::write("AI Checking: " . $textBody, 'ai', 'intent_detection');
            }
            
            // Call OpenAI API
            $response = $this->callOpenAI($prompt);
            $intent = trim(strtoupper($response));
            
            // Log AI response
            if (class_exists('\Log')) {
                \Log::write("AI Response: " . $response, 'ai', 'intent_detection');
            }
            
            // Check rate limiting
            if (!$this->shouldReply($waNumber, $intent)) {
                if (class_exists('\Log')) {
                    \Log::write("AI Rate Limited: Handler '{$intent}' for {$waNumber}", 'ai', 'intent_detection');
                }
                return false;
            }
            
            // Call appropriate handler
            $handlerName = ucwords(strtolower($intent), '_');
            $methodName = 'handle' . $handlerName;
            
            if (method_exists($this, $methodName)) {
                $this->$methodName($phoneIn, $waNumber);
                return $intent;
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Log the exception so we know why AI failed
            if (class_exists('\Log')) {
                \Log::write("AI Error: " . $e->getMessage(), 'ai', 'intent_detection');
            }
            return false;
        }
    }
    
    /**
     * AI-Powered Classification for Ambiguous Short Messages
     * Specifically classifies between PEMBUKA (greeting) or PENUTUP (closing)
     * 
     * @param string $phoneIn CSV string of phone numbers
     * @param string $textBody Original message text
     * @param string $waNumber Sender's WhatsApp number
     * @return string|false 'PEMBUKA' or 'PENUTUP' if classified, false otherwise
     */
    private function classifyAmbiguous($phoneIn, $textBody, $waNumber)
    {
        try {
            // Check if AI Config class exists and is enabled
            if (!class_exists('\\App\\Config\\AI')) {
                $configFile = __DIR__ . '/../Config/AI.php';
                if (!file_exists($configFile)) {
                    return false;
                }
                require_once $configFile;
            }
            
            if (!\App\Config\AI::isEnabled()) {
                return false;
            }
            
        } catch (\Exception $e) {
            return false;
        }
        
        try {
            // Prepare specialized AI prompt for PEMBUKA vs PENUTUP classification
            $prompt = "Kamu adalah AI classifier untuk WhatsApp bot laundry. Klasifikasikan pesan pendek berikut apakah PEMBUKA (greeting/salam awal) atau PENUTUP (closing/penutup percakapan).\\n\\n";
            $prompt .= "Kategori:\\n";
            $prompt .= "- PEMBUKA: Salam pembuka, sapaan awal (contoh: halo, hai, ping, pagi, siang, malam, sore, ka, bang, pak, bu)\\n";
            $prompt .= "- PENUTUP: Penutup percakapan, konfirmasi, terima kasih (contoh: ok, oke, sip, siap, makasih, terima kasih, thanks, sudah, lah, iya, thx)\\n\\n";
            $prompt .= "- EMOTE: cuma emote dan candaan seperti hehe haha wkwk\\n\\n";
            $prompt .= "- FALSE: belum jelas atau tidak termasuk kategori di atas\\n\\n";
            $prompt .= "Pesan: \\\"{$textBody}\\\"\\n\\n";
            $prompt .= "WAJIB JAWAB HANYA SALAH SATU: PEMBUKA, PENUTUP, EMOTE, atau FALSE (huruf kapital).";
            
            // Log AI checking input
            if (class_exists('\Log')) {
                \Log::write("AI Ambiguous Check: " . $textBody, 'ai', 'ambiguous_classification');
            }
            
            // Call OpenAI API
            $response = $this->callOpenAI($prompt);
            $intent = trim(strtoupper($response));
            
            // Log AI response
            if (class_exists('\Log')) {
                \Log::write("AI Ambiguous Response: " . $response, 'ai', 'ambiguous_classification');
            }
            
            // Validate response - must be either PEMBUKA, PENUTUP, or EMOTE
            if ($intent !== 'PEMBUKA' && $intent !== 'PENUTUP' && $intent !== 'EMOTE') {
                if (class_exists('\\Log')) {
                    \Log::write("AI Ambiguous Invalid Response: " . $intent, 'ai', 'ambiguous_classification');
                }
                return false;
            }
            
            // Check rate limiting for the determined intent
            if (!$this->shouldReply($waNumber, $intent)) {
                if (class_exists('\Log')) {
                    \Log::write("AI Ambiguous Rate Limited: '{$intent}' for {$waNumber}", 'ai', 'ambiguous_classification');
                }
                return false;
            }
            
            // Call appropriate handler
            $handlerName = ucwords(strtolower($intent), '_');
            $methodName = 'handle' . $handlerName;
            
            if (method_exists($this, $methodName)) {
                $this->$methodName($phoneIn, $waNumber);
                return $intent;
            }
            
            return false;
            
        } catch (\Exception $e) {
            // Log the exception
            if (class_exists('\Log')) {
                \Log::write("AI Ambiguous Error: " . $e->getMessage(), 'ai', 'ambiguous_classification');
            }
            return false;
        }
    }
    
    /**
     * Call OpenAI API (ChatGPT)
     * 
     * @param string $prompt The prompt to send
     * @return string AI response (intent classification)
     * @throws \Exception On API error
     */
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

    /**
     * Execute the actual request to OpenAI API
     */
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

        // DEBUG: Internal Log
        if (class_exists('\\Log')) {
             \Log::write("Target URL: $url", 'auto_reply', 'ai');
             \Log::write("Model: $model | Timeout: $timeout", 'auto_reply', 'ai');
             \Log::write("cURL Executing...", 'auto_reply', 'ai');
        }
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // LOG: cURL execution result
        if (class_exists('\\Log')) {
            \Log::write("cURL executed - HTTP Code: {$httpCode}, Error: " . ($curlError ?: 'None'), 'auto_reply', 'ai');
            if ($result) {
                // Log shorter preview
                \Log::write("API Response (first 500 chars): " . substr($result, 0, 500), 'auto_reply', 'ai');
            } else {
                \Log::write("❌ API Response is EMPTY/FALSE", 'auto_reply', 'ai');
            }
        }
        
        // Check for cURL errors
        if ($result === false) {
            if (class_exists('\\Log')) {
                \Log::write("❌ OpenAI API cURL error: {$curlError}", 'auto_reply', 'ai');
            }
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
            if (class_exists('\\Log')) {
                \Log::write("❌ {$errorMsg}", 'auto_reply', 'ai');
            }
            throw new \Exception($errorMsg);
        }
        
        // Parse response
        $response = json_decode($result, true);
        
        // LOG: JSON decode result
        if (class_exists('\\Log')) {
            if ($response === null) {
                \Log::write("❌ JSON decode failed - Invalid JSON response", 'auto_reply', 'ai');
            } else {
                \Log::write("✅ JSON decoded successfully", 'auto_reply', 'ai');
            }
        }
        
        // Extract text from OpenAI response structure
        if (isset($response['choices'][0]['message']['content'])) {
            $extractedText = trim($response['choices'][0]['message']['content']);
            if (class_exists('\\Log')) {
                \Log::write("✅ Extracted text from response: '{$extractedText}'", 'auto_reply', 'ai');
            }
            return $extractedText;
        }
        
        // LOG: Invalid structure
        if (class_exists('\\Log')) {
            \Log::write("❌ OpenAI API: Invalid response structure - Response: " . json_encode($response), 'auto_reply', 'ai');
        }
        
        throw new \Exception("OpenAI API: Invalid response structure");
    }
}
