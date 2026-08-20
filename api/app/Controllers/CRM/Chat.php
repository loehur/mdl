<?php

namespace App\Controllers\CRM;

use App\Core\Controller;
use App\Helpers\CRM\CrmChatMergeHelper;
use App\Helpers\CRM\WaSenderContext;

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
            
            // Auto-close expired conversations (CSW timeout > 24 hours)
            // Rule: If last message was > 24 hours ago, close the session
            $sqlClose = "UPDATE wa_conversations 
                         SET status = 'closed' WHERE status = 'open' 
                         AND last_in_at < (NOW() - INTERVAL 24 HOUR)";
            $db->query($sqlClose);
            
            // Auto-delete old messages (older than 90 days)
            $deleteDays = 90;
            $sqlDeleteIn = "DELETE FROM wa_messages_in WHERE created_at < (NOW() - INTERVAL $deleteDays DAY)";
            $db->query($sqlDeleteIn);
            
            $sqlDeleteOut = "DELETE FROM wa_messages_out WHERE created_at < (NOW() - INTERVAL $deleteDays DAY)";
            $db->query($sqlDeleteOut);

            // Fetch conversations
            // status can be 'open', 'closed', etc.
            // We want 'open' generally, or maybe all active
            // Modified to include kode_cabang from database using local column 'code'
            
            // Pagination parameters
            $offset = (int)($_GET['offset'] ?? 0);
            $limit = (int)($_GET['limit'] ?? 30);
            
            // Search parameter
            $search = trim($_GET['search'] ?? '');
            
            // Specific conversation ID (for refresh CSW status)
            $conversationId = (int)($_GET['conversation_id'] ?? 0);
            
            // Safety: Max 100 per request
            if ($limit > 100) $limit = 100;
            if ($offset < 0) $offset = 0;
            
            // Fetch limit+1 to check if there's more data
            $fetchLimit = $limit + 1;
            
            $userId = $_GET['user_id'] ?? null;
            $whereClause = "1=1"; // Always true, untuk base condition
            
            // Add specific conversation ID filter (for refresh CSW - don't fetch all conversations)
            if ($conversationId > 0) {
                $whereClause .= " AND c.id = " . $conversationId;
                $fetchLimit = 1; // Only need 1 result
            }
            // Add search filter if provided
            elseif (!empty($search)) {
                $safSearch = $db->conn()->real_escape_string($search);
                $whereClause .= " AND (
                    c.contact_name LIKE '%$safSearch%' 
                    OR c.wa_number LIKE '%$safSearch%'
                    OR COALESCE(c.code, '00') LIKE '%$safSearch%'
                )";
            }
            
            // Get user role:
            // admin from crm_users; crew from mdl_laundry.cabang (id_cabang)
            $isAdmin = false;
            
            if ($userId) {
                $userRecord = $db
                    ->where('LOWER(username)', strtolower($userId))
                    ->get('crm_users')
                    ->row();
                    
                if ($userRecord) {
                    $role = strtolower($userRecord->role ?? '');
                    $isAdmin = ($role === 'admin');
                }
                // Non-admin treated as crew (cabang id_cabang) below
            }
            
            if ($userId && !$isAdmin) {
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
            
            $sql = "
                SELECT 
                    c.id, 
                    c.wa_number, 
                    c.contact_name, 
                    c.status,
                    c.conv_case,
                    0 as unread_count,
                    c.last_message as last_message,
                    c.last_message_at as last_message_time,
                    c.assigned_user_id,
                    COALESCE(c.code, '00') as kode_cabang,
                    c.cust_id,
                    c.partner
                FROM wa_conversations c
                WHERE $whereClause
                ORDER BY c.last_message_at DESC
                LIMIT ? OFFSET ?
            ";
    
            $query = $db->query($sql, [$fetchLimit, $offset]);
            
            if (!$query) {
                 // DB Error checking
                throw new \Exception("Database Query Failed: " . $db->conn()->error);
            }

            $conversations = $query->result();

            $activeDeliveryByCustId = $this->fetchActiveDeliveryMap($db, array_map(
                static function ($conv) {
                    return (int) ($conv->cust_id ?? 0);
                },
                $conversations
            ));

            $activePermintaanByPhone = $this->fetchActivePermintaanMap($db, array_map(
                static function ($conv) {
                    return (string) ($conv->wa_number ?? '');
                },
                $conversations
            ));
            
            // Check if there are more conversations
            $hasMore = count($conversations) > $limit;
            
            // Trim to actual limit (remove last element if we fetched limit+1)
            if ($hasMore) {
                array_pop($conversations); // For conversations, last is OK (DESC order)
            }
            
            // Normalize Case (Handle JSON Array List)
            foreach ($conversations as &$conv) {
                // Default values
                $conv->case_val = 0;
                $conv->case_status = null;
                $conv->case_history = []; // New field for full history

                $mergedLast = CrmChatMergeHelper::mergeLastMessageMeta($db, (string) ($conv->wa_number ?? ''), $conv);
                if (!empty($mergedLast['last_message'])) {
                    $conv->last_message = $mergedLast['last_message'];
                }
                if (!empty($mergedLast['last_message_time'])) {
                    $conv->last_message_time = $mergedLast['last_message_time'];
                }

                $csw = CrmChatMergeHelper::getCswStatus($db, (string) ($conv->wa_number ?? ''));
                $conv->ycloud_open = $csw['ycloud_open'];
                $conv->fonnte_open = $csw['fonnte_open'];
                $conv->last_in_at_ycloud = $csw['last_in_at_ycloud'];
                $conv->last_in_at_fonnte = $csw['last_in_at_fonnte'];
                $conv->default_reply_channel = $csw['default_reply_channel'];
                $conv->can_reply = $csw['can_reply'];
                $conv->unread_count = CrmChatMergeHelper::countUnreadForPhone($db, (string) ($conv->wa_number ?? ''));

                $caseList = $this->normalizeCaseList($conv->conv_case ?? null);
                $hasActiveDelivery = !empty($activeDeliveryByCustId[(int) ($conv->cust_id ?? 0)]);
                $caseList = $this->mergeDeliveryCase($caseList, $hasActiveDelivery);
                $hasActivePermintaan = !empty($activePermintaanByPhone[ltrim((string) ($conv->wa_number ?? ''), '+')]);
                $caseList = $this->mergePermintaanCase($caseList, $hasActivePermintaan);
                $conv->case_history = $caseList;

                $lastOpenCase = null;
                foreach ($caseList as $item) {
                    if ((int) ($item['case'] ?? 0) > 0 && (($item['status'] ?? 'open') !== 'closed')) {
                        $lastOpenCase = $item;
                    }
                }

                if ($lastOpenCase) {
                    $conv->case_val = (int) ($lastOpenCase['case'] ?? 0);
                    $conv->case_status = $lastOpenCase['status'] ?? 'open';
                } elseif (!empty($caseList)) {
                    $lastItem = end($caseList);
                    $conv->case_val = (int) ($lastItem['case'] ?? 0);
                    $conv->case_status = $lastItem['status'] ?? null;
                }
            }
            
            // Check what we actually got
            if (empty($conversations)) {
                $this->success([
                    'conversations' => [],
                    'has_more' => false,
                    'offset' => $offset,
                    'limit' => $limit,
                    'total_returned' => 0
                ], 'Query executed but returned 0 rows. Check if table wa_conversations is empty.');
            }

            // Return data with pagination info
            $this->success([
                'conversations' => $conversations,
                'has_more' => $hasMore,
                'offset' => $offset,
                'limit' => $limit,
                'total_returned' => count($conversations)
            ], 'OK');

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

    private function normalizeCaseList($rawCase): array
    {
        if (is_string($rawCase)) {
            $trimmed = trim($rawCase);
            if ($trimmed !== '' && ($trimmed[0] === '[' || $trimmed[0] === '{')) {
                $parsed = json_decode($trimmed, true);
                if (is_array($parsed)) {
                    if (isset($parsed[0])) {
                        return $parsed;
                    }
                    if (isset($parsed['case'])) {
                        return [$parsed];
                    }
                }
            }
        }

        if (is_numeric($rawCase)) {
            return [['case' => (int) $rawCase, 'status' => 'open']];
        }

        return [];
    }

    private function mergeDeliveryCase(array $caseList, bool $hasActiveDelivery): array
    {
        $filtered = [];
        foreach ($caseList as $item) {
            if ((int) ($item['case'] ?? 0) === 2) {
                continue;
            }
            $filtered[] = $item;
        }

        if ($hasActiveDelivery) {
            $filtered[] = ['case' => 2, 'status' => 'open'];
        }

        return array_values($filtered);
    }

    private function fetchActiveDeliveryMap($db, array $custIds): array
    {
        $custIds = array_values(array_unique(array_filter(array_map('intval', $custIds))));
        if ($custIds === []) {
            return [];
        }

        // delivery_request ada di mdl_laundry (db index 1), bukan mdl_main (db index 0)
        $dbLaundry = $this->db(1);
        $placeholders = implode(',', array_fill(0, count($custIds), '?'));
        try {
            $rows = $dbLaundry->query(
                "SELECT cust_id
                 FROM delivery_request
                 WHERE cust_id IN ($placeholders)
                   AND delivery_status IN ('berjalan','menunggu_pembayaran')
                 GROUP BY cust_id",
                $custIds
            )->result_array();
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $custId = (int) ($row['cust_id'] ?? 0);
            if ($custId > 0) {
                $map[$custId] = true;
            }
        }

        return $map;
    }

    /**
     * Fetch wa_permintaan_session yang masih aktif (status='open' + notify_expires_at > NOW()).
     * Return map: wa_number (tanpa leading '+') => true.
     * Tabel ada di mdl_laundry (db index 1).
     */
    private function fetchActivePermintaanMap($db, array $phones): array
    {
        $phones = array_values(array_unique(array_filter(array_map(
            static function ($p) {
                return ltrim(preg_replace('/[^0-9+]/', '', (string) $p), '+');
            },
            $phones
        ))));

        if ($phones === []) {
            return [];
        }

        // wa_permintaan_session ada di mdl_laundry (db index 1)
        $dbLaundry = $this->db(1);
        $placeholders = implode(',', array_fill(0, count($phones), '?'));
        try {
            $rows = $dbLaundry->query(
                "SELECT phone
                 FROM wa_permintaan_session
                 WHERE status = 'open'
                   AND notify_expires_at > NOW()
                   AND phone IN ($placeholders)
                 GROUP BY phone",
                $phones
            )->result_array();
        } catch (\Throwable $e) {
            // tabel belum ada
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $p = ltrim(preg_replace('/[^0-9]/', '', (string) ($row['phone'] ?? '')), '');
            if ($p !== '') {
                $map[$p] = true;
            }
        }

        return $map;
    }

    private function mergePermintaanCase(array $caseList, bool $hasActivePermintaan): array
    {
        $filtered = [];
        foreach ($caseList as $item) {
            if ((int) ($item['case'] ?? 0) === 3) {
                continue;
            }
            $filtered[] = $item;
        }

        if ($hasActivePermintaan) {
            $filtered[] = ['case' => 3, 'status' => 'open'];
        }

        return array_values($filtered);
    }

    public function getMessages()
    {
        try {
            $phone = $this->query('phone');
            if (!$phone) {
                $this->error('Phone required');
            }

            $offset = (int) ($this->query('offset') ?? 0);
            $limit = (int) ($this->query('limit') ?? 20);
            if ($limit > 100) {
                $limit = 100;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            $db = $this->db(0);

            $nomor = WaSenderContext::toNomorNasional($phone);
            if ($nomor === null) {
                $this->error('Phone required');
            }
            $like = '%' . $nomor;
            $phoneExpr = WaSenderContext::sqlDigitsExpr('phone');
            $fetchLimit = $limit + 1;

            $includeFonnte = CrmChatMergeHelper::fonnteMessageTablesReady($db);
            $fonnteInStatusSql = CrmChatMergeHelper::fonnteInboundStatusReady($db)
                ? "COALESCE(status, 'received') as status"
                : "'received' as status";

            $ycloudUnion = "
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
                        quoted_message_body,
                        NULL as sender_code,
                        0 as `private`,
                        'Y' as provider
                     FROM wa_messages_in 
                     WHERE {$phoneExpr} LIKE ?)
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
                        quoted_message_body,
                        sender_code,
                        COALESCE(`private`, 0) as `private`,
                        'Y' as provider
                     FROM wa_messages_out 
                     WHERE {$phoneExpr} LIKE ?)";

            $fonnteUnion = "
                    UNION ALL
                    (SELECT 
                        id,
                        CAST(inboxid AS CHAR) as wamid,
                        text,
                        type,
                        'customer' as sender,
                        created_at as time,
                        {$fonnteInStatusSql},
                        NULL as media_id,
                        media_url,
                        NULL as caption,
                        NULL as quoted_message_id,
                        NULL as quoted_message_body,
                        NULL as sender_code,
                        0 as `private`,
                        'F' as provider
                     FROM wa_fonnte_messages_in
                     WHERE {$phoneExpr} LIKE ?)
                    UNION ALL
                    (SELECT 
                        id,
                        fonnte_message_id as wamid,
                        COALESCE(text, '') as text,
                        type,
                        'me' as sender,
                        created_at as time,
                        status,
                        NULL as media_id,
                        media_url,
                        NULL as caption,
                        CAST(reply_inboxid AS CHAR) as quoted_message_id,
                        NULL as quoted_message_body,
                        sender_code,
                        0 as `private`,
                        'F' as provider
                     FROM wa_fonnte_messages_out
                     WHERE {$phoneExpr} LIKE ?)";

            $sql = "
            SELECT * FROM (
                SELECT * FROM (
                    {$ycloudUnion}
                    " . ($includeFonnte ? $fonnteUnion : '') . "
                ) AS combined_msgs
                ORDER BY time DESC
                LIMIT ? OFFSET ?
            ) AS latest_msgs
            ORDER BY time ASC
        ";

            $params = [$like, $like];
            if ($includeFonnte) {
                $params[] = $like;
                $params[] = $like;
            }
            $params[] = $fetchLimit;
            $params[] = $offset;

            $messages = $db->query($sql, $params)->result_array();

            if (!class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
                require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
            }

            foreach ($messages as &$msg) {
                if (!empty($msg['media_url'])) {
                    $msg['media_url'] = \App\Helpers\CRM\FonnteMessageStore::normalizeMediaUrl((string) $msg['media_url']);
                }

                $privateValue = $msg['private'] ?? $msg['`private`'] ?? null;
                if ($privateValue !== null && $privateValue !== '' && $privateValue !== false) {
                    $msg['private'] = (int) $privateValue;
                } else {
                    $msg['private'] = 0;
                }

                $provider = strtoupper((string) ($msg['provider'] ?? 'Y'));
                $msg['provider'] = ($provider === 'F') ? 'F' : 'Y';
                $rawId = $msg['id'] ?? 0;
                $msg['id'] = $msg['provider'] . '-' . $rawId;
            }
            unset($msg);

            $hasMore = count($messages) > $limit;
            if ($hasMore) {
                array_shift($messages);
            }

            $this->success([
                'messages' => $messages,
                'has_more' => $hasMore,
                'offset' => $offset,
                'limit' => $limit,
                'total_returned' => count($messages),
                'fonnte_merged' => $includeFonnte,
            ]);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('[getMessages] ' . $e->getMessage(), 'cms', 'chat');
            }
            http_response_code(500);
            echo json_encode([
                'status' => false,
                'message' => 'PHP Error in getMessages',
                'error' => $e->getMessage(),
            ]);
            exit;
        }
    }



    public function reply()
    {
        $body = $this->getBody();
        $phone = $body['phone'] ?? null;
        $message = $body['message'] ?? null;
        $replyTo = $body['reply_to'] ?? null; // WAMID / inboxid
        $channelReq = $body['channel'] ?? 'auto';

        if (!$phone || !$message) $this->error('Missing required fields (phone, message)');

        $db = $this->db(0);
        $this->assertAdminCanReply($db, $body['user_id'] ?? null);

        if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
        }

        $csw = CrmChatMergeHelper::getCswStatus($db, (string) $phone);
        $channel = CrmChatMergeHelper::resolveReplyChannel($csw, is_string($channelReq) ? $channelReq : 'auto');
        if ($channel === null) {
            $this->error('Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text.', 400);
        }

        $senderCode = $body['sender_code'] ?? null;
        if (!$senderCode && isset($body['user_id'])) {
            $senderCode = $this->resolveSenderCode($db, $body['user_id']);
        }
        if (!$senderCode && isset($_SESSION['mdl_crm_session']['user']['code'])) {
            $senderCode = $_SESSION['mdl_crm_session']['user']['code'];
        }

        if ($channel === 'fonnte') {
            $this->replyViaFonnte($db, $phone, $message, $replyTo, $senderCode);
            return;
        }

        if (!class_exists('\\App\\Helpers\\CRM\\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
        }

        $wa = new \App\Helpers\CRM\WhatsAppService();
        $res = $wa->sendFreeText($phone, $message, $replyTo, $senderCode);

        if ($res['success']) {
            if ($replyTo && isset($res['local_id'])) {
                $db->update('wa_messages_out', [
                    'quoted_message_id' => $replyTo,
                ], ['id' => $res['local_id']]);
            }

            $conv = CrmChatMergeHelper::findWaConversation($db, (string) $phone);
            if ($conv && !empty($conv->wa_number)) {
                $db->update('wa_conversations', [
                    'last_message' => 'o- ' . mb_substr($message, 0, 50),
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['wa_number' => $conv->wa_number]);
            }

            $data = $res['data'];
            $data['local_id'] = $res['local_id'] ?? null;
            $data['channel'] = 'ycloud';
            $data['provider'] = 'Y';

            $this->success($data, 'Reply sent');
        } else {
            $this->error('Failed to send WhatsApp: ' . ($res['error'] ?? 'Unknown error'), 500);
        }
    }

    /**
     * Balasan CRM via Fonnte (CSW Fonnte terbuka).
     */
    private function replyViaFonnte($db, string $phone, string $message, $replyTo, ?string $senderCode): void
    {
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
        }
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
        }
        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/SapaanStatsHelper.php';
        }

        $fonnte = new \App\Helpers\CRM\FonnteService();
        $options = [];
        if ($replyTo !== null && $replyTo !== '' && is_numeric($replyTo)) {
            $options['inboxid'] = (int) $replyTo;
        }
        $result = $fonnte->sendMessage($phone, $message, $options);
        if (empty($result['success'])) {
            $this->error('Failed to send via Fonnte: ' . ($result['error'] ?? 'Unknown error'), 500);
        }

        $waNumber = CrmChatMergeHelper::normalizeWaNumber($phone);
        $code = ($senderCode !== null && trim($senderCode) !== '')
            ? trim($senderCode)
            : \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
        $isHuman = \App\Helpers\CRM\SapaanStatsHelper::isHumanSenderCode($code);

        $store = new \App\Helpers\CRM\FonnteMessageStore($db);
        $responseData = is_array($result['data'] ?? null) ? $result['data'] : null;
        $fonnteId = \App\Helpers\CRM\FonnteService::extractMessageId($responseData);
        $stateId = \App\Helpers\CRM\FonnteService::extractStateId($responseData);
        $localId = $store->saveOutgoing($waNumber, $message, [
            'fonnte_message_id' => $fonnteId,
            'fonnte_stateid' => $stateId,
            'reply_inboxid' => !empty($options['inboxid']) ? (int) $options['inboxid'] : null,
            'source' => $isHuman ? 'human' : 'autoreply',
            'sender_code' => $code,
            'status' => 'sent',
        ]);

        if ($isHuman) {
            try {
                \App\Helpers\CRM\SapaanStatsHelper::recordStatsIfHuman($db, $waNumber, $message, $code);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $now = date('Y-m-d H:i:s');
        $conv = CrmChatMergeHelper::findWaConversation($db, $phone);
        $conversationId = 0;
        $kodeCabang = '00';
        $custId = null;
        $assignedUserId = null;
        if ($conv) {
            $conversationId = (int) ($conv->id ?? 0);
            $kodeCabang = $conv->code ?? '00';
            $custId = $conv->cust_id ?? null;
            $assignedUserId = $conv->assigned_user_id ?? null;
            $db->update('wa_conversations', [
                'last_message' => 'o- ' . mb_substr($message, 0, 50),
                'last_message_at' => $now,
                'updated_at' => $now,
            ], ['wa_number' => $conv->wa_number]);
        }

        CrmChatMergeHelper::pushWebSocket([
            'type' => 'agent_message_sent',
            'target_id' => '0',
            'notify' => false,
            'conversation_id' => $conversationId,
            'phone' => $waNumber,
            'contact_name' => $conv->contact_name ?? null,
            'kode_cabang' => $kodeCabang,
            'cust_id' => $custId,
            'assignment_user_id' => $assignedUserId,
            'message' => [
                'id' => $localId !== null ? ('F-' . $localId) : null,
                'wamid' => $fonnteId !== null ? (string) $fonnteId : null,
                'text' => $message,
                'type' => 'text',
                'sender_code' => $code,
                'quoted_message_id' => !empty($options['inboxid']) ? (string) $options['inboxid'] : null,
                'time' => $now,
                'status' => 'sent',
                'provider' => 'F',
            ],
        ]);

        $this->success([
            'local_id' => $localId,
            'message_id' => $fonnteId,
            'channel' => 'fonnte',
            'provider' => 'F',
        ], 'Reply sent via Fonnte');
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

        if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
        }

        [$inSql, $variants] = CrmChatMergeHelper::phoneInClause((string) $phone);
        if ($inSql === '') {
            $this->error('Phone required');
        }

        // 1. Get WAMIDs for yCloud API sync (semua varian nomor)
        $unreads = $db->query(
            "SELECT wamid FROM wa_messages_in WHERE phone IN ({$inSql}) AND (status != 'read' OR status IS NULL) AND wamid IS NOT NULL",
            $variants
        )->result_array();

        // 2. Mark read lokal (yCloud + Fonnte)
        CrmChatMergeHelper::markYcloudInboundRead($db, (string) $phone);
        CrmChatMergeHelper::markFonnteInboundRead($db, (string) $phone);
        
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
        
        if (!class_exists('\App\Helpers\CRM\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
        }
        $wa = new \App\Helpers\CRM\WhatsAppService();
        
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

            // Pertahankan case 2 (Pickup/Delivery) yang masih open — dituntaskan via laundry Delivery
            $preserved = [];
            $existing = $db->query("SELECT conv_case FROM wa_conversations WHERE wa_number = ?", [$phone])->row();
            if ($existing && isset($existing->conv_case)) {
                $raw = $existing->conv_case;
                $list = [];
                if (is_string($raw) && (strpos(trim($raw), '[') === 0 || strpos(trim($raw), '{') === 0)) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $list = isset($decoded[0]) ? $decoded : (isset($decoded['case']) ? [$decoded] : []);
                    }
                } elseif (is_numeric($raw) && (int)$raw === 2) {
                    $list[] = ['case' => 2, 'status' => 'open'];
                }
                foreach ($list as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    if ((int)($item['case'] ?? 0) === 2 && ($item['status'] ?? 'open') !== 'closed') {
                        $preserved[] = [
                            'case' => 2,
                            'status' => 'open',
                        ];
                    }
                }
            }

            // Update case (Overwrite history for manual action), keep open pickup cases
            $jsonCase = json_encode(array_values(array_merge(
                $preserved,
                [[
                    'case' => (int)$caseVal,
                    'status' => 'done'
                ]]
            )));
            
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
                    'sender_id' => $userId,
                    'all_closed' => true
                ];
                
                
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

            if ((int)$caseVal === 2) {
                $this->error('Case Delivery Request mengikuti delivery_request aktif dan tidak bisa diubah manual');
            }

            if ((int)$caseVal === 3) {
                $this->error('Case Permintaan mengikuti wa_permintaan_session aktif dan tidak bisa diubah manual');
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
                // Push WebSocket for general case update
                $payload = [
                    'type' => 'case_updated',
                    'phone' => $phone,
                    'case' => (int)$caseVal,
                    'target_id' => '0',
                    'sender_id' => $userId
                ];
                
                $this->pushToWebSocket($payload);
                
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

    /**
     * Set flag partner pada wa_conversations: 1 = partner, NULL = bukan partner.
     * Body JSON: phone (required), partner (bool|0|1 — true/1 set 1, false/0/null set NULL)
     */
    public function setPartner()
    {
        try {
            $this->handleCors();
            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $phone = $body['phone'] ?? null;
            $raw = $body['partner'] ?? null;

            if (!$phone) {
                $this->error('Phone required');
            }

            $enabled = false;
            if ($raw === true || $raw === 1 || $raw === '1') {
                $enabled = true;
            }

            $db = $this->db(0);
            $exists = $db->query('SELECT id FROM wa_conversations WHERE wa_number = ?', [$phone])->row();
            if (!$exists) {
                $this->error('Conversation not found');
            }

            if ($enabled) {
                $db->update('wa_conversations', ['partner' => 1], ['wa_number' => $phone]);
            } else {
                $db->query('UPDATE wa_conversations SET partner = NULL WHERE wa_number = ?', [$phone]);
            }

            $this->success(['partner' => $enabled ? 1 : null], 'Partner updated');
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false,
                'message' => 'Server Error: ' . $e->getMessage(),
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

            $targetCase = (int)$caseVal;
            if ($targetCase === 2) {
                $this->error('Case Delivery Request mengikuti delivery_request aktif dan tidak bisa di-resolve manual');
            }
            if ($targetCase === 3) {
                $this->error('Case Permintaan mengikuti wa_permintaan_session aktif dan tidak bisa di-resolve manual — gunakan tombol Selesai di panel Laundry');
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
                
                // Check if any open cases remain after resolution
                $hasOpenCases = false;
                foreach ($caseList as $c) {
                    if (($c['status'] ?? '') === 'open') {
                        $hasOpenCases = true;
                        break;
                    }
                }

                // Push WebSocket
                $payload = [
                    'type' => 'case_resolved',
                    'phone' => $phone,
                    'case' => $targetCase,
                    'target_id' => '0', // Broadcast to all
                    'sender_id' => $userId,
                    'all_closed' => !$hasOpenCases // Flag for Silent Push (Cancel Notification)
                ];
                
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
        
        if (!class_exists('\App\Helpers\CRM\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
        }
        $wa = new \App\Helpers\CRM\WhatsAppService();
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

    /**
     * Proxy media HTTP→HTTPS untuk CRM (mixed-content fix).
     * GET ?url=http://api.nalju.com/...
     */
    public function proxyMedia()
    {
        $url = trim((string) ($this->query('url') ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            http_response_code(400);
            die('URL required');
        }

        if (!class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || !\App\Helpers\CRM\FonnteMessageStore::isProxyAllowedMediaHost($host)) {
            http_response_code(403);
            die('Host not allowed');
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MdL-CRM/1.0');
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($data === false || $httpCode < 200 || $httpCode >= 300) {
            http_response_code(404);
            die('Media fetch failed');
        }

        $mime = 'application/octet-stream';
        if (is_string($contentType) && $contentType !== '') {
            $mime = trim(explode(';', $contentType)[0]);
        }

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        echo $data;
        exit;
    }

    private function pushToWebSocket($data)
    {
        $url = \App\Helpers\CRM\WaServer::incomingUrl();
        
        // Log payload for debugging
        if (class_exists('\Log')) {
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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
            $channelReq = $body['channel'] ?? 'auto';
            
            if (!$phone) {
                $this->error('Missing phone number');
            }
            
            $db = $this->db(0);
            $this->assertAdminCanReply($db, $body['user_id'] ?? null);

            if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
                require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
            }

            $csw = CrmChatMergeHelper::getCswStatus($db, (string) $phone);
            $channel = CrmChatMergeHelper::resolveReplyChannel($csw, is_string($channelReq) ? $channelReq : 'auto');
            if ($channel === null) {
                $this->error('Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send image.', 400);
            }

            $senderCode = $body['sender_code'] ?? null;
            if (!$senderCode && $userId) {
                $senderCode = $this->resolveSenderCode($db, $userId);
            }
            if (!$senderCode && isset($_SESSION['mdl_crm_session']['user']['code'])) {
                $senderCode = $_SESSION['mdl_crm_session']['user']['code'];
            }
            
            // Upload image to server
            $uploaded = $this->uploadImageFile($_FILES['image']);
            if (!$uploaded['success']) {
                $this->error($uploaded['error']);
            }
            
            $mediaUrl = $uploaded['url'];

            if ($channel === 'fonnte') {
                $this->sendMediaViaFonnte($db, (string) $phone, $mediaUrl, (string) $caption, $senderCode, $uploaded['path'] ?? null, 'image');
                return;
            }

            $conv = CrmChatMergeHelper::findWaConversation($db, (string) $phone);
            if (!$conv) {
                $this->error('Conversation not found');
            }
            $waNumber = $conv->wa_number;
            
            // Send via WhatsApp Service (yCloud)
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
            }
            
            try {
                $waService = new \App\Helpers\CRM\WhatsAppService();
                $result = $waService->sendImage($waNumber, $mediaUrl, $caption, $senderCode);
                
            } catch (\Throwable $e) {
                \Log::write("CRITICAL ERROR calling sendImage: " . $e->getMessage(), 'cms_error', 'Chat');
                \Log::write("Error file: " . $e->getFile() . " line " . $e->getLine(), 'cms_error', 'Chat');
                \Log::write("Stack trace: " . $e->getTraceAsString(), 'cms_error', 'Chat');
                
                $this->error('WhatsApp API error: ' . $e->getMessage(), 500);
            }
            
            if ($result['success']) {
                // Check if caption contains WA_PRIVATE_WORDS (case-insensitive)
                $isPrivate = false;
                try {
                    $isPrivate = \EnvHelper::textContainsPrivateWord($caption ?? '');
                } catch (\Throwable $e) {
                    // Jangan gagalkan simpan chat jika cek private error
                }

                // Save to database
                $messageData = [
                    'phone' => $waNumber,
                    'type' => 'image',
                    'content' => $caption,
                    'media_url' => $mediaUrl,
                    'message_id' => $result['data']['id'] ?? null,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'status' => 'sent',
                    'private' => $isPrivate ? 1 : 0,
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
                
                // Update conversation (last_message: private chat jika caption sensitif)
                $lastMsgDisplay = $isPrivate ? 'o- 🔒 _Private Chat_' : 'o- 📷 Image';
                $db->update('wa_conversations', [
                    'last_message' => $lastMsgDisplay,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ], ['wa_number' => $waNumber]);

                // Sapaan stats: dicatat di WhatsAppService::saveOutboundMessage (human only).
                
                // WS already broadcast by WhatsAppService::saveOutboundMessage — skip duplicate push
                
                $this->success([
                    'local_id' => $msgId,
                    'media_url' => $mediaUrl,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'channel' => 'ycloud',
                    'provider' => 'Y',
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
     * Send Video via WhatsApp (yCloud / Fonnte).
     */
    public function sendVideo()
    {
        try {
            if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
                $this->error('No video uploaded or upload error');
            }

            $body = $_POST;
            $phone = $body['phone'] ?? null;
            $userId = $body['user_id'] ?? null;
            $caption = $body['caption'] ?? '';
            $channelReq = $body['channel'] ?? 'auto';

            if (!$phone) {
                $this->error('Missing phone number');
            }

            $db = $this->db(0);
            $this->assertAdminCanReply($db, $body['user_id'] ?? null);

            if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
                require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
            }

            $csw = CrmChatMergeHelper::getCswStatus($db, (string) $phone);
            $channel = CrmChatMergeHelper::resolveReplyChannel($csw, is_string($channelReq) ? $channelReq : 'auto');
            if ($channel === null) {
                $this->error('Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send video.', 400);
            }

            $senderCode = $body['sender_code'] ?? null;
            if (!$senderCode && $userId) {
                $senderCode = $this->resolveSenderCode($db, $userId);
            }
            if (!$senderCode && isset($_SESSION['mdl_crm_session']['user']['code'])) {
                $senderCode = $_SESSION['mdl_crm_session']['user']['code'];
            }

            $uploaded = $this->uploadVideoFile($_FILES['video']);
            if (!$uploaded['success']) {
                $this->error($uploaded['error']);
            }

            $mediaUrl = $uploaded['url'];

            if ($channel === 'fonnte') {
                $this->sendMediaViaFonnte($db, (string) $phone, $mediaUrl, (string) $caption, $senderCode, $uploaded['path'] ?? null, 'video');
                return;
            }

            $conv = CrmChatMergeHelper::findWaConversation($db, (string) $phone);
            if (!$conv) {
                $this->error('Conversation not found');
            }
            $waNumber = $conv->wa_number;

            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
            }

            try {
                $waService = new \App\Helpers\CRM\WhatsAppService();
                $result = $waService->sendVideo($waNumber, $mediaUrl, $caption, $senderCode);
            } catch (\Throwable $e) {
                \Log::write('CRITICAL ERROR calling sendVideo: ' . $e->getMessage(), 'cms_error', 'Chat');
                $this->error('WhatsApp API error: ' . $e->getMessage(), 500);
            }

            if ($result['success']) {
                $isPrivate = false;
                try {
                    $isPrivate = \EnvHelper::textContainsPrivateWord($caption ?? '');
                } catch (\Throwable $e) {
                    // ignore
                }

                $messageData = [
                    'phone' => $waNumber,
                    'type' => 'video',
                    'content' => $caption,
                    'media_url' => $mediaUrl,
                    'message_id' => $result['data']['id'] ?? null,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'status' => 'sent',
                    'private' => $isPrivate ? 1 : 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                $msgId = $result['local_id'] ?? null;
                if (!$msgId) {
                    $msgId = $db->insert('wa_messages_out', $messageData);
                } else {
                    $db->update('wa_messages_out', ['status' => 'sent', 'type' => 'video', 'media_url' => $mediaUrl], ['id' => $msgId]);
                }

                $lastMsgDisplay = $isPrivate ? 'o- 🔒 _Private Chat_' : 'o- 🎥 Video';
                $db->update('wa_conversations', [
                    'last_message' => $lastMsgDisplay,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['wa_number' => $waNumber]);

                $this->success([
                    'local_id' => $msgId,
                    'media_url' => $mediaUrl,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'channel' => 'ycloud',
                    'provider' => 'Y',
                ], 'Video sent successfully');
            } else {
                $this->error($result['error'] ?? 'Failed to send video', 500);
            }
        } catch (\Exception $e) {
            \Log::write('sendVideo ERROR: ' . $e->getMessage(), 'cms_error', 'Chat');
            $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Kirim media (gambar/video) via Fonnte (CSW Fonnte terbuka).
     */
    private function sendMediaViaFonnte($db, string $phone, string $mediaUrl, string $caption, ?string $senderCode, ?string $localPath, string $mediaType = 'image'): void
    {
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteService')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteService.php';
        }
        if (!class_exists('\\App\\Helpers\\CRM\\FonnteMessageStore')) {
            require_once __DIR__ . '/../../Helpers/CRM/FonnteMessageStore.php';
        }
        if (!class_exists('\\App\\Helpers\\CRM\\SapaanStatsHelper')) {
            require_once __DIR__ . '/../../Helpers/CRM/SapaanStatsHelper.php';
        }

        $mediaType = $mediaType === 'video' ? 'video' : 'image';
        $defaultExt = $mediaType === 'video' ? 'video.mp4' : 'image.jpg';
        $filename = null;
        if ($localPath && is_file($localPath)) {
            $filename = basename($localPath);
        } elseif ($mediaUrl !== '') {
            $filename = basename(parse_url($mediaUrl, PHP_URL_PATH) ?: $defaultExt);
        }

        $fonnte = new \App\Helpers\CRM\FonnteService();
        $options = ['url' => $mediaUrl];
        if ($filename) {
            $options['filename'] = $filename;
        }

        $messageText = $caption !== '' ? $caption : ' ';
        $result = $fonnte->sendMessage($phone, $messageText, $options);
        if (empty($result['success'])) {
            $label = $mediaType === 'video' ? 'video' : 'image';
            $this->error('Failed to send ' . $label . ' via Fonnte: ' . ($result['error'] ?? 'Unknown error'), 500);
        }

        $waNumber = CrmChatMergeHelper::normalizeWaNumber($phone);
        $code = ($senderCode !== null && trim($senderCode) !== '')
            ? trim($senderCode)
            : \App\Helpers\CRM\SapaanStatsHelper::SENDER_CODE_AUTOREPLY;
        $isHuman = \App\Helpers\CRM\SapaanStatsHelper::isHumanSenderCode($code);

        $statsFallback = $mediaType === 'video' ? '🎥 Video' : '📷 Image';
        $lastPreview = $mediaType === 'video' ? 'o- 🎥 Video' : 'o- 📷 Image';

        $store = new \App\Helpers\CRM\FonnteMessageStore($db);
        $responseData = is_array($result['data'] ?? null) ? $result['data'] : null;
        $fonnteId = \App\Helpers\CRM\FonnteService::extractMessageId($responseData);
        $stateId = \App\Helpers\CRM\FonnteService::extractStateId($responseData);
        $localId = $store->saveOutgoing($waNumber, trim($caption), [
            'type' => $mediaType,
            'media_url' => $mediaUrl,
            'fonnte_message_id' => $fonnteId,
            'fonnte_stateid' => $stateId,
            'source' => $isHuman ? 'human' : 'autoreply',
            'sender_code' => $code,
            'status' => 'sent',
        ]);

        if ($isHuman) {
            try {
                \App\Helpers\CRM\SapaanStatsHelper::recordStatsIfHuman(
                    $db,
                    $waNumber,
                    trim($caption) !== '' ? trim($caption) : $statsFallback,
                    $code
                );
            } catch (\Throwable $e) {
                // ignore
            }
        }

        $now = date('Y-m-d H:i:s');
        $conv = CrmChatMergeHelper::findWaConversation($db, $phone);
        $conversationId = 0;
        $kodeCabang = '00';
        $custId = null;
        $assignedUserId = null;
        if ($conv) {
            $conversationId = (int) ($conv->id ?? 0);
            $kodeCabang = $conv->code ?? '00';
            $custId = $conv->cust_id ?? null;
            $assignedUserId = $conv->assigned_user_id ?? null;
            $db->update('wa_conversations', [
                'last_message' => $lastPreview,
                'last_message_at' => $now,
                'updated_at' => $now,
            ], ['wa_number' => $conv->wa_number]);
        }

        CrmChatMergeHelper::pushWebSocket([
            'type' => 'agent_message_sent',
            'target_id' => '0',
            'notify' => false,
            'conversation_id' => $conversationId,
            'phone' => $waNumber,
            'contact_name' => $conv->contact_name ?? null,
            'kode_cabang' => $kodeCabang,
            'cust_id' => $custId,
            'assignment_user_id' => $assignedUserId,
            'message' => [
                'id' => $localId !== null ? ('F-' . $localId) : null,
                'wamid' => $fonnteId !== null ? (string) $fonnteId : null,
                'text' => $caption,
                'type' => $mediaType,
                'media_url' => $mediaUrl,
                'sender_code' => $code,
                'time' => $now,
                'status' => 'sent',
                'provider' => 'F',
            ],
        ]);

        $successMsg = $mediaType === 'video' ? 'Video sent via Fonnte' : 'Image sent via Fonnte';
        $this->success([
            'local_id' => $localId,
            'media_url' => $mediaUrl,
            'message_id' => $fonnteId,
            'channel' => 'fonnte',
            'provider' => 'F',
        ], $successMsg);
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

    /**
     * Upload video file to server (max 16MB — limit WA).
     */
    private function uploadVideoFile($file)
    {
        try {
            if ($file['size'] > 16 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File too large (max 16MB)'];
            }

            $allowedTypes = ['video/mp4', 'video/3gpp', 'video/webm', 'video/quicktime'];
            $mime = (string) ($file['type'] ?? '');
            if (!in_array($mime, $allowedTypes, true)) {
                return ['success' => false, 'error' => 'Invalid video type'];
            }

            $uploadDir = __DIR__ . '/../../../uploads/wa_media/' . date('Y/m/');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp4';
            $filename = uniqid('vid_') . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }

            $url = 'https://api.nalju.com/uploads/wa_media/' . date('Y/m/') . $filename;

            return [
                'success' => true,
                'path' => $uploadPath,
                'url' => $url,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update last_message_at for a conversation
     * Used when opening chat from search results or loaded more conversations
     */
    public function updateLastMessageAt()
    {
        try {
            $db = $this->db(0);
            $body = json_decode(file_get_contents('php://input'), true);
            
            $phone = $body['phone'] ?? null;
            if (!$phone) {
                $this->error('Phone number is required');
            }
            
            // Update last_message_at to current time
            $updated = $db->update('wa_conversations', [
                'last_message_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], ['wa_number' => $phone]);
            
            if ($updated) {
                $this->success(['updated' => true], 'Last message time updated');
            } else {
                $this->error('Failed to update last message time');
            }
            
        } catch (\Exception $e) {
            \Log::write("updateLastMessageAt ERROR: " . $e->getMessage(), 'cms_error', 'Chat');
            $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get last_message_at for a conversation
     * Used for polling to check if conversation has new messages
     */
    public function getLastMessageAt()
    {
        try {
            $db = $this->db(0);
            $phone = $_GET['phone'] ?? null;
            
            if (!$phone) {
                $this->error('Phone number is required');
            }
            
            // Get last_message_at from conversation (merged yCloud + Fonnte)
            if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
                require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
            }
            $conversation = \App\Helpers\CRM\CrmChatMergeHelper::findWaConversation($db, (string) $phone);
            
            if (!$conversation) {
                $this->error('Conversation not found');
            }

            $mergedLast = \App\Helpers\CRM\CrmChatMergeHelper::mergeLastMessageMeta(
                $db,
                (string) $phone,
                $conversation
            );
            
            $this->success([
                'phone' => $phone,
                'last_message_at' => $mergedLast['last_message_time'] ?? ($conversation->last_message_at ?? null),
                'conversation_id' => $conversation->id ?? null,
                'status' => $conversation->status ?? null,
            ], 'Last message time retrieved');
            
        } catch (\Exception $e) {
            \Log::write("getLastMessageAt ERROR: " . $e->getMessage(), 'cms_error', 'Chat');
            $this->error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Resolve sender code:
     * - admin: crm_users.code
     * - crew (cabang id_cabang): hardcoded CR
     */
    private function resolveSenderCode($db, $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $userRecord = $db
            ->where('LOWER(username)', strtolower((string) $userId))
            ->get('crm_users')
            ->row();

        if ($userRecord) {
            $role = strtolower($userRecord->role ?? '');
            if ($role === 'admin') {
                return !empty($userRecord->code) ? $userRecord->code : null;
            }
        }

        if (is_numeric($userId)) {
            $cabang = $this->db(1)
                ->where('id_cabang', (int) $userId)
                ->get('cabang')
                ->row();

            if ($cabang) {
                return 'CR';
            }
        }

        return null;
    }

    private function isAdminUser($db, $userId): bool
    {
        if ($userId === null || $userId === '') {
            $sessionUser = $_SESSION['mdl_crm_session']['user'] ?? null;
            if (is_array($sessionUser)) {
                if (strtolower((string) ($sessionUser['role'] ?? '')) === 'admin') {
                    return true;
                }
                $userId = $sessionUser['username'] ?? null;
            }
        }

        if ($userId === null || $userId === '') {
            return false;
        }

        $userRecord = $db
            ->where('LOWER(username)', strtolower((string) $userId))
            ->get('crm_users')
            ->row();

        return $userRecord && strtolower((string) ($userRecord->role ?? '')) === 'admin';
    }

    private function assertAdminCanReply($db, $userId = null): void
    {
        if (!$this->isAdminUser($db, $userId)) {
            $this->error('Hanya admin yang dapat membalas chat', 403);
        }
    }

}
