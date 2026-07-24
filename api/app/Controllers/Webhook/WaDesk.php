<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\WaDeskServer;

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

        $key = $this->resolveKey($businessPhone, $phoneId);
        if (!$key) {
            // try alternate: some payloads swap to/from for business
            $key = $this->resolveKey($to, $phoneId) ?: $this->resolveKey($from, $phoneId);
        }
        if (!$key) {
            if (class_exists('\Log')) {
                \Log::write('WaDesk inbound: key not found for business=' . $businessPhone . ' phoneId=' . $phoneId, 'wadesk', 'Webhook');
            }
            return;
        }

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

        $conv = $db->query(
            "SELECT * FROM conversations WHERE ycloud_key_id = ? AND phone = ? LIMIT 1",
            [(int) $key['id'], $customerPhone]
        )->row_array();

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

        WaDeskServer::push([
            'type' => 'message_in',
            'tenant_id' => (int) $key['tenant_id'],
            'team_id' => (int) $key['team_id'],
            'conversation_id' => $convId,
            'message_id' => $msgId,
            'preview' => $bodyText,
            'phone' => $customerPhone,
            'name' => $profileName,
        ]);
    }

    private function handleStatus(array $data): void
    {
        $status = $data['whatsappMessageStatus'] ?? ($data['data'] ?? []);
        $wamid = $status['wamid'] ?? ($status['id'] ?? null);
        $st = $status['status'] ?? null;
        if (!$wamid || !$st) {
            return;
        }

        $this->db($this->dbIndex)->query(
            "UPDATE messages SET status = ? WHERE ycloud_msg_id = ? LIMIT 1",
            [$st, $wamid]
        );
    }

    private function resolveKey(string $businessPhone, $phoneId): ?array
    {
        $db = $this->db($this->dbIndex);
        if ($phoneId) {
            $row = $db->query(
                "SELECT * FROM ycloud_keys WHERE ycloud_phone_id = ? AND status = 'active' LIMIT 1",
                [(string) $phoneId]
            )->row_array();
            if ($row) {
                return $row;
            }
        }
        if ($businessPhone === '') {
            return null;
        }
        return $db->query(
            "SELECT * FROM ycloud_keys WHERE phone_number = ? AND status = 'active' LIMIT 1",
            [$businessPhone]
        )->row_array() ?: null;
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
