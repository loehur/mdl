<?php

namespace App\Helpers\WaDesk;

/**
 * TemplateSender — shared logic for sending WhatsApp templates from WaDesk.
 *
 * Used by the Blast cron so conversation creation, message storage, and YCloud
 * calls are consistent with Chat::send, but without the CSW-open guard.
 */
class TemplateSender
{
    private $db;
    private int $dbIndex;

    public function __construct($db, int $dbIndex = 7)
    {
        $this->db = $db;
        $this->dbIndex = $dbIndex;
    }

    /**
     * Send a WhatsApp template to one recipient.
     *
     * @param array  $key       ycloud_keys row (must include api_key_enc, phone_number, id, tenant_id, team_id)
     * @param array  $tpl       wa_templates row
     * @param array  $paramDefs wa_template_params rows for the template
     * @param string $phone     normalised phone number (digits only, 62xxx)
     * @param array  $rawParams associative map {param_name => value} from CSV row
     * @param int    $sentByUserId user id to store as sender (0 for system/cron)
     *
     * @return array{success:bool, message_id:int, conversation_id:int, error:string}
     */
    public function sendOne(
        array $key,
        array $tpl,
        array $paramDefs,
        string $phone,
        array $rawParams,
        int $sentByUserId = 0
    ): array {
        try {
            $limitGuard = new DailyKeyLimit($this->db);
            $quota = $limitGuard->reserve((int) $key['id'], $phone, $sentByUserId ?: null, 'blast');
            if (!$quota['allowed']) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => $quota['error'],
                ];
            }

            $teamId = (int) $key['team_id'];
            $tenantId = (int) $key['tenant_id'];
            $teamQuota = new TemplateQuota($this->db);
            $teamQuota->ensureRow($teamId, $tenantId);
            if (!$teamQuota->canConsume($teamId, 1)) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => 'Kuota template team habis',
                ];
            }

            $apiKey = Crypto::decrypt($key['api_key_enc']);
            $client = new YCloud($apiKey, $key['phone_number']);

            [$sendParams, $named, $indexed, $paramsForStore] = $this->resolveTemplateParams($paramDefs, $rawParams);

            $previewSource = (string) ($tpl['body_preview'] ?? '');
            if ($previewSource === '') {
                $previewSource = '[template] ' . $tpl['template_name'];
            }
            $preview = YCloud::renderPreview($previewSource, $named, $indexed);

            $result = $client->sendTemplate(
                $phone,
                $tpl['template_name'],
                $tpl['language'] ?: 'id',
                $sendParams
            );

            if (!$result['success']) {
                $yErr = $result['data']['error']['message']
                    ?? ($result['data']['message'] ?? 'Template send failed');
                return ['success' => false, 'message_id' => 0, 'conversation_id' => 0, 'error' => 'YCloud: ' . $yErr];
            }

            $conv = $this->getOrCreateConversation($key, $phone, null);
            $fakeUser = ['id' => $sentByUserId];
            $msgId = $this->storeOutbound($conv, $fakeUser, 'template', $preview, $tpl['template_name'], $paramsForStore, $result);
            $this->touchConversationOut((int) $conv['id'], $preview);

            $teamQuota->consume(
                $teamId,
                $tenantId,
                $sentByUserId ?: null,
                'blast',
                'message',
                $msgId
            );

            // Push WS notification (best-effort)
            try {
                Server::push([
                    'type'            => 'message_out',
                    'tenant_id'       => $tenantId,
                    'team_id'         => $teamId,
                    'conversation_id' => (int) $conv['id'],
                    'message_id'      => $msgId,
                ]);
            } catch (\Throwable $e) {
                // Non-fatal
            }

            return [
                'success'         => true,
                'message_id'      => $msgId,
                'conversation_id' => (int) $conv['id'],
                'error'           => '',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message_id' => 0, 'conversation_id' => 0, 'error' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers (mirrors Chat.php logic)
    // -------------------------------------------------------------------------

    private function getOrCreateConversation(array $key, string $phone, ?string $name): array
    {
        $existing = $this->db->query(
            "SELECT * FROM conversations WHERE ycloud_key_id = ? AND phone = ? LIMIT 1",
            [(int) $key['id'], $phone]
        )->row_array();

        if ($existing) {
            return $existing;
        }

        $id = (int) $this->db->insert('conversations', [
            'tenant_id'     => (int) $key['tenant_id'],
            'team_id'       => (int) $key['team_id'],
            'ycloud_key_id' => (int) $key['id'],
            'phone'         => $phone,
            'name'          => $name,
            'unread'        => 0,
        ]);

        return $this->db->query(
            "SELECT * FROM conversations WHERE id = ? LIMIT 1",
            [$id]
        )->row_array();
    }

    private function storeOutbound(array $conv, array $user, string $type, string $body, ?string $templateName, ?array $params, array $result): int
    {
        $ycloudId = $result['data']['id'] ?? ($result['data']['wamid'] ?? null);
        return (int) $this->db->insert('messages', [
            'conversation_id' => (int) $conv['id'],
            'direction'       => 'out',
            'type'            => $type,
            'body'            => $body,
            'template_name'   => $templateName,
            'params_json'     => $params !== null ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
            'ycloud_msg_id'   => $ycloudId,
            'external_id'     => $result['external_id'] ?? null,
            'status'          => 'sent',
            'sent_by_user_id' => ((int) ($user['id'] ?? 0)) ?: null,
        ]);
    }

    private function touchConversationOut(int $convId, string $preview): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->update('conversations', [
            'last_message'    => mb_substr($preview, 0, 500),
            'last_out_at'     => $now,
            'last_message_at' => $now,
        ], ['id' => $convId]);
    }

    /**
     * Map request values + template param definitions → YCloud send payload + preview maps.
     * Identical to Chat::resolveTemplateParams.
     *
     * Accepts raw keys as: param_name, "1"/"2", or component_index ("body_1", "button_1").
     *
     * @return array{0:array,1:array<string,string>,2:array<int,string>,3:array}
     */
    private function resolveTemplateParams(array $defs, array $rawParams): array
    {
        $named      = [];
        $indexed    = [];
        $sendParams = [];
        $paramsForStore = [];

        if ($defs === []) {
            if ($rawParams !== [] && array_keys($rawParams) !== range(0, count($rawParams) - 1)) {
                foreach ($rawParams as $k => $v) {
                    $named[(string) $k] = (string) $v;
                    $sendParams[] = [
                        'component'  => 'body',
                        'param_name' => (string) $k,
                        'text'       => (string) $v,
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
            $idx       = (int) ($def['param_index'] ?? 0);
            $csvKey    = $component . '_' . $idx;

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

            $entry = ['component' => $component, 'text' => $value];
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
            $sendParams[]     = $entry;
            $paramsForStore[] = $entry;
        }

        return [$sendParams, $named, $indexed, $paramsForStore];
    }
}
