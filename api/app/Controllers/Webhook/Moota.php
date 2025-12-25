<?php
namespace App\Controllers\Webhook;

use App\Core\Controller;

class Moota extends Controller
{
    public function index()
    {
        // ==============================
        // CONFIGURATION
        // ==============================
        $expected_moota_user = 'epokOBKvWaJ'; // Ganti dengan Moota User ID Anda
        $secret              = 'zVTSalmH'; // Ganti dengan Moota Secret Key Anda

        // Enable error logging
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', 'logs/moota/' . date('Y/m/') . date('d') . '_php_errors.log');

        header('Content-Type: application/json; charset=utf-8');

        $json = file_get_contents('php://input');

        // Logging incoming request
        \Log::write("=== NEW ===", 'webhook', 'Moota');
        \Log::write("Req: " . $json, 'webhook', 'Moota');

        // Get headers
        $headers = $this->getRequestHeaders();
        \Log::write("Head: " . json_encode($headers), 'webhook', 'Moota');

        // Validate required headers
        $moota_user = isset($headers['X-MOOTA-USER']) ? $headers['X-MOOTA-USER'] : '';
        $moota_webhook = isset($headers['X-MOOTA-WEBHOOK']) ? $headers['X-MOOTA-WEBHOOK'] : '';
        $user_agent = isset($headers['User-Agent']) ? $headers['User-Agent'] : '';
        $signature_provided = isset($headers['Signature']) ? $headers['Signature'] : '';


        \Log::write("Sign: $signature_provided", 'webhook', 'Moota');

        // Validate User-Agent (harus MootaBot)
        if (strpos($user_agent, 'MootaBot') === false) {
            \Log::write("Err: UA", 'webhook', 'Moota');
            echo json_encode(['status' => false, 'message' => 'Invalid User-Agent']);
            return;
        }

        // Validate X-MOOTA-USER
        if ($moota_user !== $expected_moota_user) {
            \Log::write("Err: User", 'webhook', 'Moota');
            echo json_encode(['status' => false, 'message' => 'Invalid Moota User']);
            return;
        }

        // Validate Signature using HMAC SHA256
        $signature_generated = hash_hmac('sha256', $json, $secret);

        if ($signature_provided !== $signature_generated) {
            \Log::write("Err: Sign", 'webhook', 'Moota');
            echo json_encode(['status' => false, 'message' => 'Invalid Signature']);
            return;
        }

        \Log::write("Sign OK", 'webhook', 'Moota');

        $data = json_decode($json, true);

        if (!$data) {
            \Log::write("Err: JSON", 'webhook', 'Moota');
            echo json_encode(['status' => false, 'message' => 'Invalid JSON']);
            return;
        }

        // Moota mengirim array mutasi
        if (!is_array($data)) {
            \Log::write("Err: Not Array", 'webhook', 'Moota');
            echo json_encode(['status' => false, 'message' => 'Invalid data format']);
            return;
        }

        $processed_count = 0;
        $success_count = 0;
        $error_count = 0;

        // Loop through each mutation
        foreach ($data as $index => $mutation) {
            $processed_count++;

            \Log::write("--- Idx: $index ---", 'webhook', 'Moota');

            if (isset($mutation['amount']) && isset($mutation['type']) && isset($mutation['bank_id']) && isset($mutation['mutation_id'])) {
                \Log::write("Amt: " . $mutation['amount'] . ", Typ: " . $mutation['type'] . ", Bank: " . $mutation['bank_id'], 'webhook', 'Moota');
            } else {
                \Log::write("Err: Field Miss", 'webhook', 'Moota');
                $error_count++;
                continue;
            }

            $amount = $mutation['amount'];
            $type = $mutation['type'];
            $bank_id = $mutation['bank_id'];
            $mutation_id = $mutation['mutation_id'];

            // Konversi sesuai tipe data di database: amount=int(11), bank_id=varchar(100)
            $amount = (int)$amount;  // amount adalah INTEGER di database
            $bank_id = trim((string)$bank_id);  // bank_id adalah VARCHAR

            if ($type !== 'CR') {
                \Log::write("Skip: !CR", 'webhook', 'Moota');
                continue;
            }

            //cek sudah ada mutation_id di wh_moota
            try {
                $db_instance = $this->db(0);
                if (!$db_instance) {
                    \Log::write("Err: DB 0", 'webhook', 'Moota');
                    $error_count++;
                    continue;
                }

                $cek_existing_query = $db_instance->get_where("wh_moota", [
                    "mutation_id" => $mutation_id
                ]);

                $existing_count = $cek_existing_query->num_rows();

                if ($existing_count > 0) {
                    \Log::write("Skip: Proc ID $mutation_id", 'webhook', 'Moota');
                    continue;
                }
                // \Log::write("New ID: $mutation_id", 'moota');
            } catch (\Exception $e) {
                \Log::write("Exc: Check " . $e->getMessage(), 'webhook', 'Moota');

                $error_count++;
                continue;
            } catch (\Error $e) {
                \Log::write("Err: Check " . $e->getMessage(), 'webhook', 'Moota');

                $error_count++;
                continue;
            }

            //cek wh_moota dengan bank_id, amount, state != paid
            try {
                $cek_pending_query = $db_instance->get_where("wh_moota", [
                    "bank_id" => $bank_id,
                    "amount" => $amount,
                    "state !=" => 'paid',
                ]);

                if (!$cek_pending_query) {
                    \Log::write("Err: Qry Null", 'webhook', 'Moota');
                    $error_count++;
                    continue;
                }

                $pending_count = $cek_pending_query->num_rows();

                if ($pending_count == 1) {
                    \Log::write("Fnd: $bank_id, $amount", 'webhook', 'Moota');
                    //udpate state jadi paid
                    $update = $db_instance->update(
                        "wh_moota",
                        [
                            "state" => 'paid',
                            "mutation_id" => $mutation_id,
                        ],
                        [
                            "bank_id" => $bank_id,
                            "amount" => $amount,
                            "state !=" => 'paid',
                        ],
                    );

                    if ($update) {
                        \Log::write("Upd: Paid $bank_id, $amount", 'webhook', 'Moota');
                        //UPDATE KAS JADI STATUS_MUTASI 3 DENGAN REF_FINANCE DARI wh_moota
                        $pending_record = $cek_pending_query->row();
                        $this->processTarget($pending_record, $pending_record->trx_id);
                    } else {
                        \Log::write("Err: Upd Fail $bank_id, $amount", 'webhook', 'Moota');
                        $error_count++;
                        continue;
                    }
                } elseif ($pending_count > 1) {
                    \Log::write("Skip: >1 Rec $bank_id, $amount", 'webhook', 'Moota');

                    $update_conflict = $db_instance->update_limit(
                        "wh_moota",
                        [
                            "state" => 'paid_waiting',
                            "mutation_id" => $mutation_id,
                        ],
                        [
                            "bank_id" => $bank_id,
                            "amount" => $amount,
                            "state !=" => 'paid_waiting',
                        ],
                        1
                    );

                    if ($update_conflict) {
                        \Log::write("Cflct: $bank_id, $amount", 'webhook', 'Moota');
                    } else {
                        \Log::write("Err: Calc Flag $bank_id", 'webhook', 'Moota');
                        $error_count++;
                    }

                    // cek sisa yang belum paid_waiting
                    $remaining_query = $db_instance->get_where("wh_moota", [
                        "bank_id" => $bank_id,
                        "amount" => $amount,
                        "state !=" => 'paid_waiting',
                    ]);
                    $remaining_count = $remaining_query->num_rows();
                    if ($remaining_count == 0) {
                        \Log::write("Res: All Cflct $bank_id", 'webhook', 'Moota');
                        // update semua kas dengan trx id yang paid_waiting jadi status_mutasi 3
                        $conflict_records = $db_instance->get_where("wh_moota", [
                            "bank_id" => $bank_id,
                            "amount" => $amount,
                            "state" => 'paid_waiting',
                        ])->result();
                        foreach ($conflict_records as $conflict_record) {
                            $update_kas = $this->processTarget($conflict_record, $conflict_record->trx_id);
                            if ($update_kas !== false) {
                                // update state jadi paid
                                $update_final = $db_instance->update(
                                    "wh_moota",
                                    [
                                        "state" => 'paid',
                                    ],
                                    [
                                        "id" => $conflict_record->id,
                                    ]
                                );
                                if ($update_final) {
                                    \Log::write("Fin: $conflict_record->id", 'webhook', 'Moota');
                                } else {
                                    \Log::write("Err: Fin $conflict_record->id", 'webhook', 'Moota');
                                }
                            } else {
                                \Log::write("Err: Trg $conflict_record->id", 'webhook', 'Moota');
                            }
                        }
                    } else {
                        \Log::write("Rem: $remaining_count $bank_id", 'webhook', 'Moota');
                    }


                    continue;
                } else {
                    \Log::write("Err: No Rec $bank_id", 'webhook', 'Moota');
                    $error_count++;
                }
                // \Log::write("Chk OK", 'moota');
            } catch (\Exception $e) {
                \Log::write("Exc: Pend " . $e->getMessage(), 'webhook', 'Moota');

                $error_count++;
                continue;
            } catch (\Error $e) {
                \Log::write("Err: Pend " . $e->getMessage(), 'webhook', 'Moota');

                $error_count++;
                continue;
            }

            $success_count++;
        }

        \Log::write("=== END ===", 'webhook', 'Moota');
        \Log::write("Proc: $processed_count, OK: $success_count, Err: $error_count", 'webhook', 'Moota');

        echo json_encode([
            'status' => true,
            'message' => 'Webhook processed',
            'processed' => $processed_count,
            'success' => $success_count,
            'error' => $error_count
        ]);
    }

    /**
     * Proses target tertentu berdasarkan data dari wh_moota
     */
    private function processTarget($record, $order_id)
    {
        // Jika ada field target, proses sesuai target
        if (isset($record->target) && isset($record->book)) {
            $target = $record->target;
            $book = $record->book;

            \Log::write("Proc: $target, $book", 'webhook', 'Moota');

            if ($target == "kas_laundry") {
                // FIX: use db(0) directly instead of year iteration
                \Log::write("Upd Kas: db(0)", 'webhook', 'Moota');

                try {
                    $db_update_instance = $this->db(0);
                    if (!$db_update_instance) {
                        \Log::write("Err: DB 0", 'webhook', 'Moota');
                        return false;
                    }

                    // Status sukses = status_mutasi 3
                    $update = $db_update_instance->update(
                        "kas",
                        ["status_mutasi" => 3],
                        ["ref_finance" => $order_id]
                    );

                    if (!$update) {
                        \Log::write("Err: Upd Kas $order_id", 'webhook', 'Moota');
                        return false;
                    } else {
                        \Log::write("OK: Upd Kas $order_id", 'webhook', 'Moota');
                    }
                } catch (\Exception $e) {
                    \Log::write("Exc: Kas " . $e->getMessage(), 'webhook', 'Moota');
                    return false;
                }
            } else {
                \Log::write("No Logic: $target", 'webhook', 'Moota');
            }
        } else {
            \Log::write("Skip: No trg/bk", 'webhook', 'Moota');
            return false;
        }
    }

    /**
     * Mengambil semua headers dari request
     */
    private function getRequestHeaders()
    {
        $headers = [];

        // Jika fungsi getallheaders() tersedia (Apache)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            // Fallback untuk server lain (nginx, etc)
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    // Convert HTTP_X_MOOTA_USER to X-Moota-User format
                    $header_name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$header_name] = $value;
                }
            }

            // Handle special headers
            if (isset($_SERVER['CONTENT_TYPE'])) {
                $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
            }
            if (isset($_SERVER['CONTENT_LENGTH'])) {
                $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
            }
        }

        // Normalize header names untuk X-MOOTA-USER dan X-MOOTA-WEBHOOK
        $normalized = [];
        foreach ($headers as $key => $value) {
            // Handle both "X-Moota-User" and "X-MOOTA-USER" formats
            if (strtolower($key) === 'x-moota-user') {
                $normalized['X-MOOTA-USER'] = $value;
            } elseif (strtolower($key) === 'x-moota-webhook') {
                $normalized['X-MOOTA-WEBHOOK'] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return array_merge($headers, $normalized);
    }
}
