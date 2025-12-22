<?php
namespace App\Models;

use App\Core\DB;

class WAReplies
{
    /**
     * Process inbound message text and perform actions
     * 
     * @param string $phoneIn CSV string of phone numbers properly quoted for SQL IN clause
     * @param string $textBody The text body of the message
     * @param string $waNumber The sender's WhatsApp number (e.g. +62...)
     */
    public function process($phoneIn, $textBody, $waNumber)
    {
        $textBodyToCheck = trim($textBody ?? '');
        
        $textBodyToCheck = strtolower($textBodyToCheck);
        
        $cekBon = [
            'ping', 'halo', 'atas nama', 'ats nama', 'atas nma',
            'bon', 'struk', 'nota', 'bill', 'kirim', 'tagihan', 'resi'
        ];
        $cekStatus = [
            'cek',
            'udh siap',
            'dh siap',
            'uda siap',
            'dah siap',
            'udah siap',
            'sudah siap',
            'udh beres',
            'dh beres',
            'uda beres',
            'dah beres',
            'udah beres',
            'sudah beres',
            'udh selesai',
            'dh selesai',
            'uda selesai',
            'dah selesai',
            'udah selesai',
            'sudah selesai',
            'bs diambil',
            'bs di ambil',
            'bisa diambil',
            'bisa di ambil',
            'bs dijemput',
            'bisa dijemput',
            'bs di jemput',
            'bisa di jemput',
            'kpn siap',
            'kapan siap',
            'kpn selesai',
            'kapan selesai'
        ];

        // Check for 'bon' related keywords (Substring check)
        if (in_array($textBodyToCheck, $cekBon, true)) {
            $this->handleBon($phoneIn, $waNumber);
            return true;
        }

        // Check for 'status' related keywords (Substring check)
        if (in_array($textBodyToCheck, $cekStatus, true)) {
            $this->handleStatus($phoneIn, $waNumber);
            return true;
        }

        return false;
    }
    
    private function handleStatus($phoneIn, $waNumber)
    {
        // Instantiate service early
        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
            require_once __DIR__ . '/../Helpers/WhatsAppService.php';
        }
        $waService = new \App\Helpers\WhatsAppService();
        
        $db1 = DB::getInstance(1);
        $limitTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        $sql = "SELECT * FROM notif 
                WHERE state = 'pending' 
                AND insertTime >= '$limitTime' 
                AND phone IN ($phoneIn)
                ORDER BY insertTime ASC";

        // DEBUG: Log the query
        \Log::write("Checking Pending Notifs Query: $sql", 'webhook', 'WhatsApp');
        
        $pendingNotifs = $db1->query($sql)->result_array();

        // DEBUG: Log result count
        \Log::write("Result Count: " . count($pendingNotifs), 'webhook', 'WhatsApp');

        
        if (!empty($pendingNotifs)) {
             \Log::write("Found " . count($pendingNotifs) . " pending notifs for $waNumber.", 'webhook', 'WhatsApp');
             
             foreach ($pendingNotifs as $notif) {
                 // Send message (Free text is allowed now since customer just messaged us)
                 
                 // DEBUG: Log sending attempt
                 \Log::write("Sending Notif ID: " . $notif['id_notif'] . " to $waNumber", 'webhook', 'WhatsApp');

                 $res = $waService->sendFreeText($waNumber, $notif['text']);
                 
                 // DEBUG: Log send result
                 \Log::write("Send Result: " . json_encode($res), 'webhook', 'WhatsApp');

                 $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                 $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null); // YCloud returns id or message_id
                 
                 // Update state immediately
                 $updateData = ['state' => $status];
                 if ($msgId) {
                     $updateData['id_api'] = $msgId;
                 }
                 
                 $updateRes = $db1->update('notif', $updateData, ['id_notif' => $notif['id_notif']]);
                 
                 // DEBUG: Log update result
                 \Log::write("Update Database Result: " . var_export($updateRes, true), 'webhook', 'WhatsApp');
             }
        }else{
            //cek dulu ada tidak nya nota terbuka
            $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
            $phone0 = '0' . substr($cleanPhone, 2);

            // DEBUG: Log phone number processing
            \Log::write("Checking Open Transactions. CleanPhone: $cleanPhone, Phone0: $phone0", 'webhook', 'WhatsApp');

            $where = "nomor_pelanggan IN ($phoneIn)";
            $pelanggan = $db1->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();
            
            // DEBUG: Log customer lookup
            \Log::write("Customer Lookup Query: SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where", 'webhook', 'WhatsApp');
            \Log::write("Customer Found: " . count($pelanggan), 'webhook', 'WhatsApp');

            $id_pelanggans = array_column($pelanggan, 'id_pelanggan');
            $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
            $nama_pelanggan = strtoupper($nama_pelanggans[0] ?? ''); // fix index 0 if empty

            if (empty($id_pelanggans)) {
                // DEBUG: Customer not found
                \Log::write("Customer Not Found for phone: $phone0", 'webhook', 'WhatsApp');
                $waService->sendFreeText($waNumber, 'Mohon Maaf, nomor Anda belum terdaftar di Madinah Laundry. Terima kasih');
            } else {
                $ids_in = implode(',', $id_pelanggans);
                $sqlSales = "SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan";
                
                // DEBUG: Log sales lookup
                \Log::write("Sales Lookup Query: $sqlSales", 'webhook', 'WhatsApp');

                $sales = $db1->query($sqlSales)->result_array();
                $noRefs = array_column($sales, 'no_ref');

                // DEBUG: Log found refs
                \Log::write("Open Refs Found: " . implode(',', $noRefs), 'webhook', 'WhatsApp');

                if (empty($noRefs)) {
                    $waService->sendFreeText($waNumber, 'Yth. *' . $nama_pelanggan . '*, tidak ada transaksi terbuka dengan nomor Anda. Terima kasih');
                } else {
                    $listIdPenjualan = [];
                    foreach ($noRefs as $noRef) {
                        $id_penjualans = array_column($db1->query("SELECT id_penjualan FROM sale WHERE id_user_ambil = 0 AND bin = 0 AND tuntas = 0 AND no_ref = '$noRef'")->result_array(), 'id_penjualan');
                        
                        // DEBUG: Log pending items for ref
                        \Log::write("Checking Ref $noRef. Items: " . implode(',', $id_penjualans), 'webhook', 'WhatsApp');

                        $id_penjualans_in = implode(',', $id_penjualans);
                        $noRefsNotif = !empty($id_penjualans) ? array_column($db1->query("SELECT * FROM notif WHERE tipe = 2 AND no_ref IN ($id_penjualans_in)")->result_array(), 'no_ref') : [];
                        
                        $sisaIDPenjualan = array_diff($id_penjualans, $noRefsNotif);
                        
                        // DEBUG: Diff count
                        \Log::write("Unnotified Items for $noRef: " . count($sisaIDPenjualan), 'webhook', 'WhatsApp');

                        if (count($sisaIDPenjualan) > 0) {
                            array_push($listIdPenjualan, $sisaIDPenjualan);
                        }
                    }
                    if (count($listIdPenjualan) > 0) {
                        $listIdPenjualanIn = implode(',', $listIdPenjualan); // Ini mungkin perlu dirapikan karena $listIdPenjualan bisa array of arrays atau array of IDs, depending on array_push usage.
                        // Correction: array_push($listIdPenjualan, $sisaIDPenjualan) pushes an ARRAY. implode will fail or print "Array".
                        // Assuming the original code wanted to flatten it. Let's stick to original logic structure but add logs.
                        
                        // Wait, looking at original code: array_push($listIdPenjualan, $sisaIDPenjualan). 
                        // $sisaIDPenjualan is an array. So $listIdPenjualan becomes [[id1, id2], [id3]].
                        // implode(',', $listIdPenjualan) would produce "Array,Array" warning.
                        // Let's fix the logic slightly to be safe while logging.
                        
                        // Flattening for safe implode
                         $flatList = [];
                         foreach($listIdPenjualan as $subArr) {
                             if(is_array($subArr)) {
                                 foreach($subArr as $v) $flatList[] = $v;
                             } else {
                                 $flatList[] = $subArr;
                             }
                         }
                        $listIdPenjualanIn = implode(',', $flatList);

                        $waService->sendFreeText($waNumber, 'Yth. *' . $nama_pelanggan . '*, List laundry dalam pengerjaan:\n*' . $listIdPenjualanIn . '*\n\nKarna sudah *CEK*, nanti akan dikabari jika sudah selesai. Terima kasih');
                    } else {
                        $waService->sendFreeText($waNumber, 'Yth. *' . $nama_pelanggan . '*, semua laundry Anda sudah selesai. Terima kasih');
                    }
                }
            }
        }
    }

    private function handleBon($phoneIn, $waNumber)
    {
        // Instantiate service early
        if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
            require_once __DIR__ . '/../Helpers/WhatsAppService.php';
        }
        $waService = new \App\Helpers\WhatsAppService();

        // Use DB(1)
        $db1 = DB::getInstance(1);

        // Derive phon Terima kasihom waNumber (+628... or 628...)
        $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber);
        $phone0 = '0' . substr($cleanPhone, 2);

        // Find customer
        $where = "nomor_pelanggan IN ($phoneIn)";
        $pelanggan = $db1->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan WHERE $where")->result_array();
        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');

        $nama_pelanggans = array_column($pelanggan, 'nama_pelanggan');
        $nama_pelanggan = strtoupper($nama_pelanggans[0]);

        if (!empty($id_pelanggans)) {
            $ids_in = implode(',', $id_pelanggans);
            
            // Find untutored sales
            $sales = $db1->query("SELECT * FROM sale WHERE tuntas = 0 AND bin = 0 AND id_pelanggan IN ($ids_in) GROUP BY no_ref, tuntas, id_pelanggan")->result_array();

            $noRefs = array_column($sales, 'no_ref');
            
            if (!empty($noRefs)) {
                // Remove refs that already have a notification of tipe 1
                $noRefsIn = "'" . implode("','", $noRefs) . "'";
                $existingRefs = array_column($db1->query("SELECT no_ref FROM notif WHERE tipe = 1 AND no_ref IN ($noRefsIn)")->result_array(), 'no_ref');
                $missingRefs = array_diff($noRefs, $existingRefs);
                
                if (count($missingRefs) > 0) {
                    foreach ($missingRefs as $ref) {
                        // Create context with User-Agent to avoid potential filtering
                        $opts = [
                            "http" => [
                                "method" => "GET",
                                "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
                            ]
                        ];
                        $context = stream_context_create($opts);
                        
                        $apiResponse = @file_get_contents("https://ml.nalju.com/Get/wa_nota/" . urlencode($ref), false, $context);
                        if ($apiResponse) {
                            $responseData = json_decode($apiResponse, true);
                            if (!empty($responseData['text'])) {
                                // Insert Notif
                                $id_notif = (date('Y') - 2020) . date('mdHis') . rand(0, 9) . rand(0, 9);
                                $insertData = [
                                'id_notif'   => $id_notif,
                                'id_cabang'  => $sales[array_search($ref, $noRefs)]['id_cabang'],
                                'tipe'       => 1,
                                'no_ref'     => $ref,
                                'text'       => $responseData['text'],
                                'phone'      => $phone0,
                                'state'      => 'pending',
                                ];
                            
                                $isInserted = $db1->insert('notif', $insertData);
                                
                                // Check if insert successful
                                // DB::insert returns insert_id. Since we provide manual ID (varchar), insert_id might be 0.
                                // 0 evaluates to false in loose comparison. Must use !== false.
                                if ($isInserted !== false) {
                                    $res = $waService->sendFreeText($waNumber, $responseData['text']);
                                    
                                    $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                                    $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null); 
                                    
                                    // Update state immediately
                                    $updateData = ['state' => $status];
                                    if ($msgId) {
                                        $updateData['id_api'] = $msgId;
                                    }
                                    
                                    $db1->update('notif', $updateData, ['id_notif' => $id_notif]);
                                } else {
                                    $conn = $db1->conn();
                                    $errorMsg = $conn->error ?? 'No Error Msg';
                                    if (empty($errorMsg) && !empty($conn->error_list)) {
                                        $errorMsg = json_encode($conn->error_list);
                                    }
                                    
                                    // Try to get last query if available in wrapper
                                    $lastQuery = method_exists($db1, 'last_query') ? $db1->last_query() : 'N/A';
                                    
                                    \Log::write("Insert Notif FAILED! Return value: " . var_export($isInserted, true), 'webhook', 'WhatsApp');
                                    \Log::write("Error: " . $errorMsg . " | ErrNo: " . ($conn->errno ?? 0), 'webhook', 'WhatsApp');
                                    \Log::write("Insert Data: " . json_encode($insertData), 'webhook', 'WhatsApp');
                                }
                            }
                        }
                    }
                } else {
                    //cek dulu jika pending kirimkan wa nya
                    $pending = $db1->query("SELECT * FROM notif WHERE tipe = 1 AND no_ref IN ($noRefsIn) AND state = 'pending'")->result_array();
                    if (!empty($pending)) {
                        foreach ($pending as $p) {
                            $res = $waService->sendFreeText($waNumber, $p['text']);

                            $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                            $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null); 
                            
                            // Update state immediately
                            $updateData = ['state' => $status];
                            if ($msgId) {
                                $updateData['id_api'] = $msgId;
                            }
                            
                            $db1->update('notif', $updateData, ['id_notif' => $p['id_notif']]);
                        }
                    }else{
                        $waService->sendFreeText($waNumber, 'Yth. *' . $nama_pelanggan . '*, semua nota/bon sudah kami kirimkan ke nomor Anda. Terima kasih');
                    }
                }
            }else{
                $waService->sendFreeText($waNumber, 'Yth. *' . $nama_pelanggan . '*, semua transaksi Anda sudah selesai, atau pastikan gunakan nomor yang terdaftar untuk melakukan request nota/bon. Terima kasih');
            }
        }else{
            $waService->sendFreeText($waNumber, 'Mohon Maaf, nomor Anda belum terdaftar di Madinah Laundry. Terima kasih');
        }
    }
}
