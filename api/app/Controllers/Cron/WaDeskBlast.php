<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\WaDesk\TemplateSender as WaDeskTemplateSender;

/**
 * WaDeskBlast — process wa_blast_recipients queue in batches.
 *
 * URL: /Cron/WaDeskBlast/index?limit=30
 * Schedule every 1-2 minutes on the server cron.
 *
 * Rate limit: ~1 request/sec to YCloud (usleep between sends).
 */
class WaDeskBlast extends Controller
{
    private const DB_INDEX   = 7;
    /** Conservative rate limit: ~2.5–4.0 sec between YCloud calls (jitter). */
    private const SLEEP_MIN_US = 2500000;
    private const SLEEP_MAX_US = 4000000;

    public function index()
    {
        $limit = min(30, max(1, (int) ($_GET['limit'] ?? 20)));

        $db = $this->db(self::DB_INDEX);
        $output = '';
        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        // Pick pending blast jobs (oldest first)
        $channelsTable = 'wa_channels';
        $blasts = $db->query(
            "SELECT b.*, k.device_id, k.phone_number, k.tenant_id AS key_tenant_id, k.team_id,
                    t.template_name, t.language, t.body_preview
             FROM wa_blasts b
             INNER JOIN {$channelsTable} k ON k.id = b.channel_id
             INNER JOIN wa_templates t ON t.id = b.template_id
             WHERE b.status IN ('pending','processing')
             ORDER BY b.id ASC
             LIMIT 10"
        )->result_array();

        if (!$blasts) {
            $output .= "WaDeskBlast: no active blasts\n";
            echo $output;
            return;
        }

        foreach ($blasts as $blast) {
            $blastId = (int) $blast['id'];

            // Mark as processing
            if ($blast['status'] === 'pending') {
                $db->update('wa_blasts', [
                    'status'     => 'processing',
                    'started_at' => date('Y-m-d H:i:s'),
                ], ['id' => $blastId]);
            }

            // Load template param defs
            $paramDefs = $db->query(
                "SELECT component, button_sub_type, button_index, param_index, param_name, label, is_required, maxlength
                 FROM wa_template_params WHERE template_id = ?
                 ORDER BY FIELD(component,'header','body','button'), param_index ASC",
                [(int) $blast['template_id']]
            )->result_array();

            // Claim a batch of pending recipients
            $recipients = $db->query(
                "SELECT * FROM wa_blast_recipients
                 WHERE blast_id = ? AND status = 'pending'
                 ORDER BY id ASC
                 LIMIT {$limit}",
                [$blastId]
            )->result_array();

            if (!$recipients) {
                // Check if all done
                $this->finishIfComplete($db, $blast);
                continue;
            }

            $channelRow = [
                'id'          => (int) $blast['channel_id'],
                'device_id'   => $blast['device_id'] ?? '',
                'phone_number'=> $blast['phone_number'],
                'tenant_id'   => (int) $blast['key_tenant_id'],
                'team_id'     => (int) $blast['team_id'],
            ];

            $tplRow = [
                'id'           => (int) $blast['template_id'],
                'template_name'=> $blast['template_name'],
                'language'     => $blast['language'],
                'body_preview' => $blast['body_preview'],
            ];

            $sender = new WaDeskTemplateSender($db, self::DB_INDEX);

            foreach ($recipients as $recip) {
                $recipId = (int) $recip['id'];
                $phone   = (string) $recip['phone'];
                $rawParams = [];

                if (!empty($recip['params_json'])) {
                    $decoded = json_decode($recip['params_json'], true);
                    if (is_array($decoded)) {
                        $rawParams = $decoded;
                    }
                }

                $result = $sender->sendOne($channelRow, $tplRow, $paramDefs, $phone, $rawParams, 0, true, [
                    'blast_id' => $blastId,
                    'blast_recipient_id' => $recipId,
                ]);

                if ($result['success']) {
                    $db->update('wa_blast_recipients', [
                        'status'          => 'sent',
                        'conversation_id' => $result['conversation_id'],
                        'message_id'      => $result['message_id'],
                        'sent_at'         => date('Y-m-d H:i:s'),
                    ], ['id' => $recipId]);

                    $db->query(
                        "UPDATE wa_blasts SET sent = sent + 1 WHERE id = ?",
                        [$blastId]
                    );
                    $succeeded++;
                } else {
                    $db->update('wa_blast_recipients', [
                        'status' => 'failed',
                        'error'  => mb_substr($result['error'], 0, 500),
                        'sent_at'=> date('Y-m-d H:i:s'),
                    ], ['id' => $recipId]);

                    $db->query(
                        "UPDATE wa_blasts SET failed = failed + 1 WHERE id = ?",
                        [$blastId]
                    );
                    $failed++;
                }

                $processed++;
                $output .= "recip#{$recipId} phone={$phone} " . ($result['success'] ? 'OK' : 'FAIL: ' . $result['error']) . "\n";

                // Conservative rate limit with jitter (~2.5–4.0 sec)
                usleep(random_int(self::SLEEP_MIN_US, self::SLEEP_MAX_US));
            }

            // Re-load blast to check completion
            $blastNow = $db->query(
                "SELECT * FROM wa_blasts WHERE id = ? LIMIT 1",
                [$blastId]
            )->row_array();

            $this->finishIfComplete($db, $blastNow);
        }

        $output  = "WaDeskBlast run\n"
            . "processed={$processed}\n"
            . "succeeded={$succeeded}\n"
            . "failed={$failed}\n"
            . $output;

        echo $output;
    }

    private function tableExists(string $table): bool
    {
        try {
            $row = $this->db(self::DB_INDEX)->query(
                "SELECT COUNT(*) AS cnt FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            )->row_array();
            return (int) ($row['cnt'] ?? 0) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function finishIfComplete($db, array $blast): void
    {
        $blastId = (int) $blast['id'];
        $pending = (int) $db->query(
            "SELECT COUNT(*) AS cnt FROM wa_blast_recipients WHERE blast_id = ? AND status = 'pending'",
            [$blastId]
        )->row_array()['cnt'];

        if ($pending === 0 && $blast['status'] !== 'cancelled') {
            $db->update('wa_blasts', [
                'status'      => 'done',
                'finished_at' => date('Y-m-d H:i:s'),
            ], ['id' => $blastId]);
        }
    }
}
