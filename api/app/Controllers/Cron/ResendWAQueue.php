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
            $output .= "processed=0\n";
            $output .= "skipped=0\n";

            header('Content-Type: text/plain');
            echo $output;
            return;
        }

        $waService = new \App\Helpers\WhatsAppService();
        $processed = 0;
        $skipped = 0;

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

        $output .= "SUMMARY intervalMinutes={$intervalMinutes} limit={$limit} processed={$processed} skipped={$skipped}\n";

        header('Content-Type: text/plain');
        echo $output;
    }
}

