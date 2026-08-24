<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\DailyKeyLimit as WaDeskDailyKeyLimit;
use App\Helpers\WaDesk\FreeTextSpamGuard;
use App\Helpers\WaDesk\Server as WaDeskServer;
use App\Helpers\WaDesk\TemplateQuota as WaDeskTemplateQuota;
use App\Helpers\WaDesk\Kirimin as WaDeskKirimin;

/**
 * Chat — conversations, messages, send free/template
 */
class Chat extends WaDeskController
{
    public function getConversations()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        [$visSql, $visBinds] = $this->visibilitySql('c');
        $q = trim((string) $this->query('q', ''));
        $filter = strtolower(trim((string) $this->query('filter', 'all')));

        $tbl = $this->channelsTable();
        $unreadRow = $this->db($this->db_index)->query(
            "SELECT COUNT(*) AS cnt
             FROM conversations c
             INNER JOIN {$tbl} k ON k.id = c.channel_id
             WHERE {$visSql} AND c.unread > 0",
            $visBinds
        )->row_array();
        $unreadCount = (int) ($unreadRow['cnt'] ?? 0);

        $binds = $visBinds;
        $sql = "SELECT c.*, k.label AS key_label, k.label AS channel_label,
                       k.phone_number AS wa_number, t.name AS team_name,
                       c.channel_id AS channel_id
                FROM conversations c
                INNER JOIN {$tbl} k ON k.id = c.channel_id
                LEFT JOIN teams t ON t.id = c.team_id
                WHERE {$visSql}";
        if ($filter === 'unread') {
            $sql .= ' AND c.unread > 0';
        } elseif ($filter === 'open') {
            $sql .= ' AND c.last_in_at IS NOT NULL AND c.last_in_at >= DATE_SUB(NOW(), INTERVAL 23 HOUR)';
        }
        if ($q !== '') {
            $sql .= ' AND (c.phone LIKE ? OR c.name LIKE ? OR c.last_message LIKE ?)';
            $like = '%' . $q . '%';
            $binds[] = $like;
            $binds[] = $like;
            $binds[] = $like;
        }
        $sql .= ' ORDER BY COALESCE(c.last_message_at, c.updated_at, c.created_at) DESC LIMIT 200';

        $rows = $this->db($this->db_index)->query($sql, $binds)->result_array();
        foreach ($rows as &$row) {
            $row['csw_open'] = WaDeskKirimin::isWithinCsw($row['last_in_at'] ?? null);
        }

        $this->success([
            'conversations' => $rows,
            'unread_count' => $unreadCount,
        ]);
    }

    public function getMessages()
    {
        $this->verifyAuth();
        $this->requireChatUser();

        $convId = (int) $this->query('conversation_id', 0);
        if ($convId <= 0) {
            $this->error('conversation_id required', 400);
        }

        $conv = $this->findAccessibleConversation($convId);
        if (!$conv) {
            $this->error('Conversation tidak ditemukan', 404);
        }

        $beforeId = (int) $this->query('before_id', 0);
        $limit = min(100, max(1, (int) $this->query('limit', 50)));

        $sql = "SELECT m.*, u.name AS sender_name
                FROM messages m
                LEFT JOIN users u ON u.id = m.sent_by_user_id
                WHERE m.conversation_id = ?";
        $binds = [$convId];
        if ($beforeId > 0) {
            $sql .= ' AND m.id < ?';
            $binds[] = $beforeId;
        }
        $sql .= ' ORDER BY m.id DESC LIMIT ' . (int) $limit;

        $rows = array_reverse($this->db($this->db_index)->query($sql, $binds)->result_array());
        foreach ($rows as &$m) {
            if (!empty($m['params_json']) && is_string($m['params_json'])) {
                $m['params'] = json_decode($m['params_json'], true);
            }
        }

        $this->success([
            'conversation' => array_merge($conv, [
                'csw_open' => WaDeskKirimin::isWithinCsw($conv['last_in_at'] ?? null),
            ]),
            'messages' => $rows,
        ]);
    }

    public function markRead()
    {
        $this->verifyAuth();
        $this->requireChatUser();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $convId = (int) ($body['conversation_id'] ?? 0);
        $conv = $this->findAccessibleConversation($convId);
        if (!$conv) {
            $this->error('Conversation tidak ditemukan', 404);
        }

        $this->db($this->db_index)->update('conversations', ['unread' => 0], ['id' => $convId]);
        $this->success(null, 'Marked read');
    }

    /** POST /WaDesk/Chat/moderateTemplateParams — AI cek nilai parameter template */
    public function moderateTemplateParams()
    {
        $this->verifyAuth();
        $user = $this->requireOperationalTeam();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $templateId = (int) ($body['template_id'] ?? 0);
        $rawParams = $body['template_params'] ?? [];
        if (!is_array($rawParams)) {
            $rawParams = [];
        }

        if ($templateId <= 0) {
            $this->error('template_id wajib', 400);
        }

        $tpl = $this->findTemplateForTenant($templateId, (int) $user['tenant_id']);
        if (!$tpl) {
            $this->error('Template tidak ditemukan', 404);
        }

        if ($this->getTenantOpenAiApiKey((int) $user['tenant_id']) === '') {
            $this->error('OpenAI API key belum diatur. Simpan di Admin → OpenAI.', 400);
        }

        $tplParamDefs = $this->loadTemplateParamDefs($templateId);
        $result = $this->moderateTemplateParamValues((int) $user['tenant_id'], $tplParamDefs, $rawParams);

        $this->success([
            'safe' => (bool) ($result['safe'] ?? false),
            'reason' => (string) ($result['reason'] ?? ''),
        ], ($result['safe'] ?? false) ? 'Parameter aman' : 'Parameter ditolak');
    }

    public function send()
    {
        $this->verifyAuth();
        $user = $this->requireOperationalTeam();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $mode = $body['mode'] ?? 'free';
        $channelId = (int) ($body['channel_id'] ?? 0);
        $phone = $this->normalizePhone((string) ($body['phone'] ?? ''));
        $conversationId = (int) ($body['conversation_id'] ?? 0);

        if ($conversationId > 0) {
            $conv = $this->findAccessibleConversation($conversationId);
            if (!$conv) {
                $this->error('Conversation tidak ditemukan', 404);
            }
            $channelId = (int) $conv['channel_id'];
            $phone = $conv['phone'];
        } else {
            if ($channelId <= 0 || $phone === '') {
                $this->error('channel_id dan phone wajib untuk chat baru', 400);
            }
            $conv = null;
        }

        $channel = $this->findAccessibleChannel($channelId);
        if (!$channel) {
            $this->error('Channel tidak dapat diakses', 403);
        }

        if (!$conv) {
            $conv = $this->getOrCreateConversation($channel, $phone, $body['name'] ?? null);
        }

        $cswOpen = WaDeskKirimin::isWithinCsw($conv['last_in_at'] ?? null);
        $deviceId = trim((string) ($channel['device_id'] ?? ''));
        if ($deviceId === '') {
            $this->error('Channel belum punya device_id Kirimin', 400);
        }
        $client = $this->requireKiriminConfigured((int) $user['tenant_id']);

        if ($mode === 'template') {
            if ($cswOpen) {
                $this->error('CSW terbuka — gunakan free text, bukan template', 400, [
                    'csw_open' => true,
                ]);
            }
            $templateId = (int) ($body['template_id'] ?? 0);
            $templateName = trim((string) ($body['template_name'] ?? ''));
            $language = trim((string) ($body['language'] ?? 'id')) ?: 'id';
            $rawParams = $body['template_params'] ?? [];
            if (!is_array($rawParams)) {
                $rawParams = [];
            }

            $tpl = null;
            $tplParamDefs = [];
            if ($templateId > 0) {
                $tpl = $this->findTemplateForTenant($templateId, (int) $user['tenant_id']);
                if (!$tpl) {
                    \Log::write(json_encode([
                        'template_id'  => $templateId,
                        'channel_id'   => $channel['id'] ?? null,
                        'device_id'    => $deviceId ?? null,
                    ], JSON_UNESCAPED_UNICODE), 'wadesk', 'template_not_found');
                    $this->error('Template tidak ditemukan', 404);
                }
                $templateName = $tpl['template_name'];
                $language = $tpl['language'] ?: $language;
                $tplParamDefs = $this->loadTemplateParamDefs($templateId);
                $this->assertTemplateOnChannel($templateId, $channel, (int) $user['tenant_id']);
            }
            if ($templateName === '') {
                $this->error('template_name atau template_id wajib', 400);
            }

            $limitGuard = new WaDeskDailyKeyLimit($this->db($this->db_index));
            $quota = $limitGuard->canSend((int) $channel['tenant_id'], $phone);
            if (!$quota['allowed']) {
                $this->error($quota['error'], 422, [
                    'daily_limit' => $quota['limit'],
                    'used_today' => $quota['used'],
                    'tenant_id' => (int) $channel['tenant_id'],
                    'phone' => $phone,
                ]);
            }

            // Per-team template quota (admin ikut team saat sudah join)
            $teamQuota = new WaDeskTemplateQuota($this->db($this->db_index));
            $teamId = (int) $channel['team_id'];
            $teamQuota->ensureRow($teamId, (int) $channel['tenant_id']);
            if (!$teamQuota->canConsume($teamId, 1)) {
                $this->error('Kuota template team habis', 422, [
                    'team_id' => $teamId,
                    'balance' => $teamQuota->getBalance($teamId),
                ]);
            }

            [$sendParams, $named, $indexed, $paramsForStore] = $this->resolveTemplateParams(
                $tplParamDefs,
                $rawParams
            );

            $lengthErrors = $this->validateTemplateParamValues($tplParamDefs, $rawParams);
            if ($lengthErrors !== []) {
                $this->error(implode('; ', $lengthErrors), 422);
            }

            $this->requireTemplateParamsSafe((int) $user['tenant_id'], $tplParamDefs, $rawParams);

            $previewSource = (string) ($tpl['body_preview'] ?? '');
            if ($previewSource === '') {
                $previewSource = (string) ($body['message'] ?? '');
            }
            if ($previewSource === '') {
                $previewSource = '[template] ' . $templateName;
            }
            $preview = WaDeskKirimin::buildFilledPreview(
                $previewSource,
                $tplParamDefs,
                $named,
                $indexed
            );

            \Log::write(json_encode([
                'phone'          => $phone,
                'template_name'  => $templateName,
                'language'       => $language,
                'channel_id'     => $channel['id'],
                'device_id'      => $deviceId,
                'tpl_param_defs' => $tplParamDefs,
                'raw_params'     => $rawParams,
                'send_params'    => $sendParams,
            ], JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_req');

            $result = $client->sendTemplate($deviceId, $phone, $templateName, $language, $sendParams);

            \Log::write('RESULT: ' . json_encode($result, JSON_UNESCAPED_UNICODE), 'wadesk', 'send_template_res');

            if (!$result['success']) {
                $provErr = \App\Helpers\WaDesk\TemplateFailLogger::extractProviderError($result);
                $this->logTemplateSendFailure([
                    'tenant_id' => (int) $channel['tenant_id'],
                    'team_id' => (int) $channel['team_id'],
                    'channel_id' => (int) $channel['id'],
                    'user_id' => (int) $user['id'],
                    'conversation_id' => (int) $conv['id'],
                    'source' => 'chat',
                    'phone' => $phone,
                    'template_id' => $templateId > 0 ? $templateId : null,
                    'template_name' => $templateName,
                    'language' => $language,
                    'device_id' => $deviceId,
                    'preview' => $preview,
                    'error_message' => $provErr['message'],
                    'error_code' => $provErr['code'],
                    'http_code' => $provErr['http_code'],
                    'request' => [
                        'phone' => $phone,
                        'template_name' => $templateName,
                        'language' => $language,
                        'device_id' => $deviceId,
                        'channel_id' => (int) $channel['id'],
                        'template_params' => $rawParams,
                        'send_params' => $sendParams,
                        'external_id' => $result['external_id'] ?? null,
                    ],
                    'response' => $result,
                ]);
                $yErr = $provErr['message'];
                $this->error('Kirimin Reject: ' . $yErr, 502, $result['data']);
            }

            $limitGuard->recordSuccess((int) $channel['tenant_id'], $phone, (int) $user['id'], 'template');

            $msgId = $this->storeOutbound($conv, $user, 'template', $preview, $templateName, $paramsForStore, $result);
            $this->touchConversationOut($conv['id'], $preview);

            $consumedBalance = null;
            $consumed = $teamQuota->consume(
                $teamId,
                (int) $channel['tenant_id'],
                (int) $user['id'],
                'chat',
                'message',
                $msgId
            );
            if (!$consumed['ok']) {
                // Race: provider already charged; keep message, do not fail the send response
                try {
                    \Log::write(
                        'WaDesk template quota consume failed after Kirimin success: team=' . $teamId . ' msg=' . $msgId,
                        'wadesk',
                        'Quota'
                    );
                } catch (\Throwable $e) {
                    /* ignore */
                }
            }
            $consumedBalance = $consumed['balance'] ?? $teamQuota->getBalance($teamId);

            WaDeskServer::push([
                'type' => 'message_out',
                'tenant_id' => (int) $channel['tenant_id'],
                'team_id' => (int) $channel['team_id'],
                'conversation_id' => (int) $conv['id'],
                'message_id' => $msgId,
            ]);

            $this->success([
                'message_id' => $msgId,
                'conversation_id' => (int) $conv['id'],
                'mode' => 'template',
                'csw_open' => false,
                'preview' => $preview,
                'kirimin' => $result['data'],
                'template_quota_balance' => $consumedBalance,
            ], 'Template terkirim');
        }

        // free text
        if (!$cswOpen) {
            $this->error('CSW tertutup — kirim template untuk membuka percakapan', 400, [
                'csw_open' => false,
            ]);
        }

        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            $this->error('message wajib', 400);
        }

        $originalMessage = $message;
        $polish = $this->polishFreeMessageText((int) $user['tenant_id'], $message);
        if (!$polish['status']) {
            $this->error($polish['reason'] ?: 'Pesan ditolak AI', 422, [
                'status' => false,
                'reason' => $polish['reason'],
            ]);
        }
        $message = trim($polish['new_words']);
        if ($message === '') {
            $this->error('AI gagal merapikan pesan', 422);
        }

        $pendingOutbound = $this->findLastUnansweredOutboundText((int) $conv['id']);
        if ($pendingOutbound !== null) {
            $spam = $this->checkFreeTextDuplicateSpam(
                (int) $user['tenant_id'],
                (string) ($pendingOutbound['body'] ?? ''),
                $message
            );
            if (!empty($spam['duplicate_spam'])) {
                $this->error($spam['reason'] ?: FreeTextSpamGuard::REJECT_REASON, 422, [
                    'status' => false,
                    'reason' => $spam['reason'] ?: FreeTextSpamGuard::REJECT_REASON,
                ]);
            }
        }

        $limitGuard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        $quota = $limitGuard->canSend((int) $channel['tenant_id'], $phone);
        if (!$quota['allowed']) {
            $this->error($quota['error'], 422, [
                'daily_limit' => $quota['limit'],
                'used_today' => $quota['used'],
                'tenant_id' => (int) $channel['tenant_id'],
                'phone' => $phone,
            ]);
        }

        $result = $client->sendFreeText($deviceId, $phone, $message, $body['reply_to'] ?? null);
        if (!$result['success']) {
            $yErr = $result['data']['error']['message'] ?? ($result['data']['message'] ?? 'Send failed');
            $this->error('Kirimin Reject: ' . $yErr, 502, $result['data']);
        }

        $limitGuard->recordSuccess((int) $channel['tenant_id'], $phone, (int) $user['id'], 'free');

        $msgId = $this->storeOutbound($conv, $user, 'text', $message, null, null, $result, $originalMessage);
        $this->touchConversationOut($conv['id'], $message);

        WaDeskServer::push([
            'type' => 'message_out',
            'tenant_id' => (int) $channel['tenant_id'],
            'team_id' => (int) $channel['team_id'],
            'conversation_id' => (int) $conv['id'],
            'message_id' => $msgId,
        ]);

        $this->success([
            'message_id' => $msgId,
            'conversation_id' => (int) $conv['id'],
            'mode' => 'free',
            'csw_open' => true,
            'kirimin' => $result['data'],
            'ai_polished' => $message !== $originalMessage,
            'sent_message' => $message,
        ], 'Pesan terkirim');
    }

    private function findAccessibleConversation(int $id): ?array
    {
        [$visSql, $binds] = $this->visibilitySql('c');
        $binds[] = $id;
        return $this->db($this->db_index)->query(
            "SELECT c.* FROM conversations c WHERE {$visSql} AND c.id = ? LIMIT 1",
            $binds
        )->row_array() ?: null;
    }

    private function findAccessibleChannel(int $channelId): ?array
    {
        $user = $this->currentUser();
        $tbl = $this->channelsTable();
        if (!$this->hasOperationalTeam($user)) {
            return null;
        }
        return $this->db($this->db_index)->query(
            "SELECT * FROM {$tbl} WHERE id = ? AND tenant_id = ? AND team_id = ? AND status = 'active' LIMIT 1",
            [$channelId, (int) $user['tenant_id'], (int) $user['team_id']]
        )->row_array() ?: null;
    }

    private function getOrCreateConversation(array $channel, string $phone, ?string $name): array
    {
        $existing = $this->db($this->db_index)->query(
            "SELECT * FROM conversations WHERE channel_id = ? AND phone = ? LIMIT 1",
            [(int) $channel['id'], $phone]
        )->row_array();
        if ($existing) {
            return $existing;
        }

        $id = (int) $this->db($this->db_index)->insert('conversations', [
            'tenant_id' => (int) $channel['tenant_id'],
            'team_id' => (int) $channel['team_id'],
            'channel_id' => (int) $channel['id'],
            'phone' => $phone,
            'name' => $name,
            'unread' => 0,
        ]);

        return $this->db($this->db_index)->query(
            "SELECT * FROM conversations WHERE id = ? LIMIT 1",
            [$id]
        )->row_array();
    }

    private function storeOutbound(
        array $conv,
        array $user,
        string $type,
        string $body,
        ?string $templateName,
        ?array $params,
        array $result,
        ?string $bodyRaw = null
    ): int {
        $providerId = $result['data']['message_id']
            ?? $result['data']['id']
            ?? ($result['data']['wamid'] ?? null);
        $row = [
            'conversation_id' => (int) $conv['id'],
            'direction' => 'out',
            'type' => $type,
            'body' => $body,
            'template_name' => $templateName,
            'params_json' => $params !== null ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
            'provider_msg_id' => $providerId,
            'external_id' => $result['external_id'] ?? null,
            'status' => 'sent',
            'sent_by_user_id' => (int) $user['id'],
        ];
        if ($bodyRaw !== null && trim($bodyRaw) !== '') {
            $row['body_raw'] = $bodyRaw;
        }
        return (int) $this->db($this->db_index)->insert('messages', $row);
    }

    /** Last free-text outbound not yet followed by any inbound reply. */
    private function findLastUnansweredOutboundText(int $conversationId): ?array
    {
        if ($conversationId <= 0) {
            return null;
        }

        $latest = $this->db($this->db_index)->query(
            "SELECT id, direction, type, body
             FROM messages
             WHERE conversation_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$conversationId]
        )->row_array();

        if (!$latest || ($latest['direction'] ?? '') !== 'out') {
            return null;
        }

        if (($latest['type'] ?? '') !== 'text') {
            return null;
        }

        $body = trim((string) ($latest['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        return $latest;
    }

    /**
     * Map request values + template param definitions → YCloud send payload + preview maps.
     *
     * Accepts raw keys as: param_name, "1"/"2", or component_index ("body_1", "button_1").
     *
     * @return array{0:array,1:array<string,string>,2:array<int,string>,3:array}
     */
    private function resolveTemplateParams(array $defs, array $rawParams): array
    {
        $named = [];
        $indexed = [];
        $sendParams = [];
        $paramsForStore = [];

        // No defs: pass through laundry-style assoc or list
        if ($defs === []) {
            if ($rawParams !== [] && array_keys($rawParams) !== range(0, count($rawParams) - 1)) {
                foreach ($rawParams as $k => $v) {
                    $named[(string) $k] = (string) $v;
                    $sendParams[] = [
                        'component' => 'body',
                        'param_name' => (string) $k,
                        'text' => (string) $v,
                    ];
                }
                return [$sendParams, $named, $indexed, $rawParams];
            }
            $list = array_values($rawParams);
            foreach ($list as $i => $v) {
                $indexed[$i + 1] = (string) $v;
            }
            return [$list, $named, $indexed, $list];
        }

        $isList = $rawParams === [] || array_keys($rawParams) === range(0, count($rawParams) - 1);
        $listCursor = 0;

        foreach ($defs as $def) {
            $component = strtolower((string) ($def['component'] ?? 'body'));
            $paramName = trim((string) ($def['param_name'] ?? ''));
            $idx = (int) ($def['param_index'] ?? 0);
            $csvKey = $component . '_' . $idx;

            $value = '';
            if ($paramName !== '' && !$isList && array_key_exists($paramName, $rawParams)) {
                $value = (string) $rawParams[$paramName];
            } elseif (!$isList && array_key_exists($csvKey, $rawParams)) {
                $value = (string) $rawParams[$csvKey];
            } elseif (!$isList && array_key_exists((string) $idx, $rawParams)) {
                $value = (string) $rawParams[(string) $idx];
            } elseif ($isList && array_key_exists($listCursor, $rawParams)) {
                $value = (string) $rawParams[$listCursor];
                $listCursor++;
            } elseif ($isList && array_key_exists($idx - 1, $rawParams)) {
                $value = (string) $rawParams[$idx - 1];
            }

            if ($paramName !== '') {
                $named[$paramName] = $value;
            }
            if ($idx > 0) {
                $indexed[$idx] = $value;
            }

            $entry = [
                'component' => $component,
                'text' => $value,
            ];
            if ($paramName !== '') {
                $entry['param_name'] = $paramName;
            }
            if ($component === 'button') {
                $entry['button_sub_type'] = trim((string) ($def['button_sub_type'] ?? '')) ?: 'url';
                $btnIndex = $def['button_index'] ?? null;
                $entry['button_index'] = ($btnIndex === null || $btnIndex === '')
                    ? max(0, $idx - 1)
                    : (int) $btnIndex;
                $entry['param_index'] = $idx;
            }
            $sendParams[] = $entry;
            $paramsForStore[] = $entry;
        }

        return [$sendParams, $named, $indexed, $paramsForStore];
    }

    private function touchConversationOut(int $convId, string $preview): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db($this->db_index)->update('conversations', [
            'last_message' => mb_substr($preview, 0, 500),
            'last_out_at' => $now,
            'last_message_at' => $now,
        ], ['id' => $convId]);
    }

    /** @return array<int,array> */
    private function loadTemplateParamDefs(int $templateId): array
    {
        $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
        $hasMaxlength = $this->columnExists('wa_template_params', 'maxlength');
        $paramCols = $hasButtonMeta
            ? "component, button_sub_type, button_index, param_index, param_name, label, is_required"
            : "component, param_index, param_name, label, is_required";
        if ($hasMaxlength) {
            $paramCols .= ', maxlength';
        }

        return $this->db($this->db_index)->query(
            "SELECT $paramCols FROM wa_template_params WHERE template_id = ?
             ORDER BY FIELD(component,'header','body','button'), param_index ASC",
            [$templateId]
        )->result_array();
    }
}
