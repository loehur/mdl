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
        
        // Use Env Config if available
        if (empty($this->apiKey) || strpos($this->apiKey, 'YOUR_') !== false) {
             if (!class_exists('\App\Config\Env')) {
                 $envPath = __DIR__ . '/../Config/Env.php';
                 if (file_exists($envPath)) {
                     require_once $envPath;
                 }
             }
             
             if (class_exists('\App\Config\Env') && defined('\App\Config\Env::WA_API_KEY')) {
                 $this->apiKey = \App\Config\Env::WA_API_KEY;
             }
        }
        
        $this->baseUrl = WhatsAppConfig::getBaseUrl();
        if (empty($this->baseUrl)) {
            $this->baseUrl = 'https://api.ycloud.com/v2';
        }
        
        $this->whatsappNumber = WhatsAppConfig::getWhatsAppNumber();
    }
    
    public function getApiKeyPrefix()
    {
        return substr($this->apiKey, 0, 8) . '...';
    }
    
    /**
     * Send free-form text message (within 23-hour CSW)
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
     * Send template message (can be sent anytime, even outside 23-hour CSW)
     * 
     * @param string $to Customer phone number
     * @param string $templateName Template name registered in WhatsApp Business
     * @param string $language Language code (e.g., 'id', 'en')
     * @param array $parameters Template parameters/variables
     * @param string $messageText Optional pre-rendered message text for database storage
     * @return array Response from yCloud API
     */
    public function sendTemplate($to, $templateName, $language = 'id', $parameters = [], $messageText = null)
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
        
        
        // Pass messageText as metadata (not sent to API, but used for DB storage)
        $result = $this->sendRequest('/whatsapp/messages', $payload, 'POST', $messageText);
        
        return $result;
    }
    
    /**
     * Mark message as read
     * 
     * @param string $messageId WAMID to mark as read
     * @return array API Response
     */
    public function markAsRead($messageId)
    {
        $payload = [
            'status' => 'read',
            'messageId' => $messageId
        ];
        
        return $this->sendRequest('/whatsapp/messages/status', $payload);
    }

    /**
     * Retrieve Media from YCloud
     * @param string $mediaId
     * @return array|false [data, mime_type] or false
     */
    public function retrieveMedia($mediaId)
    {
        // 1. Get Media URL info
        // GET /whatsapp/media/{mediaId}
        $res = $this->sendRequest("/whatsapp/media/$mediaId", [], 'GET');
        
        if (!$res['success']) {
            // Extract YCloud Error Message
            $errorMsg = $res['data']['error']['message'] ?? $res['data']['error']['code'] ?? 'Unknown API Error';
            $httpCode = $res['http_code'] ?? 0;
            return ['error' => "API Error: $errorMsg (Status: $httpCode)", 'raw' => $res];
        }
        
        if (!isset($res['data']['url'])) {
            return ['error' => 'No URL in response', 'raw' => $res];
        }
        
        $url = $res['data']['url'];
        
        // 2. Download Content
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$data) {
             return ['error' => "Download Failed ($httpCode): $curlErr"];
        }
        
        return [
            'data' => $data,
            'mime_type' => $contentType
        ];
    }
    
    /**
     * Download and save media to local storage
     * @param string $mediaId
     * @param string|null $directUrl Optional direct download URL from webhook
     * @param string|null $directMimeType Optional mime type from webhook
     * @return string|null Public URL of saved file
     */
    public function downloadAndSaveMedia($mediaId, $directUrl = null, $directMimeType = null)
    {
        $mediaData = null;
        $mime = $directMimeType;
        
        // Scenario 1: Use Direct URL (Faster & Robust)
        if ($directUrl) {
            $mediaData = @file_get_contents($directUrl);
            if (!$mediaData) {
                // Fallback custom curl if file_get_contents blocked
                $ch = curl_init($directUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'MdL-Backend/1.0');
                $mediaData = curl_exec($ch);
                curl_close($ch);
            }
        }
        
        // Scenario 2: Retrieve from API if no direct URL or download failed
        if (!$mediaData) {
            $media = $this->retrieveMedia($mediaId);
            if (isset($media['data'])) {
                $mediaData = $media['data'];
                $mime = $media['mime_type']; // Use API mime if available
            }
        }
        
        if (!$mediaData) return null;
        
        // Save Path: api/uploads/whatsapp/YYYY/MM/
        $relativePath = '/uploads/whatsapp/' . date('Y/m');
        $baseDir = __DIR__ . '/../../' . $relativePath;
        
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0755, true);
        }
        
        $ext = $this->mime2ext($mime);
        
        // Default filename
        $filename = $mediaId . '.' . $ext;
        $savePath = $baseDir . '/' . $filename;
        
        // Media path (no verbose logging)
        
        $saved = false;
        
        // COMPRESSION LOGIC: Only for Images
        if ($mime && strpos($mime, 'image/') !== false) {
             try {
                 $im = @imagecreatefromstring($mediaData);
                 if ($im) {
                     // 1. Resize if too big (Max 1024px)
                     $width = imagesx($im);
                     $height = imagesy($im);
                     $maxDim = 1024;
                     
                     if ($width > $maxDim || $height > $maxDim) {
                         $ratio = $width / $height;
                         if ($ratio > 1) { // Landscape
                             $newWidth = $maxDim;
                             $newHeight = $maxDim / $ratio;
                         } else { // Portrait
                             $newHeight = $maxDim;
                             $newWidth = $maxDim * $ratio;
                         }
                         
                         // Cast to int to prevent "Implicit conversion from float to int" error
                         $newWidth = (int) round($newWidth);
                         $newHeight = (int) round($newHeight);
                         
                         $newIm = imagecreatetruecolor($newWidth, $newHeight);
                         
                         // Handle Transparency (fill white)
                         $white = imagecolorallocate($newIm, 255, 255, 255);
                         imagefilledrectangle($newIm, 0, 0, $newWidth, $newHeight, $white);
                         
                         imagecopyresampled($newIm, $im, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                         imagedestroy($im);
                         $im = $newIm;
                     }
                     
                     // 2. Force convert to JPG & Compress (Quality 60)
                     $filename = $mediaId . '.jpg'; // Force extension
                     $savePath = $baseDir . '/' . $filename;
                     
                     imagejpeg($im, $savePath, 60);
                     imagedestroy($im);
                     $saved = true;
                 }
             } catch (\Throwable $e) {
                 \Log::write("Image compression failed: " . $e->getMessage(), 'wa_media_error', 'error');
             }
        }
        
        if (!$saved) {
            file_put_contents($savePath, $mediaData);
        }
        
        // Get Base URL
        $baseUrl = 'https://api.nalju.com';
        if (class_exists('\App\Config\Env') && defined('\App\Config\Env::BASE_URL')) {
             $baseUrl = rtrim(\App\Config\Env::BASE_URL, '/');
        }
        return $baseUrl . $relativePath . '/' . $filename;
    }
    
    private function mime2ext($mime)
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/amr' => 'amr',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];
        // strip ; charset=... if present
        $mime = explode(';', $mime)[0];
        return $map[$mime] ?? 'bin';
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
     * CSW = 23 hours from last customer message
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
        
        return $hoursDiff <= 23;
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
     * @param string $method HTTP method (default: POST)
     * @param string|null $messageText Optional pre-rendered message text for database storage
     * @return array API response
     */
    private function sendRequest($endpoint, $payload, $method = 'POST', $messageText = null)
    {
        $url = $this->baseUrl . $endpoint;
        
        // Removed verbose request logging
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $this->apiKey
        ]);
        
        // Set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);         
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);  
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log only errors to reduce verbosity
        
        if ($error) {
            if (class_exists('\Log')) {
                \Log::write("!! CURL ERROR: $error", 'wa_error', 'SendRequest');
            }
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
        $localId = null;
        if ($success && isset($responseData['id'])) {
            try {
                $localId = $this->saveOutboundMessage($payload, $responseData, $messageText);
            } catch (\Throwable $e) {
                if (class_exists('\Log')) {
                    \Log::write("!! EXCEPTION saving outbound: " . $e->getMessage(), 'wa_error', 'SaveOutbound');
                }
                // Silently catch any error - don't let it affect the API response
                if (function_exists('error_log')) {
                    error_log("saveOutboundMessage exception: " . $e->getMessage());
                }
            }
        } else {
             if (class_exists('\Log')) {
                \Log::write("!! API FAIL or NO ID: " . json_encode($responseData), 'wa_error', 'SendRequest');
            }
        }
        
        return [
            'success' => $success,
            'http_code' => $httpCode,
            'data' => $responseData,
            'local_id' => $localId, // Expose ID to controller
            'raw_response' => $response
        ];
    }
    
    /**
     * Save outbound message to wa_messages table
     * 
     * @param array $payload Request payload sent to API
     * @param array $response API response
     * @param string|null $messageText Optional pre-rendered message text (for templates)
     */
    private function saveOutboundMessage($payload, $response, $messageText = null)
    {       
        // Wrap everything in try-catch to prevent breaking the main flow
        try {
            // Validate essential data first
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $messageId = $response['id'] ?? null; // Provider message ID
            $wamid = $response['wamid'] ?? null; // May be NULL initially, updated by webhook
            
            // Essential: must have phone and message_id
            if (!$waNumber || !$messageId) {
                if (class_exists('\Log')) {
                    \Log::write("!! VALIDATION FAILED - Phone: " . ($waNumber ?: 'EMPTY') . ", MessageID: " . ($messageId ?: 'EMPTY') . " | Payload: " . json_encode($payload), 'wa_error', 'SaveOutbound');
                }
                return;
            }
            
            // Extract message content based on type EARLY so we can use it for last_message
            $content = null;
            $templateParams = null;
            $mediaUrl = null;
            
            if ($messageType === 'text' && isset($payload['text']['body'])) {
                $content = $payload['text']['body'];
            } elseif ($messageType === 'template' && isset($payload['template']['name'])) {
                // FALLBACK: Extract text from template parameters
                $templateText = '';
                if (isset($payload['template']['components'])) {
                    foreach ($payload['template']['components'] as $component) {
                        if ($component['type'] === 'body' && isset($component['parameters'])) {
                            $params = [];
                            foreach ($component['parameters'] as $param) {
                                if ($param['type'] === 'text') {
                                    $params[] = $param['text'];
                                }
                            }
                            // Build readable text from parameters
                            // Format: "Customer: BUDI | Order: ... | Total: ... | Link: ..."
                            $templateText = implode(' | ', $params);
                        }
                    }
                }
                
                // Store readable text in content, not template name
                $content = $templateText ?: $payload['template']['name']; // Fallback to template name if no text
                
                // Store template params for reference
                if (isset($payload['template']['components'])) {
                    $templateParams = json_encode($payload['template']['components']);
                }
            } elseif (isset($payload[$messageType]['link'])) {
                $mediaUrl = $payload[$messageType]['link'];
                $content = $payload[$messageType]['caption'] ?? null;
            }
            
            // Determine text for last_message
            $lastMessageText = $content;
            if (empty($lastMessageText)) {
                $lastMessageText = ($messageType === 'template') 
                    ? "Template: " . ($payload['template']['name'] ?? '') 
                    : "Media: $messageType";
            }

            // Load DB class if not already loaded
            if (!class_exists('\\App\\Core\\DB')) {
                $dbPath = __DIR__ . '/../Core/DB.php';
                
                if (!file_exists($dbPath)) {
                    if (class_exists('\Log')) {
                        \Log::write("!! DB.php NOT FOUND at $dbPath", 'wa_error', 'SaveOutbound');
                    }
                    return;
                }
                require_once $dbPath;
                
                // Double check if class loaded successfully
                if (!class_exists('\\App\\Core\\DB')) {
                    if (class_exists('\Log')) {
                        \Log::write("!! DB class FAILED to load after require", 'wa_error', 'SaveOutbound');
                    }
                    return;
                }
            }
            
            $db = new \App\Core\DB(0); // Main database with correct namespace
            
            // Verify database connection
            if (!$db || !method_exists($db, 'get_where')) {
                if (class_exists('\Log')) {
                    \Log::write("!! DB instance creation FAILED or missing get_where method", 'wa_error', 'SaveOutbound');
                }
                return;
            }
            
            // Fetch Customer Data from Laundry DB
            $userData = $this->getUserData($waNumber);
            $contactName = $userData['contact_name'] ?? null;
            $code = $userData['code'] ?? null;
            
            // Get or create conversation (NO CUSTOMER CREATION on Outbound)
            // Try find customer
            $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
            
            if ($conv && $conv->num_rows() > 0) {
                // Update conversation
                $updateData = [
                    'last_message' => $lastMessageText,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                if ($contactName) $updateData['contact_name'] = $contactName;
                if ($code) $updateData['code'] = $code;
                
                $db->update('wa_conversations', $updateData, ['wa_number' => $waNumber]);
            } else {
                // Create new conversation
                $convData = [
                    'wa_number' => $waNumber,
                    'status' => 'closed',
                    'last_message' => $lastMessageText,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if ($contactName) $convData['contact_name'] = $contactName;
                if ($code) $convData['code'] = $code;
                
                $db->insert('wa_conversations', $convData);
            }
            // Conversation ID check removed as we don't use ID anymore
            
            
            // Save outbound message to wa_messages_out
            $messageData = [
                // 'conversation_id' => $conversationId, // Removed as column deleted
                'phone' => $waNumber,
                'wamid' => $wamid,
                'message_id' => $messageId,
                'type' => $messageType, // Direct use - no mapping needed if column is VARCHAR
                'content' => $content,
                'template_params' => $templateParams,
                'media_url' => $mediaUrl,
                'status' => 'accepted', // Initial status when API accepted
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $msgId = $db->insert('wa_messages_out', $messageData);
            
            if (!$msgId) {
                $dbError = $db->conn()->error ?? 'Unknown';
                if (class_exists('\Log')) {
                    \Log::write("!! INSERT FAILED to wa_messages_out | Phone: $waNumber, MsgID: $messageId | DB Error: $dbError | Data: " . json_encode($messageData), 'wa_error', 'SaveOutbound');
                }
            } else {
                // ====== WEBSOCKET PUSH (CENTRALIZED) ======
                // Push to WebSocket for real-time UI update
                // This is the SINGLE SOURCE for all outbound messages (autoreply + manual)
                try {
                    // Get conversation data for WebSocket payload
                    $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
                    $conversation_id = 0;
                    $kode_cabang = $code ?? '00';
                    
                    if ($conv && $conv->num_rows() > 0) {
                        $convRow = $conv->row();
                        $conversation_id = $convRow->id ?? 0;
                    }
                    
                    $wsPayload = [
                        'type' => 'agent_message_sent',
                        'conversation_id' => $conversation_id,
                        'phone' => $waNumber,
                        'contact_name' => $contactName,
                        'kode_cabang' => $kode_cabang,
                        'sender_id' => 0, // System/Auto = 0, or can be passed as parameter
                        'message' => [
                            'id' => $msgId, // Use local DB ID
                            'wamid' => $wamid,
                            'text' => $content,
                            'type' => $messageType,
                            'media_url' => $mediaUrl,
                            'time' => date('Y-m-d H:i:s'),
                            'status' => 'sent'
                        ]
                    ];
                    
                    // Send to WebSocket
                    $wsUrl = 'https://waserver.nalju.com/incoming';
                    $ch = curl_init($wsUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wsPayload));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                    curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
                    curl_exec($ch);
                    curl_close($ch);
                } catch (\Throwable $wsError) {
                    // Silently fail - don't break main flow
                    if (class_exists('\Log')) {
                        \Log::write("!! WebSocket push failed: " . $wsError->getMessage(), 'wa_error', 'WebSocket');
                    }
                }
            }
            
            return $msgId; // Return the Local DB ID (or null if failed)
            
        } catch (\Throwable $e) {
            // Detailed exception logging
            $errorMsg = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            $errorTrace = $e->getTraceAsString();
            
            if (class_exists('\Log')) {
                \Log::write("!! EXCEPTION in saveOutboundMessage: $errorMsg at $errorFile:$errorLine", 'wa_error', 'SaveOutbound');
                \Log::write("!! Stack Trace: $errorTrace", 'wa_error', 'SaveOutbound');
                \Log::write("!! Payload was: " . json_encode($payload ?? []), 'wa_error', 'SaveOutbound');
            }
            
            // Also log to PHP error log
            if (function_exists('error_log')) {
                error_log("saveOutboundMessage error: $errorMsg at $errorFile:$errorLine");
            }
        }
    }
    

    
    /**
     * Send Image via WhatsApp
     * 
     * @param string $to Phone number
     * @param string $imageUrl URL to the image
     * @param string $caption Optional caption
     * @return array Response with success status and data
     */
    public function sendImage($to, $imageUrl, $caption = '')
    {

        
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];
        
        if ($caption) {
            $payload['image']['caption'] = $caption;
        }
        
        try {
            // Use correct YCloud endpoint: /whatsapp/messages
            $response = $this->sendRequest('/whatsapp/messages', $payload);
            
            // Parse response - check http_code (underscore, not camelCase!)
            if ($response['success'] && ($response['http_code'] == 200 || $response['http_code'] == 201)) {
                // Response already parsed by sendRequest, use 'data' directly
                $data = $response['data'];
                
                if (isset($data['id']) || isset($data['message_id'])) {
                    $responseData = [
                        'id' => $data['id'] ?? $data['message_id'] ?? null,
                        'wamid' => $data['wamid'] ?? null,
                        'status' => $data['status'] ?? 'sent'
                    ];
                    
                    // Outbound message already saved by sendRequest
                    $localId = $response['local_id'] ?? null;
                    
                    return [
                        'success' => true,
                        'data' => $responseData,
                        'local_id' => $localId
                    ];
                }
            }
            
            // Error
            if (class_exists('\Log')) {
                \Log::write("sendImage FAILED - response: " . json_encode($response), 'wa_error', 'SendImage');
            }
            
            return [
                'success' => false,
                'error' => $response['error'] ?? 'Failed to send image',
                'httpCode' => $response['http_code'] ?? 500
            ];
            
        } catch (\Exception $e) {
            if (class_exists('\Log')) {
                \Log::write("sendImage EXCEPTION: " . $e->getMessage(), 'wa_error', 'SendImage');
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user data (contact_name, code) from laundry database
     */
    private function getUserData($waNumber)
    {
        // Connect to Laundry DB (DB 1)
        if (!class_exists('\App\Core\DB')) return null;
        
        try {
            $db = new \App\Core\DB(1);
            if (!$db) return null;
            
            $cleanPhone = preg_replace('/[^0-9]/', '', $waNumber); // 628...
            $phone0 = '0' . substr($cleanPhone, 2); // 08...
            
            // Search Customer
            $customer = $db->query("SELECT * FROM pelanggan WHERE nomor_pelanggan LIKE '%" . substr($phone0, 2) . "%' ORDER BY updated_at DESC LIMIT 1")->row();
            
            if (!$customer) return null;
            
            $result = [
                'contact_name' => $customer->nama_pelanggan,
                'assigned_user_id' => null,
                'code' => null
            ];
            
            $last_sale = $db->query("SELECT * FROM sale WHERE id_pelanggan = " . $customer->id_pelanggan . " ORDER BY insertTime DESC LIMIT 1")->row();
            if ($last_sale) {
                $result['assigned_user_id'] = $last_sale->id_cabang;
                $cabang = $db->query("SELECT kode_cabang FROM cabang WHERE id_cabang = " . $last_sale->id_cabang)->row();
                if ($cabang) $result['code'] = $cabang->kode_cabang;
            }
            
            return $result;
            
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                 \Log::write("getUserData Exception: " . $e->getMessage(), 'wa_error', 'Helper');
            }
            return null;
        }
    }
}
