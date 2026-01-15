<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

class Chat extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    public function getConversations()
    {
        // DEBUG: Force show errors
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        try {
            $db = $this->db(0);
            
            // Auto-close expired conversations (CSW timeout > 22 hours)
            // Rule: If last message was > 22 hours ago, close the session
            $sqlClose = "UPDATE wa_conversations 
                         SET status = 'closed' WHERE status = 'open' 
                         AND last_in_at < (NOW() - INTERVAL 23 HOUR)";
            $db->query($sqlClose);
            
            // Auto-delete old messages (older than 30 days)
            $sqlDeleteIn = "DELETE FROM wa_messages_in WHERE created_at < (NOW() - INTERVAL 30 DAY)";
            $db->query($sqlDeleteIn);
            
            $sqlDeleteOut = "DELETE FROM wa_messages_out WHERE created_at < (NOW() - INTERVAL 30 DAY)";
            $db->query($sqlDeleteOut);

            // Fetch conversations
            // status can be 'open', 'closed', etc.
            // We want 'open' generally, or maybe all active
            // Modified to include kode_cabang from database using local column 'code'
            
            $userId = $_GET['user_id'] ?? null;
            $whereClause = "c.updated_at >= (NOW() - INTERVAL 30 DAY)";
            
            // Get user role from database (case-insensitive like Auth.php)
            $isAdmin = false;
            $isDriver = false;
            
            if ($userId) {
                $userRecord = $db
                    ->where('LOWER(username)', strtolower($userId))
                    ->get('crm_users')
                    ->row();
                    
                if ($userRecord) {
                    $role = strtolower($userRecord->role ?? 'crew');
                    $isAdmin = ($role === 'admin');
                    $isDriver = ($role === 'driver');
                }
            }
            
            if ($userId && !$isAdmin) {
               if ($isDriver) {
                   // Handle Legacy Int, Single JSON Object, and JSON Array List
                   // Structure: [{"case": 2, "status": "open", ...}, ...]
                   // Only show Case 2 that is OPEN (not closed)
                   // Must match both case:2 AND status:open in the same JSON object
                   $whereClause .= " AND (
                        (c.conv_case LIKE '%\"case\":2%' AND c.conv_case LIKE '%\"status\":\"open\"%')
                        OR (c.conv_case LIKE '%\"case\":\"2\"%' AND c.conv_case LIKE '%\"status\":\"open\"%')
                   )";

               } else {
                   // Crew Role: Filter by assigned_user_id
                   // For numeric IDs, use intval for safety
                   if (is_numeric($userId)) {
                       $whereClause .= " AND c.assigned_user_id = " . intval($userId);
                   } else {
                       // For string IDs, use proper escaping
                       // Use underlying connection for escaping since DB wrapper has no escape()
                       $safeId = $db->conn()->real_escape_string($userId);
                       $whereClause .= " AND c.assigned_user_id = '$safeId'";
                   }
               }
            }
            
            $sql = "
                SELECT 
                    c.id, 
                    c.wa_number, 
                    c.contact_name, 
                    c.status,
                    c.conv_case,
                    (
                        SELECT COUNT(*) 
                        FROM wa_messages_in m 
                        WHERE m.phone = c.wa_number 
                        AND (m.status != 'read' OR m.status IS NULL)
                    ) as unread_count,
                    c.last_message as last_message,
                    c.last_message_at as last_message_time,
                    c.assigned_user_id,
                    COALESCE(c.code, '00') as kode_cabang
                FROM wa_conversations c
                WHERE $whereClause
                ORDER BY c.last_message_at DESC
            ";
    
            $query = $db->query($sql);
            
            if (!$query) {
                 // DB Error checking
                throw new \Exception("Database Query Failed: " . $db->conn()->error);
            }

            $conversations = $query->result();
            
            // Normalize Case (Handle JSON Array List)
            foreach ($conversations as &$conv) {
                // Default values
                $conv->case_val = 0;
                $conv->case_status = null;
                $conv->case_history = []; // New field for full history

                // Check if 'conv_case' column exists and has content
                if (isset($conv->conv_case)) {
                    // If JSON
                    if (is_string($conv->conv_case) && (strpos(trim($conv->conv_case), '{') === 0 || strpos(trim($conv->conv_case), '[') === 0)) {
                        $jsonP = json_decode($conv->conv_case, true);
                        
                        if (is_array($jsonP)) {
                            // Check if List (Array of Objects)
                            if (isset($jsonP[0])) {
                                // It's a list, take the LAST item as current active case
                                $lastItem = end($jsonP);
                                $conv->case_val = (int)($lastItem['case'] ?? 0);
                                $conv->case_status = $lastItem['status'] ?? null;
                                $conv->case_history = $jsonP;
                            } elseif (isset($jsonP['case'])) {
                                // Single Object (Legacy JSON)
                                $conv->case_val = (int)$jsonP['case'];
                                $conv->case_status = $jsonP['status'] ?? null;
                                $conv->case_history = [$jsonP];
                            }
                        }
                    } elseif (is_numeric($conv->conv_case)) {
                        // Legacy integer in 'conv_case' column
                        $conv->case_val = (int)$conv->conv_case;
                    }
                }
            }
            
            // Check what we actually got
            if (empty($conversations)) {
                $this->success([], 'Query executed but returned 0 rows. Check if table wa_conversations is empty.');
            }

            // Return ALL data without filter to see what is happening using success method
            // This will show up in your browser network tab -> Response
            $this->success($conversations, 'OK');

        } catch (\Throwable $e) {
            // Log to file for easier checking if console is hard
            \Log::write("[Error] " . $e->getMessage(), 'cms', 'chat');

            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'PHP Error in Chat Controller',
                'error' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function getMessages()
    {
        $phone = $this->query('phone');
        if (!$phone) $this->error('Phone required');

        $db = $this->db(0);

        // Normalize input phone to digits only
        $normPhone = preg_replace('/[^0-9]/', '', $phone);
        // Use last 10 digits for matching (covers local and international formats)
        $matchDigits = substr($normPhone, -10);

        $sql = "
            SELECT * FROM (
                SELECT * FROM (
                    (SELECT 
                        id,
                        wamid,
                        text,
                        type,
                        'customer' as sender,
                        created_at as time,
                        status,
                        media_id,
                        media_url,
                        media_caption as caption,
                        quoted_message_id,
                        NULL as sender_code
                     FROM wa_messages_in 
                     WHERE RIGHT(REPLACE(REPLACE(phone, '+', ''), '-', ''), 10) = ?)
                    UNION ALL
                    (SELECT 
                        id,
                        wamid,
                        COALESCE(content, '') as text,
                        type,
                        'me' as sender,
                        created_at as time,
                        status,
                        NULL as media_id,
                        media_url,
                        NULL as caption,
                        quoted_message_id,
                        sender_code
                     FROM wa_messages_out 
                     WHERE RIGHT(REPLACE(REPLACE(phone, '+', ''), '-', ''), 10) = ?)
                ) AS combined_msgs
                ORDER BY time DESC
                LIMIT 50
            ) AS latest_msgs
            ORDER BY time ASC
        ";
        
        $messages = $db->query($sql, [$matchDigits, $matchDigits])->result();
        
        $this->success($messages);
    }



    public function reply()
    {
        $body = $this->getBody();
        $phone = $body['phone'] ?? null;
        $message = $body['message'] ?? null;
        $replyTo = $body['reply_to'] ?? null; // WAMID of message being replied to

        if (!$phone || !$message) $this->error('Missing required fields (phone, message)');

        $db = $this->db(0);
        
        // 1. Get Conversation Info (using phone)
        $conv = $db->get_where('wa_conversations', ['wa_number' => $phone])->row();
        // We do not strict check here if conv exists, as we are sending to phone directly. 
        // But for broadcast 'contact_name', it is useful.

        // 2. Send Message using Helper
        if (!class_exists('\App\Helpers\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
        }
        
        $wa = new \App\Helpers\WhatsAppService();
        
        // Get sender_code: 1. From request body, 2. From database by user_id, 3. From session
        $senderCode = $body['sender_code'] ?? null;
        
        if (!$senderCode && isset($body['user_id'])) {
            // Lookup code from crm_users directly
            $userId = $body['user_id'];
            $userRecord = $db
                ->where('LOWER(username)', strtolower($userId))
                ->get('crm_users')
                ->row();
            
            if ($userRecord && !empty($userRecord->code)) {
                $senderCode = $userRecord->code;
            }
        }
        
        // Fallback to session code
        if (!$senderCode && isset($_SESSION['mdl_crm_session']['user']['code'])) {
            $senderCode = $_SESSION['mdl_crm_session']['user']['code'];
        }

        $res = $wa->sendFreeText($phone, $message, $replyTo, $senderCode); // Pass senderCode

        if ($res['success']) {
            // Update quoted_message_id in wa_messages_out if reply_to provided
            if ($replyTo && isset($res['local_id'])) {
                $db->update('wa_messages_out', [
                    'quoted_message_id' => $replyTo
                ], ['id' => $res['local_id']]);
            }
            
            // Update conversation last_message using wa_number
            $db->update('wa_conversations', [
                'last_message' => $message,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['wa_number' => $phone]);

            $data = $res['data'];
            $data['local_id'] = $res['local_id'] ?? null; // Attach local DB ID
            
            // *** BROADCAST TO ALL AGENTS via WebSocket ***
            $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
            
            $broadcastPayload = [
                'type' => 'agent_message_sent',
                'phone' => $phone, // PRIMARY IDENTIFIER
                'conversation_id' => $conv->id ?? 0, // Optional legacy
                'target_id' => '0', // Broadcast to ALL agents
                'sender_id' => $userId, 
                'message' => [
                    'id' => $data['local_id'] ?? time(),
                    'wamid' => $data['id'] ?? $data['wamid'] ?? null,
                    'text' => $message,
                    'type' => 'text',
                    'sender' => 'me',
                    'time' => date('Y-m-d H:i:s'),
                    'status' => 'sent',
                    'quoted_message_id' => $replyTo // Include quoted message reference
                ],
                'contact_name' => $conv->contact_name ?? '',
                'phone' => $phone
            ];
            
            $this->pushToWebSocket($broadcastPayload);
            
            $this->success($data, 'Reply sent');
        } else {
            $this->error('Failed to send WhatsApp: ' . ($res['error'] ?? 'Unknown error'), 500);
        }
    }
    public function markRead()
    {
       try {
        $body = json_decode(file_get_contents('php://input'), true);
        $phone = $body['phone'] ?? null;
        
        if (!$phone) {
             $phone = $this->query('phone');
        }
        
        if(!$phone) $this->error('Phone required');
        
        $db = $this->db(0);
        
        // 1. Get WAMIDs for API Sync
        $unreads = $db->query("SELECT wamid FROM wa_messages_in WHERE phone = ? AND (status != 'read' OR status IS NULL) AND wamid IS NOT NULL", [$phone])->result_array();
        
        // 2. Direct Query Update ALL messages
        $db->query("UPDATE wa_messages_in SET status = 'read' WHERE phone = ?", [$phone]);
        
        // ALWAYS Push WS to sync status (Broadcast to ALL via target_id='0')
        $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
        
        $payload = [
            'type' => 'conversation_read',
            'phone' => $phone,
            'target_id' => '0', // Node.js server will broadcast to ALL if target='0'
            'sender_id' => $userId, // Exclude sender from broadcast
            'unread_count' => 0
        ];
        
        $this->pushToWebSocket($payload);
        
        if (empty($unreads)) {
            $this->success([], 'No unread messages (Local updated)');
        }
        
        if (!class_exists('\App\Helpers\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
        }
        $wa = new \App\Helpers\WhatsAppService();
        
        foreach ($unreads as $msg) {
            $wa->markAsRead($msg['wamid']);
        }
        
        $this->success(['count' => count($unreads)], 'Marked as read');

       } catch (\Throwable $e) {
            // Manual fail safe response
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            exit;
       }
    }
    
    public function markAsDone()
    {
        try {
            $this->handleCors();
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            // Get case from body, default to 0 if not provided
            $caseVal = $body['case'] ?? 0;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            
            $db = $this->db(0);
            
            // Update case (Overwrite history for manual action)
            $jsonCase = json_encode([[
                'case' => (int)$caseVal,
                'status' => 'done'
            ]]);
            
            // NOTE: Also close conversation
            $updated = $db->update('wa_conversations', 
                [
                    'conv_case' => $jsonCase,
                    'status' => 'closed'
                ], 
                ['wa_number' => $phone]
            );
            
            if ($updated) {
                // Push WebSocket to update all clients
                $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
                
                $payload = [
                    'type' => 'case_updated',
                    'phone' => $phone,
                    'case' => (int)$caseVal,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId
                ];
                
                
                \Log::write("Pushing case update to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['case' => (int)$caseVal], 'Conversation marked as done');
            } else {
                $this->error('Failed to update case');
            }
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage()
            ]);
            exit;
        }
    }

    /**
     * Unified Case Update Endpoint
     * Client must send 'case' value from body
     */
    public function updateCase()
    {
        try {
            $this->handleCors();
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            $caseVal = $body['case'] ?? null;
            $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
            
            if (!$phone) $this->error('Phone required');
            if ($caseVal === null) $this->error('Case value (case) required');
            
            // Ignore Case 0
            if ((int)$caseVal === 0) {
                 $this->success([], 'Case 0 ignored');
                 return;
            }
            
            $db = $this->db(0);
            
            // 1. Fetch existing cases first to support multi-case (append logic)
            $existing = $db->query("SELECT conv_case FROM wa_conversations WHERE wa_number = ?", [$phone])->row();
            $caseList = [];
            
            if ($existing && isset($existing->conv_case)) {
                $raw = $existing->conv_case;
                // Parse existing JSON
                if (is_string($raw) && (strpos(trim($raw), '[') === 0)) {
                    $caseList = json_decode($raw, true) ?? [];
                } elseif (is_numeric($raw)) {
                     // Legacy support: convert single int to array item
                     $caseList[] = ['case' => (int)$raw, 'status' => 'open'];
                }
            }

            // Always close Case 4 (Follow Up) when updating cases (instead of removing, keep history)
            foreach ($caseList as &$c) {
                if (isset($c['case']) && (int)$c['case'] === 4 && ($c['status'] ?? '') !== 'closed') {
                    $c['status'] = 'closed';
                }
            }
            unset($c);
            
            // 2. Add or Update the requested case
            $newCaseVal = (int)$caseVal;
            
            // Check if there are other open cases (for Case 4 logic)
            $hasOtherOpenCases = false;
            foreach ($caseList as $c) {
                if (isset($c['case']) && (int)$c['case'] !== 4 && ($c['status'] ?? '') === 'open') {
                    $hasOtherOpenCases = true;
                    break;
                }
            }
            
            // NEW RULE: If trying to add Case 4 but other cases are open, SKIP
            if ($newCaseVal === 4 && $hasOtherOpenCases) {
                $this->success(['case' => null], 'Other cases are open - Case 4 not needed');
            }
            
            $found = false;
            
            foreach ($caseList as &$item) {
                if (isset($item['case']) && (int)$item['case'] === $newCaseVal) {
                    // Case already exists, refresh timestamp/status
                    $item['status'] = 'open';
                    $item['status'] = 'open';
                    // Timestamp removed as per request
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // Append new case
                $caseList[] = [
                    'case' => $newCaseVal,
                    'status' => 'open'
                ];
            }

            
            // 3. Save back entire list
            $jsonCase = json_encode($caseList);
            
            $updated = $db->update('wa_conversations', 
                ['conv_case' => $jsonCase], 
                ['wa_number' => $phone]
            );
            
            if ($updated) {
                // Get conversation info for notification
                $conv = $db->get_where('wa_conversations', ['wa_number' => $phone])->row();
                $contactName = $conv->contact_name ?? 'Customer';
                
                // Push WebSocket for general case update
                $payload = [
                    'type' => 'case_updated',
                    'phone' => $phone,
                    'case' => (int)$caseVal,
                    'target_id' => '0',
                    'sender_id' => $userId
                ];
                
                \Log::write("Pushing case update to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                // ⭐ SPECIAL: Push notification to DRIVERS when Case 2 (Pickup/Delivery) is added
                if ((int)$caseVal === 2) {
                    $driverPayload = [
                        'type' => 'driver_pickup_added',
                        'phone' => $phone,
                        'contact_name' => $contactName,
                        'case' => 2,
                        'target_id' => '0', // Broadcast - server will filter to drivers only
                        'message' => "📦 Pickup/Delivery request from $contactName"
                    ];
                    
                    \Log::write("Pushing Case 2 notification to drivers: " . json_encode($driverPayload), 'cms_ws', 'Chat');
                    $this->pushToWebSocket($driverPayload);
                }
                
                $this->success(['case' => (int)$caseVal], 'Case updated');

            } else {
                $this->error('Failed to update case');
            }
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage()
            ]);
            exit;
        }
    }

    
    public function reopenConversation()
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            
            $db = $this->db(0);
            
            // Check if there are other open cases - if so, don't add Case 4
            $existing = $db->query("SELECT conv_case FROM wa_conversations WHERE wa_number = ?", [$phone])->row();
            $hasOtherOpenCases = false;
            
            if ($existing && isset($existing->conv_case)) {
                $caseList = json_decode($existing->conv_case, true) ?? [];
                if (is_array($caseList)) {
                    foreach ($caseList as $c) {
                        if (isset($c['case']) && (int)$c['case'] !== 4 && ($c['status'] ?? '') === 'open') {
                            $hasOtherOpenCases = true;
                            break;
                        }
                    }
                }
            }
            
            // If other cases are open, skip adding Case 4
            if ($hasOtherOpenCases) {
                $this->success(['case' => null], 'Conversation already has open cases - Case 4 not needed');
            }
            
            // Update case to 4 (urgent)
            $caseVal = 4;
            $jsonCase = json_encode([[
                'case' => $caseVal,
                'status' => 'reopened'
            ]]);
            
            $updated = $db->update('wa_conversations', 
                ['conv_case' => $jsonCase], 
                ['wa_number' => $phone]
            );
            
            if ($updated) {
                // Push WebSocket to update all clients
                $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
                
                $payload = [
                    'type' => 'case_updated',
                    'phone' => $phone,
                    'case' => $caseVal,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId
                ];
                
                \Log::write("Pushing case update to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['case' => 4], 'Conversation reopened - needs attention');

            } else {
                $this->error('Failed to update case');
            }
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage()
            ]);
            exit;
        }
    }
    
    public function resolveCase()
    {
        try {
            $this->handleCors();
            
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            $caseVal = $body['case'] ?? null;
            $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            if ($caseVal === null) {
                $this->error('Case value required');
            }
            
            $db = $this->db(0);
            
            // Fetch existing cases (use parameterized query)
            $existing = $db->query("SELECT conv_case FROM wa_conversations WHERE wa_number = ?", [$phone])->row();
            $caseList = [];
            $modified = false;
            
            if ($existing && isset($existing->conv_case)) {
                $raw = $existing->conv_case;
                if (is_string($raw) && (strpos(trim($raw), '[') === 0)) {
                    $caseList = json_decode($raw, true) ?? [];
                } elseif (is_numeric($raw)) {
                     // Legacy
                     $caseList[] = ['case' => (int)$raw, 'status' => 'open'];
                }
            }
            
            $targetCase = (int)$caseVal;
            
            // Find and close target case + ALWAYS close Case 4 (Follow Up)
            foreach ($caseList as &$item) {
                $itemCase = (int)($item['case'] ?? 0);
                
                // Close target case
                if ($itemCase === $targetCase) {
                    $item['status'] = 'closed';
                    if(isset($item['resolved_at'])) unset($item['resolved_at']);
                    if(isset($item['resolved_by'])) unset($item['resolved_by']);
                    if(isset($item['timestamp'])) unset($item['timestamp']);
                    $modified = true;
                }
                
                // ALWAYS close Case 4 (Follow Up) when ANY case is resolved
                if ($itemCase === 4 && ($item['status'] ?? 'open') !== 'closed') {
                    $item['status'] = 'closed';
                    if(isset($item['timestamp'])) unset($item['timestamp']);
                    $modified = true;
                }
            }

            
            if ($modified) {
                $jsonCase = json_encode($caseList);
                $db->update('wa_conversations', 
                    ['conv_case' => $jsonCase], 
                    ['wa_number' => $phone]
                );
                
                // Push WebSocket
                $payload = [
                    'type' => 'case_resolved',
                    'phone' => $phone,
                    'case' => $targetCase,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId
                ];
                
                \Log::write("Pushing case resolved to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['case' => $targetCase], 'Case resolved successfully');
            } else {
                // If not found or already closed, just return success to update UI
                $this->success(['case' => $targetCase], 'Case already resolved or not found');
            }
            
        } catch (\Throwable $e) {
            \Log::write("resolveCase ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine(), 'cms_error', 'Chat');
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage()
            ]);
            exit;
        }
    }

    public function media()
    {
        $id = $this->query('id');
        if (!$id) {
             http_response_code(400); die('ID required');
        }
        
        if (!class_exists('\App\Helpers\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
        }
        $wa = new \App\Helpers\WhatsAppService();
        $media = $wa->retrieveMedia($id);
        
        if (isset($media['data'])) {
            header('Content-Type: ' . $media['mime_type']);
            header('Cache-Control: public, max-age=86400'); // Cache 1 day
            echo $media['data'];
            exit;
        }
        
        http_response_code(404);
        $errMsg = $media['error'] ?? 'Unknown error';
        
        echo "Media Retrieval Failed.\n";
        echo "Error: $errMsg\n";
        
        if (strpos($errMsg, '404') !== false) {
            $prefix = $wa->getApiKeyPrefix();
            echo "\nPossible Causes:\n- API Key ($prefix) does not match the WhatsApp Account that received the image.\n- Media ID expired (> 30 days)\n- Media deleted";
        }
        
        if (isset($media['raw'])) {
            echo "\n\nDebug Raw Response:\n" . json_encode($media['raw'], JSON_PRETTY_PRINT);
        }
    }

    private function pushToWebSocket($data)
    {
        $url = 'https://waserver.nalju.com/incoming';
        
        // Log payload for debugging
        if (class_exists('\Log')) {
            \Log::write("WS Push: " . json_encode($data), 'cms_ws');
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
        
        if (curl_errno($ch) && class_exists('\Log')) {
             \Log::write("WS Curl Error: " . curl_error($ch), 'cms_ws_error');
        }
        
        curl_close($ch);
        return $result;   
    }

    /**
     * Send Image via WhatsApp
     */
    public function sendImage()
    {
        // Log entry point
        if (class_exists('\Log')) {
            \Log::write("sendImage called", 'cms_debug', 'Chat');
            \Log::write("FILES: " . json_encode($_FILES), 'cms_debug', 'Chat');
            \Log::write("POST: " . json_encode($_POST), 'cms_debug', 'Chat');
        }
        
        try {
            // Validate file upload
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                \Log::write("File upload validation failed", 'cms_error', 'Chat');
                $this->error('No image uploaded or upload error');
            }
            
            $body = $_POST;
            $phone = $body['phone'] ?? null;
            $userId = $body['user_id'] ?? null;
            $caption = $body['caption'] ?? '';
            
            if (!$phone) {
                $this->error('Missing phone number');
            }
            
            \Log::write("Getting DB connection", 'cms_debug', 'Chat');
            $db = $this->db(0);
            
            \Log::write("Fetching conversation for phone: $phone", 'cms_debug', 'Chat');
            // Get conversation details
            $conversation = $db->get_where('wa_conversations', ['wa_number' => $phone])->row();
            if (!$conversation) {
                $this->error('Conversation not found');
            }
            
            $waNumber = $phone; // Alias
            
            \Log::write("Starting image upload", 'cms_debug', 'Chat');
            // Upload image to server
            $uploaded = $this->uploadImageFile($_FILES['image']);
            \Log::write("Upload result: " . json_encode($uploaded), 'cms_debug', 'Chat');
            if (!$uploaded['success']) {
                $this->error($uploaded['error']);
            }
            
            $mediaUrl = $uploaded['url'];
            
            // Send via WhatsApp Service
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
            }
            
            \Log::write("Initializing WhatsAppService", 'cms_debug', 'Chat');
            
            try {
                $waService = new \App\Helpers\WhatsAppService();
                $waService = new \App\Helpers\WhatsAppService();
                \Log::write("WhatsAppService instance created successfully", 'cms_debug', 'Chat');
                
                // Get sender_code: 1. From POST body, 2. From database by user_id, 3. From session
                $senderCode = $body['sender_code'] ?? null;
                
                if (!$senderCode && $userId) {
                    // Lookup code from crm_users directly
                    $userRecord = $db
                        ->where('LOWER(username)', strtolower($userId))
                        ->get('crm_users')
                        ->row();
                    
                    if ($userRecord && !empty($userRecord->code)) {
                        $senderCode = $userRecord->code;
                    }
                }
                
                // Fallback to session code
                if (!$senderCode && isset($_SESSION['mdl_crm_session']['user']['code'])) {
                    $senderCode = $_SESSION['mdl_crm_session']['user']['code'];
                }
                
                \Log::write("Sending image to: $waNumber, URL: $mediaUrl, SenderCode: $senderCode", 'cms_debug', 'Chat');
                $result = $waService->sendImage($waNumber, $mediaUrl, $caption, $senderCode);
                \Log::write("WA send result: " . json_encode($result), 'cms_debug', 'Chat');
                
            } catch (\Throwable $e) {
                \Log::write("CRITICAL ERROR calling sendImage: " . $e->getMessage(), 'cms_error', 'Chat');
                \Log::write("Error file: " . $e->getFile() . " line " . $e->getLine(), 'cms_error', 'Chat');
                \Log::write("Stack trace: " . $e->getTraceAsString(), 'cms_error', 'Chat');
                
                $this->error('WhatsApp API error: ' . $e->getMessage(), 500);
            }
            
            if ($result['success']) {
                // Save to database
                $messageData = [
                    'phone' => $waNumber,
                    'type' => 'image',
                    'content' => $caption,
                    'media_url' => $mediaUrl,
                    'message_id' => $result['data']['id'] ?? null,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'status' => 'sent',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // FIX: Use local_id from Service if available to avoid duplicate insert
                $msgId = $result['local_id'] ?? null;
                
                if (!$msgId) {
                    $msgId = $db->insert('wa_messages_out', $messageData);
                } else {
                     // Ensure status is 'sent' (Service sets 'accepted')
                     $db->update('wa_messages_out', ['status' => 'sent'], ['id' => $msgId]);
                }
                
                // Update conversation
                $db->update('wa_conversations', [
                    'last_message' => '📷 Image',
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['wa_number' => $waNumber]);
                
                // Broadcast via WebSocket
                $broadcastPayload = [
                    'type' => 'agent_message_sent',
                    'phone' => $waNumber,
                    'conversation_id' => $conversation->id ?? 0,
                    'target_id' => '0',
                    'sender_id' => $userId,
                    'message' => [
                        'id' => $msgId,
                        'wamid' => $result['data']['wamid'] ?? null,
                        'text' => $caption,
                        'type' => 'image',
                        'media_url' => $mediaUrl,
                        'sender' => 'me',
                        'time' => date('Y-m-d H:i:s'),
                        'status' => 'sent'
                    ]
                ];
                
                $this->pushToWebSocket($broadcastPayload);
                
                $this->success([
                    'local_id' => $msgId,
                    'media_url' => $mediaUrl,
                    'wamid' => $result['data']['wamid'] ?? null
                ], 'Image sent successfully');
            } else {
                $this->error($result['error'] ?? 'Failed to send image', 500);
            }
            
        } catch (\Exception $e) {
            \Log::write("sendImage ERROR: " . $e->getMessage(), 'cms_error', 'Chat');
            $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Upload image file to server
     */
    private function uploadImageFile($file)
    {
        try {
            // Validate size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File too large (max 5MB)'];
            }
            
            // Validate type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid file type'];
            }
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../../../uploads/wa_media/' . date('Y/m/');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_') . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }
            
            // Generate URL
            $url = 'https://api.nalju.com/uploads/wa_media/' . date('Y/m/') . $filename;
            
            return [
                'success' => true,
                'path' => $uploadPath,
                'url' => $url
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }


}
