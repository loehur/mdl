<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Models\IAK;

/**
 * PayBill Controller
 * Menangani pembayaran tagihan postpaid (listrik, PDAM, dll)
 */
class PayBill extends Controller
{
    private $iak;
    private $waPrivate = '081268098300'; // Nomor WA untuk notifikasi error

    public function __construct()
    {
        $this->iak = new IAK();
    }

    /**
     * Get postpaid month format (YYYYMM)
     */
    private function getPostMonth()
    {
        return date('Ym');
    }

    /**
     * Normalisasi response_code IAK untuk perbandingan ketat (hindari switch PHP yang longgar).
     * Ref: https://api.iak.id/api/postpaid/response-code
     */
    private function normalizeIakResponseCode($rc)
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

    /**
     * Simpan hasil inquiry ke postpaid lalu langsung eksekusi pay-pasca di request yang sama (tanpa menunggu cron berikutnya).
     */
    private function insertPostpaidAndPayNow($dt, $d, $month)
    {
        if (empty($d['tr_id']) || empty($d['ref_id'])) {
            $warn = $dt['description'] . " - CHECK OK tetapi tr_id/ref_id kosong — bayar tidak dijalankan\n";
            $this->sendWaNotif($this->waPrivate, $dt['description'] . " inquiry tanpa tr_id/ref_id: " . json_encode($d));
            return $warn;
        }

        $col = [
            'response_code' => $d['response_code'],
            'message' => $d['message'],
            'tr_id' => $d['tr_id'],
            'tr_name' => $d['tr_name'],
            'period' => $d['period'],
            'nominal' => $d['nominal'],
            'admin' => $d['admin'],
            'ref_id' => $d['ref_id'],
            'code' => $d['code'],
            'customer_id' => $d['hp'],
            'price' => $d['price'],
            'selling_price' => $d['selling_price'],
            'description' => serialize($d['desc']),
            'tr_status' => 0,
            'id_cabang' => $dt['id_cabang']
        ];
        $do = $this->db(0)->insert("postpaid", $col);
        if (!$do) {
            $alert = "POSTPAID - DB ERROR - Insert postpaid failed\n";
            $this->sendWaNotif($this->waPrivate, $alert);
            return $alert;
        }

        $output = $dt['description'] . " - CHECK - " . ($d['message'] ?? '') . "\n";

        $a = $this->db(0)->get_where('postpaid', ['ref_id' => $d['ref_id']])->row_array();
        if (empty($a)) {
            $a = [
                'ref_id' => $d['ref_id'],
                'tr_id' => $d['tr_id'],
                'customer_id' => $d['hp'] ?? $dt['customer_id'],
                'code' => $d['code'] ?? $dt['code'],
                'message' => $d['message'] ?? '',
                'response_code' => $d['response_code'] ?? '',
                'price' => $d['price'] ?? null,
                'balance' => $d['balance'] ?? null,
            ];
        }

        $output .= $this->bayar_after_cek($d['ref_id'], $dt, $a, $month);
        return $output;
    }

    /**
     * Verifikasi pembayaran postpaid sukses untuk update last_bill.
     * Ref: https://api.iak.id/api/postpaid/response-code — RC 00 = PAYMENT SUCCESS.
     * Catatan: respons pay-pasca sering tidak menyertakan field status; default tr_status=3 di sini
     * dulu membuat last_bill tidak pernah ter-update meski RC 00.
     */
    private function isPaymentSuccessForLastBill($d, $a, $rc)
    {
        if ($this->normalizeIakResponseCode($rc) !== '00') {
            return false;
        }
        // Status 3 = pending (IAK) — jangan anggap lunas
        if (isset($d['status']) && (int) $d['status'] === 3) {
            return false;
        }
        $customerId = $d['hp'] ?? $a['customer_id'] ?? null;
        $code = $d['code'] ?? $a['code'] ?? null;
        return !empty($customerId) && !empty($code);
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWaNotif($phone, $message)
    {
        // Load WhatsApp Service if available
        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
        }
        $waService = new \App\Helpers\WhatsAppService();
        return $waService->sendFreeText($phone, $message);
    }

    /**
     * Bayar setelah cek tagihan
     */
    public function bayar_after_cek($ref_id, $dt, $a, $month)
    {
        $msg = "";
        $response = $this->iak->post_pay($a);
        
        if (isset($response['data'])) {
            $d = $response['data'];

            $rc = $this->normalizeIakResponseCode(isset($d['response_code']) ? $d['response_code'] : $a['response_code']);
            $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
            $price = isset($d['price']) ? $d['price'] : $a['price'];
            $message = isset($d['message']) ? $d['message'] : $a['message'];
            $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
            $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
            $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
            // Jangan default ke 3 bila status tidak ada — RC 00 + status kosong = sukses menurut IAK
            $tr_status = isset($d['status']) ? $d['status'] : ($a['tr_status'] ?? null);

            if ($rc === '17') {
                $alert = $dt['description'] . " - POSTPAID LIST - " . $message . " Rp" . number_format($price);
                $msg .= $alert . "\n";
                $res = $this->sendWaNotif($this->waPrivate, $alert);
                if (!($res['success'] ?? false)) {
                    $msg .= "WHATSAPP ERROR\n";
                }
                return $msg;
            }
            if ($rc === '04') {
                $tr_status = 2;
            }
            // Pay-pasca RC 00 tanpa field status: jangan biarkan tr_status tetap 0 (sisa dari inquiry)
            if ($rc === '00' && !isset($d['status'])) {
                $tr_status = 1;
            }

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                $set = ['last_bill' => $month];
                $update = $this->db(0)->update('postpaid_list', $set, ['customer_id' => $customerId, 'code' => $code]);
                if ($update) {
                    $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                } else {
                    $alert = "POSTPAID ERROR - Update failed";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                    return $msg;
                }
            }

            $set = [
                'tr_status' => $tr_status !== null ? $tr_status : 1,
                'datetime' => $datetime,
                'noref' => $noref,
                'price' => $price,
                'message' => $message,
                'balance' => $balance,
                'tr_id' => $tr_id,
                'response_code' => $rc
            ];
            $update = $this->db(0)->update('postpaid', $set, ['ref_id' => $ref_id]);
            if ($update) {
                $msg .= $dt['description'] . " - PAY - " . $message . "\n";
                $alert = $dt['description'] . " - PAY - " . $message . " (RC:" . $rc . ")";
                $this->sendWaNotif($this->waPrivate, $alert);
            } else {
                $alert = "POSTPAID ERROR - Update postpaid failed";
                $msg .= $alert . "\n";
                $this->sendWaNotif($this->waPrivate, $alert);
            }
        } else {
            $alert = "UNKNOWN RESPONSE: " . json_encode($response);
            $msg .= $alert . "\n";
            $this->sendWaNotif($this->waPrivate, $alert);
        }
        return $msg;
    }

    /**
     * Cek status setelah bayar
     */
    public function cek_after_bayar($ref_id, $dt, $a, $month)
    {
        $msg = "";
        $response = $this->iak->post_cek($ref_id);
        
        if (isset($response['data'])) {
            $d = $response['data'];
            if (isset($d['status'])) {
                if ($d['status'] == $a['tr_status']) {
                    return $dt['description'] . " Pending " . $a['message'] . "\n";
                }
            }

            $message = isset($d['message']) ? $d['message'] : $a['message'];
            $rc = $this->normalizeIakResponseCode(isset($d['response_code']) ? $d['response_code'] : $a['response_code']);
            $price = isset($d['price']) ? $d['price'] : $a['price'];
            $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
            $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
            $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
            $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
            $tr_status = isset($d['status']) ? $d['status'] : $a['tr_status'];

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                $set = ['last_bill' => $month];
                $update = $this->db(0)->update('postpaid_list', $set, ['customer_id' => $customerId, 'code' => $code]);
                if ($update) {
                    $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                } else {
                    $alert = "POSTPAID - DB ERROR - Update postpaid_list failed";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                    return $msg;
                }
            }

            $set = [
                'tr_status' => $tr_status !== null && $tr_status !== '' ? $tr_status : 1,
                'datetime' => $datetime,
                'noref' => $noref,
                'price' => $price,
                'message' => $message,
                'balance' => $balance,
                'tr_id' => $tr_id,
                'response_code' => $rc
            ];
            $update = $this->db(0)->update('postpaid', $set, ['ref_id' => $ref_id]);
            if ($update) {
                $msg .= $dt['description'] . " - POSTPAID - " . $message . "\n";
            } else {
                $alert = "POSTPAID - DB ERROR - Update postpaid failed";
                $msg .= $alert . "\n";
                $this->sendWaNotif($this->waPrivate, $alert);
            }
        } else {
            $alert = "UNKNOWN RESPONSE: " . json_encode($response);
            $msg .= $alert . "\n";
            $this->sendWaNotif($this->waPrivate, $alert);
        }

        return $msg;
    }

    /**
     * Main function: Cek dan bayar semua tagihan
     */
    public function index()
    {
        // CEK SEMUA TAGIHAN
        $month = $this->getPostMonth();
        $output = "";

        $data = $this->db(0)->query("SELECT * FROM postpaid_list WHERE en = ? ORDER BY code ASC", [1])->result_array();
        
        foreach ($data as $dt) {
            $code = $dt['code'];
            $customer_id = $dt['customer_id'];

            if ($dt['last_bill'] == $month) {
                $output .= $dt['description'] . " PAID\n";
                continue;
            }

            // Cek tagihan yang sudah pernah di cek atau dibayar
            $cek = $this->db(0)->query("SELECT * FROM postpaid WHERE customer_id = ? AND code = ? AND (tr_status = 0 OR tr_status = 3)", [$dt['customer_id'], $dt['code']])->result_array();
            
            if (count($cek) > 0) {
                foreach ($cek as $a) {
                    $ref_id = $a['ref_id'];

                    if ($a['tr_status'] == 3) {
                        // tr_status 3 = pending/failed. Retry bayar (bukan cek status) agar:
                        // - jika gagal (saldo tidak cukup): retry + kirim WA saat gagal lagi
                        // - jika pending: post_pay bisa return status terbaru
                        $output .= $this->bayar_after_cek($ref_id, $dt, $a, $month);
                    } else {
                        // Bayar karena sudah pernah di cek (tr_status 0)
                        $output .= $this->bayar_after_cek($ref_id, $dt, $a, $month);
                    }
                }
            } else {
                // Cek tagihan karena belum pernah di cek sama sekali
                $response = $this->iak->post_inquiry($code, $customer_id, $dt['id_cabang']);
                
                if (isset($response['data'])) {
                    $d = $response['data'];

                    if (isset($d['response_code'])) {
                        $inqRc = $this->normalizeIakResponseCode($d['response_code']);
                        switch ($inqRc) {
                            case "01":
                            case "34":
                            case "40":
                                // SUDAH DIBAYAR (invoice/bill sudah dibayar) - boleh update last_bill
                                // Catatan: 10 = BILL IS NOT AVAILABLE (tagihan belum tersedia) - JANGAN update
                                $resHp = $d['hp'] ?? $customer_id;
                                $resCode = $d['code'] ?? $code;
                                if (empty($resHp) || empty($resCode) || $resHp != $customer_id || $resCode != $code) {
                                    $alert = "POSTPAID - SUDAH DIBAYAR data mismatch (hp/code)";
                                    $output .= $alert . "\n";
                                    $this->sendWaNotif($this->waPrivate, $alert);
                                } else {
                                    $set = ['last_bill' => $month];
                                    $update = $this->db(0)->update('postpaid_list', $set, ['customer_id' => $customer_id, 'code' => $code]);
                                    if ($update) {
                                        $output .= $dt['description'] . " " . $d['message'] . "\n";
                                    } else {
                                        $alert = "POSTPAID - DB ERROR - Update postpaid_list failed";
                                        $output .= $alert . "\n";
                                        $this->sendWaNotif($this->waPrivate, $alert);
                                    }
                                }
                                break;

                            case "10":
                                // BILL IS NOT AVAILABLE - tagihan belum tersedia, JANGAN update last_bill
                                $output .= $dt['description'] . " - " . ($d['message'] ?? 'Tagihan belum tersedia, coba lagi nanti') . "\n";
                                break;
                                
                            case "00":
                            case "05":
                            case "39":
                            case "201":
                                // Inquiry sukses: simpan ke DB lalu pay-pasca di request ini (satu jalan dengan cron)
                                $output .= $this->insertPostpaidAndPayNow($dt, $d, $month);
                                break;
                                
                            case "106":
                                // PROVIDER GANGGUAN
                                if (isset($d['message'])) {
                                    $alert = $dt['description'] . " - " . $d['message'];
                                } else {
                                    $alert = "UNKNOWN RESPONSE CODE: " . $d['response_code'];
                                }
                                $output .= $alert . "\n";
                                $this->sendWaNotif($this->waPrivate, $alert);
                                break;
                                
                            default:
                                if (isset($d['message'])) {
                                    $alert = $dt['description'] . " - RESPONSE CODE: " . $d['response_code'] . " - " . $d['message'];
                                } else {
                                    $alert = "UNKNOWN RESPONSE CODE: " . $d['response_code'];
                                }
                                $output .= $alert . "\n";
                                $this->sendWaNotif($this->waPrivate, $alert);
                                break;
                        }
                    } else {
                        $alert = "UNKNOWN RESPONSE: " . json_encode($d);
                        $output .= $alert . "\n";
                        $this->sendWaNotif($this->waPrivate, $alert);
                    }
                } else {
                    $alert = "UNKNOWN RESPONSE: " . json_encode($response);
                    $output .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                }
            }
        }

        // Output sebagai plain text untuk cron
        header('Content-Type: text/plain');
        echo $output;
    }
}
