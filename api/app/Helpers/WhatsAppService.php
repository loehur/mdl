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
        // Fallback hardcode (Safety net)
        if (empty($this->apiKey) || strpos($this->apiKey, 'YOUR_') !== false) {
            $this->apiKey = '3d997235552a4b868972e0915a7700e5';
        }
        
        $this->baseUrl = WhatsAppConfig::getBaseUrl();
        if (empty($this->baseUrl)) {
            $this->baseUrl = 'https://api.ycloud.com/v2';
        }
        
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
        
        return $hoursDiff < 24;
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
        // Clean: keep only digits and plus
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Case: Starts with 0 (e.g. 0812...) -> +62812...
        if (substr($phone, 0, 1) === '0') {
            return '+62' . substr($phone, 1);
        }
        
        // Case: Starts with 62 (e.g. 62812...) -> +62812...
        if (substr($phone, 0, 2) === '62') {
            return '+' . $phone;
        }
        
        // Case: Starts with 8 (e.g. 812...) -> +62812...
        if (substr($phone, 0, 1) === '8') {
            return '+62' . $phone;
        }
        
        // Case: Already has + (e.g. +62812...)
        if (substr($phone, 0, 1) === '+') {
            return $phone;
        }
        
        // Default to adding + if missing
        return '+' . $phone;
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
        // Wrapped in try-catch to absolutely prevent breaking the response
        if ($success && isset($responseData['id'])) {
            try {
                $this->saveOutboundMessage($payload, $responseData);
            } catch (\Throwable $e) {
                // Silently catch any error - don't let it affect the API response
                if (function_exists('error_log')) {
                    error_log("saveOutboundMessage exception: " . $e->getMessage());
                }
            }
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
        $logFile = __DIR__ . '/../../logs/wa_outbound_errors.log';
        
        // Ensure log directory exists
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Helper function to log
        $log = function($message) use ($logFile) {
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
        };
        
        $log("=== SAVE OUTBOUND MESSAGE START ===");
        
        // Wrap everything in try-catch to prevent breaking the main flow
        try {
            // Validate essential data first
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $messageId = $response['id'] ?? null; // Provider message ID
            $wamid = $response['wamid'] ?? null; // May be NULL initially, updated by webhook
            
            $log("Data: phone=$waNumber, msg_id=$messageId, type=$messageType");
            
            // Essential: must have phone and message_id
            if (!$waNumber || !$messageId) {
                $log("ERROR: Validation failed - missing phone or message_id");
                return;
            }
            
            $log("✓ Validation passed");
            
            // Load DB class if not already loaded
            if (!class_exists('\\App\\Core\\DB')) {
                $dbPath = __DIR__ . '/../Core/DB.php';
                $log("Loading DB from: $dbPath");
                
                if (!file_exists($dbPath)) {
                    $log("ERROR: DB.php not found at $dbPath");
                    return;
                }
                require_once $dbPath;
                
                // Double check if class loaded successfully
                if (!class_exists('\\App\\Core\\DB')) {
                    $log("ERROR: DB class not loaded after require");
                    return;
                }
            }
            
            $log("✓ DB class loaded");
            
            $db = new \App\Core\DB(0); // Main database with correct namespace
            
            // Verify database connection
            if (!$db || !method_exists($db, 'get_where')) {
                $log("ERROR: DB instance invalid or get_where not found");
                return;
            }
            
            $log("✓ DB connected");
            
            // Get or create customer
            $customerId = null;
            $customer = $db->get_where('wa_customers', ['wa_number' => $waNumber]);
            
            if ($customer && $customer->num_rows() > 0) {
                $customerId = $customer->row()->id;
                $log("✓ Customer found: ID=$customerId");
            } else {
                $log("Creating new customer...");
                // Create new customer
                $customerId = $db->insert('wa_customers', [
                    'wa_number' => $waNumber,
                    'first_contact_at' => date('Y-m-d H:i:s'),
                    'total_messages' => 0,
                    'is_active' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($customerId) {
                    $log("✓ Customer created: ID=$customerId");
                } else {
                    $dbError = $db->conn()->error ?? 'unknown';
                    $log("ERROR: Customer insert failed: $dbError");
                }
            }
            
            if (!$customerId) {
                $log("ERROR: No customer ID - aborting");
                return;
            }
            
            // Get or create conversation
            $conversationId = null;
            $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
            
            if ($conv && $conv->num_rows() > 0) {
                $conversationId = $conv->row()->id;
                $log("✓ Conversation found: ID=$conversationId");
            } else {
                $log("Creating new conversation...");
                // Create new conversation
                $conversationId = $db->insert('wa_conversations', [
                    'customer_id' => $customerId,
                    'wa_number' => $waNumber,
                    'status' => 'open',
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                if ($conversationId) {
                    $log("✓ Conversation created: ID=$conversationId");
                } else {
                    $dbError = $db->conn()->error ?? 'unknown';
                    $log("ERROR: Conversation insert failed: $dbError");
                }
            }
            
            if (!$conversationId) {
                $log("ERROR: No conversation ID - aborting");
                return;
            }
            
            // Extract message content based on type
            $content = null;
            $templateParams = null;
            $mediaUrl = null;
            
            if ($messageType === 'text' && isset($payload['text']['body'])) {
                $content = $payload['text']['body'];
            } elseif ($messageType === 'template' && isset($payload['template']['name'])) {
                $content = $payload['template']['name']; // Store template name in content
                // Store template params if available
                if (isset($payload['template']['components'])) {
                    $templateParams = json_encode($payload['template']['components']);
                }
            } elseif (isset($payload[$messageType]['link'])) {
                $mediaUrl = $payload[$messageType]['link'];
                $content = $payload[$messageType]['caption'] ?? null;
            }
            
            $log("Content extracted: " . substr($content ?? 'NULL', 0, 50));
            
            // Save outbound message to wa_messages_out
            $messageData = [
                'conversation_id' => $conversationId,
                'phone' => $waNumber,
                'wamid' => $wamid,
                'message_id' => $messageId,
                'type' => $messageType,
                'content' => $content,
                'template_params' => $templateParams,
                'media_url' => $mediaUrl,
                'status' => 'accepted', // Initial status when API accepted
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $log("Inserting to wa_messages_out...");
            $msgId = $db->insert('wa_messages_out', $messageData);
            
            if ($msgId) {
                $log("✓✓✓ SUCCESS! Message saved: ID=$msgId");
                
                // Update conversation's last_out_at
                $db->update('wa_conversations', [
                    'last_out_at' => date('Y-m-d H:i:s')
                ], ['id' => $conversationId]);
                
                $log("✓ Conversation updated");
            } else {
                $dbError = $db->conn()->error ?? 'unknown';
                $log("ERROR: Message insert FAILED!");
                $log("DB Error: $dbError");
                $log("Data: " . json_encode($messageData));
            }
            
            $log("=== END ===\n");
            
        } catch (\Throwable $e) {
            $log("EXCEPTION: " . $e->getMessage());
            $log("File: " . $e->getFile() . " Line: " . $e->getLine());
            $log("Trace: " . $e->getTraceAsString());
            $log("=== END (with exception) ===\n");
            
            // Also log to PHP error log
            if (function_exists('error_log')) {
                error_log("WhatsApp saveOutboundMessage error: " . $e->getMessage());
            }
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
