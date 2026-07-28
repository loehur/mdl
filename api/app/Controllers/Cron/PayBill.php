<?php

namespace App\Controllers\Cron;

use App\Core\Controller;
use App\Helpers\Shared\PostpaidTrStatus;
use App\Models\IAK;

/**
 * PayBill Controller
 * Menangani pembayaran tagihan postpaid (listrik, PDAM, dll)
 *
 * postpaid.tr_status: 0=sudah cek/blm bayar, 1=sukses, 2=gagal, 3=dalam proses — lihat PostpaidTrStatus.
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
     * Kolom INT di postpaid — jangan kirim string kosong (MySQL strict: Incorrect integer value '').
     */
    private function intOrNullForPostpaid($v)
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) {
            return (int) round((float) $v);
        }
        return null;
    }

    /**
     * Untuk UPDATE postpaid: kolom INT NOT NULL — jangan kirim NULL (pertahankan nilai lama di DB jika API tidak kirim).
     */
    private function applyIntColumnsIfPresentForPostpaidUpdate(array &$set, array $d, array $keys)
    {
        foreach ($keys as $k) {
            $v = $this->intOrNullForPostpaid($d[$k] ?? null);
            if ($v !== null) {
                $set[$k] = $v;
            }
        }
    }

    /**
     * Nominal wajib ada (numerik) sebelum INSERT ke postpaid — tanpa nominal, tidak insert.
     */
    private function hasNominalForPostpaidInsert($d)
    {
        return $this->intOrNullForPostpaid($d['nominal'] ?? null) !== null;
    }

    /**
     * tr_id dan tr_name wajib terisi sebelum INSERT (atau upgrade identitas tagihan) — jangan paksa kosong.
     */
    private function hasTrIdAndTrNameForPostpaid(array $d)
    {
        $tid = isset($d['tr_id']) ? trim((string) $d['tr_id']) : '';
        $tname = isset($d['tr_name']) ? trim((string) $d['tr_name']) : '';

        return $tid !== '' && $tname !== '';
    }

    /**
     * Simpan hasil inquiry ke postpaid lalu langsung eksekusi pay-pasca di request yang sama (tanpa menunggu cron berikutnya).
     */
    private function insertPostpaidAndPayNow($dt, $d, $month)
    {
        if (empty($d['ref_id'])) {
            $warn = $dt['description'] . " - CHECK OK tetapi ref_id kosong — bayar tidak dijalankan\n";
            $this->sendWaNotif($this->waPrivate, $dt['description'] . " inquiry tanpa ref_id: " . json_encode($d));
            return $warn;
        }
        if (!$this->hasTrIdAndTrNameForPostpaid($d)) {
            return $dt['description'] . " - CHECK OK tetapi tr_id/tr_name kosong — tidak insert / tidak dibayar\n";
        }
        if (!$this->hasNominalForPostpaidInsert($d)) {
            return $dt['description'] . " - CHECK OK tetapi nominal kosong — tidak insert / tidak dibayar\n";
        }

        $col = [
            'response_code' => $d['response_code'],
            'message' => $d['message'],
            'tr_id' => $d['tr_id'],
            'tr_name' => $d['tr_name'],
            'period' => $d['period'],
            'nominal' => $this->intOrNullForPostpaid($d['nominal'] ?? null),
            'admin' => $this->intOrNullForPostpaid($d['admin'] ?? null),
            'ref_id' => $d['ref_id'],
            'code' => $d['code'],
            'customer_id' => $d['hp'],
            'price' => $this->intOrNullForPostpaid($d['price'] ?? null) ?? 0,
            'selling_price' => $this->intOrNullForPostpaid($d['selling_price'] ?? null),
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
     * Catatan: respons pay-pasca sering tidak menyertakan field status; PostpaidTrStatus memetakan RC 00 tanpa status ke sukses (1).
     */
    private function isPaymentSuccessForLastBill($d, $a, $rc)
    {
        if ($this->normalizeIakResponseCode($rc) !== '00') {
            return false;
        }
        // tr_status 3 = dalam proses — jangan anggap lunas untuk last_bill
        if (isset($d['status']) && (int) $d['status'] === 3) {
            return false;
        }
        $customerId = $d['hp'] ?? $a['customer_id'] ?? null;
        $code = $d['code'] ?? $a['code'] ?? null;
        return !empty($customerId) && !empty($code);
    }

    /**
     * RC pay/inquiry: tagihan sudah lunas di biller (tidak ada yang perlu dibayar lagi).
     * Ref: https://api.iak.id/api/postpaid/response-code — 01, 34, 40
     */
    private function isAlreadyPaidResponseCode($rc)
    {
        return in_array($this->normalizeIakResponseCode($rc), ['01', '34', '40'], true);
    }

    /**
     * Sudah ada baris sukses (tr_status=1) di bulan ini untuk rekap — hindari duplikat.
     */
    private function postpaidHasSuccessThisMonth($customerId, $code, $monthYm)
    {
        $rows = $this->db(0)->query(
            "SELECT id FROM postpaid WHERE customer_id = ? AND code = ? AND tr_status = 1 AND DATE_FORMAT(insertTime, '%Y%m') = ? LIMIT 1",
            [$customerId, $code, $monthYm]
        )->result_array();
        return count($rows) > 0;
    }

    /**
     * Baris postpaid bulan ini yang belum sukses (bukan hanya expired): pending, expired, gagal, dll.
     */
    private function findNonSuccessPostpaidIdThisMonth($customerId, $code, $monthYm)
    {
        $rows = $this->db(0)->query(
            "SELECT id FROM postpaid WHERE customer_id = ? AND code = ? AND tr_status <> 1 AND DATE_FORMAT(insertTime, '%Y%m') = ? ORDER BY id DESC LIMIT 1",
            [$customerId, $code, $monthYm]
        )->result_array();
        return count($rows) > 0 ? (int) $rows[0]['id'] : null;
    }

    /**
     * Sebelum postpaid_list.last_bill di-set ke bulan ini: pastikan ada minimal 1 baris postpaid
     * tr_status=1 untuk bulan tersebut. Jika belum, naikkan satu baris (prioritas ref_id) jadi sukses.
     */
    private function ensureSuccessPostpaidThisMonthBeforeLastBill($customerId, $code, $monthYm, array $patch = [], $preferRefId = null)
    {
        if ($this->postpaidHasSuccessThisMonth($customerId, $code, $monthYm)) {
            return true;
        }
        $db = $this->db(0);
        $set = array_merge(['tr_status' => 1], $patch);

        if (!empty($preferRefId)) {
            $rows = $db->query(
                "SELECT id FROM postpaid WHERE ref_id = ? AND customer_id = ? AND code = ? AND DATE_FORMAT(insertTime, '%Y%m') = ? AND tr_status <> 1 LIMIT 1",
                [$preferRefId, $customerId, $code, $monthYm]
            )->result_array();
            if (count($rows) > 0) {
                $id = (int) $rows[0]['id'];
                if ($db->update('postpaid', $set, ['id' => $id]) && $db->affected_rows() > 0) {
                    return true;
                }
            }
        }

        $id = $this->findNonSuccessPostpaidIdThisMonth($customerId, $code, $monthYm);
        if ($id === null) {
            return false;
        }

        return $db->update('postpaid', $set, ['id' => $id]) && $db->affected_rows() > 0;
    }

    /**
     * Inquiry RC 01/34/40 (tagihan sudah lunas): jika belum ada baris sukses bulan ini,
     * paksa satu baris postpaid bulan ini (customer+code) — hanya update tr_status, response_code (00), message.
     * Hanya gagal jika tidak ada sama sekali baris di bulan tersebut.
     */
    private function ensureSuccessPostpaidThisMonthForAlreadyPaidInquiry($customerId, $code, $monthYm, array $d)
    {
        if ($this->postpaidHasSuccessThisMonth($customerId, $code, $monthYm)) {
            return true;
        }
        $db = $this->db(0);
        $rows = $db->query(
            "SELECT id FROM postpaid WHERE customer_id = ? AND code = ? AND DATE_FORMAT(insertTime, '%Y%m') = ? ORDER BY id DESC LIMIT 1",
            [$customerId, $code, $monthYm]
        )->result_array();
        if (count($rows) === 0) {
            return false;
        }
        $id = (int) $rows[0]['id'];
        // Hanya tiga kolom — jangan sentuh price, nominal, tr_id, dll.
        $set = [
            'tr_status' => 1,
            'response_code' => '00',
            'message' => $d['message'] ?? '',
        ];
        $db->update('postpaid', $set, ['id' => $id]);

        return $this->postpaidHasSuccessThisMonth($customerId, $code, $monthYm);
    }

    /**
     * Update last_bill ke bulan ini hanya jika sudah ada (atau baru dibuat) riwayat sukses di postpaid bulan ini.
     */
    private function tryUpdatePostpaidListLastBill($month, $customerId, $code, array $patch, $preferRefId = null)
    {
        if ($customerId === null || $customerId === '' || $code === null || $code === '') {
            return false;
        }
        if (!$this->ensureSuccessPostpaidThisMonthBeforeLastBill($customerId, $code, $month, $patch, $preferRefId)) {
            return false;
        }

        return (bool) $this->db(0)->update('postpaid_list', ['last_bill' => $month], ['customer_id' => $customerId, 'code' => $code]);
    }

    /**
     * Sama seperti tryUpdatePostpaidListLastBill, tetapi jika gagal: untuk inquiry "sudah lunas"
     * paksa satu baris bulan ini jadi sukses (RC 00 + message inquiry) lalu set last_bill.
     */
    private function tryUpdatePostpaidListLastBillAfterAlreadyPaidInquiry($month, $customerId, $code, array $d)
    {
        if ($customerId === null || $customerId === '' || $code === null || $code === '') {
            return false;
        }
        if (!$this->ensureSuccessPostpaidThisMonthForAlreadyPaidInquiry($customerId, $code, $month, $d)) {
            return false;
        }

        return (bool) $this->db(0)->update('postpaid_list', ['last_bill' => $month], ['customer_id' => $customerId, 'code' => $code]);
    }

    /**
     * INSERT rekap inquiry baru hanya jika nominal ada (numerik).
     */
    private function inquiryHasNominalForRecap($d)
    {
        return $this->hasNominalForPostpaidInsert($d);
    }

    /**
     * Ubah baris bulan ini yang belum sukses → sukses (inquiry sudah lunas).
     */
    private function upgradeNonSuccessPostpaidFromInquiry($dt, $d, $month)
    {
        if (!$this->hasTrIdAndTrNameForPostpaid($d)) {
            return false;
        }
        $customerId = $d['hp'] ?? $dt['customer_id'];
        $code = $d['code'] ?? $dt['code'];
        $id = $this->findNonSuccessPostpaidIdThisMonth($customerId, $code, $month);
        if ($id === null) {
            return false;
        }
        $rc = $this->normalizeIakResponseCode($d['response_code'] ?? '');
        $ref = !empty($d['ref_id']) ? $d['ref_id'] : ('mdlpost-inq-' . date('YmdHis') . '-' . $dt['id_cabang']);
        $set = [
            'response_code' => $d['response_code'] ?? $rc,
            'message' => $d['message'] ?? '',
            'tr_id' => trim((string) $d['tr_id']),
            'tr_name' => trim((string) $d['tr_name']),
            'period' => $d['period'] ?? '',
            'ref_id' => $ref,
            'price' => $this->intOrNullForPostpaid($d['price'] ?? null) ?? 0,
            'description' => isset($d['desc']) ? serialize($d['desc']) : serialize([]),
            'tr_status' => 1,
        ];
        $this->applyIntColumnsIfPresentForPostpaidUpdate($set, $d, ['nominal', 'admin', 'selling_price']);
        if (!empty($d['datetime'])) {
            $set['datetime'] = $d['datetime'];
        }
        if (!empty($d['balance'])) {
            $set['balance'] = $d['balance'];
        }
        $db = $this->db(0);
        $ok = $db->update('postpaid', $set, ['id' => $id]);
        return $ok && $db->affected_rows() > 0;
    }

    /**
     * Rekap inquiry "sudah lunas": sudah ada sukses bulan ini → selesai;
     * ada baris bulan ini dengan tr_status != 1 → update jadi sukses (bukan hanya expired);
     * belum ada baris → INSERT baru hanya jika response punya nominal.
     */
    private function insertPostpaidInquiryRecapRow($dt, $d, $month)
    {
        $customerId = $d['hp'] ?? $dt['customer_id'];
        $code = $d['code'] ?? $dt['code'];
        if ($this->postpaidHasSuccessThisMonth($customerId, $code, $month)) {
            return;
        }
        if ($this->upgradeNonSuccessPostpaidFromInquiry($dt, $d, $month)) {
            return;
        }
        // Tanpa nominal di response — tidak insert baris baru
        if (!$this->inquiryHasNominalForRecap($d)) {
            return;
        }
        if (!$this->hasTrIdAndTrNameForPostpaid($d)) {
            return;
        }
        $rc = $this->normalizeIakResponseCode($d['response_code'] ?? '');
        $ref = !empty($d['ref_id']) ? $d['ref_id'] : ('mdlpost-inq-' . date('YmdHis') . '-' . $dt['id_cabang']);
        $col = [
            'response_code' => $d['response_code'] ?? $rc,
            'message' => $d['message'] ?? '',
            'tr_id' => trim((string) $d['tr_id']),
            'tr_name' => trim((string) $d['tr_name']),
            'period' => $d['period'] ?? '',
            'nominal' => $this->intOrNullForPostpaid($d['nominal'] ?? null),
            'admin' => $this->intOrNullForPostpaid($d['admin'] ?? null),
            'ref_id' => $ref,
            'code' => $code,
            'customer_id' => $customerId,
            'price' => $this->intOrNullForPostpaid($d['price'] ?? null) ?? 0,
            'selling_price' => $this->intOrNullForPostpaid($d['selling_price'] ?? null),
            'description' => isset($d['desc']) ? serialize($d['desc']) : serialize([]),
            'tr_status' => 1,
            'id_cabang' => $dt['id_cabang'],
        ];
        if (!empty($d['datetime'])) {
            $col['datetime'] = $d['datetime'];
        }
        if (!empty($d['balance'])) {
            $col['balance'] = $d['balance'];
        }
        $this->db(0)->insert('postpaid', $col);
    }

    /**
     * Setelah bayar sukses: ubah baris bulan ini yang belum sukses (jika ada) jadi sukses dengan data pembayaran.
     */
    private function upgradeNonSuccessPostpaidFromPayment($customerId, $code, $month, $set, $d, $a)
    {
        $id = $this->findNonSuccessPostpaidIdThisMonth($customerId, $code, $month);
        if ($id === null) {
            return false;
        }
        $merge = [
            'tr_status' => $set['tr_status'],
            'datetime' => $set['datetime'],
            'noref' => $set['noref'],
            'price' => $set['price'],
            'message' => $set['message'],
            'balance' => $set['balance'],
            'tr_id' => $set['tr_id'],
            'response_code' => $set['response_code'],
        ];
        $newRef = $d['ref_id'] ?? $a['ref_id'] ?? null;
        if (!empty($newRef)) {
            $merge['ref_id'] = $newRef;
        }
        $db = $this->db(0);
        $ok = $db->update('postpaid', $merge, ['id' => $id]);
        return $ok && $db->affected_rows() > 0;
    }

    private function persistPostpaidAfterPayment($ref_id, $dt, $set, $d, $a)
    {
        $db = $this->db(0);
        $ok = $db->update('postpaid', $set, ['ref_id' => $ref_id]);
        if ($ok && $db->affected_rows() > 0) {
            return true;
        }
        $customerId = $d['hp'] ?? $a['customer_id'] ?? $dt['customer_id'];
        $code = $d['code'] ?? $a['code'] ?? $dt['code'];
        $month = $this->getPostMonth();
        if ($this->upgradeNonSuccessPostpaidFromPayment($customerId, $code, $month, $set, $d, $a)) {
            return true;
        }
        if ($this->postpaidHasSuccessThisMonth($customerId, $code, $month)) {
            return false;
        }
        $nominalPay = $this->intOrNullForPostpaid($d['nominal'] ?? null)
            ?? $this->intOrNullForPostpaid($a['nominal'] ?? null);
        if ($nominalPay === null) {
            return false;
        }
        $trIdIns = trim((string) ($set['tr_id'] ?? $d['tr_id'] ?? $a['tr_id'] ?? ''));
        $trNameIns = trim((string) ($d['tr_name'] ?? $a['tr_name'] ?? ''));
        if ($trIdIns === '' || $trNameIns === '') {
            return false;
        }
        $ref = !empty($d['ref_id']) ? $d['ref_id'] : (!empty($a['ref_id']) ? $a['ref_id'] : ('mdlpost-pay-' . date('YmdHis') . '-' . $dt['id_cabang']));
        $col = [
            'response_code' => $set['response_code'],
            'message' => $set['message'],
            'tr_id' => $trIdIns,
            'tr_name' => $trNameIns,
            'period' => $a['period'] ?? '',
            'nominal' => $nominalPay,
            'admin' => $this->intOrNullForPostpaid($a['admin'] ?? null),
            'ref_id' => $ref,
            'code' => $code,
            'customer_id' => $customerId,
            'price' => $this->intOrNullForPostpaid($set['price'] ?? ($a['price'] ?? null)) ?? 0,
            'selling_price' => $this->intOrNullForPostpaid($a['selling_price'] ?? null),
            'description' => !empty($a['description']) ? $a['description'] : serialize([]),
            'tr_status' => $set['tr_status'],
            'id_cabang' => $dt['id_cabang'],
            'datetime' => $set['datetime'] ?? null,
            'noref' => $set['noref'] ?? null,
            'balance' => $set['balance'] ?? null,
        ];
        $ins = $db->insert('postpaid', $col);
        if ($ins !== false) {
            return true;
        }
        // Mis. ref_id bentrok: coba update baris yang sudah ada
        return (bool) $db->update('postpaid', $set, ['ref_id' => $ref]);
    }

    /**
     * Send WhatsApp notification
     */
    private function sendWaNotif($phone, $message)
    {
        // Load WhatsApp Service if available
        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
            require_once __DIR__ . '/../../Helpers/CRM/WhatsAppService.php';
        }
        $waService = new \App\Helpers\CRM\WhatsAppService();
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

            if ($rc === '17') {
                $alert = $dt['description'] . " - POSTPAID LIST - " . $message . " Rp" . number_format($price);
                $msg .= $alert . "\n";
                $res = $this->sendWaNotif($this->waPrivate, $alert);
                if (!($res['success'] ?? false)) {
                    $msg .= "WHATSAPP ERROR\n";
                }
                return $msg;
            }

            $tr_status = PostpaidTrStatus::resolve($d, $a, $rc);

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
            $persisted = $this->persistPostpaidAfterPayment($ref_id, $dt, $set, $d, $a);

            $lastBillPatch = [
                'response_code' => $rc,
                'message' => $message,
                'price' => $price,
                'balance' => $balance,
                'tr_id' => $tr_id,
                'datetime' => $datetime,
                'noref' => $noref,
            ];

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                $update = $this->tryUpdatePostpaidListLastBill($month, $customerId, $code, $lastBillPatch, $ref_id);
                if ($update) {
                    $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                } else {
                    $alert = "POSTPAID ERROR - Update failed (belum ada riwayat sukses postpaid bulan ini)";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                    return $msg;
                }
            } elseif ($this->isAlreadyPaidResponseCode($rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                if (!empty($customerId) && !empty($code)
                    && (string) $customerId === (string) $dt['customer_id']
                    && (string) $code === (string) $dt['code']) {
                    $update = $this->tryUpdatePostpaidListLastBill($month, $customerId, $code, $lastBillPatch, $ref_id);
                    if ($update) {
                        $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                    } else {
                        $alert = "POSTPAID ERROR - Update failed (sudah lunas, belum ada riwayat sukses postpaid bulan ini)";
                        $msg .= $alert . "\n";
                        $this->sendWaNotif($this->waPrivate, $alert);
                        return $msg;
                    }
                } elseif (!empty($customerId) && !empty($code)) {
                    $alert = "POSTPAID - SUDAH LUNAS data mismatch (hp/code)";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                }
            }
            if ($persisted) {
                $msg .= $dt['description'] . " - PAY - " . $message . "\n";
                $alert = $dt['description'] . " - PAY - " . $message . " (RC:" . $rc . ")";
                $this->sendWaNotif($this->waPrivate, $alert);
            } else {
                $alert = "POSTPAID ERROR - Simpan postpaid gagal (update/insert)";
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
            $tr_status = PostpaidTrStatus::resolve($d, $a, $rc);

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
            $persisted = $this->persistPostpaidAfterPayment($ref_id, $dt, $set, $d, $a);

            $lastBillPatch = [
                'response_code' => $rc,
                'message' => $message,
                'price' => $price,
                'balance' => $balance,
                'tr_id' => $tr_id,
                'datetime' => $datetime,
                'noref' => $noref,
            ];

            if ($this->isPaymentSuccessForLastBill($d, $a, $rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                $update = $this->tryUpdatePostpaidListLastBill($month, $customerId, $code, $lastBillPatch, $ref_id);
                if ($update) {
                    $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                } else {
                    $alert = "POSTPAID - DB ERROR - Update postpaid_list failed (belum ada riwayat sukses postpaid bulan ini)";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                    return $msg;
                }
            } elseif ($this->isAlreadyPaidResponseCode($rc)) {
                $customerId = $d['hp'] ?? $a['customer_id'];
                $code = $d['code'] ?? $a['code'];
                if (!empty($customerId) && !empty($code)
                    && (string) $customerId === (string) $dt['customer_id']
                    && (string) $code === (string) $dt['code']) {
                    $update = $this->tryUpdatePostpaidListLastBill($month, $customerId, $code, $lastBillPatch, $ref_id);
                    if ($update) {
                        $msg .= $dt['description'] . " - POSTPAID LIST - " . $message . "\n";
                    } else {
                        $alert = "POSTPAID - DB ERROR - Update postpaid_list failed (sudah lunas, belum ada riwayat sukses postpaid bulan ini)";
                        $msg .= $alert . "\n";
                        $this->sendWaNotif($this->waPrivate, $alert);
                        return $msg;
                    }
                } elseif (!empty($customerId) && !empty($code)) {
                    $alert = "POSTPAID - SUDAH LUNAS data mismatch (hp/code)";
                    $msg .= $alert . "\n";
                    $this->sendWaNotif($this->waPrivate, $alert);
                }
            }

            if ($persisted) {
                $msg .= $dt['description'] . " - POSTPAID - " . $message . "\n";
            } else {
                $alert = "POSTPAID - DB ERROR - Simpan postpaid failed";
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
                        // tr_status 3 = dalam proses. Retry bayar (bukan cek status) agar:
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
                                    $this->insertPostpaidInquiryRecapRow($dt, $d, $month);
                                    $lastBillPatch = [
                                        'response_code' => $this->normalizeIakResponseCode($d['response_code'] ?? ''),
                                        'message' => $d['message'] ?? '',
                                    ];
                                    foreach (['price', 'balance', 'tr_id', 'datetime', 'noref'] as $k) {
                                        if (isset($d[$k]) && $d[$k] !== '' && $d[$k] !== null) {
                                            $lastBillPatch[$k] = $d[$k];
                                        }
                                    }
                                    $update = $this->tryUpdatePostpaidListLastBill($month, $customer_id, $code, $lastBillPatch, $d['ref_id'] ?? null);
                                    if (!$update) {
                                        $update = $this->tryUpdatePostpaidListLastBillAfterAlreadyPaidInquiry($month, $customer_id, $code, $d);
                                    }
                                    if ($update) {
                                        $output .= $dt['description'] . " " . ($d['message'] ?? '') . "\n";
                                    } else {
                                        $alert = "POSTPAID - DB ERROR - Update postpaid_list gagal (tidak ada baris postpaid bulan ini untuk customer)";
                                        $output .= $alert . "\n";
                                    }
                                }
                                break;

                            case "10":
                                // BILL IS NOT AVAILABLE - tagihan belum tersedia, JANGAN update last_bill
                                $output .= $dt['description'] . " - " . ($d['message'] ?? 'Tagihan belum tersedia, coba lagi nanti') . "\n";
                                break;
                                
                            case "00":
                            case "02":
                            case "05":
                            case "39":
                            case "201":
                                // Inquiry sukses / tagihan belum lunas (02) dengan data bayar: simpan ke DB lalu pay-pasca di request ini
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
