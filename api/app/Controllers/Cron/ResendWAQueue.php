<?php

namespace App\Controllers\Cron;

use App\Core\Controller;

/**
 * Resend WA outbound messages stuck in wa_messages_out.status='queue'
 * after yCloud timeout/network errors.
 *
 * URL (example):
 * /Cron/ResendWAQueue/index?interval=2&limit=20
 */
class ResendWAQueue extends Controller
{
    public function index()
    {
        $db = $this->db(0);
        $output = '';

        $intervalMinutes = (int)($_GET['interval'] ?? 2);
        if ($intervalMinutes < 1) $intervalMinutes = 2;

        $limit = (int)($_GET['limit'] ?? 20);
        if ($limit < 1) $limit = 20;
        if ($limit > 50) $limit = 50;

        // Sinkronkan last_in_at dari pesan masuk (24 jam) sebelum cek CSW / resend
        $syncedLastIn = $this->syncLastInAtFromMessagesIn($db);

        // Only text messages can be safely resent from stored "content"
        $sql = "
            SELECT id, external_id, phone, type, content, sender_code, quoted_message_id, created_at
            FROM wa_messages_out
            WHERE status = 'queue'
              AND external_id IS NOT NULL
              AND external_id <> ''
              AND created_at <= (NOW() - INTERVAL ? MINUTE)
            ORDER BY created_at ASC
            LIMIT ?
        ";

        $rows = $db->query($sql, [$intervalMinutes, $limit])->result_array();
        if (!is_array($rows)) $rows = [];

        if (count($rows) === 0) {
            $output .= "WA RESEND QUEUE - Queue is empty\n";
            $output .= "intervalMinutes={$intervalMinutes}\n";
            $output .= "limit={$limit}\n";
            $output .= "synced_last_in_at_rows={$syncedLastIn}\n";
            $output .= "processed=0\n";
            $output .= "skipped=0\n";
            $output .= "removed_csw=0\n";

            header('Content-Type: text/plain');
            echo $output;
            return;
        }

        $waService = new \App\Helpers\WhatsAppService();
        $processed = 0;
        $skipped = 0;
        $removedCsw = 0;

        foreach ($rows as $r) {
            $id = (int)($r['id'] ?? 0);
            $externalId = $r['external_id'] ?? null;
            $phone = $r['phone'] ?? null;
            $type = $r['type'] ?? 'text';
            $content = $r['content'] ?? '';
            $senderCode = $r['sender_code'] ?? null;
            $quotedMessageId = $r['quoted_message_id'] ?? null;
            $queueCreatedAt = $r['created_at'] ?? null;

            if (!$id || empty($externalId) || empty($phone) || $type !== 'text') {
                $skipped++;
                $output .= "SKIP id={$id} phone=" . ($phone ?? '') . " reason=invalid_data_or_not_text\n";
                continue;
            }

            // Safety check:
            // If there is any newer outbound message for the same phone with status
            // NOT in ('queue','failed'), then this queue record is superseded and
            // should be marked failed (no need to resend).
            if (!empty($queueCreatedAt)) {
                $checkSql = "
                    SELECT id
                    FROM wa_messages_out
                    WHERE phone = ?
                      AND created_at > ?
                      AND status NOT IN ('queue', 'failed')
                    ORDER BY created_at DESC
                    LIMIT 1
                ";
                $newer = $db->query($checkSql, [$phone, $queueCreatedAt])->row();
                if ($newer) {
                    $db->update(
                        'wa_messages_out',
                        [
                            'status' => 'failed',
                            'error_message' => 'Superseded by newer outbound send (status != queue/failed).'
                        ],
                        ['id' => $id]
                    );
                    $skipped++;
                    $output .= "SKIP id={$id} phone={$phone} reason=superseded_by_newer_outbound\n";
                    continue;
                }
            }

            // yCloud free text hanya dalam CSW — sama dengan WhatsApp/send & isWithinCsw()
            $lastInAt = $this->getWaConversationLastInAt($db, $phone);
            if (!$waService->isWithinCsw($lastInAt)) {
                $db->delete('wa_messages_out', ['id' => $id]);
                $removedCsw++;
                $output .= "DELETE id={$id} phone={$phone} reason=csw_closed\n";
                continue;
            }

            // Lock row to prevent parallel cron from resending the same record
            $locked = $db->update(
                'wa_messages_out',
                ['status' => 'processing'],
                ['id' => $id, 'status' => 'queue']
            );
            if (!$locked || $db->affected_rows() <= 0) {
                $output .= "SKIP id={$id} phone={$phone} reason=lock_failed\n";
                continue;
            }

            // Fire & forget: WhatsAppService will upsert status based on yCloud webhook
            // If yCloud still fails (timeout/network), WhatsAppService will keep/return status='queue'
            try {
                $waService->sendFreeText($phone, $content, $quotedMessageId, $senderCode, $externalId);
                $processed++;
                $output .= "OK id={$id} phone={$phone} external_id={$externalId}\n";
            } catch (\Throwable $e) {
                $skipped++;
                $output .= "ERR id={$id} phone={$phone} external_id={$externalId} message=" . $e->getMessage() . "\n";
            }
        }

        $output .= "SUMMARY intervalMinutes={$intervalMinutes} limit={$limit} synced_last_in_at_rows={$syncedLastIn} processed={$processed} skipped={$skipped} removed_csw={$removedCsw}\n";

        header('Content-Type: text/plain');
        echo $output;
    }

    /**
     * Update wa_conversations.last_in_at dari agregat wa_messages_in (24 jam terakhir):
     * per nomor (group by phone), ambil MAX(created_at), cocokkan baris conversation
     * dengan nomor yang sama setelah normalisasi digit (agar +628… = 628…).
     * Memakai GREATEST(..., max_in) agar tidak menggeser last_in_at ke waktu yang lebih lama
     * jika kolom sudah lebih baru dari sumber lain.
     *
     * @return int perkiraan baris ter-update (mysqli affected_rows pada satu statement UPDATE)
     */
    private function syncLastInAtFromMessagesIn($db): int
    {
        try {
            $sql = "
                UPDATE wa_conversations c
                INNER JOIN (
                    SELECT
                        REGEXP_REPLACE(m.phone, '[^0-9]', '') AS phone_digits,
                        MAX(m.created_at) AS max_in
                    FROM wa_messages_in m
                    WHERE m.created_at >= (NOW() - INTERVAL 24 HOUR)
                    GROUP BY REGEXP_REPLACE(m.phone, '[^0-9]', '')
                ) src ON REGEXP_REPLACE(c.wa_number, '[^0-9]', '') = src.phone_digits
                SET
                    c.last_in_at = GREATEST(
                        COALESCE(c.last_in_at, '1970-01-01 00:00:00'),
                        src.max_in
                    ),
                    c.updated_at = NOW()
            ";
            $db->query($sql, []);

            return (int) $db->conn()->affected_rows;
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('ResendWAQueue syncLastInAtFromMessagesIn: ' . $e->getMessage(), 'cron', 'ResendWAQueue');
            }

            return 0;
        }
    }

    /**
     * last_in_at dari wa_conversations untuk CSW yCloud (format wa_number: 628… atau +628…).
     */
    private function getWaConversationLastInAt($db, string $phone): ?string
    {
        $norm = $this->normalizePhoneForWaConversations($phone);
        if ($norm === null) {
            return null;
        }
        [$wa1, $wa2] = $norm;
        try {
            $q = $db->query(
                'SELECT last_in_at FROM wa_conversations WHERE wa_number IN (?, ?) ORDER BY last_in_at DESC LIMIT 1',
                [$wa1, $wa2]
            );
            if ($q && $q->num_rows() > 0) {
                $row = $q->row();

                return isset($row->last_in_at) ? (string) $row->last_in_at : null;
            }
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('ResendWAQueue getWaConversationLastInAt: ' . $e->getMessage(), 'cron', 'ResendWAQueue');
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}|null [628…, +628…]
     */
    private function normalizePhoneForWaConversations(string $phone): ?array
    {
        $ph = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($ph) < 8) {
            return null;
        }
        if (substr($ph, 0, 2) === '08') {
            $ph = '628' . substr($ph, 2);
        } elseif (substr($ph, 0, 1) === '8' && substr($ph, 0, 2) !== '62') {
            $ph = '62' . $ph;
        } elseif (substr($ph, 0, 2) !== '62') {
            $ph = '62' . $ph;
        }

        return [$ph, '+' . $ph];
    }
}

