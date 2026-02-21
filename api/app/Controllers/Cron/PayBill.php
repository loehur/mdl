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
     * Verifikasi bahwa pembayaran tagihan BENAR-BENAR sukses sebelum update last_bill.
     * Harus memenuhi: tr_status=1, response_code sukses, dan data customer valid.
     */
    private function isPaymentSuccessForLastBill($d, $a, $rc, $tr_status)
    {
        if ($tr_status != 1) {
            return false;
        }
        $successCodes = ['00', '01', '10', '34', '40'];
        if (!in_array((string)$rc, $successCodes)) {
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

            $rc = isset($d['response_code']) ? $d['response_code'] : $a['response_code'];
            $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
            $price = isset($d['price']) ? $d['price'] : $a['price'];
            $message = isset($d['message']) ? $d['message'] : $a['message'];
            $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
            $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
            $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
            $tr_status = isset($d['status']) ? $d['status'] : 3;

            switch ($rc) {
                case '17':
                    $alert = $dt['description'] . " - POSTPAID LIST - " . $message . " Rp" . number_format($price);
                    $msg .= $alert . "\n";
                    $res = $this->sendWaNotif($this->waPrivate, $alert);
                    if (!($res['success'] ?? false)) {
                        $msg .= "WHATSAPP ERROR\n";
                    }
                    return $msg;
                    break;
                case '04':
                    $tr_status = 2;
                    break;
            }

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc, $tr_status)) {
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
                'tr_status' => $tr_status,
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
                $msg .= $dt['description'] . " - PAY - " . $a['message'] . "\n";
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
            $rc = isset($d['response_code']) ? $d['response_code'] : $a['response_code'];
            $price = isset($d['price']) ? $d['price'] : $a['price'];
            $balance = isset($d['balance']) ? $d['balance'] : $a['balance'];
            $tr_id = isset($d['tr_id']) ? $d['tr_id'] : $a['tr_id'];
            $datetime = isset($d['datetime']) ? $d['datetime'] : $a['datetime'];
            $noref = isset($d['noref']) ? $d['noref'] : $a['noref'];
            $tr_status = isset($d['status']) ? $d['status'] : $a['tr_status'];

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc, $tr_status)) {
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
                'tr_status' => $tr_status,
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
                $msg .= $dt['description'] . " - POSTPAID - " . $a['message'] . "\n";
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
                        switch ($d['response_code']) {
                            case "01":
                            case "10":
                            case "34":
                            case "40":
                                // SUDAH DIBAYAR - verifikasi response cocok dengan data yang dicek
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
                                
                            case "00":
                            case "05":
                            case "39":
                            case "201":
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
                                if ($do) {
                                    $output .= $dt['description'] . " - CHECK - " . $d['message'] . "\n";

                                    // Bayar karena sudah pernah di cek
                                    $a = $this->db(0)->get_where('postpaid', ['ref_id' => $d['ref_id']])->row_array();
                                    $output .= $this->bayar_after_cek($d['ref_id'], $dt, $a, $month);
                                } else {
                                    $alert = "POSTPAID - DB ERROR - Insert postpaid failed\n";
                                    $output .= $alert . "\n";
                                    $this->sendWaNotif($this->waPrivate, $alert);
                                }
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
