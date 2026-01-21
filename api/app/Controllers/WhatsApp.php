<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\WhatsAppService;

/**
 * WhatsApp Controller
 * Endpoint untuk mengirim pesan WhatsApp via yCloud API
 * URL: /WhatsApp/{method}
 */
class WhatsApp extends Controller
{
    private $whatsappService;
    
    
    public function __construct()
    {
        $this->handleCors();
        $this->whatsappService = new WhatsAppService();
    }
    
    /**
     * Validate IP Whitelist
     * Only allow requests from specific IP address
     */
    private function validateIpWhitelist()
    {
        $allowedIps = ['194.233.94.47']; // IP server yang diizinkan
        
        // Get client IP (consider proxy headers)
        $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        
        // If multiple IPs in X-Forwarded-For, get the first one
        if (strpos($clientIp, ',') !== false) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }
        
        // Check if IP is in whitelist
        if (!in_array($clientIp, $allowedIps)) {
            $this->error(
                'Access denied. Your IP address is not authorized to access this endpoint.',
                403,
                [
                    'client_ip' => $clientIp,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            );
        }
    }
    
    
    /**
     * Default endpoint - return API info
     */
    public function index()
    {
        $this->success([
            'name' => 'WhatsApp API',
            'version' => '1.0',
        ], 'WhatsApp API Ready');
    }
    
    public function send()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        
        $this->validate($body, ['phone', 'message_mode']);
        
        $phone = $body['phone'];
        $messageMode = strtolower($body['message_mode']);
        $senderCode = $body['sender_code'] ?? 'null';
        
        // Override: nomor-nomor tertentu WAJIB pakai mode 'free'
        foreach (\Env::FORBID_WA_TEMPLATE_HP as $fhp) {
            if (strpos($phone, $fhp) !== false) {
                $messageMode = 'free';
                break;
            }
        }

        $last_in_at = null;
        
        // Normalisasi Phone
        $ph = preg_replace('/[^0-9]/', '', $phone);
        if(substr($ph, 0, 2)=='08') $ph='628'.substr($ph, 2);
        elseif(substr($ph, 0, 1)=='8') $ph='62'.$ph;
        
        $phone1 = $ph;       // 628...
        $phone2 = '+' . $ph; // +628...
        
        $db = $this->db(0);
        
        // WAJIB: Cek CSW dari database untuk setiap request
        try {
            // CHECK 1: Cek di wa_conversations (Source of Truth for CSW)
            $qCust = $db->query("SELECT last_in_at FROM wa_conversations WHERE wa_number IN ('$phone1', '$phone2') LIMIT 1");
            if ($qCust->num_rows() > 0) {
                $last_in_at = $qCust->row()->last_in_at;
            } else {
                $last_in_at = null;
            }
        } catch (\Exception $e) {
            // Log warning but don't crash, assume no previous message
            $last_in_at = null;
        }
        
        // Check CSW status - WAJIB untuk setiap request
        $isWithinCsw = $this->whatsappService->isWithinCsw($last_in_at);
        
        // Safely calculate hours elapsed (default to very high if no message)
        $hoursElapsed = 99999;
        if ($last_in_at) {
            $hoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $last_in_at);
        }
        
        // Business Logic: Free text mode (CHECK CSW FIRST before sending)
        if ($messageMode === 'free') {
            // Validate message content
            if (empty($body['message'])) {
                $this->error('Message content is required for free text mode', 400);
            }
            
            // ✅ WAJIB: CHECK CSW FROM DATABASE BEFORE SENDING
            // Prevent unnecessary yCloud API calls when CSW is expired
            if (!$isWithinCsw) {
                $this->error(
                    'Customer Service Window (CSW) expired. Cannot send free text message.',
                    400,
                    [
                        'csw_expired' => true,
                        'hours_elapsed' => round($hoursElapsed, 2),
                        'last_in_at' => $last_in_at ?? 'No previous message',
                        'phone_sent' => $phone,
                        'suggestion' => 'chat ke Laundry Bot dulu ya'
                    ]
                );
            }
            
            // FIX: Extract text from JSON if message is JSON format (from WAGenerator)
            $messageText = $body['message'];
            $decodedMsg = json_decode($body['message'], true);
            if ($decodedMsg && isset($decodedMsg['text'])) {
                $messageText = $decodedMsg['text'];
            }
            
            // CSW is valid - proceed to send via yCloud
            $result = $this->whatsappService->sendFreeText($phone, $messageText, null, $senderCode);
            
            if (!$result['success']) {
                // Check if it's a CSW error from yCloud (double-check)
                $errorData = $result['data']['error'] ?? [];
                $errorCode = $errorData['code'] ?? '';
                $errorMsg = $errorData['message'] ?? ($result['error'] ?? 'Failed to send');
                
                // If it's CSW expired (131047 = message outside window), return specific error
                if (strpos($errorCode, '131047') !== false || strpos($errorMsg, 'outside') !== false || strpos($errorMsg, '24 hour') !== false) {
                    $this->error(
                        'Customer Service Window (CSW) expired (confirmed by yCloud).',
                        400,
                        [
                            'csw_expired' => true,
                            'ycloud_error' => $errorMsg,
                            'phone_sent' => $phone,
                            'suggestion' => 'Please ask customer to send a message to WhatsApp business first.'
                        ]
                    );
                }
                
                \Log::write('Failed to send free text: ' . json_encode($result), 'whatsapp', 'api');
                $this->error('Failed to send WhatsApp message: ' . $errorMsg, 500, $result);
            }
            
            $this->success([
                'message_id' => $result['data']['id'] ?? null,
                'status' => $result['data']['status'] ?? 'sent',
                'mode' => 'free_text',
                'to' => $phone,
                'csw_status' => [
                    'within_csw' => true,
                    'hours_elapsed' => round($hoursElapsed, 2)
                ]
            ], 'WhatsApp free text sent successfully');
        }
        
        // Business Logic: Template mode (smart - try free first, fallback to template)
        if ($messageMode === 'template') {
            // Try to send as free text first if CSW is open
            if ($isWithinCsw && !empty($body['message'])) {
                // FIX: Extract text from JSON if message is JSON format
                $freeTextMsg = $body['message'];
                $decodedFree = json_decode($body['message'], true);
                if ($decodedFree && isset($decodedFree['text'])) {
                    $freeTextMsg = $decodedFree['text'];
                }
                
                // CSW is open, try free text
                $result = $this->whatsappService->sendFreeText($phone, $freeTextMsg, null, $senderCode);
                
                if ($result['success']) {
                    // Free text succeeded - return immediately
                    $this->success([
                        'message_id' => $result['data']['id'] ?? null,
                        'status' => $result['data']['status'] ?? 'sent',
                        'mode' => 'free_text',
                        'to' => $phone,
                        'csw_status' => [
                            'within_csw' => true,
                            'hours_elapsed' => round($hoursElapsed, 2),
                            'note' => 'Sent as free text because CSW is open'
                        ]
                    ], 'WhatsApp free text sent successfully (CSW open)');
                    return; // Explicit return (though success() already calls exit)
                }
                // If free text failed, continue to template fallback below
            }
            
            // CSW expired or free text failed - use template

            //tambahkan keamanan pastikan dikirim hanya menerima domain ip server ip 194.233.94.47
            $this->validateIpWhitelist();

            // Validate template name (template_params can be empty array)
            if (empty($body['template_name'])) {
                $this->error('Template name is required when CSW is closed', 400);
            }
            if (!isset($body['template_params'])) {
                $body['template_params'] = []; // Default to empty array
            }

            $templateLanguage = $body['template_language'] ?? 'id';
            $templateParams = $body['template_params'];
            $templateName = $body['template_name'];
            
            // Convert associative array to indexed array
            if (is_array($templateParams) && !isset($templateParams[0])) {
                $templateParams = array_values($templateParams);
            }

            // Send template (pass the original message text for database storage)
            $messageText = $body['message'] ?? ''; // The rendered text from WAGenerator
            $result = $this->whatsappService->sendTemplate(
                $phone,
                $templateName,
                $templateLanguage,
                $templateParams,
                $messageText
            );
            
            
            if (!$result['success'] || empty($result['data']['id'])) {
                 $yError = $result['error']['message'] ?? ($result['error'] ?? 'Template send failed');
                 \Log::write("YCloud Reject: $yError | " . json_encode($result), 'whatsapp', 'api');
                 $this->error("YCloud Reject: $yError", 502, $result);
            }
            
            $this->success([
                'id' => $result['data']['id'] ?? null,  // Add id field for Antrian.php
                'message_id' => $result['data']['id'] ?? null,
                'status' => $result['data']['status'] ?? 'sent',
                'mode' => 'template',
                'template_name' => $templateName,
                'to' => $phone,
                'csw_status' => [
                    'within_csw' => $isWithinCsw,
                    'hours_elapsed' => round($hoursElapsed, 2),
                    'note' => 'Template used because CSW expired or free text unavailable'
                ]
            ], 'WhatsApp template sent successfully');
        }
        
        $this->error('Invalid message_mode. Use "free" or "template"', 400);
    }
    
}
