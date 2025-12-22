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
                -- Temporarily removed for debugging: WHERE c.status = 'open'
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
            
            // Debug: Check statuses
            $statuses = [];
            foreach($conversations as $c) {
                $statuses[] = $c->status;
            }
            $statusCounts = array_count_values($statuses);
    
            $this->success($conversations, 'Retrieved ' . count($conversations) . ' chats. Statuses found: ' . json_encode($statusCounts));

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
