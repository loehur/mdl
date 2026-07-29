<?php

namespace App\Controllers\WaDesk;

use App\Helpers\WaDesk\DailyKeyLimit as WaDeskDailyKeyLimit;
use App\Helpers\WaDesk\Crypto as WaDeskCrypto;
use App\Helpers\WaDesk\Server as WaDeskServer;
use App\Helpers\WaDesk\TemplateQuota as WaDeskTemplateQuota;
use App\Helpers\WaDesk\YCloud as WaDeskYCloud;

/**
 * Chat — conversations, messages, send free/template
 */
class Chat extends WaDeskController
{
    public function getConversations()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();

        [$visSql, $binds] = $this->visibilitySql('c');
        $q = trim((string) $this->query('q', ''));

        $sql = "SELECT c.*, k.label AS key_label, k.phone_number AS wa_number, t.name AS team_name
                FROM conversations c
                INNER JOIN ycloud_keys k ON k.id = c.ycloud_key_id
                LEFT JOIN teams t ON t.id = c.team_id
                WHERE {$visSql}";
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
            $row['csw_open'] = WaDeskYCloud::isWithinCsw($row['last_in_at'] ?? null);
        }

        $this->success(['conversations' => $rows]);
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
                'csw_open' => WaDeskYCloud::isWithinCsw($conv['last_in_at'] ?? null),
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

    public function send()
    {
        $this->verifyAuth();
        $user = $this->requireChatUser();
        if (!$this->isPost()) {
            $this->error('Method not allowed', 405);
        }

        $body = $this->getBody();
        $mode = $body['mode'] ?? 'free';
        $ycloudKeyId = (int) ($body['ycloud_key_id'] ?? 0);
        $phone = $this->normalizePhone((string) ($body['phone'] ?? ''));
        $conversationId = (int) ($body['conversation_id'] ?? 0);

        if ($conversationId > 0) {
            $conv = $this->findAccessibleConversation($conversationId);
            if (!$conv) {
                $this->error('Conversation tidak ditemukan', 404);
            }
            $ycloudKeyId = (int) $conv['ycloud_key_id'];
            $phone = $conv['phone'];
        } else {
            if ($ycloudKeyId <= 0 || $phone === '') {
                $this->error('ycloud_key_id dan phone wajib untuk chat baru', 400);
            }
            $conv = null;
        }

        $key = $this->findAccessibleKey($ycloudKeyId);
        if (!$key) {
            $this->error('API key tidak dapat diakses', 403);
        }

        if (!$conv) {
            $conv = $this->getOrCreateConversation($key, $phone, $body['name'] ?? null);
        }

        $cswOpen = WaDeskYCloud::isWithinCsw($conv['last_in_at'] ?? null);
        $apiKey = WaDeskCrypto::decrypt($key['api_key_enc']);
        $client = new WaDeskYCloud($apiKey, $key['phone_number']);

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
                $tpl = $this->findTemplateForKey($templateId, $key);
                if (!$tpl) {
                    $this->error('Template tidak ditemukan', 404);
                }
                $templateName = $tpl['template_name'];
                $language = $tpl['language'] ?: $language;
                // Check if button_meta migration has been applied
                $hasButtonMeta = $this->columnExists('wa_template_params', 'button_sub_type');
                $paramCols = $hasButtonMeta
                    ? "component, button_sub_type, button_index, param_index, param_name, label, is_required"
                    : "component, param_index, param_name, label, is_required";
                $tplParamDefs = $this->db($this->db_index)->query(
                    "SELECT $paramCols FROM wa_template_params WHERE template_id = ?
                     ORDER BY FIELD(component,'header','body','button'), param_index ASC",
                    [$templateId]
                )->result_array();
            }
            if ($templateName === '') {
                $this->error('template_name atau template_id wajib', 400);
            }

            $limitGuard = new WaDeskDailyKeyLimit($this->db($this->db_index));
            $quota = $limitGuard->reserve((int) $key['id'], $phone, (int) $user['id'], 'template');
            if (!$quota['allowed']) {
                $this->error($quota['error'], 422, [
                    'daily_limit' => $quota['limit'],
                    'used_today' => $quota['used'],
                    'api_key_id' => (int) $key['id'],
                    'phone' => $phone,
                ]);
            }

            // Admin has no team — skip per-team template quota (TL/agent only)
            $isAdmin = ($user['role'] ?? '') === 'admin';
            $teamQuota = new WaDeskTemplateQuota($this->db($this->db_index));
            $teamId = (int) $key['team_id'];
            if (!$isAdmin) {
                $teamQuota->ensureRow($teamId, (int) $key['tenant_id']);
                if (!$teamQuota->canConsume($teamId, 1)) {
                    $this->error('Kuota template team habis', 422, [
                        'team_id' => $teamId,
                        'balance' => $teamQuota->getBalance($teamId),
                    ]);
                }
            }

            [$sendParams, $named, $indexed, $paramsForStore] = $this->resolveTemplateParams(
                $tplParamDefs,
                $rawParams
            );

            $previewSource = (string) ($tpl['body_preview'] ?? '');
            if ($previewSource === '') {
                $previewSource = (string) ($body['message'] ?? '');
            }
            if ($previewSource === '') {
                $previewSource = '[template] ' . $templateName;
            }
            $preview = WaDeskYCloud::buildFilledPreview(
                $previewSource,
                $tplParamDefs,
                $named,
                $indexed
            );

            // --- DEBUG LOG ---
            $debugLog = [
                'ts'            => date('Y-m-d H:i:s'),
                'phone'         => $phone,
                'template_name' => $templateName,
                'language'      => $language,
                'key_id'        => $key['id'],
                'key_phone'     => $key['phone_number'] ?? '',
                'api_key_hash'  => $key['api_key_hash'] ?? 'n/a',
                'tpl_param_defs'=> $tplParamDefs,
                'raw_params'    => $rawParams,
                'send_params'   => $sendParams,
            ];
            file_put_contents('/tmp/wadesk_send_template.log',
                json_encode($debugLog, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n---\n",
                FILE_APPEND);
            // --- END DEBUG LOG ---

            $result = $client->sendTemplate($phone, $templateName, $language, $sendParams);

            // --- DEBUG LOG RESULT ---
            file_put_contents('/tmp/wadesk_send_template.log',
                'RESULT: ' . json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n===\n",
                FILE_APPEND);
            // --- END DEBUG LOG RESULT ---

            if (!$result['success']) {
                $yErr = $result['data']['error']['message'] ?? ($result['data']['message'] ?? 'Template send failed');
                $this->error('YCloud Reject: ' . $yErr, 502, $result['data']);
            }

            $msgId = $this->storeOutbound($conv, $user, 'template', $preview, $templateName, $paramsForStore, $result);
            $this->touchConversationOut($conv['id'], $preview);

            $consumedBalance = null;
            if (!$isAdmin) {
                $consumed = $teamQuota->consume(
                    $teamId,
                    (int) $key['tenant_id'],
                    (int) $user['id'],
                    'chat',
                    'message',
                    $msgId
                );
                if (!$consumed['ok']) {
                    // Race: YCloud already charged; keep message, do not fail the send response
                    try {
                        \Log::write(
                            'WaDesk template quota consume failed after YCloud success: team=' . $teamId . ' msg=' . $msgId,
                            'wadesk',
                            'Quota'
                        );
                    } catch (\Throwable $e) {
                        /* ignore */
                    }
                }
                $consumedBalance = $consumed['balance'] ?? $teamQuota->getBalance($teamId);
            }

            WaDeskServer::push([
                'type' => 'message_out',
                'tenant_id' => (int) $key['tenant_id'],
                'team_id' => (int) $key['team_id'],
                'conversation_id' => (int) $conv['id'],
                'message_id' => $msgId,
            ]);

            $this->success([
                'message_id' => $msgId,
                'conversation_id' => (int) $conv['id'],
                'mode' => 'template',
                'csw_open' => false,
                'preview' => $preview,
                'ycloud' => $result['data'],
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

        $limitGuard = new WaDeskDailyKeyLimit($this->db($this->db_index));
        $quota = $limitGuard->reserve((int) $key['id'], $phone, (int) $user['id'], 'free');
        if (!$quota['allowed']) {
            $this->error($quota['error'], 422, [
                'daily_limit' => $quota['limit'],
                'used_today' => $quota['used'],
                'api_key_id' => (int) $key['id'],
                'phone' => $phone,
            ]);
        }

        $result = $client->sendFreeText($phone, $message, $body['reply_to'] ?? null);
        if (!$result['success']) {
            $yErr = $result['data']['error']['message'] ?? ($result['data']['message'] ?? 'Send failed');
            $this->error('YCloud Reject: ' . $yErr, 502, $result['data']);
        }

        $msgId = $this->storeOutbound($conv, $user, 'text', $message, null, null, $result);
        $this->touchConversationOut($conv['id'], $message);

        WaDeskServer::push([
            'type' => 'message_out',
            'tenant_id' => (int) $key['tenant_id'],
            'team_id' => (int) $key['team_id'],
            'conversation_id' => (int) $conv['id'],
            'message_id' => $msgId,
        ]);

        $this->success([
            'message_id' => $msgId,
            'conversation_id' => (int) $conv['id'],
            'mode' => 'free',
            'csw_open' => true,
            'ycloud' => $result['data'],
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

    private function findAccessibleKey(int $keyId): ?array
    {
        $user = $this->currentUser();
        if ($user['role'] === 'admin') {
            return $this->db($this->db_index)->query(
                "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? AND status = 'active' LIMIT 1",
                [$keyId, (int) $user['tenant_id']]
            )->row_array() ?: null;
        }
        return $this->db($this->db_index)->query(
            "SELECT * FROM ycloud_keys WHERE id = ? AND tenant_id = ? AND team_id = ? AND status = 'active' LIMIT 1",
            [$keyId, (int) $user['tenant_id'], (int) $user['team_id']]
        )->row_array() ?: null;
    }

    private function getOrCreateConversation(array $key, string $phone, ?string $name): array
    {
        $existing = $this->db($this->db_index)->query(
            "SELECT * FROM conversations WHERE ycloud_key_id = ? AND phone = ? LIMIT 1",
            [(int) $key['id'], $phone]
        )->row_array();
        if ($existing) {
            return $existing;
        }

        $id = (int) $this->db($this->db_index)->insert('conversations', [
            'tenant_id' => (int) $key['tenant_id'],
            'team_id' => (int) $key['team_id'],
            'ycloud_key_id' => (int) $key['id'],
            'phone' => $phone,
            'name' => $name,
            'unread' => 0,
        ]);

        return $this->db($this->db_index)->query(
            "SELECT * FROM conversations WHERE id = ? LIMIT 1",
            [$id]
        )->row_array();
    }

    private function storeOutbound(array $conv, array $user, string $type, string $body, ?string $templateName, ?array $params, array $result): int
    {
        $ycloudId = $result['data']['id'] ?? ($result['data']['wamid'] ?? null);
        return (int) $this->db($this->db_index)->insert('messages', [
            'conversation_id' => (int) $conv['id'],
            'direction' => 'out',
            'type' => $type,
            'body' => $body,
            'template_name' => $templateName,
            'params_json' => $params !== null ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
            'ycloud_msg_id' => $ycloudId,
            'external_id' => $result['external_id'] ?? null,
            'status' => 'sent',
            'sent_by_user_id' => (int) $user['id'],
        ]);
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
}
