<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;
use App\Helpers\PostpaidTrStatus;

/**
 * Webhook IAK — update tabel prepaid dan/atau postpaid sesuai ref_id.
 * Ref payload umum: data.ref_id, data.status, data.rc atau response_code, dll.
 */
class IAK extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');
        $response = json_decode($json, true);

        \Log::write("Incoming: " . $json, 'webhook', 'IAK');

        if (!isset($response['data'])) {
            \Log::write("Err: No data", 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'DATA RESPONSE NOT FOUND']);
            return;
        }

        $d = $response['data'];
        $ref_id = $d['ref_id'] ?? null;

        if (!$ref_id) {
            \Log::write("Err: No ref_id", 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'REF_ID NOT FOUND']);
            return;
        }

        try {
            $db0 = $this->db(0);
            if (!$db0) {
                \Log::write("Err: DB 0", 'webhook', 'IAK');
                echo json_encode(['status' => false, 'message' => 'Database connection failed']);
                return;
            }

            // 1) Prepaid (pulsa / prabayar)
            $prepaidQuery = $db0->get_where('prepaid', ['ref_id' => $ref_id]);
            if ($prepaidQuery && $prepaidQuery->num_rows() > 0) {
                $this->updatePrepaid($db0, $ref_id, $d, $prepaidQuery->row_array());
                return;
            }

            // 2) Postpaid (tagihan pasca)
            $postpaidQuery = $db0->get_where('postpaid', ['ref_id' => $ref_id]);
            if ($postpaidQuery && $postpaidQuery->num_rows() > 0) {
                $this->updatePostpaid($db0, $ref_id, $d, $postpaidQuery->row_array());
                return;
            }

            \Log::write("Err: Not found prepaid/postpaid ref_id=$ref_id", 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Record not found']);
        } catch (\Exception $e) {
            \Log::write("Err: Exception " . $e->getMessage(), 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (\Error $e) {
            \Log::write("Err: Error " . $e->getMessage(), 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Samakan dengan PayBill — response_code dua digit.
     */
    private function normalizeResponseCode($rc)
    {
        if ($rc === null || $rc === '') {
            return '';
        }
        if (is_int($rc) || (is_string($rc) && ctype_digit((string) $rc))) {
            $n = (int) $rc;
            if ($n < 100) {
                return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            }
            return (string) $n;
        }
        return (string) $rc;
    }

    private function updatePrepaid($db0, $ref_id, $d, $a)
    {
        $tr_status = $d['status'] ?? $a['tr_status'];
        $price = $d['price'] ?? $a['price'];
        $message = $d['message'] ?? $a['message'];
        $balance = $d['balance'] ?? $a['balance'];
        $tr_id = $d['tr_id'] ?? $a['tr_id'];
        $rc = $d['rc'] ?? $d['response_code'] ?? $a['rc'];
        $sn = $d['sn'] ?? $a['sn'];

        $set = [
            'sn' => $sn,
            'tr_status' => $tr_status,
            'price' => $price,
            'message' => $message,
            'balance' => $balance,
            'tr_id' => $tr_id,
            'rc' => $rc,
        ];

        $update = $db0->update('prepaid', $set, ['ref_id' => $ref_id]);

        if ($update) {
            \Log::write("OK prepaid: $ref_id, SN: $sn, Status: $tr_status", 'webhook', 'IAK');
            $this->sendWhatsAppNotification($d);
            echo json_encode(['status' => true, 'message' => 'Updated successfully', 'product' => 'prepaid']);
        } else {
            \Log::write("Err: Update prepaid fail $ref_id", 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Update failed']);
        }
    }

    private function updatePostpaid($db0, $ref_id, $d, $a)
    {
        $rcRaw = $d['response_code'] ?? $d['rc'] ?? $a['response_code'] ?? '';
        $rc = $this->normalizeResponseCode($rcRaw);

        $tr_status = PostpaidTrStatus::resolve($d, $a, $rc);
        $price = $d['price'] ?? $a['price'];
        $message = $d['message'] ?? $a['message'];
        $balance = $d['balance'] ?? $a['balance'];
        $tr_id = $d['tr_id'] ?? $a['tr_id'];
        $datetime = $d['datetime'] ?? $a['datetime'] ?? null;
        $noref = $d['noref'] ?? $a['noref'] ?? null;

        $set = [
            'tr_status' => $tr_status,
            'response_code' => $rc !== '' ? $rc : ($a['response_code'] ?? ''),
            'message' => $message,
            'price' => $price,
            'balance' => $balance,
            'tr_id' => $tr_id,
        ];

        if ($datetime !== null && $datetime !== '') {
            $set['datetime'] = $datetime;
        }
        if ($noref !== null && $noref !== '') {
            $set['noref'] = $noref;
        }

        $update = $db0->update('postpaid', $set, ['ref_id' => $ref_id]);

        if ($update) {
            $okRows = $db0->affected_rows() > 0;
            \Log::write(
                "OK postpaid: $ref_id, RC: {$set['response_code']}, Status: {$set['tr_status']}, affected: " . ($okRows ? 'yes' : 'no'),
                'webhook',
                'IAK'
            );
            echo json_encode([
                'status' => true,
                'message' => 'Updated successfully',
                'product' => 'postpaid',
                'affected' => $okRows,
            ]);
        } else {
            \Log::write("Err: Update postpaid fail $ref_id", 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Update failed']);
        }
    }

    /**
     * Kirim notifikasi WhatsApp setelah transaksi prepaid sukses (format ref_id wa-...)
     */
    private function sendWhatsAppNotification($record)
    {
        try {
            $ref_id = $record['ref_id'] ?? '';
            $sn = $record['sn'] ?? '';
            $message = $record['message'] ?? '';

            $parts = explode('-', $ref_id);

            if (count($parts) < 2 || $parts[0] != 'wa') {
                return;
            }

            $waNumber = $parts[1];
            $waNumber = preg_replace('/[^0-9]/', '', $waNumber);

            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
            }
            $waService = new \App\Helpers\WhatsAppService();
            if ($sn) {
                $sn = explode('/', $sn)[0];
                $text = "*" . $sn . "*";
            } else {
                $text = $message;
            }

            $waService->sendFreeText($waNumber, $text);

            \Log::write("WA Notif sent to $waNumber", 'webhook', 'IAK');
        } catch (\Exception $e) {
            \Log::write("Err: WA Notif " . $e->getMessage(), 'webhook', 'IAK');
        }
    }
}
