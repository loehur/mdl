<?php

namespace App\Controllers\Webhook;

use App\Core\Controller;

/**
 * Fonnte WhatsApp Webhook Handler
 * @see https://docs.fonnte.com/
 *
 * URL: /Webhook/WA_Fonnte
 */
class WA_Fonnte extends Controller
{
    private const NO_REGISTER_TEXT = 'Mohon Maaf, nomor Anda belum terdaftar di Madinah Laundry. Terima kasih';
    private const DEFAULT_REPLY = "Mohon maaf, whatsapp ini tidak dapat membalas pesan 🙏🏻.\n\nSilahkan kirimkan pesan ke *Madinah Laundry (CS)*\n💬 wa.me/6281170706611\n\nTerimakasih 😊";

    public function index()
    {
        header('Content-Type: application/json; charset=utf-8');

        $method = $_SERVER['REQUEST_METHOD'];

        if ($method === 'GET') {
            echo json_encode(['status' => 'ok', 'message' => 'Fonnte webhook endpoint']);
            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!$data) {
            \Log::write('WA_Fonnte: Invalid JSON', 'webhook', 'Fonnte');
            echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
            return;
        }

        // Parse Fonnte webhook payload
        $device = $data['device'] ?? null;
        $sender = $data['sender'] ?? null;
        $message = $data['message'] ?? null;
        $text = $data['text'] ?? null;       // button text
        $member = $data['member'] ?? null;
        $name = $data['name'] ?? null;
        $location = $data['location'] ?? null;
        $pollname = $data['pollname'] ?? null;
        $choices = $data['choices'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
        $inboxid = $data['inboxid'] ?? null;
        $url = $data['url'] ?? null;
        $filename = $data['filename'] ?? null;
        $extension = $data['extension'] ?? null;

        $replyText = self::DEFAULT_REPLY;

        $messageToCheck = trim($message ?? $text ?? '');
        $cekStatusPattern = '/^\s*(cek|sta*tu*s)\s*$/i';

        if (preg_match($cekStatusPattern, $messageToCheck)) {
            $waNumber = $this->normalizeWaNumber($sender);
            if ($waNumber && $this->shouldHandle($waNumber, 'status', 1)) {
                $replyText = $this->getStatusReplyText($sender);
                $this->sendReply($sender, $replyText, $inboxid);
            } else {
                $replyText = ''; // Cooldown - tidak kirim untuk hindari spam
            }
        } else {
            $this->sendReply($sender, $replyText, $inboxid);
        }

        echo json_encode(['status' => 'ok', 'reply' => $replyText]);
    }

    /**
     * Rate limit - sama dengan WAReplies, pakai wa_auto_reply_log (db 0)
     * @return bool True jika boleh kirim, false jika masih cooldown
     */
    private function shouldHandle($waNumber, $handler, $cooldownMinutes = 1)
    {
        $db = $this->db(0);

        $result = $db->query(
            "SELECT created_at FROM wa_auto_reply_log WHERE phone = ? AND handler = ? ORDER BY created_at DESC LIMIT 1",
            [$waNumber, $handler]
        );

        if ($result && $result->num_rows() > 0) {
            $lastReply = $result->row()->created_at;
            $cooldownEnd = date('Y-m-d H:i:s', strtotime($lastReply) + ($cooldownMinutes * 60));
            if (date('Y-m-d H:i:s') < $cooldownEnd) {
                return false;
            }
        }

        $existing = $db->query(
            "SELECT * FROM wa_auto_reply_log WHERE phone = ? AND handler = ? LIMIT 1",
            [$waNumber, $handler]
        )->row();

        if ($existing) {
            $db->update('wa_auto_reply_log', ['created_at' => date('Y-m-d H:i:s')], ['phone' => $waNumber, 'handler' => $handler]);
        } else {
            $db->insert('wa_auto_reply_log', [
                'phone' => $waNumber,
                'handler' => $handler,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    /**
     * Normalize sender ke format wa_number (+62xxx)
     */
    private function normalizeWaNumber($sender)
    {
        if (empty($sender)) return null;
        $clean = preg_replace('/[^0-9]/', '', $sender);
        if (strlen($clean) < 8) return null;
        if (substr($clean, 0, 1) === '0') {
            $clean = '62' . substr($clean, 1);
        } elseif (substr($clean, 0, 2) !== '62') {
            $clean = '62' . $clean;
        }
        return '+' . $clean;
    }

    /**
     * Handle status (cek/status) - return text balasan saja (logic sama WAReplies)
     * @param string|null $sender Nomor WA pengirim (628xxx)
     * @return string Teks balasan
     */
    private function getStatusReplyText($sender)
    {
        if (empty($sender)) {
            return self::NO_REGISTER_TEXT;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $sender);
        if (strlen($cleanPhone) < 8) {
            return self::NO_REGISTER_TEXT;
        }

        if (substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = '62' . substr($cleanPhone, 1);
        } elseif (substr($cleanPhone, 0, 2) !== '62') {
            $cleanPhone = '62' . $cleanPhone;
        }

        $phone0 = '0' . substr($cleanPhone, 2);
        $phonePlus = '+' . $cleanPhone;
        $phoneIn = "'$cleanPhone','$phone0','$phonePlus','" . substr($cleanPhone, 2) . "'";

        $db = $this->db(1);
        $where = "nomor_pelanggan IN ($phoneIn)";
        $pelanggan = $db->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();

        if (empty($pelanggan)) {
            return self::NO_REGISTER_TEXT;
        }

        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
        $nama_pelanggan = strtoupper($pelanggan[0]['nama_pelanggan'] ?? '');
        $ids_in = implode(',', $id_pelanggans);

        $sales = $db->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();
        $noRefs = array_column($sales, 'no_ref');

        if (empty($noRefs)) {
            return 'Pak/Bu *' . $nama_pelanggan . '*, belum ada Nota/Bon terbuka. Terima kasih';
        }

        $listIdPenjualan = [];
        $listIdSelesai = [];
        $allIdPenjualan = [];

        foreach ($noRefs as $noRef) {
            $safeNoRef = $db->conn()->real_escape_string($noRef);
            $get_penjualan = $db->query("SELECT id_penjualan, id_pelanggan, letak FROM sale WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND no_ref = '$safeNoRef'")->result_array();
            $id_penjualans = array_column($get_penjualan, 'id_penjualan');
            $quotedIds = array_map(function ($id) use ($db) {
                return "'" . $db->conn()->real_escape_string($id) . "'";
            }, $id_penjualans);
            $id_penjualans_in = implode(',', $quotedIds);

            $existingNotifIds = [];
            if (!empty($id_penjualans) && !empty($id_penjualans_in)) {
                $existingNotifIds = array_column($db->query("SELECT no_ref FROM notif WHERE tipe = 2 AND no_ref IN ($id_penjualans_in)")->result_array(), 'no_ref');
            }

            $completedWithLocation = [];
            $inProgressItems = [];

            foreach ($get_penjualan as $sale) {
                $id_penjualan = $sale['id_penjualan'];
                $letak = $sale['letak'] ?? '';
                $hasNotif = in_array($id_penjualan, $existingNotifIds);
                $hasLocation = !empty(trim($letak));

                $allIdPenjualan[] = $id_penjualan;
                if ($hasNotif && $hasLocation) {
                    $completedWithLocation[] = $id_penjualan;
                } else {
                    $inProgressItems[] = $id_penjualan;
                }
            }

            if (!empty($inProgressItems)) {
                $listIdPenjualan[] = $inProgressItems;
            }
            if (!empty($completedWithLocation)) {
                $listIdSelesai[] = $completedWithLocation;
            }
        }

        $list_link = "";
        foreach (array_unique($id_pelanggans) as $id_pelanggan) {
            $list_link .= "https://ml.nalju.com/I/" . $id_pelanggan . "\n";
        }

        $statusList = [];
        foreach ($listIdPenjualan as $subArr) {
            foreach ((array)$subArr as $v) {
                $statusList[] = "#" . $v . " - Dalam Pengerjaan";
            }
        }
        foreach ($listIdSelesai as $subArr) {
            foreach ((array)$subArr as $v) {
                $statusList[] = "#" . $v . " - Selesai";
            }
        }

        if (empty($statusList) && !empty($allIdPenjualan)) {
            foreach ($allIdPenjualan as $id) {
                $statusList[] = "#" . $id . " - Selesai";
            }
        }

        $statusText = implode("\n", $statusList);
        return "*" . $nama_pelanggan . "*,\nStatus Laundry:\n" . $statusText . "\n" . $list_link;
    }

    /**
     * Kirim balasan via Fonnte API
     */
    private function sendReply($target, $message, $inboxid = null)
    {
        if (empty($target) || empty($message)) {
            return;
        }
        if (!class_exists('\\App\\Helpers\\FonnteService')) {
            require_once __DIR__ . '/../../Helpers/FonnteService.php';
        }
        $fonnte = new \App\Helpers\FonnteService();
        $options = [];
        if ($inboxid) {
            $options['inboxid'] = (int) $inboxid;
        }
        $fonnte->sendMessage($target, $message, $options);
    }
}
