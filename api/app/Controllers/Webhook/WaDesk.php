<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\WaDesk\Server as WaDeskServer;

/**
 * Kirimin.id webhook for WaDesk — /Webhook/WaDesk
 */
class WaDesk extends Controller
{
    private int $dbIndex = 7;

    public function index()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            return $this->verify();
        }
        if ($method === 'POST') {
            return $this->receive();
        }

        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function verify(): void
    {
        $mode = $_GET['hub_mode'] ?? null;
        $token = $_GET['hub_verify_token'] ?? ($_GET['token'] ?? null);
        $challenge = $_GET['hub_challenge'] ?? ($_GET['challenge'] ?? null);
        $verifyToken = defined('\Env::WADESK_VERIFY_TOKEN')
            ? \Env::WADESK_VERIFY_TOKEN
            : (\Env::WA_VERIFY_TOKEN ?? '');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        if ($token !== null && $token === $verifyToken) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
            exit;
        }

        http_response_code(403);
        exit;
    }

    private function receive(): void
    {
        $json = file_get_contents('php://input');
        $headers = $this->kirimHeaders();
        $this->logWebhook(
            'POST ip=' . ($this->clientIp())
            . ' event=' . ($headers['event'] ?: '-')
            . ' source=' . ($headers['source'] ?: '-')
            . ' delivery=' . ($headers['delivery'] ?: '-')
            . ' raw=' . mb_substr((string) $json, 0, 4000)
        );

        $data = json_decode($json, true);
        if (!$data) {
            $this->logWebhook('SKIP invalid JSON body');
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        $handled = false;
        try {
            $handled = $this->dispatchWebhook($data, $headers);
        } catch (\Throwable $e) {
            $this->logWebhook('ERROR ' . $e->getMessage());
        }

        if (!$handled) {
            $type = strtolower((string) ($data['type'] ?? $data['event'] ?? ''));
            $this->logWebhook(
                'UNHANDLED type=' . ($type ?: 'none')
                . ' keys=' . implode(',', array_slice(array_keys($data), 0, 12))
            );
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /** @param array<string,string> $headers */
    private function dispatchWebhook(array $data, array $headers): bool
    {
        $event = strtolower($headers['event'] ?? '');
        $type = strtolower((string) ($data['type'] ?? $data['event'] ?? ''));

        if ($event === 'message.received' || $type === 'message.received') {
            if ($this->looksLikeMetaInbound($data)) {
                $this->handleMetaInbound($data);
                return true;
            }
            if ($this->looksLikeKirimNativeEnvelope($data)) {
                $this->handleKirimNativeInbound($data);
                return true;
            }
            if ($this->looksLikeKiriminInbound($data)) {
                $this->handleKiriminInbound($data);
                return true;
            }
        }

        if (in_array($event, ['message.status', 'message.sent'], true)
            || in_array($type, ['message.status', 'message.sent'], true)) {
            if ($this->looksLikeMetaStatus($data)) {
                $this->handleMetaStatus($data);
                return true;
            }
            if ($this->looksLikeKirimNativeEnvelope($data)) {
                $this->handleKirimNativeStatus($data);
                return true;
            }
            if ($this->looksLikeKiriminStatus($data)) {
                $this->handleStatus($data);
                return true;
            }
        }

        if ($type === 'whatsapp.inbound_message.received') {
            $this->handleYCloudInbound($data);
            return true;
        }

        if (in_array($type, ['whatsapp.message.status.updated', 'whatsapp.message.updated', 'message.status', 'status'], true)) {
            $this->handleStatus($data);
            return true;
        }

        if ($this->looksLikeMetaInbound($data)) {
            $this->handleMetaInbound($data);
            return true;
        }

        if ($this->looksLikeMetaStatus($data)) {
            $this->handleMetaStatus($data);
            return true;
        }

        if ($this->looksLikeKiriminInbound($data)) {
            $this->handleKiriminInbound($data);
            return true;
        }

        if ($this->looksLikeKirimNativeEnvelope($data)) {
            if (str_contains($type, 'status') || str_contains($type, 'sent')) {
                $this->handleKirimNativeStatus($data);
            } else {
                $this->handleKirimNativeInbound($data);
            }
            return true;
        }

        if ($this->looksLikeKiriminStatus($data)) {
            $this->handleStatus($data);
            return true;
        }

        return false;
    }

    /** @return array{event:string,source:string,delivery:string} */
    private function kirimHeaders(): array
    {
        $pick = static function (string $name): string {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            return trim((string) ($_SERVER[$serverKey] ?? ''));
        };

        return [
            'event' => $pick('X-Kirim-Event'),
            'source' => $pick('X-Kirim-Source'),
            'delivery' => $pick('X-Kirim-Delivery-Id'),
        ];
    }

    private function clientIp(): string
    {
        return trim((string) (
            $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_CF_CONNECTING_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? ''
        ));
    }

    private function logWebhook(string $text): void
    {
        if (class_exists('\Log')) {
            \Log::write($text, 'wadesk', 'Webhook');
        }

        $dir = dirname(__DIR__, 3) . '/logs/' . date('Y-m-d');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $dir . '/wadesk_webhook.log',
            date('H:i:s') . ' ' . $text . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private function handleYCloudInbound(array $data): void
    {
        $whatsapp = $data['whatsappInboundMessage'] ?? ($data['whatsapp'] ?? []);
        if (empty($whatsapp)) {
            $whatsapp = $data['data']['whatsappInboundMessage'] ?? ($data['data'] ?? []);
        }
        $this->persistInbound(
            (string) ($whatsapp['from'] ?? ''),
            (string) ($whatsapp['to'] ?? ''),
            $whatsapp['fromPhoneNumberId'] ?? ($whatsapp['phoneNumberId'] ?? ($data['whatsappPhoneNumberId'] ?? null)),
            $whatsapp['device_id'] ?? ($data['device_id'] ?? null),
            $whatsapp['wamid'] ?? ($whatsapp['id'] ?? null),
            (string) ($whatsapp['type'] ?? 'text'),
            $this->extractBody($whatsapp),
            $whatsapp['customerProfile']['name'] ?? ($whatsapp['profile']['name'] ?? null)
        );
    }

    private function handleKiriminInbound(array $data): void
    {
        $from = (string) ($data['sender'] ?? $data['from'] ?? $data['phone'] ?? $data['phone_number'] ?? '');
        $deviceId = $data['whatsapp_device_id'] ?? ($data['device_id'] ?? ($data['deviceId'] ?? null));
        $businessPhone = (string) ($data['to'] ?? $data['receiver'] ?? $data['business_phone'] ?? $data['display_phone_number'] ?? '');
        $msgId = $data['message_id'] ?? ($data['id'] ?? ($data['inboxid'] ?? null));
        $type = (string) ($data['type'] ?? $data['message_type'] ?? 'text');
        $body = (string) ($data['message'] ?? $data['text'] ?? $data['body'] ?? $data['content'] ?? '');
        if ($body === '' && isset($data['caption'])) {
            $body = (string) $data['caption'];
        }
        $name = $data['push_name'] ?? ($data['user_name'] ?? ($data['name'] ?? ($data['profile_name'] ?? null)));

        if (!empty($data['fromMe']) || !empty($data['from_me'])) {
            return;
        }

        $this->persistInbound($from, $businessPhone, null, $deviceId, $msgId, $type, $body, $name);
    }

    private function handleMetaInbound(array $data): void
    {
        $entry = $data['entry'][0]['changes'][0]['value'] ?? [];
        $messages = $entry['messages'][0] ?? null;
        if (!$messages || !is_array($messages)) {
            return;
        }
        $metadata = $entry['metadata'] ?? [];
        $kirim = is_array($data['kirim'] ?? null) ? $data['kirim'] : [];
        $deviceId = $kirim['whatsapp_device_id']
            ?? ($kirim['device_id'] ?? null)
            ?? ($data['device_id'] ?? null);

        $this->persistInbound(
            (string) ($messages['from'] ?? ''),
            (string) ($metadata['display_phone_number'] ?? ''),
            $metadata['phone_number_id'] ?? null,
            $deviceId,
            $messages['id'] ?? null,
            (string) ($messages['type'] ?? 'text'),
            $this->extractBody($messages),
            $entry['contacts'][0]['profile']['name'] ?? null
        );
    }

    private function handleMetaStatus(array $data): void
    {
        $entry = $data['entry'][0]['changes'][0]['value'] ?? [];
        $statuses = $entry['statuses'] ?? [];
        if (!is_array($statuses)) {
            return;
        }

        foreach ($statuses as $statusRow) {
            if (!is_array($statusRow)) {
                continue;
            }
            $this->handleStatus([
                'status' => $statusRow['status'] ?? '',
                'id' => $statusRow['id'] ?? null,
                'message_id' => $statusRow['id'] ?? null,
            ]);
        }
    }

    private function handleKirimNativeInbound(array $data): void
    {
        $inner = is_array($data['data'] ?? null) ? $data['data'] : [];
        $msg = is_array($inner['message'] ?? null) ? $inner['message'] : $inner;
        $meta = is_array($inner['meta'] ?? null) ? $inner['meta'] : [];

        if (!empty($msg['from_me']) || !empty($inner['from_me'])) {
            return;
        }

        $from = (string) ($msg['from'] ?? $inner['from'] ?? $inner['phone'] ?? '');
        $to = (string) ($msg['to'] ?? $meta['display_phone_number'] ?? $inner['to'] ?? '');
        $deviceId = $inner['whatsapp_device_id']
            ?? ($meta['whatsapp_device_id'] ?? null)
            ?? ($data['kirim']['whatsapp_device_id'] ?? null);
        $msgId = $msg['provider_id'] ?? ($msg['id'] ?? ($inner['message_id'] ?? null));
        $type = (string) ($msg['type'] ?? $inner['message_type'] ?? 'text');
        $body = (string) ($msg['text'] ?? $msg['body'] ?? $msg['message'] ?? $inner['message'] ?? $inner['text'] ?? '');
        $name = $inner['contact']['name'] ?? ($inner['name'] ?? ($msg['push_name'] ?? null));

        $this->persistInbound($from, $to, $meta['phone_number_id'] ?? null, $deviceId, $msgId, $type, $body, $name);
    }

    private function handleKirimNativeStatus(array $data): void
    {
        $inner = is_array($data['data'] ?? null) ? $data['data'] : [];
        $msg = is_array($inner['message'] ?? null) ? $inner['message'] : $inner;

        $this->handleStatus([
            'status' => $msg['status'] ?? ($inner['status'] ?? ''),
            'id' => $msg['provider_id'] ?? ($msg['id'] ?? null),
            'message_id' => $msg['provider_id'] ?? ($msg['id'] ?? null),
            'external_id' => $msg['external_id'] ?? ($inner['external_id'] ?? null),
        ]);
    }

    private function persistInbound(
        string $fromRaw,
        string $businessRaw,
        $phoneId,
        $deviceId,
        $wamid,
        string $type,
        string $bodyText,
        $profileName
    ): void {
        $customerPhone = $this->normalizePhone($fromRaw);
        $businessPhone = $this->normalizePhone($businessRaw);
        if ($customerPhone === '') {
            return;
        }

        $resolved = $this->resolveInboundRoute($customerPhone, $businessPhone, $phoneId, $deviceId);
        if (!$resolved) {
            $this->logWebhook(
                'channel not found customer=' . $customerPhone
                . ' business=' . $businessPhone
                . ' phone_id=' . ($phoneId ?? '-')
                . ' device=' . ($deviceId ?? '-')
            );
            return;
        }
        $channel = $resolved['channel'];
        $conv = $resolved['conversation'];

        $db = $this->db($this->dbIndex);
        if ($wamid) {
            $dup = $db->query(
                "SELECT id FROM messages WHERE provider_msg_id = ? LIMIT 1",
                [$wamid]
            )->row_array();
            if ($dup) {
                return;
            }
        }

        if ($bodyText === '') {
            $bodyText = '[' . $type . ']';
        }

        $now = date('Y-m-d H:i:s');
        if (!$conv) {
            $convId = (int) $db->insert('conversations', [
                'tenant_id' => (int) $channel['tenant_id'],
                'team_id' => (int) $channel['team_id'],
                'channel_id' => (int) $channel['id'],
                'phone' => $customerPhone,
                'name' => $profileName,
                'last_message' => mb_substr($bodyText, 0, 500),
                'last_in_at' => $now,
                'last_message_at' => $now,
                'unread' => 1,
            ]);
        } else {
            $convId = (int) $conv['id'];
            $db->update('conversations', [
                'last_message' => mb_substr($bodyText, 0, 500),
                'last_in_at' => $now,
                'last_message_at' => $now,
                'unread' => ((int) ($conv['unread'] ?? 0)) + 1,
                'name' => $profileName ?: ($conv['name'] ?? null),
            ], ['id' => $convId]);
        }

        $msgId = (int) $db->insert('messages', [
            'conversation_id' => $convId,
            'direction' => 'in',
            'type' => $type,
            'body' => $bodyText,
            'provider_msg_id' => $wamid,
            'status' => 'received',
        ]);

        $this->logWebhook(
            'INBOUND saved conv=' . $convId
            . ' msg=' . $msgId
            . ' customer=' . $customerPhone
            . ' channel=' . (int) $channel['id']
        );

        WaDeskServer::push([
            'type' => 'message_in',
            'tenant_id' => (int) ($conv['tenant_id'] ?? $channel['tenant_id']),
            'team_id' => (int) ($conv['team_id'] ?? $channel['team_id']),
            'conversation_id' => $convId,
            'message_id' => $msgId,
            'preview' => $bodyText,
            'phone' => $customerPhone,
            'name' => $profileName,
        ]);
    }

    private function handleStatus(array $data): void
    {
        $status = $data['whatsappMessageStatusUpdate']
            ?? ($data['whatsappMessageStatus'] ?? null)
            ?? ($data['whatsappMessage'] ?? null)
            ?? ($data['status'] ?? null)
            ?? ($data['data'] ?? []);

        if (is_string($status)) {
            $status = ['status' => $status, 'id' => $data['message_id'] ?? ($data['id'] ?? null)];
        }
        if (!is_array($status) || $status === []) {
            return;
        }

        $wamid = $status['wamid'] ?? ($status['message_id'] ?? null);
        $messageId = $status['id'] ?? ($status['messageId'] ?? null);
        $externalId = $status['externalId'] ?? ($status['external_id'] ?? null);
        $st = strtolower((string) ($status['status'] ?? ($data['delivery_status'] ?? '')));
        if ($st === '') {
            return;
        }

        if (in_array($st, ['accepted', 'pending'], true)) {
            $st = 'sent';
        }

        $db = $this->db($this->dbIndex);
        $row = null;

        if ($wamid) {
            $row = $db->query(
                "SELECT id, conversation_id, provider_msg_id, external_id, status
                 FROM messages WHERE provider_msg_id = ? LIMIT 1",
                [$wamid]
            )->row_array();
        }
        if (!$row && $messageId) {
            $row = $db->query(
                "SELECT id, conversation_id, provider_msg_id, external_id, status
                 FROM messages WHERE provider_msg_id = ? LIMIT 1",
                [$messageId]
            )->row_array();
        }
        if (!$row && $externalId) {
            $row = $db->query(
                "SELECT id, conversation_id, provider_msg_id, external_id, status
                 FROM messages WHERE external_id = ? LIMIT 1",
                [$externalId]
            )->row_array();
        }
        if (!$row) {
            return;
        }

        $db->update('messages', [
            'status' => $st,
            'provider_msg_id' => $wamid ?: ($messageId ?: ($row['provider_msg_id'] ?? null)),
        ], ['id' => (int) $row['id']]);

        $conv = $db->query(
            "SELECT tenant_id, team_id FROM conversations WHERE id = ? LIMIT 1",
            [(int) $row['conversation_id']]
        )->row_array();

        if ($conv) {
            WaDeskServer::push([
                'type' => 'message_status',
                'tenant_id' => (int) $conv['tenant_id'],
                'team_id' => (int) $conv['team_id'],
                'conversation_id' => (int) $row['conversation_id'],
                'message_id' => (int) $row['id'],
                'status' => $st,
                'provider_msg_id' => $wamid ?: $messageId,
            ]);
        }
    }

    /** @return array{channel: array, conversation: ?array}|null */
    private function resolveInboundRoute(
        string $customerPhone,
        string $businessPhone,
        $phoneId,
        $deviceId
    ): ?array {
        $candidates = $this->findCandidateChannels($businessPhone, $phoneId, $deviceId);
        if ($candidates === []) {
            return null;
        }

        $byId = [];
        foreach ($candidates as $row) {
            $byId[(int) $row['id']] = $row;
        }
        $ids = array_keys($byId);
        $db = $this->db($this->dbIndex);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $binds = array_merge([$customerPhone], $ids);

        $conv = $db->query(
            "SELECT c.*
             FROM conversations c
             WHERE c.phone = ?
               AND c.channel_id IN ($placeholders)
             ORDER BY
               (c.last_out_at IS NULL) ASC,
               c.last_out_at DESC,
               (c.last_message_at IS NULL) ASC,
               c.last_message_at DESC,
               c.id DESC
             LIMIT 1",
            $binds
        )->row_array() ?: null;

        if ($conv) {
            $channelId = (int) $conv['channel_id'];
            $channel = $byId[$channelId] ?? $db->query(
                "SELECT * FROM {$this->channelsTable()} WHERE id = ? LIMIT 1",
                [$channelId]
            )->row_array();
            if ($channel) {
                return ['channel' => $channel, 'conversation' => $conv];
            }
        }

        usort($candidates, static fn ($a, $b) => (int) $b['id'] <=> (int) $a['id']);
        return ['channel' => $candidates[0], 'conversation' => null];
    }

    /** @return list<array> */
    private function findCandidateChannels(string $businessPhone, $phoneId, $deviceId): array
    {
        $db = $this->db($this->dbIndex);
        $tbl = $this->channelsTable();
        $byId = [];

        if ($deviceId) {
            $rows = $db->query(
                "SELECT * FROM {$tbl} WHERE device_id = ? AND status = 'active'",
                [(string) $deviceId]
            )->result_array();
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }

        if ($businessPhone !== '') {
            $rows = $db->query(
                "SELECT * FROM {$tbl} WHERE phone_number = ? AND status = 'active'",
                [$businessPhone]
            )->result_array();
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }

        return array_values($byId);
    }

    private function channelsTable(): string
    {
        return 'wa_channels';
    }

    private function looksLikeKiriminInbound(array $data): bool
    {
        return isset($data['sender']) || isset($data['message']) || isset($data['text'])
            || isset($data['whatsapp_device_id'])
            || (isset($data['device_id']) && (isset($data['from']) || isset($data['phone'])));
    }

    private function looksLikeMetaInbound(array $data): bool
    {
        return isset($data['entry'][0]['changes'][0]['value']['messages']);
    }

    private function looksLikeMetaStatus(array $data): bool
    {
        return isset($data['entry'][0]['changes'][0]['value']['statuses']);
    }

    private function looksLikeKirimNativeEnvelope(array $data): bool
    {
        return isset($data['data']) && is_array($data['data'])
            && (isset($data['type']) || isset($data['event']));
    }

    private function looksLikeKiriminStatus(array $data): bool
    {
        $event = strtolower((string) ($data['event'] ?? $data['type'] ?? ''));
        return str_contains($event, 'status') && (isset($data['message_id']) || isset($data['id']));
    }

    private function extractBody(array $payload): string
    {
        $type = $payload['type'] ?? 'text';
        if ($type === 'text') {
            return (string) ($payload['text']['body'] ?? ($payload['body'] ?? ''));
        }
        if ($type === 'image') {
            return (string) ($payload['image']['caption'] ?? '[image]');
        }
        if ($type === 'button') {
            return (string) ($payload['button']['text'] ?? '[button]');
        }
        return '[' . $type . ']';
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        if (!str_starts_with($digits, '62') && strlen($digits) >= 9) {
            $digits = '62' . ltrim($digits, '0');
        }
        return $digits;
    }
}
