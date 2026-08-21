<?php

namespace App\Controllers\Laundry;

use App\Core\Controller;
use App\Helpers\CRM\FreeTextOutboundDispatcher;
use App\Helpers\CRM\WhatsAppService;

/**
 * WhatsApp Controller (Laundry / Water / Resto outbound)
 * Endpoint untuk mengirim pesan WhatsApp via yCloud API
 * URL: /Laundry/WhatsApp/{method}
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
        
        $phone = (string) $body['phone'];

        // Fail fast: reject too-short phone numbers
        $phoneDigits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phoneDigits) < 8) {
            $this->error('Invalid phone number. Phone length must be at least 8 digits.', 400, [
                'phone' => $phone,
                'phone_digits' => $phoneDigits
            ]);
        }
        
        $messageMode = strtolower($body['message_mode']);
        $senderCode = $body['sender_code'] ?? null;
        
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
        
        if ($messageMode === 'free') {
            if (empty($body['message'])) {
                $this->error('Message content is required for free text mode', 400);
            }

            $messageText = $body['message'];
            $decodedMsg = json_decode($body['message'], true);
            if ($decodedMsg && isset($decodedMsg['text'])) {
                $messageText = $decodedMsg['text'];
            }

            $res = FreeTextOutboundDispatcher::dispatch($db, $this->whatsappService, $phone, $messageText, $senderCode);
            if (!empty($res['ok'])) {
                $this->success($res['http_data'], $res['http_message']);
            }
            $this->error($res['http_message'], $res['http_code'] ?? 400, $res['http_data'] ?? null);
        }
        
        // Business Logic: Template mode — jika CSW yCloud terbuka (line manapun): batalkan template, kirim free text
        if ($messageMode === 'template') {
            if (!class_exists('\\App\\Helpers\\CRM\\CrmChatMergeHelper')) {
                require_once __DIR__ . '/../../Helpers/CRM/CrmChatMergeHelper.php';
            }
            $csw = \App\Helpers\CRM\CrmChatMergeHelper::getCswStatus($db, $phone);

            if (!empty($csw['can_reply'])) {
                if (empty($body['message'])) {
                    $this->error(
                        'Saat CSW YCloud terbuka, pengiriman template dibatalkan — field message wajib diisi untuk free text.',
                        400,
                        [
                            'line_csw' => $csw['line_csw'] ?? [],
                            'template_cancelled' => true,
                        ]
                    );
                }

                $freeTextMsg = $body['message'];
                $decodedFree = json_decode($body['message'], true);
                if ($decodedFree && isset($decodedFree['text'])) {
                    $freeTextMsg = $decodedFree['text'];
                }

                $resTpl = FreeTextOutboundDispatcher::dispatch(
                    $db,
                    $this->whatsappService,
                    $phone,
                    $freeTextMsg,
                    $senderCode,
                    ['template_cancelled' => true]
                );
                if (!empty($resTpl['ok'])) {
                    $this->success($resTpl['http_data'], $resTpl['http_message']);
                }
                $this->error($resTpl['http_message'], $resTpl['http_code'] ?? 400, $resTpl['http_data'] ?? null);
            }

            // CSW tertutup — kirim template (yCloud)
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
                    'within_csw' => false,
                    'hours_elapsed' => round($hoursElapsed, 2),
                    'note' => 'Template via yCloud: CSW tertutup (jendela 24 jam)',
                ],
            ], 'WhatsApp template sent successfully');
        }
        
        $this->error('Invalid message_mode. Use "free" or "template"', 400);
    }
}
