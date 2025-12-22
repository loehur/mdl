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
                    ORDER BY m.created_at DESC 
                    LIMIT 1
                ) as last_message_text,
                (
                     SELECT received_at
                     FROM wa_messages_in m
                     WHERE m.phone = c.wa_number
                     ORDER BY m.created_at DESC
                     LIMIT 1
                ) as last_message_time
            FROM wa_conversations c
            ORDER BY c.updated_at DESC
        ";

        $query = $db->query($sql);
        $conversations = $query->result();

        $this->success($conversations, 'Conversations retrieved successfully');
    }
}
