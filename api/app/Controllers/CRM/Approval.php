<?php

namespace App\Controllers\CRM;

use App\Core\Controller;

/**
 * CRM Approval - List pembayaran NonTunai per customer
 * Data sama dengan laundry AdminApproval/index/NonTunai
 * Menggunakan db(1) = mdl_laundry
 */
class Approval extends Controller
{
    public function __construct()
    {
        $this->handleCors();
    }

    /**
     * GET /CRM/Approval/list?cust_id=123
     * List pembayaran pending untuk customer (NonTunai)
     */
    public function list()
    {
        $custId = (int) ($_GET['cust_id'] ?? 0);
        if ($custId <= 0) {
            $this->error('cust_id required', 400);
        }

        $db = $this->db(1);

        $sql = "SELECT k.ref_finance, k.note, k.id_user, k.id_client, k.jenis_transaksi, SUM(k.jumlah) as total, MAX(k.insertTime) as insertTime
                FROM kas k
                WHERE k.id_client = ? AND k.metode_mutasi = 2 AND k.status_mutasi = 2 AND k.ref_finance <> ''
                GROUP BY k.ref_finance
                ORDER BY k.ref_finance DESC
                LIMIT 50";
        $rows = $db->query($sql, [$custId])->result_array();

        $items = [];
        foreach ($rows as $a) {
            $pelanggan = 'Pelanggan';
            $karyawan = '';
            $jenisBill = $this->jenisBillLabel($a['jenis_transaksi']);

            $pel = $db->query("SELECT nama_pelanggan FROM pelanggan WHERE id_pelanggan = ?", [(int)$a['id_client']])->row_array();
            if ($pel) {
                $pelanggan = $pel['nama_pelanggan'];
            }

            $usr = $db->query("SELECT nama_user FROM user WHERE id_user = ?", [(int)$a['id_user']])->row_array();
            if ($usr) {
                $karyawan = $usr['nama_user'];
            }

            if ($a['jenis_transaksi'] == 5) {
                $usr2 = $db->query("SELECT nama_user FROM user WHERE id_user = ?", [(int)$a['id_client']])->row_array();
                if ($usr2) {
                    $pelanggan = $usr2['nama_user'];
                }
            } elseif ($a['jenis_transaksi'] == 7) {
                $pelanggan = 'Umum';
            }

            $items[] = [
                'ref_finance' => $a['ref_finance'],
                'note' => $a['note'],
                'id_user' => $a['id_user'],
                'id_client' => $a['id_client'],
                'jenis_transaksi' => $a['jenis_transaksi'],
                'jenis_bill' => $jenisBill,
                'total' => (float) $a['total'],
                'pelanggan' => $pelanggan,
                'karyawan' => $karyawan,
                'insertTime' => $a['insertTime'] ?? null,
            ];
        }

        $this->success(['items' => $items], 'OK');
    }

    /**
     * POST /CRM/Approval/operasi
     * Body: { id: ref_finance, tipe: 3|4 } (3=terima, 4=tolak)
     */
    public function operasi()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }

        $body = $this->getBody();
        $id = trim($body['id'] ?? $body['ref_finance'] ?? '');
        $tipe = (int) ($body['tipe'] ?? 0);

        if (empty($id) || !in_array($tipe, [3, 4])) {
            $this->error('Invalid id or tipe. Use tipe 3 (terima) or 4 (tolak)', 400);
        }

        $db = $this->db(1);
        $updated = $db->update('kas', ['status_mutasi' => $tipe], ['ref_finance' => $id]);

        if (!$updated) {
            $this->error('Update failed', 500);
        }

        // WebSocket push & wa_conversations priority update (same as laundry NonTunai)
        try {
            $kasData = $db->query("SELECT id_client FROM kas WHERE ref_finance = ? LIMIT 1", [$id])->row_array();
            if ($kasData && !empty($kasData['id_client'])) {
                $pel = $db->query("SELECT nomor_pelanggan FROM pelanggan WHERE id_pelanggan = ?", [(int)$kasData['id_client']])->row_array();
                if ($pel && !empty($pel['nomor_pelanggan'])) {
                    $cleanPhone = preg_replace('/[^0-9]/', '', $pel['nomor_pelanggan']);
                    $phone08 = '0' . substr($cleanPhone, -10);
                    $phone62 = '62' . substr($cleanPhone, -10);
                    $phonePlus62 = '+62' . substr($cleanPhone, -10);
                    $phones = ["'$phone08'", "'$phone62'", "'$phonePlus62'"];
                    $phoneIn = implode(',', $phones);

                    $dbMain = $this->db(0);
                    if (method_exists($dbMain, 'query')) {
                        $dbMain->query("UPDATE wa_conversations SET priority = 0 WHERE priority = 2 AND wa_number IN ($phoneIn)");
                    }

                    $payload = [
                        'type' => 'priority_updated',
                        'phone' => $phonePlus62,
                        'priority' => 0,
                        'target_id' => '0',
                        'sender_id' => 'system',
                    ];
                    $ch = curl_init(\App\Helpers\CRM\WaServer::incomingUrl());
                    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode($payload),
                        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                        CURLOPT_TIMEOUT => 3,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal
        }

        $this->success(['status' => 'ok', 'tipe' => $tipe], 'OK');
    }

    private function jenisBillLabel($jenis)
    {
        $map = [1 => 'Laundry', 3 => 'Member', 5 => 'Kasbon', 6 => 'Deposit', 7 => 'Jualan'];
        return $map[(int)$jenis] ?? 'Lainnya';
    }
}
