<?php

namespace App\Helpers;

use App\Config\WhatsApp as WhatsAppConfig;

/**
 * yCloud WhatsApp API Service
 * Official WhatsApp Business API via yCloud
 */
class WhatsAppService
{
    private $apiKey;
    private $baseUrl;
    private $whatsappNumber;
    
    public function __construct()
    {
        $this->apiKey = WhatsAppConfig::getApiKey();
        $this->baseUrl = WhatsAppConfig::getBaseUrl();
        $this->whatsappNumber = WhatsAppConfig::getWhatsAppNumber();
    }
    
    /**
     * Send free-form text message (within 24-hour CSW)
     * 
     * @param string $to Customer phone number (format: +628xxx)
     * @param string $message Text message content
     * @return array Response from yCloud API
     */
    public function sendFreeText($to, $message)
    {
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        return $this->sendRequest('/whatsapp/messages', $payload);
    }
    
    /**
     * Send template message (can be sent anytime, even outside 24-hour CSW)
     * 
     * @param string $to Customer phone number
     * @param string $templateName Template name registered in WhatsApp Business
     * @param string $language Language code (e.g., 'id', 'en')
     * @param array $parameters Template parameters/variables
     * @return array Response from yCloud API
     */
    public function sendTemplate($to, $templateName, $language = 'id', $parameters = [])
    {
        $components = [];
        
        // Add body parameters if provided
        if (!empty($parameters)) {
            $bodyParams = [];
            foreach ($parameters as $param) {
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => $param
                ];
            }
            
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams
            ];
        }
        
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $language
                ],
                'components' => $components
            ]
        ];
        
        return $this->sendRequest('/whatsapp/messages', $payload);
    }
    
    /**
     * Send media message (image, document, video, audio)
     * 
     * @param string $to Customer phone number
     * @param string $type Media type: image|document|video|audio
     * @param string $mediaUrl URL of the media file
     * @param string $caption Optional caption for image/video
     * @param string $filename Optional filename for document
     * @return array Response from yCloud API
     */
    public function sendMedia($to, $type, $mediaUrl, $caption = null, $filename = null)
    {
        $mediaData = [
            'link' => $mediaUrl
        ];
        
        if ($caption && in_array($type, ['image', 'video'])) {
            $mediaData['caption'] = $caption;
        }
        
        if ($filename && $type === 'document') {
            $mediaData['filename'] = $filename;
        }
        
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => $type,
            $type => $mediaData
        ];
        
        return $this->sendRequest('/whatsapp/messages', $payload);
    }
    
    /**
     * Send interactive button message
     * 
     * @param string $to Customer phone number
     * @param string $bodyText Message body
     * @param array $buttons Array of buttons [['id' => 'btn1', 'title' => 'Button 1'], ...]
     * @param string $headerText Optional header text
     * @param string $footerText Optional footer text
     * @return array Response from yCloud API
     */
    public function sendButtons($to, $bodyText, $buttons, $headerText = null, $footerText = null)
    {
        $action = [
            'buttons' => []
        ];
        
        foreach ($buttons as $button) {
            $action['buttons'][] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'],
                    'title' => $button['title']
                ]
            ];
        }
        
        $interactive = [
            'type' => 'button',
            'body' => [
                'text' => $bodyText
            ],
            'action' => $action
        ];
        
        if ($headerText) {
            $interactive['header'] = [
                'type' => 'text',
                'text' => $headerText
            ];
        }
        
        if ($footerText) {
            $interactive['footer'] = [
                'text' => $footerText
            ];
        }
        
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'interactive',
            'interactive' => $interactive
        ];
        
        return $this->sendRequest('/whatsapp/messages', $payload);
    }
    
    /**
     * Calculate hours difference between two timestamps
     * 
     * @param string $datetime1 First datetime
     * @param string $datetime2 Second datetime
     * @return float Hours difference
     */
    public function diffHours($datetime1, $datetime2)
    {
        $timestamp1 = strtotime($datetime1);
        $timestamp2 = strtotime($datetime2);
        $diff = abs($timestamp1 - $timestamp2);
        return $diff / 3600; // Convert seconds to hours
    }
    
    /**
     * Check if customer is within Customer Service Window (CSW)
     * CSW = 24 hours from last customer message
     * 
     * @param string $lastMessageAt Datetime of last customer message
     * @return bool True if within CSW, false if expired
     */
    public function isWithinCsw($lastMessageAt)
    {
        if (empty($lastMessageAt)) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $hoursDiff = $this->diffHours($now, $lastMessageAt);
        $cswDuration = WhatsAppConfig::getCswDuration();
        
        return $hoursDiff <= $cswDuration;
    }
    
    
    /**
     * Format 'from' number for YCloud API
     * Format: whatsapp:+62xxx
     * 
     * @param string $phone Phone number
     * @return string Formatted whatsapp number with prefix
     */
    private function formatFromNumber($phone)
    {
        // Clean the phone number first
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Ensure it's in E.164 format (+62xxx)
        if (substr($phone, 0, 1) === '0') {
            $phone = '+62' . substr($phone, 1);
        } elseif (substr($phone, 0, 1) !== '+') {
            $phone = '+62' . $phone;
        }
        
        // Add whatsapp: prefix
        return 'whatsapp:' . $phone;
    }
    
    /**
     * Format phone number to international format
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number (+62xxx)
     */
    private function formatPhoneNumber($phone)
    {
        // Remove spaces, dashes, parentheses
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If starts with 0, replace with +62
        if (substr($phone, 0, 1) === '0') {
            $phone = '+62' . substr($phone, 1);
        }
        
        // If doesn't start with +, add +62
        if (substr($phone, 0, 1) !== '+') {
            $phone = '+62' . $phone;
        }
        
        // Return E.164 format WITHOUT whatsapp: prefix
        // The prefix is only for 'from' field, not 'to'
        return $phone;
    }
    
    /**
     * Send HTTP request to yCloud API
     * 
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @return array API response
     */
    private function sendRequest($endpoint, $payload)
    {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey
        ]);
        
        // Set timeout untuk menghindari waiting terlalu lama
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);         // Max 10 detik untuk total request
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);  // Max 5 detik untuk connect
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log the request and response
        $this->logMessage($endpoint, $payload, $response, $httpCode);
        
        if ($error) {
            return [
                'success' => false,
                'error' => $error,
                'http_code' => $httpCode
            ];
        }
        
        $responseData = json_decode($response, true);
        $success = $httpCode >= 200 && $httpCode < 300;
        
        // Save outbound message to database if successful
        if ($success && isset($responseData['id'])) {
            $this->saveOutboundMessage($payload, $responseData);
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $responseData,
            'raw_response' => $response
        ];
    }
    
    /**
     * Save outbound message to wa_messages table
     * 
     * @param array $payload Request payload sent to API
     * @param array $response API response
     */
    private function saveOutboundMessage($payload, $response)
    {
        try {
            // Load database (assuming using same DB structure as webhook)
            require_once __DIR__ . '/../Core/DB.php';
            $db = new \DB(0); // Main database
            
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $wamid = $response['wamid'] ?? null;
            $messageId = $response['id'] ?? null;
            
            if (!$waNumber || !$wamid) {
                return; // Can't save without essential data
            }
            
            // Get or create customer
            $customer = $db->get_where('wa_customers', ['wa_number' => $waNumber]);
            
            if ($customer->num_rows() > 0) {
                $customerId = $customer->row()->id;
            } else {
                // Create new customer
                $customerId = $db->insert('wa_customers', [
                    'wa_number' => $waNumber,
                    'first_contact_at' => date('Y-m-d H:i:s'),
                    'total_messages' => 0,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Get or create conversation
            $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
            
            if ($conv->num_rows() > 0) {
                $conversationId = $conv->row()->id;
            } else {
                // Create new conversation
                $conversationId = $db->insert('wa_conversations', [
                    'customer_id' => $customerId,
                    'wa_number' => $waNumber,
                    'status' => 'open',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Extract message content
            $textBody = null;
            $mediaUrl = null;
            
            if ($messageType === 'text' && isset($payload['text']['body'])) {
                $textBody = $payload['text']['body'];
            } elseif (isset($payload[$messageType]['link'])) {
                $mediaUrl = $payload[$messageType]['link'];
            }
            
            // Save outbound message
            $messageData = [
                'conversation_id' => $conversationId,
                'customer_id' => $customerId,
                'direction' => 'out',
                'message_type' => $messageType,
                'text' => $textBody,
                'media_url' => $mediaUrl,
                'provider_message_id' => $messageId,
                'wamid' => $wamid,
                'status' => 'sent', // Initial status
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $db->insert('wa_messages', $messageData);
            
        } catch (\Exception $e) {
            // Silent fail - don't break the main flow
            // Could log this error if needed
        }
    }
    
    /**
     * Log WhatsApp message
     * 
     * @param string $endpoint API endpoint
     * @param array $payload Request payload
     * @param string $response API response
     * @param int $httpCode HTTP status code
     */
    private function logMessage($endpoint, $payload, $response, $httpCode)
    {
        $config = WhatsAppConfig::getConfig();
        
        if (!$config['log_messages']) {
            return;
        }
        
        $logDir = $config['log_path'];
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/messages_' . date('Y-m-d') . '.log';
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'payload' => $payload,
            'response' => $response,
            'http_code' => $httpCode
        ];
        
        file_put_contents(
            $logFile,
            json_encode($logEntry, JSON_PRETTY_PRINT) . "\n---\n",
            FILE_APPEND
        );
    }
}
