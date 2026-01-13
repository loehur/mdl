<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;

class IAK extends Controller
{
    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $json = file_get_contents('php://input');
        $response = json_decode($json, true);

        // Log incoming webhook
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

            // Get existing record
            $existing_query = $db0->get_where("prepaid", ["ref_id" => $ref_id]);
            
            if (!$existing_query || $existing_query->num_rows() == 0) {
                \Log::write("Err: Not found $ref_id", 'webhook', 'IAK');
                echo json_encode(['status' => false, 'message' => 'Record not found']);
                return;
            }

            $a = $existing_query->row_array();

            // Prepare update data with fallback to existing values
            $tr_status = $d['status'] ?? $a['tr_status'];
            $price = $d['price'] ?? $a['price'];
            $message = $d['message'] ?? $a['message'];
            $balance = $d['balance'] ?? $a['balance'];
            $tr_id = $d['tr_id'] ?? $a['tr_id'];
            $rc = $d['rc'] ?? $a['rc'];
            $sn = $d['sn'] ?? $a['sn'];

            $set = [
                'sn' => $sn,
                'tr_status' => $tr_status,
                'price' => $price,
                'message' => $message,
                'balance' => $balance,
                'tr_id' => $tr_id,
                'rc' => $rc
            ];

            $update = $db0->update('prepaid', $set, ['ref_id' => $ref_id]);

            if ($update) {
                \Log::write("OK: $ref_id, SN: $sn, Status: $tr_status", 'webhook', 'IAK');
                $this->sendWhatsAppNotification($a);
                echo json_encode(['status' => true, 'message' => 'Updated successfully']);
            } else {
                \Log::write("Err: Update fail $ref_id", 'webhook', 'IAK');
                echo json_encode(['status' => false, 'message' => 'Update failed']);
            }

        } catch (\Exception $e) {
            \Log::write("Err: Exception " . $e->getMessage(), 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (\Error $e) {
            \Log::write("Err: Error " . $e->getMessage(), 'webhook', 'IAK');
            echo json_encode(['status' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Kirim notifikasi WhatsApp setelah transaksi sukses
     */
    private function sendWhatsAppNotification($record)
    {
        try {
            $ref_id = $record['ref_id'] ?? '';
            $sn = $record['sn'] ?? '';
            $message = $record['message'] ?? '';
            
            // Ambil bagian SN sebelum "/" (token number saja)
            if ($sn) {
                $sn = explode('/', $sn)[0];  // Fixed: separator dulu, baru string
            }
            
            // Parse ref_id: wa-{waNumber}-{datetime}-{id_cabang}
            $parts = explode('-', $ref_id);
            
            // Validasi: harus dimulai dengan 'wa' dan punya minimal 2 bagian
            if (count($parts) < 2 || $parts[0] != 'wa') {
                return;
            }

            $waNumber = $parts[1];
            
            // Hapus karakter non-digit (seperti +) dari nomor WA
            $waNumber = preg_replace('/[^0-9]/', '', $waNumber);

            // Kirim notifikasi
            $waService = new \App\Services\WA();
            if ($sn) {
                $text = "*" . implode(' ', str_split($sn, 4)) . "*";
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
