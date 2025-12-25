<?php

namespace App\Controllers\CMS;

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
                         SET status = 'closed', priority = 0
                         WHERE status = 'open' 
                         AND last_in_at < (NOW() - INTERVAL 23 HOUR)";
            $db->query($sqlClose);

            // Fetch conversations
            // status can be 'open', 'closed', etc.
            // We want 'open' generally, or maybe all active
            // Modified to include kode_cabang from database using local column 'code'
            
            $userId = $_GET['user_id'] ?? null;
            $whereClause = "c.updated_at >= (NOW() - INTERVAL 3 DAY)";
            
            // If user is NOT in Admin Range (1000-1010), filter by their ID
            $isAdmin = ($userId >= 1000 && $userId <= 1010);
            
            if ($userId && !$isAdmin) {
               // Use proper escaping if possible, or cast to int if numeric ID
               // Assuming int IDs
               $whereClause .= " AND c.assigned_user_id = " . intval($userId);
            }
            
            $sql = "
                SELECT 
                    c.id, 
                    c.wa_number, 
                    c.contact_name, 
                    c.status,
                    c.priority,
                    (
                        SELECT COUNT(*) 
                        FROM wa_messages_in m 
                        WHERE m.phone = c.wa_number 
                        AND (m.status != 'read' OR m.status IS NULL)
                    ) as unread_count,
                    c.last_message as last_message,
                    c.updated_at as last_message_time,
                    c.assigned_user_id,
                    COALESCE(c.code, '00') as kode_cabang
                FROM wa_conversations c
                WHERE $whereClause
                ORDER BY c.priority DESC, c.updated_at DESC
            ";
    
            $query = $db->query($sql);
            
            if (!$query) {
                 // DB Error in query preparing or something unknown
                 // Since we use $db->query() which throws exception on prepare failure inside,
                 // we might not reach here unless logic changes.
                 // But let's be safe and check connection error
                throw new \Exception("Database Query Failed: " . $db->conn()->error);
            }

            $conversations = $query->result();
            
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
                        media_caption as caption
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
                        NULL as caption
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
        $res = $wa->sendFreeText($phone, $message); // Use phone directly

        if ($res['success']) {
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
                    'status' => 'sent'
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
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            
            $db = $this->db(0);
            
            // Update priority to 0 (done/resolved)
            $updated = $db->update('wa_conversations', 
                ['priority' => 0], 
                ['wa_number' => $phone]
            );
            
            if ($updated) {
                // Push WebSocket to update all clients
                $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
                
                $payload = [
                    'type' => 'priority_updated',
                    'phone' => $phone,
                    'priority' => 0,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId
                ];
                
                
                \Log::write("Pushing priority update to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['priority' => 0], 'Conversation marked as done');
            } else {
                $this->error('Failed to update priority');
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
    
    public function checkPayment()
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            
            $db = $this->db(0);
            
            // Update priority to 2 (check payment)
            $updated = $db->update('wa_conversations', 
                ['priority' => 2], 
                ['wa_number' => $phone]
            );
            
            if ($updated) {
                // Push WebSocket to update all clients
                $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
                
                $payload = [
                    'type' => 'priority_updated',
                    'phone' => $phone,
                    'priority' => 2,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId
                ];
                
                \Log::write("Pushing priority update to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['priority' => 2], 'Conversation marked for payment check');
            } else {
                $this->error('Failed to update priority');
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

    public function testWS()
    {
        $convId = $this->query('id') ?? '123';
        $target = $this->query('target') ?? '0';
        
        $payload = [
            'type' => 'conversation_read',
            'conversation_id' => $convId,
            'target_id' => $target,
            'message' => ['id' => time(), 'text' => 'TEST_WS_MANUAL'], 
            'unread_count' => 0
        ];
        
        $res = $this->pushToWebSocket($payload);
        
        header('Content-Type: application/json');
        echo json_encode(['result' => $res, 'payload' => $payload]);
        exit;
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
                \Log::write("WhatsAppService instance created successfully", 'cms_debug', 'Chat');
                
                \Log::write("Sending image to: $waNumber, URL: $mediaUrl", 'cms_debug', 'Chat');
                $result = $waService->sendImage($waNumber, $mediaUrl, $caption);
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
