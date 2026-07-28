<?php

namespace App\Controllers\Cron;

use App\Controllers\Invoice\InvoiceController;
use App\Helpers\CRM\WhatsAppService;

/**
 * Kirim reminder WA template untuk invoice unpaid H-3 s/d H.
 *
 * URL example:
 * /Cron/InvoiceDueReminder/index?secret=YOUR_CRON_SECRET
 */
class InvoiceDueReminder extends InvoiceController
{
    private const TEMPLATE_NAME = 'template_utility_20260725132816';
    private const TEMPLATE_LANG = 'id';
    private const PUBLIC_BASE = 'https://invoice.nalju.com/#/i/';

    public function index()
    {
        if (!$this->verifyCronSecret()) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(401);
            echo "ERROR: Unauthorized\n";
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');

        $db = $this->db($this->db_index);
        if (!$db) {
            echo "ERROR: Database connection failed\n";
            return;
        }

        $today = date('Y-m-d');
        $rows = $db->query(
            "SELECT i.id, i.invoice_number, i.public_token, i.title, i.customer_name,
                    i.customer_phone, i.issue_date, i.due_date, i.total,
                    DATEDIFF(i.due_date, ?) AS days_until_due
             FROM invoices i
             LEFT JOIN invoice_wa_reminders r
               ON r.invoice_id = i.id AND r.remind_date = ?
             WHERE i.due_date IS NOT NULL
               AND i.due_date BETWEEN ? AND DATE_ADD(?, INTERVAL 3 DAY)
               AND i.payment_status != 'paid'
               AND i.status != 'cancelled'
               AND i.customer_phone IS NOT NULL
               AND TRIM(i.customer_phone) != ''
               AND r.id IS NULL
             ORDER BY i.due_date ASC, i.id ASC
             LIMIT 200",
            [$today, $today, $today, $today]
        )->result_array();

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        echo "InvoiceDueReminder run at " . date('Y-m-d H:i:s') . "\n";
        echo "Candidates: " . count($rows) . "\n\n";

        $wa = new WhatsAppService();

        foreach ($rows as $inv) {
            $id = (int) $inv['id'];
            $daysUntil = (int) $inv['days_until_due'];

            try {
                $phone = trim((string) $inv['customer_phone']);
                if ($phone === '') {
                    $skipped++;
                    echo "SKIP #{$id}: empty phone\n";
                    continue;
                }

                $params = [
                    'customer_name' => (string) ($inv['customer_name'] ?? ''),
                    'invoice_title' => (string) ($inv['title'] ?? ''),
                    'invoice_number' => (string) ($inv['invoice_number'] ?? ''),
                    'date' => !empty($inv['issue_date'])
                        ? date('d/m/Y', strtotime($inv['issue_date']))
                        : '-',
                    'total' => 'Rp' . number_format((float) $inv['total'], 0, ',', '.') . ',-',
                    'invoice_link' => self::PUBLIC_BASE . $inv['public_token'],
                ];

                $result = $wa->sendTemplate(
                    $phone,
                    self::TEMPLATE_NAME,
                    self::TEMPLATE_LANG,
                    $params
                );

                $ok = !empty($result['success']) && !empty($result['data']['id']);
                $messageId = $ok ? (string) $result['data']['id'] : null;
                $errorMsg = null;

                if (!$ok) {
                    $errorMsg = $result['error']['message']
                        ?? ($result['error'] ?? null)
                        ?? ($result['data']['error']['message'] ?? 'send failed');
                    if (is_array($errorMsg)) {
                        $errorMsg = json_encode($errorMsg);
                    }
                    $errorMsg = substr((string) $errorMsg, 0, 1000);
                }

                $db->insert('invoice_wa_reminders', [
                    'invoice_id' => $id,
                    'remind_date' => $today,
                    'days_until_due' => $daysUntil,
                    'wa_message_id' => $messageId,
                    'status' => $ok ? 'sent' : 'failed',
                    'error_message' => $errorMsg,
                ]);

                if ($ok) {
                    $sent++;
                    echo "OK #{$id} {$inv['invoice_number']} H-{$daysUntil} -> {$messageId}\n";
                } else {
                    $failed++;
                    echo "FAIL #{$id} {$inv['invoice_number']}: {$errorMsg}\n";
                }
            } catch (\Throwable $e) {
                $failed++;
                try {
                    $db->insert('invoice_wa_reminders', [
                        'invoice_id' => $id,
                        'remind_date' => $today,
                        'days_until_due' => $daysUntil,
                        'wa_message_id' => null,
                        'status' => 'failed',
                        'error_message' => substr($e->getMessage(), 0, 1000),
                    ]);
                } catch (\Throwable $ignore) {
                    // unique conflict or db error
                }
                echo "ERR #{$id}: " . $e->getMessage() . "\n";
            }
        }

        echo "\nDone. sent={$sent} failed={$failed} skipped={$skipped}\n";
    }

    protected function verifyCronSecret(): bool
    {
        $expected = '';
        if (class_exists('Env') && defined('Env::CRON_SECRET')) {
            $expected = (string) \Env::CRON_SECRET;
        }

        if ($expected === '') {
            $expected = getenv('CRON_SECRET') ?: '';
        }

        if ($expected === '') {
            return false;
        }

        $provided = trim((string) ($_GET['secret'] ?? ''));
        if ($provided === '' && !empty($_SERVER['HTTP_X_CRON_SECRET'])) {
            $provided = trim((string) $_SERVER['HTTP_X_CRON_SECRET']);
        }

        return hash_equals($expected, $provided);
    }
}
