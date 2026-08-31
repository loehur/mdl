<?php

namespace App\Helpers\WaDesk;

/**
 * TemplateSender — shared logic for sending WhatsApp templates from WaDesk.
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
     * @param array $channel wa_channels row (device_id, phone_number, id, tenant_id, team_id)
     */
    public function sendOne(
        array $channel,
        array $tpl,
        array $paramDefs,
        string $phone,
        array $rawParams,
        int $sentByUserId = 0,
        bool $skipParamModeration = false,
        array $logMeta = [],
        ?int $teamId = null
    ): array {
        try {
            if (array_key_exists('template_sending_enabled', $channel)
                && (int) $channel['template_sending_enabled'] !== 1) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => 'Template sending is disabled for this channel',
                ];
            }

            $tenantId = (int) $channel['tenant_id'];
            $wabaId = trim((string) ($channel['waba_id'] ?? ''));
            if ($wabaId === '') {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => 'WABA ID belum diatur untuk channel ini. Isi manual di Admin → Channel.',
                ];
            }

            $limitGuard = new DailyKeyLimit($this->db);
            $quota = $limitGuard->canSend($tenantId, $phone);
            if (!$quota['allowed']) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => $quota['error'],
                ];
            }

            $teamId = $teamId ?: ((int) ($channel['team_id'] ?? 0));
            if ($teamId <= 0) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => 'Blast tanpa team tujuan',
                ];
            }
            $teamRow = $this->db->query(
                'SELECT template_category FROM teams WHERE id = ? AND tenant_id = ? LIMIT 1',
                [$teamId, $tenantId]
            )->row_array();
            $teamCategory = strtoupper(trim((string) ($teamRow['template_category'] ?? 'UTILITY')));
            $templateCategory = strtoupper(trim((string) ($tpl['meta_category'] ?? 'UTILITY')));
            if (!in_array($teamCategory, ['UTILITY', 'MARKETING'], true)) $teamCategory = 'UTILITY';
            if (!in_array($templateCategory, ['UTILITY', 'MARKETING'], true)) $templateCategory = 'UTILITY';
            if ($teamCategory !== $templateCategory) {
                return ['success' => false, 'message_id' => 0, 'conversation_id' => 0,
                    'error' => 'Kategori template tidak sesuai dengan kategori team (' . $teamCategory . ').'];
            }
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

            $devFee = new TenantDevFee($this->db);
            if (!$devFee->canSend($tenantId)) {
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => 'Kuota Dev Fee tenant habis',
                ];
            }

            $deviceId = trim((string) ($channel['meta_phone_number_id'] ?? $channel['device_id'] ?? ''));
            if ($deviceId === '') {
                return ['success' => false, 'message_id' => 0, 'conversation_id' => 0, 'error' => 'Channel tanpa Phone Number ID'];
            }

            $isMeta = strtolower(trim((string) ($channel['provider'] ?? 'kirimin'))) === 'meta';
            $client = null;
            if ($isMeta) {
                $client = new Meta();
                if (!$client->configured()) {
                    return ['success' => false, 'message_id' => 0, 'conversation_id' => 0, 'error' => 'META_WA_ACCESS_TOKEN belum dikonfigurasi'];
                }
            } else {
                $apiKey = $this->fetchTenantKiriminApiKey($tenantId);
                $client = Kirimin::fromApiKey($apiKey);
            }
            [$sendParams, $named, $indexed, $paramsForStore] = $this->resolveTemplateParams($paramDefs, $rawParams);

            $lengthErr = $this->validateParamLengths($paramDefs, $rawParams);
            if ($lengthErr !== '') {
                return ['success' => false, 'message_id' => 0, 'conversation_id' => 0, 'error' => $lengthErr];
            }

            if (!$skipParamModeration) {
                $openAiKey = $this->fetchTenantOpenAiApiKey($tenantId);
                if ($openAiKey === '') {
                    return [
                        'success' => false,
                        'message_id' => 0,
                        'conversation_id' => 0,
                        'error' => 'OpenAI API key belum diatur. Simpan di Admin → OpenAI.',
                    ];
                }
                $moderator = new TemplateParamModerator($this->db);
                $mod = $moderator->moderate($openAiKey, TemplateParamModerator::entriesFromDefs($paramDefs, $rawParams));
                if (!$mod['safe']) {
                    return [
                        'success' => false,
                        'message_id' => 0,
                        'conversation_id' => 0,
                        'error' => $mod['reason'] ?: 'Konten parameter tidak aman',
                    ];
                }
            }

            $previewSource = (string) ($tpl['body_preview'] ?? '');
            if ($previewSource === '') {
                $previewSource = '[template] ' . $tpl['template_name'];
            }
            $preview = Kirimin::buildFilledPreview($previewSource, $paramDefs, $named, $indexed);

            $result = $client->sendTemplate(
                $deviceId,
                $phone,
                $tpl['template_name'],
                $tpl['language'] ?: 'id',
                $sendParams
            );

            if (!$result['success']) {
                $provErr = TemplateFailLogger::extractProviderError($result);
                $this->logProviderFailure(
                    $channel,
                    $tpl,
                    $phone,
                    $deviceId,
                    $preview,
                    $rawParams,
                    $sendParams,
                    $result,
                    $provErr,
                    $sentByUserId,
                    $logMeta,
                    $teamId
                );
                return [
                    'success' => false,
                    'message_id' => 0,
                    'conversation_id' => 0,
                    'error' => ($isMeta ? 'Meta: ' : 'Kirimin: ') . $provErr['message'],
                ];
            }

            $limitGuard->recordSuccess($tenantId, $phone, $sentByUserId ?: null, 'blast');
            if ((int) ($channel['id'] ?? 0) > 0) {
                $this->db->query(
                    'UPDATE wa_channels SET template_sent_count = template_sent_count + 1 WHERE id = ?',
                    [(int) $channel['id']]
                );
                ChannelDailyStats::recordTemplateSent($this->db, (int) $channel['id']);
                ChannelQualityRefresh::scheduleAfterUnknownTemplateSend($this->db, $channel);
            }

            $conv = $this->getOrCreateConversation($channel, $phone, null, $teamId);
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

            $devFee->recordUsage([
                'tenant_id' => $tenantId,
                'message_id' => $msgId,
                'template_id' => (int) ($tpl['id'] ?? 0),
                'template_name' => (string) ($tpl['template_name'] ?? ''),
                'user_id' => $sentByUserId ?: null,
                'team_id' => $teamId,
                'channel_id' => (int) ($channel['id'] ?? 0),
                'phone' => $phone,
                'source' => 'blast',
            ]);

            try {
                Server::push([
                    'type'            => 'message_out',
                    'tenant_id'       => $tenantId,
                    'team_id'         => $teamId,
                    'conversation_id' => (int) $conv['id'],
                    'message_id'      => $msgId,
                ]);
            } catch (\Throwable $e) {
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

    private function getOrCreateConversation(array $channel, string $phone, ?string $name, ?int $teamId = null): array
    {
        $teamId = $teamId ?: (int) ($channel['team_id'] ?? 0);
        $existing = $this->db->query(
            "SELECT * FROM conversations WHERE channel_id = ? AND team_id = ? AND phone = ? LIMIT 1",
            [(int) $channel['id'], $teamId, $phone]
        )->row_array();

        if ($existing) {
            return $existing;
        }

        $id = (int) $this->db->insert('conversations', [
            'tenant_id'     => (int) $channel['tenant_id'],
            'team_id'       => $teamId,
            'channel_id' => (int) $channel['id'],
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
        $providerId = $result['data']['message_id']
            ?? $result['data']['id']
            ?? ($result['data']['wamid'] ?? null);
        return (int) $this->db->insert('messages', [
            'conversation_id' => (int) $conv['id'],
            'direction'       => 'out',
            'type'            => $type,
            'body'            => $body,
            'template_name'   => $templateName,
            'params_json'     => $params !== null ? json_encode($params, JSON_UNESCAPED_UNICODE) : null,
            'provider_msg_id'   => $providerId,
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

    /** @return array{0:array,1:array<string,string>,2:array<int,string>,3:array} */
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

    private function fetchTenantKiriminApiKey(int $tenantId): string
    {
        $row = $this->db->query(
            "SELECT kirimin_api_key FROM tenants WHERE id = ? LIMIT 1",
            [$tenantId]
        )->row_array();
        $key = trim((string) ($row['kirimin_api_key'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Kirimin API key belum diatur untuk tenant ini');
        }
        return $key;
    }

    private function fetchTenantOpenAiApiKey(int $tenantId): string
    {
        $row = $this->db->query(
            "SELECT openai_api_key FROM tenants WHERE id = ? LIMIT 1",
            [$tenantId]
        )->row_array();
        return trim((string) ($row['openai_api_key'] ?? ''));
    }

    private function validateParamLengths(array $defs, array $rawParams): string
    {
        $defaultMax = 20;
        $isList = $rawParams === [] || array_keys($rawParams) === range(0, count($rawParams) - 1);
        $listCursor = 0;
        $errors = [];

        foreach ($defs as $def) {
            $component = strtolower((string) ($def['component'] ?? 'body'));
            $paramName = trim((string) ($def['param_name'] ?? ''));
            $idx = (int) ($def['param_index'] ?? 0);
            $csvKey = $component . '_' . $idx;
            $maxLen = (int) ($def['maxlength'] ?? 0);
            if ($maxLen < 1) {
                $maxLen = $defaultMax;
            }
            $label = trim((string) ($def['label'] ?? $paramName ?: $csvKey));

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

            if ($value !== '' && mb_strlen($value) > $maxLen) {
                $errors[] = "'{$label}' maksimal {$maxLen} karakter";
            }
        }

        return $errors !== [] ? implode('; ', $errors) : '';
    }

    /** @param array<string,mixed> $logMeta */
    private function logProviderFailure(
        array $channel,
        array $tpl,
        string $phone,
        string $deviceId,
        string $preview,
        array $rawParams,
        array $sendParams,
        array $result,
        array $provErr,
        int $sentByUserId,
        array $logMeta,
        ?int $teamId = null
    ): void {
        try {
            $logger = new TemplateFailLogger($this->db);
            $logger->log([
                'tenant_id' => (int) $channel['tenant_id'],
                'team_id' => $teamId ?: ((int) ($channel['team_id'] ?? 0)) ?: null,
                'channel_id' => (int) $channel['id'],
                'user_id' => $sentByUserId > 0 ? $sentByUserId : null,
                'blast_id' => $logMeta['blast_id'] ?? null,
                'blast_recipient_id' => $logMeta['blast_recipient_id'] ?? null,
                'source' => 'blast',
                'phone' => $phone,
                'template_id' => (int) ($tpl['id'] ?? 0) ?: null,
                'template_name' => (string) ($tpl['template_name'] ?? ''),
                'language' => (string) ($tpl['language'] ?? 'id'),
                'device_id' => $deviceId,
                'preview' => $preview,
                'error_message' => $provErr['message'],
                'error_code' => $provErr['code'],
                'http_code' => $provErr['http_code'],
                'request' => [
                    'phone' => $phone,
                    'template_name' => $tpl['template_name'] ?? '',
                    'language' => $tpl['language'] ?? 'id',
                    'device_id' => $deviceId,
                    'channel_id' => (int) $channel['id'],
                    'blast_id' => $logMeta['blast_id'] ?? null,
                    'template_params' => $rawParams,
                    'send_params' => $sendParams,
                    'external_id' => $result['external_id'] ?? null,
                ],
                'response' => $result,
            ]);
        } catch (\Throwable $e) {
        }
    }
}
