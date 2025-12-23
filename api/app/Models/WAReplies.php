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
     */
    public function process($phoneIn, $textBody, $waNumber)
    {
        $textBodyToCheck = strtolower(trim($textBody ?? ''));
        $messageLength = mb_strlen($textBodyToCheck); // Use mb_strlen for proper UTF-8 support
        
        // Load keyword configuration
        $keywordConfig = require __DIR__ . '/../Config/AutoReplyKeywords.php';
        
        // Special case: Single character message (e.g., "p", ".", "?") -> treat as sapa (greeting)
        if ($messageLength === 1) {
            if ($this->shouldReply($waNumber, 'sapa')) {
                $this->handleSapa($phoneIn, $waNumber);
                return true;
            }
            return false; // Rate limited
        }
        
        // Check each handler's keywords
        foreach ($keywordConfig as $handler => $config) {
            $maxLength = $config['max_length'] ?? 0;
            $keywords = $config['keywords'] ?? [];
            
            // Skip if message is longer than max_length (0 = unlimited)
            if ($maxLength > 0 && $messageLength > $maxLength) {
                continue;
            }
            
            $cek;

            // Check keywords
            foreach ($keywords as $keyword) {
                if (stripos($textBodyToCheck, $keyword) !== false) {
                    // RATE LIMITING: Check if can send reply (cooldown)
                    if (!$this->shouldReply($waNumber, $handler)) {
                        return false; // Exit to prevent other handlers from triggering
                    }
                    
                    // Dynamically call handler method (e.g., handleBon, handleStatus, handleBuka)
                    $methodName = 'handle' . ucfirst($handler);
                    if (method_exists($this, $methodName)) {
                        $this->$methodName($phoneIn, $waNumber);
                        return true;
                    }
                }
            }
        }

        return false;
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

    private function handleBon($phoneIn, $waNumber)
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

                    $text = "Yth. *" . $nama_pelanggan . "*,\nSemua nota/bon sudah kami kirimkan sebelumnya. Terima kasih\n\n" . $list_link;
                    $res = $waService->sendFreeText($waNumber, $text);
                    if ($res['success']) {
                        $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                    }
                }
            } else {
                $text = 'Yth. *' . $nama_pelanggan . '*, semua transaksi Anda sudah selesai, atau pastikan gunakan nomor yang terdaftar untuk melakukan request nota/bon. Terima kasih';
                $res = $waService->sendFreeText($waNumber, $text);
                if ($res['success']) {
                    $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
                }
            }
        }        
    }

    function handleBuka($phoneIn, $waNumber){
        $waService = $this->getWaService();
        
        $variations = [
            "Madinah Laundry buka setiap hari, dari pukul 07.00 - 21.00. 🕐",
            "Kami buka setiap hari pukul 07.00 - 21.00. ⏰",
            "Jam operasional: 07.00 - 21.00 (setiap hari) 📍",
            "Buka setiap hari jam 7 pagi sampai 9 malam ya! 😊",
            "Kami buka dari jam 7 pagi sampai jam 9 malam 🕐",
            "Operasional setiap hari pukul 07.00 - 21.00 😊",
            "Buka setiap hari, jam 07.00 sampai 21.00 👍",
            "Jam buka: 07.00 - 21.00 (7 hari seminggu) ⏰",
            "Kami melayani setiap hari dari pukul 7 pagi - 9 malam 📍",
            "Buka tiap hari ya kak, jam 07.00 - 21.00 😊",
            "Madinah Laundry buka setiap hari jam 7 pagi sampai 9 malam 🕐",
            "Operasional: 07.00 - 21.00 setiap hari 👌"
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
    }

    function handleSapa($phoneIn, $waNumber){
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
            // Versi dengan terima kasih
            "Baik, terima kasih! 🙏",
            "Siap, terima kasih! 😊",
            "Oke, terima kasih 😊",
            "Okee, terima kasih 😊",
            "Sip, terima kasih! 👍",
            "Siap, terima kasih 🙏",
            "Ok siap, terima kasih 😊",
            "Oke siap, terima kasih! 😊",
            "Siapp, terima kasih 👍",
            "Ok, terima kasih! 😊"
        ];
        
        $text = $variations[array_rand($variations)];
        $res = $waService->sendFreeText($waNumber, $text);
        if ($res['success']) {
            $this->pushToWebSocket($this->buildWsPayload($waNumber, $text, $res['data']['id'] ?? null, $res['data']['wamid'] ?? null));
        }
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
        
        if (class_exists('\\Log')) {
            \Log::write('WS Push (WAReplies): ' . json_encode($data), 'cms_ws');
        }

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
        
        // Log errors but don't block auto-reply execution
        if (curl_errno($ch) && class_exists('\\Log')) {
            \Log::write('WS Curl Error (WAReplies): ' . curl_error($ch), 'cms_ws_error');
        }
        
        curl_close($ch);
        return $result;
    }
}
