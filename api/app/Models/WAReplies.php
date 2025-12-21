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
        
        switch ($textBodyToCheck) {
            case 'bon':
                $this->handleBon($phoneIn, $waNumber);
                break;
            case 'cek':
                $this->handlePendingNotifs($phoneIn, $waNumber);
                break;
            default:
                return false;
                break;
        }
        
        return true;
    }
    
    private function handlePendingNotifs($phoneIn, $waNumber)
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
        
        $pendingNotifs = $db1->query($sql)->result_array();
        
        if (!empty($pendingNotifs)) {
             \Log::write("Found " . count($pendingNotifs) . " pending notifs for $waNumber. Sending...", 'webhook', 'WhatsApp');
             
             foreach ($pendingNotifs as $notif) {
                 // Send message (Free text is allowed now since customer just messaged us)
                 $res = $waService->sendFreeText($waNumber, $notif['text']);
                 
                 $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                 $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null); // YCloud returns id or message_id
                 
                 // Update state immediately
                 $updateData = ['state' => $status];
                 if ($msgId) {
                     $updateData['id_api'] = $msgId;
                 }
                 
                 $db1->update('notif', $updateData, ['id_notif' => $notif['id_notif']]);
             }
        }else{
            $waService->sendFreeText($waNumber, 'Maaf tidak ada transaksi terbuka, pastikan nomor Anda terdaftar di Madinah Laundry. Terima kasih');
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
        $pelanggan = $db1->query("SELECT id_pelanggan FROM pelanggan WHERE $where")->result_array();
        $id_pelanggans = array_column($pelanggan, 'id_pelanggan');

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
                                if ($isInserted) {
                                    $res = $waService->sendFreeText($waNumber, $responseData['text']);
                                    
                                    if (!($res['success'] ?? false)) {
                                        \Log::write("HandleBon Send Error: " . json_encode($res), 'webhook', 'WhatsApp');
                                    } else {
                                        \Log::write("HandleBon Sent: " . json_encode($res), 'webhook', 'WhatsApp');
                                    }
                                    
                                    $status = ($res['success'] ?? false) ? 'sent' : 'failed';
                                    $msgId = $res['data']['id'] ?? ($res['data']['message_id'] ?? null); 
                                    
                                    // Update state immediately
                                    $updateData = ['state' => $status];
                                    if ($msgId) {
                                        $updateData['id_api'] = $msgId;
                                    }
                                    
                                    $db1->update('notif', $updateData, ['id_notif' => $id_notif]);
                                } else {
                                    \Log::write("Insert Notif FAILED! Error: " . $db1->conn()->error, 'webhook', 'WhatsApp');
                                    \Log::write("Insert Data: " . json_encode($insertData), 'webhook', 'WhatsApp');
                                }
                            }
                        }
                    }
                }else{
                    $waService->sendFreeText($waNumber, 'Maaf, semua nota/bon sudah kami kirimkan ke nomor Anda. Terima kasih');
                }
            }else{
                $waService->sendFreeText($waNumber, 'Maaf, semua transaksi Anda sudah selesai, atau pastikan gunakan nomor yang terdaftar untuk melakukan request nota/bon. Terima kasih');
            }
        }else{
            $waService->sendFreeText($waNumber, 'Maaf, nomor Anda tidak terdaftar di Madinah Laundry. Terima kasih');
        }
    }
}
