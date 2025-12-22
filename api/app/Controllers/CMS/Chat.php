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
                         SET status = 'closed' 
                         WHERE status = 'open' 
                         AND last_in_at < (NOW() - INTERVAL 22 HOUR)";
            $db->query($sqlClose);

            // Fetch conversations
            // status can be 'open', 'closed', etc.
            // We want 'open' generally, or maybe all active
            // Modified to include kode_cabang from database using local column 'code'
            
            $userId = $_GET['user_id'] ?? null;
            $whereClause = "c.status != 'closed'";
            
            // If user is NOT 1000 (Super Admin), filter by their ID
            if ($userId && $userId != '1000') {
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
                    (
                        SELECT COUNT(*) 
                        FROM wa_messages_in m 
                        WHERE (m.conversation_id = c.id OR m.phone = c.wa_number) 
                        AND (m.status != 'read' OR m.status IS NULL)
                    ) as unread_count,
                    c.last_message as last_message,
                    c.updated_at as last_message_time,
                    c.assigned_user_id,
                    COALESCE(c.code, '00') as kode_cabang
                FROM wa_conversations c
                WHERE $whereClause
                ORDER BY c.updated_at DESC
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
        $id = $this->query('id'); // conversation_id
        if (!$id) $this->error('Conversation ID required');

        $db = $this->db(0);

        // Fetch messages from both Inbound (wa_messages_in) and Outbound (wa_messages_out)
        // Wrapped in main query to sort global result
        // Optimized: Get only last 50 messages
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
                     WHERE conversation_id = ? 
                     AND status != 'deleted')
                     
                    UNION ALL
                    
                    (SELECT 
                        id, 
                        wamid,
                        content as text, 
                        type, 
                        'me' as sender, 
                        created_at as time, 
                        status,
                        NULL as media_id,
                        media_url,
                        NULL as caption
                     FROM wa_messages_out 
                     WHERE conversation_id = ?)
                ) AS combined_msgs
                ORDER BY time DESC
                LIMIT 50
            ) AS latest_msgs
            ORDER BY time ASC
        ";

        // Since we are using raw query with UNION, we can't easily use the builder params depending on implementation
        // But let's try strict params if possible or just manual query
        // The DB class `query` supports params
        
        $messages = $db->query($sql, [$id, $id])->result();
        
        $this->success($messages);
    }



    public function reply()
    {
        $body = $this->getBody();
        $conversationId = $body['conversation_id'] ?? null;
        $message = $body['message'] ?? null;

        if (!$conversationId || !$message) $this->error('Missing required fields');

        // 1. Get Phone Number
        $db = $this->db(0);
        $conv = $db->get_where('wa_conversations', ['id' => $conversationId])->row();
        
        if (!$conv) $this->error('Conversation not found');

        // 2. Send Message using Helper
        // Need to require if not autoloaded, but init.php handles autoloading Helpers?
        // Let's assume PSR-4 or classmap works, otherwise require it.
        if (!class_exists('\App\Helpers\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
        }
        
        $wa = new \App\Helpers\WhatsAppService();
        $res = $wa->sendFreeText($conv->wa_number, $message);

        if ($res['success']) {
            // Update conversation last_message
            $db->update('wa_conversations', [
                'last_message' => $message,
                'updated_at' => date('Y-m-d H:i:s')
            ], ['id' => $conversationId]);

            $data = $res['data'];
            $data['local_id'] = $res['local_id'] ?? null; // Attach local DB ID
            $this->success($data, 'Reply sent');
        } else {
            $this->error('Failed to send WhatsApp: ' . ($res['error'] ?? 'Unknown error'), 500);
        }
    }
    public function markRead()
    {
        $body = json_decode(file_get_contents('php://input'), true);
        $conversationId = $body['conversation_id'] ?? null;
        
        if (!$conversationId) {
             $conversationId = $this->query('conversation_id');
        }
        
        if(!$conversationId) $this->error('ID required');
        
        $db = $this->db(0);
        
        // 1. Get WAMIDs for API Sync
        $unreads = $db->query("SELECT wamid FROM wa_messages_in WHERE conversation_id = ? AND (status != 'read' OR status IS NULL) AND wamid IS NOT NULL", [$conversationId])->result_array();
        
        // 2. Direct Query Update ALL messages
        $db->query("UPDATE wa_messages_in SET status = 'read' WHERE conversation_id = ?", [$conversationId]);
        $affected = $db->affected_rows();
        
        // Push WS if local status changed
        if ($affected > 0) {
             // Get details for WS
             // $conv = $db->get_where('wa_conversations', ['id' => $conversationId])->row();
             // $targetId = $conv && $conv->assigned_user_id ? (string)$conv->assigned_user_id : '0';
             
             // FORCE BROADCAST to '0' so all agents get the Read Update
             $targetId = '0';
             
             // Notify WebSocket Server
             $this->pushToWebSocket([
                'type' => 'conversation_read',
                'conversation_id' => $conversationId,
                'target_id' => $targetId,
                'unread_count' => 0
             ]);
        }
        
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
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); 
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;   
    }

}
