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
            // Modified to include kode_cabang from database mdl_laundry
            $sql = "
                SELECT 
                    c.id, 
                    c.wa_number, 
                    c.contact_name, 
                    c.status,
                    (
                        SELECT COUNT(*) 
                        FROM wa_messages_in m 
                        WHERE m.phone = c.wa_number 
                        AND m.status = 'received'
                    ) as unread_count,
                     (
                        SELECT text 
                        FROM wa_messages_in m 
                        WHERE m.phone = c.wa_number 
                        ORDER BY m.created_at DESC 
                        LIMIT 1
                    ) as last_message_text,
                    (
                         SELECT created_at
                         FROM wa_messages_in m
                         WHERE m.phone = c.wa_number
                         ORDER BY m.created_at DESC 
                         LIMIT 1
                    ) as last_message_time,
                    c.assigned_user_id,
                    (SELECT kode_cabang FROM mdl_laundry.cabang WHERE id_cabang = c.assigned_user_id LIMIT 1) as kode_cabang
                FROM wa_conversations c
                WHERE c.status != 'closed'
                ORDER BY c.last_in_at DESC
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
                        text, 
                        type, 
                        'customer' as sender, 
                        created_at as time, 
                        status 
                     FROM wa_messages_in 
                     WHERE conversation_id = ? 
                     AND status != 'deleted')
                     
                    UNION ALL
                    
                    (SELECT 
                        id, 
                        content as text, 
                        type, 
                        'me' as sender, 
                        created_at as time, 
                        status 
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

    public function markRead()
    {
        $id = $this->getBody()['conversation_id'] ?? null;
         if (!$id) $this->error('Conversation ID required');

        $db = $this->db(0);
        
        // Update unread received messages to 'read'
        $db->query("UPDATE wa_messages_in SET status = 'read' WHERE conversation_id = ? AND status = 'received'", [$id]);
        
        $this->success([], 'Messages marked as read');
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
            $data = $res['data'];
            $data['local_id'] = $res['local_id'] ?? null; // Attach local DB ID
            $this->success($data, 'Reply sent');
        } else {
            $this->error('Failed to send WhatsApp: ' . ($res['error'] ?? 'Unknown error'), 500);
        }
    }
}
