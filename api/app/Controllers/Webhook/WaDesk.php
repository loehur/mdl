<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\WaDesk\Server as WaDeskServer;

/**
 * YCloud webhook for WaDesk — /Webhook/WaDesk
 * Resolves API key by phone / ycloud_phone_id, stores into mdl_wadesk.
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
        $token = $_GET['hub_verify_token'] ?? null;
        $challenge = $_GET['hub_challenge'] ?? null;
        $verifyToken = defined('\Env::WADESK_VERIFY_TOKEN')
            ? \Env::WADESK_VERIFY_TOKEN
            : (\Env::WA_VERIFY_TOKEN ?? '');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            header('Content-Type: text/plain');
            echo $challenge;
            exit;
        }

        http_response_code(403);
        exit;
    }

    private function receive(): void
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!$data) {
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        try {
            $type = $data['type'] ?? '';
            if ($type === 'whatsapp.inbound_message.received') {
                $this->handleInbound($data);
            } elseif ($type === 'whatsapp.message.status.updated' || $type === 'whatsapp.message.updated') {
                $this->handleStatus($data);
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('WaDesk webhook: ' . $e->getMessage(), 'wadesk', 'Webhook');
            }
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    private function handleInbound(array $data): void
    {
        $whatsapp = $data['whatsappInboundMessage'] ?? ($data['whatsapp'] ?? []);
        if (empty($whatsapp)) {
            // YCloud sometimes nests under `data`
            $whatsapp = $data['data']['whatsappInboundMessage'] ?? ($data['data'] ?? []);
        }

        $from = $this->normalizePhone((string) ($whatsapp['from'] ?? ($whatsapp['wabaPhoneNumber'] ?? '')));
        $to = $this->normalizePhone((string) ($whatsapp['to'] ?? ($whatsapp['fromPhoneNumber'] ?? '')));
        // Typical YCloud inbound: from=customer, to=business number
        $customerPhone = $this->normalizePhone((string) ($whatsapp['from'] ?? ''));
        $businessPhone = $this->normalizePhone((string) ($whatsapp['to'] ?? ''));
        $phoneId = $whatsapp['fromPhoneNumberId'] ?? ($whatsapp['phoneNumberId'] ?? ($data['whatsappPhoneNumberId'] ?? null));

        if ($customerPhone === '') {
            return;
        }

        // Prefer key that already has a thread / recent outbound for this customer
        // (shared WA number across teams must not always land on the first-created key).
        $resolved = $this->resolveInboundRoute($customerPhone, $businessPhone, $phoneId, $to, $from);
        if (!$resolved) {
            if (class_exists('\Log')) {
                \Log::write(
                    'WaDesk inbound: key not found for business=' . $businessPhone . ' phoneId=' . $phoneId,
                    'wadesk',
                    'Webhook'
                );
            }
            return;
        }
        $key = $resolved['key'];
        $conv = $resolved['conversation'];

        $wamid = $whatsapp['wamid'] ?? ($whatsapp['id'] ?? null);
        $db = $this->db($this->dbIndex);

        if ($wamid) {
            $dup = $db->query(
                "SELECT id FROM messages WHERE ycloud_msg_id = ? LIMIT 1",
                [$wamid]
            )->row_array();
            if ($dup) {
                return;
            }
        }

        $type = $whatsapp['type'] ?? 'text';
        $bodyText = '';
        if ($type === 'text') {
            $bodyText = $whatsapp['text']['body'] ?? ($whatsapp['body'] ?? '');
        } elseif ($type === 'image') {
            $bodyText = $whatsapp['image']['caption'] ?? '[image]';
        } elseif ($type === 'button') {
            $bodyText = $whatsapp['button']['text'] ?? '[button]';
        } else {
            $bodyText = '[' . $type . ']';
        }

        $now = date('Y-m-d H:i:s');
        $profileName = $whatsapp['customerProfile']['name'] ?? ($whatsapp['profile']['name'] ?? null);

        if (!$conv) {
            $convId = (int) $db->insert('conversations', [
                'tenant_id' => (int) $key['tenant_id'],
                'team_id' => (int) $key['team_id'],
                'ycloud_key_id' => (int) $key['id'],
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
            'ycloud_msg_id' => $wamid,
            'status' => 'received',
        ]);

        $teamId = (int) ($conv['team_id'] ?? $key['team_id']);
        $tenantId = (int) ($conv['tenant_id'] ?? $key['tenant_id']);

        WaDeskServer::push([
            'type' => 'message_in',
            'tenant_id' => $tenantId,
            'team_id' => $teamId,
            'conversation_id' => $convId,
            'message_id' => $msgId,
            'preview' => $bodyText,
            'phone' => $customerPhone,
            'name' => $profileName,
        ]);
    }

    private function handleStatus(array $data): void
    {
        // YCloud uses whatsappMessageStatusUpdate (same as CRM webhook)
        $status = $data['whatsappMessageStatusUpdate']
            ?? ($data['whatsappMessageStatus'] ?? null)
            ?? ($data['whatsappMessage'] ?? null)
            ?? ($data['data'] ?? []);

        if (!is_array($status) || $status === []) {
            return;
        }

        $wamid = $status['wamid'] ?? null;
        $messageId = $status['id'] ?? ($status['messageId'] ?? null);
        $externalId = $status['externalId'] ?? ($status['external_id'] ?? null);
        $st = strtolower((string) ($status['status'] ?? ''));
        if ($st === '') {
            return;
        }

        // Normalize common aliases
        if (in_array($st, ['accepted', 'pending'], true)) {
            $st = 'sent';
        }

        $db = $this->db($this->dbIndex);
        $row = null;

        if ($wamid) {
            $row = $db->query(
                "SELECT id, conversation_id, ycloud_msg_id, external_id, status
                 FROM messages WHERE ycloud_msg_id = ? LIMIT 1",
                [$wamid]
            )->row_array();
        }
        if (!$row && $messageId) {
            $row = $db->query(
                "SELECT id, conversation_id, ycloud_msg_id, external_id, status
                 FROM messages WHERE ycloud_msg_id = ? LIMIT 1",
                [$messageId]
            )->row_array();
        }
        if (!$row && $externalId) {
            $row = $db->query(
                "SELECT id, conversation_id, ycloud_msg_id, external_id, status
                 FROM messages WHERE external_id = ? LIMIT 1",
                [$externalId]
            )->row_array();
        }
        if (!$row) {
            return;
        }

        $db->update('messages', [
            'status' => $st,
            'ycloud_msg_id' => $wamid ?: ($messageId ?: ($row['ycloud_msg_id'] ?? null)),
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
                'ycloud_msg_id' => $wamid ?: $messageId,
            ]);
        }
    }

    /**
     * Pick the right ycloud_key + existing conversation for an inbound message.
     *
     * When several teams share the same WA business number / credential, LIMIT 1
     * would always hit the oldest key. Prefer a conversation that already exists
     * for this customer (especially one with recent outbound).
     *
     * @return array{key: array, conversation: ?array}|null
     */
    private function resolveInboundRoute(
        string $customerPhone,
        string $businessPhone,
        $phoneId,
        string $altTo,
        string $altFrom
    ): ?array {
        $candidates = $this->findCandidateKeys($businessPhone, $phoneId);
        if ($candidates === []) {
            $candidates = $this->findCandidateKeys($altTo, $phoneId);
        }
        if ($candidates === []) {
            $candidates = $this->findCandidateKeys($altFrom, $phoneId);
        }
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
               AND c.ycloud_key_id IN ($placeholders)
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
            $keyId = (int) $conv['ycloud_key_id'];
            $key = $byId[$keyId] ?? null;
            if (!$key) {
                $key = $db->query(
                    "SELECT * FROM ycloud_keys WHERE id = ? LIMIT 1",
                    [$keyId]
                )->row_array() ?: null;
            }
            if ($key) {
                if (class_exists('\Log') && count($byId) > 1) {
                    \Log::write(
                        'WaDesk inbound route: customer=' . $customerPhone
                        . ' keys=' . implode(',', $ids)
                        . ' picked_key=' . $keyId
                        . ' conv=' . (int) $conv['id']
                        . ' via=existing_conversation',
                        'wadesk',
                        'Webhook'
                    );
                }
                return ['key' => $key, 'conversation' => $conv];
            }
        }

        // No prior thread: prefer newest key (avoid always landing on first-created team)
        usort($candidates, static function ($a, $b) {
            return (int) $b['id'] <=> (int) $a['id'];
        });
        $key = $candidates[0];

        if (class_exists('\Log') && count($candidates) > 1) {
            \Log::write(
                'WaDesk inbound route: customer=' . $customerPhone
                . ' keys=' . implode(',', $ids)
                . ' picked_key=' . (int) $key['id']
                . ' via=fallback_newest_key',
                'wadesk',
                'Webhook'
            );
        }

        return ['key' => $key, 'conversation' => null];
    }

    /**
     * All active keys that could own this business WA number, including siblings
     * that share the same api_key_hash (shared YCloud credential across teams).
     *
     * @return list<array>
     */
    private function findCandidateKeys(string $businessPhone, $phoneId): array
    {
        $db = $this->db($this->dbIndex);
        $byId = [];

        if ($phoneId) {
            $rows = $db->query(
                "SELECT * FROM ycloud_keys WHERE ycloud_phone_id = ? AND status = 'active'",
                [(string) $phoneId]
            )->result_array();
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }

        if ($businessPhone !== '') {
            $rows = $db->query(
                "SELECT * FROM ycloud_keys WHERE phone_number = ? AND status = 'active'",
                [$businessPhone]
            )->result_array();
            foreach ($rows as $row) {
                $byId[(int) $row['id']] = $row;
            }
        }

        if ($byId === []) {
            return [];
        }

        if ($this->hasApiKeyHashColumn()) {
            $hashes = [];
            foreach ($byId as $row) {
                $h = trim((string) ($row['api_key_hash'] ?? ''));
                if ($h !== '') {
                    $hashes[$h] = true;
                }
            }
            if ($hashes !== []) {
                $hashList = array_keys($hashes);
                $ph = implode(',', array_fill(0, count($hashList), '?'));
                $siblings = $db->query(
                    "SELECT * FROM ycloud_keys
                     WHERE api_key_hash IN ($ph) AND status = 'active'",
                    $hashList
                )->result_array();
                foreach ($siblings as $row) {
                    $byId[(int) $row['id']] = $row;
                }
            }
        }

        return array_values($byId);
    }

    private function hasApiKeyHashColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $row = $this->db($this->dbIndex)->query(
                "SHOW COLUMNS FROM ycloud_keys LIKE 'api_key_hash'"
            )->row_array();
            $cached = !empty($row);
        } catch (\Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            $digits = '62' . substr($digits, 1);
        }
        return $digits;
    }
}
