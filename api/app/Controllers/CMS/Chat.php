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
            
            $sql = "
                SELECT 
                    c.*,
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
                        ORDER BY m.received_at DESC 
                        LIMIT 1
                    ) as last_message_text,
                    (
                         SELECT received_at
                         FROM wa_messages_in m
                         WHERE m.phone = c.wa_number
                         ORDER BY m.received_at DESC
                         LIMIT 1
                    ) as last_message_time
                FROM wa_conversations c
                ORDER BY c.updated_at DESC
            ";
    
            $query = $db->query($sql);
            
            if (!$query) {
                // DB Error
                throw new \Exception("Database Query Failed");
            }

            $conversations = $query->result();
    
            $this->success($conversations, 'Conversations retrieved successfully');

        } catch (\Throwable $e) {
            // Catch all errors including Fatal Errors
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'PHP Error in Chat Controller',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            exit;
        }
    }
}
