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
     * Default endpoint - return API info
     */
    public function index()
    {
        $this->success([
            'name' => 'WhatsApp API',
            'version' => '1.0',
            'provider' => 'yCloud',
            'endpoints' => [
                'POST /WhatsApp/send' => 'Send WhatsApp message (auto-detect mode based on CSW)',
                'POST /WhatsApp/send-text' => 'Send free-form text (must be within CSW)',
                'POST /WhatsApp/send-template' => 'Send template message (anytime)',
                'POST /WhatsApp/send-media' => 'Send media (image/video/document/audio)',
                'POST /WhatsApp/send-buttons' => 'Send interactive button message',
                'POST /WhatsApp/check-csw' => 'Check Customer Service Window status'
            ]
        ], 'WhatsApp API Ready');
    }
    
    /**
     * Smart send - Automatically choose between free text or template based on CSW
     * 
     * POST /WhatsApp/send
     * Body:
     * {
     *   "phone": "081234567890",
     *   "last_message_at": "2024-12-19 18:00:00",
     *   "message_mode": "free|template",
     *   "message": "Hello customer", // For free text
     *   "template_name": "greeting_template", // For template
     *   "template_params": ["John", "Doe"], // Optional template parameters
     *   "template_language": "id" // Default: id
     * }
     */
    public function send()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        
        // Validate required fields (last_message_at is now optional)
        $this->validate($body, ['phone', 'message_mode']);
        
        $phone = $body['phone'];
        $messageMode = strtolower($body['message_mode']);

        // Auto-lookup last_message_at if not provided
        $lastMessageAt = $body['last_message_at'] ?? null;
        if (empty($lastMessageAt)) {
             // Logic manual lookup DB (Controller DB Access is safer)
             // Normalisasi simple
             $ph = preg_replace('/[^0-9]/', '', $phone);
             if(substr($ph, 0, 2)=='08') $ph='628'.substr($ph, 2);
             elseif(substr($ph, 0, 1)=='8') $ph='62'.$ph;
             
             $phone1 = $ph;       // 628...
             $phone2 = '+' . $ph; // +628...
             
             $db = $this->db(0);
             $q = $db->query("SELECT last_in_at FROM wa_conversations WHERE wa_number IN ('$phone1', '$phone2') ORDER BY last_in_at DESC LIMIT 1");
             
             if ($db->num_rows() > 0) {
                 $lastMessageAt = $db->row()->last_in_at;
             }
        }
        
        // Check CSW status
        $isWithinCsw = $this->whatsappService->isWithinCsw($lastMessageAt);
        $hoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $lastMessageAt);
        
        // Business Logic: Free text mode
        if ($messageMode === 'free') {
            // Check if CSW expired
            if (!$isWithinCsw) {
                $this->error(
                    'Customer Service Window (CSW) expired. Last message was ' . 
                    round($hoursElapsed, 2) . ' hours ago. Please use template mode instead.',
                    400,
                    [
                        'csw_expired' => true,
                        'hours_elapsed' => round($hoursElapsed, 2),
                        'csw_limit' => 22,
                        'last_message_at' => $lastMessageAt,
                        'suggestion' => 'Change message_mode to "template"'
                    ]
                );
            }
            
            // Validate message content
            if (empty($body['message'])) {
                $this->error('Message content is required for free text mode', 400);
            }
            
            // Send free text
            $result = $this->whatsappService->sendFreeText($phone, $body['message']);
            
            if (!$result['success']) {
                // Log failure details
                $logMsg = date('Y-m-d H:i:s') . " [API Failure] Phone: $phone | Result: " . json_encode($result) . "\n";
                @file_put_contents(__DIR__ . '/../../logs/wa_debug_api.log', $logMsg, FILE_APPEND);
                
                $this->error('Failed to send WhatsApp message', 500, $result);
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
        
        // Business Logic: Template mode
        if ($messageMode === 'template') {
            // Validate template name
            if (empty($body['template_name'])) {
                $this->error('Template name is required for template mode', 400);
            }
            
            $templateName = $body['template_name'];
            $templateLanguage = $body['template_language'] ?? 'id';
            $templateParams = $body['template_params'] ?? [];
            
            // Send template
            $result = $this->whatsappService->sendTemplate(
                $phone,
                $templateName,
                $templateLanguage,
                $templateParams
            );
            // Check result
            if (empty($result['id']) && empty($result['message_id'])) {
                 // Extract clean error message if possible
                 $yError = $result['error']['message'] ?? ($result['error'] ?? json_encode($result));
                 // Return 502 (Bad Gateway) to indicate upstream error, not internal crash
                 $this->error("YCloud Reject: $yError", 502, $result);
            }
            
            $this->success([
                'message_id' => $result['data']['id'] ?? null,
                'status' => $result['data']['status'] ?? 'sent',
                'mode' => 'template',
                'template_name' => $templateName,
                'to' => $phone,
                'csw_status' => [
                    'within_csw' => $isWithinCsw,
                    'hours_elapsed' => round($hoursElapsed, 2),
                    'note' => 'Template can be sent anytime regardless of CSW'
                ]
            ], 'WhatsApp template sent successfully');
        }
        
        $this->error('Invalid message_mode. Use "free" or "template"', 400);
    }
    
    /**
     * Send free-form text message (must be within 24-hour CSW)
     * 
     * POST /WhatsApp/send-text
     * Body:
     * {
     *   "phone": "081234567890",
     *   "message": "Hello, this is a custom message",
     *   "last_message_at": "2024-12-19 18:00:00",
     *   "skip_csw_check": false // Optional: skip CSW validation (not recommended)
     * }
     */
    public function send_text()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        $this->validate($body, ['phone', 'message', 'last_message_at']);
        
        $phone = $body['phone'];
        $message = $body['message'];
        $lastMessageAt = $body['last_message_at'];
        $skipCswCheck = $body['skip_csw_check'] ?? false;
        
        // Check CSW unless explicitly skipped
        if (!$skipCswCheck) {
            $isWithinCsw = $this->whatsappService->isWithinCsw($lastMessageAt);
            if (!$isWithinCsw) {
                $hoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $lastMessageAt);
                $this->error(
                    'CSW expired. Use template message instead.',
                    400,
                    [
                        'hours_elapsed' => round($hoursElapsed, 2),
                        'last_message_at' => $lastMessageAt
                    ]
                );
            }
        }
        
        // Send message
        $result = $this->whatsappService->sendFreeText($phone, $message);
        
        if (!$result['success']) {
            $this->error('Failed to send message', 500, $result);
        }
        
        $this->success($result['data'], 'Message sent successfully');
    }
    
    /**
     * Send template message (can be sent anytime)
     * 
     * POST /WhatsApp/send-template
     * Body:
     * {
     *   "phone": "081234567890",
     *   "template_name": "greeting_template",
     *   "template_language": "id",
     *   "template_params": ["John", "Platinum"]
     * }
     */
    public function send_template()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        $this->validate($body, ['phone', 'template_name']);
        
        $phone = $body['phone'];
        $templateName = $body['template_name'];
        $language = $body['template_language'] ?? 'id';
        $params = $body['template_params'] ?? [];
        
        $result = $this->whatsappService->sendTemplate($phone, $templateName, $language, $params);
        
        if (!$result['success']) {
            $this->error('Failed to send template', 500, $result);
        }
        
        $this->success($result['data'], 'Template sent successfully');
    }
    
    /**
     * Send media message
     * 
     * POST /WhatsApp/send-media
     * Body:
     * {
     *   "phone": "081234567890",
     *   "type": "image", // image|document|video|audio
     *   "media_url": "https://example.com/image.jpg",
     *   "caption": "Check this out", // Optional for image/video
     *   "filename": "document.pdf", // Optional for document
     *   "last_message_at": "2024-12-19 18:00:00"
     * }
     */
    public function send_media()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        $this->validate($body, ['phone', 'type', 'media_url', 'last_message_at']);
        
        $phone = $body['phone'];
        $type = $body['type'];
        $mediaUrl = $body['media_url'];
        $caption = $body['caption'] ?? null;
        $filename = $body['filename'] ?? null;
        $lastMessageAt = $body['last_message_at'];
        
        // Validate media type
        $validTypes = ['image', 'document', 'video', 'audio'];
        if (!in_array($type, $validTypes)) {
            $this->error('Invalid media type. Use: ' . implode(', ', $validTypes), 400);
        }
        
        // Check CSW
        if (!$this->whatsappService->isWithinCsw($lastMessageAt)) {
            $hoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $lastMessageAt);
            $this->error(
                'CSW expired. Media messages require active CSW.',
                400,
                ['hours_elapsed' => round($hoursElapsed, 2)]
            );
        }
        
        $result = $this->whatsappService->sendMedia($phone, $type, $mediaUrl, $caption, $filename);
        
        if (!$result['success']) {
            $this->error('Failed to send media', 500, $result);
        }
        
        $this->success($result['data'], 'Media sent successfully');
    }
    
    /**
     * Send interactive button message
     * 
     * POST /WhatsApp/send-buttons
     * Body:
     * {
     *   "phone": "081234567890",
     *   "body_text": "Choose an option:",
     *   "buttons": [
     *     {"id": "opt1", "title": "Option 1"},
     *     {"id": "opt2", "title": "Option 2"}
     *   ],
     *   "header_text": "Menu", // Optional
     *   "footer_text": "Powered by nalju.com", // Optional
     *   "last_message_at": "2024-12-19 18:00:00"
     * }
     */
    public function send_buttons()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        $this->validate($body, ['phone', 'body_text', 'buttons', 'last_message_at']);
        
        $phone = $body['phone'];
        $bodyText = $body['body_text'];
        $buttons = $body['buttons'];
        $headerText = $body['header_text'] ?? null;
        $footerText = $body['footer_text'] ?? null;
        $lastMessageAt = $body['last_message_at'];
        
        // Validate buttons
        if (!is_array($buttons) || count($buttons) === 0) {
            $this->error('Buttons must be a non-empty array', 400);
        }
        
        if (count($buttons) > 3) {
            $this->error('Maximum 3 buttons allowed', 400);
        }
        
        // Check CSW
        if (!$this->whatsappService->isWithinCsw($lastMessageAt)) {
            $hoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $lastMessageAt);
            $this->error(
                'CSW expired. Interactive messages require active CSW.',
                400,
                ['hours_elapsed' => round($hoursElapsed, 2)]
            );
        }
        
        $result = $this->whatsappService->sendButtons($phone, $bodyText, $buttons, $headerText, $footerText);
        
        if (!$result['success']) {
            $this->error('Failed to send buttons', 500, $result);
        }
        
        $this->success($result['data'], 'Buttons sent successfully');
    }
    
    /**
     * Check Customer Service Window (CSW) status
     * 
     * POST /WhatsApp/check-csw
     * Body:
     * {
     *   "last_message_at": "2024-12-19 18:00:00"
     * }
     */
    public function check_csw()
    {
        if (!$this->isPost()) {
            $this->error('Method not allowed. Use POST', 405);
        }
        
        $body = $this->getBody();
        $this->validate($body, ['last_message_at']);
        
        $lastMessageAt = $body['last_message_at'];
        $now = date('Y-m-d H:i:s');
        
        $isWithinCsw = $this->whatsappService->isWithinCsw($lastMessageAt);
        $hoursElapsed = $this->whatsappService->diffHours($now, $lastMessageAt);
        $hoursRemaining = 24 - $hoursElapsed;
        
        $this->success([
            'within_csw' => $isWithinCsw,
            'last_message_at' => $lastMessageAt,
            'current_time' => $now,
            'hours_elapsed' => round($hoursElapsed, 2),
            'hours_remaining' => $isWithinCsw ? round($hoursRemaining, 2) : 0,
            'csw_limit_hours' => 24,
            'can_send_free_text' => $isWithinCsw,
            'must_use_template' => !$isWithinCsw,
            'expires_at' => date('Y-m-d H:i:s', strtotime($lastMessageAt . ' +24 hours'))
        ], 'CSW status retrieved');
    }
}
