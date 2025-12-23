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
        
        // Log path for debugging
        if (class_exists('\Log')) {
            \Log::write("Saving media to: $savePath (Source: " . ($directUrl ? 'Direct' : 'API') . ")", 'wa_media');
        }
        
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
     * @return array API response
     */
    private function sendRequest($endpoint, $payload)
    {
        $url = $this->baseUrl . $endpoint;
        
        // LOG REQUEST START
        if (class_exists('\Log')) {
            $preview = json_encode($payload);
            if (strlen($preview) > 200) $preview = substr($preview, 0, 200) . '...';
            \Log::write("-> SENDING WA: $endpoint | To: " . ($payload['to'] ?? '?') . " | $preview", 'wa_debug', 'SendRequest');
        }
        
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
        
        // LOG RESPONSE
        if (class_exists('\Log')) {
            \Log::write("<- RESPONSE WA ($httpCode): " . ($error ?: strip_tags(substr($response, 0, 100))), 'wa_debug', 'SendRequest');
        }
        
        // Log to internal file as well (legacy)
        $this->logMessage($endpoint, $payload, $response, $httpCode);
        
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
                $localId = $this->saveOutboundMessage($payload, $responseData);
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
     */
    private function saveOutboundMessage($payload, $response)
    {       
        // === START OUTBOUND LOGGING ===
        $logPrefix = "[OUTBOUND_SAVE]";
        
        // Log the method call
        if (class_exists('\Log')) {
            \Log::write("$logPrefix ======== START SAVE OUTBOUND MESSAGE ========", 'outbound_log', 'SaveOutbound');
            \Log::write("$logPrefix Payload: " . json_encode($payload), 'outbound_log', 'SaveOutbound');
            \Log::write("$logPrefix Response: " . json_encode($response), 'outbound_log', 'SaveOutbound');
        }
        
        // Wrap everything in try-catch to prevent breaking the main flow
        try {
            // Validate essential data first
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $messageId = $response['id'] ?? null; // Provider message ID
            $wamid = $response['wamid'] ?? null; // May be NULL initially, updated by webhook
            
            // Log extracted data
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Extracted Data - Phone: $waNumber, Type: $messageType, MsgID: $messageId, WAMID: $wamid", 'outbound_log', 'SaveOutbound');
            }
            
            // Essential: must have phone and message_id
            if (!$waNumber || !$messageId) {
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix !! VALIDATION FAILED - Phone: " . ($waNumber ?: 'EMPTY') . ", MessageID: " . ($messageId ?: 'EMPTY'), 'outbound_log', 'SaveOutbound');
                }
                \Log::write("!! SKIP SAVE: Missing phone or ID", 'wa_debug', 'SaveOutbound');
                return;
            }
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix ✓ Validation passed", 'outbound_log', 'SaveOutbound');
            }
            
            // Extract message content based on type EARLY so we can use it for last_message
            $content = null;
            $templateParams = null;
            $mediaUrl = null;
            
            if ($messageType === 'text' && isset($payload['text']['body'])) {
                $content = $payload['text']['body'];
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix Content Type: TEXT - Content: $content", 'outbound_log', 'SaveOutbound');
                }
            } elseif ($messageType === 'template' && isset($payload['template']['name'])) {
                $content = $payload['template']['name']; // Store template name in content
                // Store template params if available
                if (isset($payload['template']['components'])) {
                    $templateParams = json_encode($payload['template']['components']);
                }
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix Content Type: TEMPLATE - Name: $content, Params: " . ($templateParams ?: 'N/A'), 'outbound_log', 'SaveOutbound');
                }
            } elseif (isset($payload[$messageType]['link'])) {
                $mediaUrl = $payload[$messageType]['link'];
                $content = $payload[$messageType]['caption'] ?? null;
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix Content Type: MEDIA ($messageType) - URL: $mediaUrl, Caption: " . ($content ?: 'N/A'), 'outbound_log', 'SaveOutbound');
                }
            }
            
            // Determine text for last_message
            $lastMessageText = $content;
            if (empty($lastMessageText)) {
                $lastMessageText = ($messageType === 'template') 
                    ? "Template: " . ($payload['template']['name'] ?? '') 
                    : "Media: $messageType";
            }
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Last Message Text: $lastMessageText", 'outbound_log', 'SaveOutbound');
            }

            // Load DB class if not already loaded
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Checking DB class...", 'outbound_log', 'SaveOutbound');
            }
            
            if (!class_exists('\\App\\Core\\DB')) {
                $dbPath = __DIR__ . '/../Core/DB.php';
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix DB class not loaded, path: $dbPath", 'outbound_log', 'SaveOutbound');
                }
                
                if (!file_exists($dbPath)) {
                    if (class_exists('\Log')) {
                        \Log::write("$logPrefix !! DB.php NOT FOUND at $dbPath", 'outbound_log', 'SaveOutbound');
                    }
                    return;
                }
                require_once $dbPath;
                
                // Double check if class loaded successfully
                if (!class_exists('\\App\\Core\\DB')) {
                    if (class_exists('\Log')) {
                        \Log::write("$logPrefix !! DB class FAILED to load after require", 'outbound_log', 'SaveOutbound');
                    }
                    return;
                }
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓ DB class loaded successfully", 'outbound_log', 'SaveOutbound');
                }
            } else {
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓ DB class already available", 'outbound_log', 'SaveOutbound');
                }
            }
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Creating DB instance...", 'outbound_log', 'SaveOutbound');
            }
            
            $db = new \App\Core\DB(0); // Main database with correct namespace
            
            // Verify database connection
            if (!$db || !method_exists($db, 'get_where')) {
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix !! DB instance creation FAILED or missing get_where method", 'outbound_log', 'SaveOutbound');
                }
                return;
            }
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix ✓ DB instance created successfully", 'outbound_log', 'SaveOutbound');
            }
            
            // Get or create conversation (NO CUSTOMER CREATION on Outbound)
            $conversationId = null;
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Looking for conversation with wa_number: $waNumber", 'outbound_log', 'SaveOutbound');
            }
                      
            // Try find customer
            $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Conversation query result: " . ($conv ? "Found " . $conv->num_rows() . " rows" : "NULL"), 'outbound_log', 'SaveOutbound');
            }
            
            if ($conv && $conv->num_rows() > 0) {
                $conversationId = $conv->row()->id;
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓ Found existing conversation ID: $conversationId", 'outbound_log', 'SaveOutbound');
                }
                
                // Update conversation
                $updateData = [
                    'last_message' => $lastMessageText,
                    'last_out_at' => date('Y-m-d H:i:s')
                ];
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix Updating conversation with: " . json_encode($updateData), 'outbound_log', 'SaveOutbound');
                }
                
                $db->update('wa_conversations', $updateData, ['id' => $conversationId]);
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓ Conversation updated", 'outbound_log', 'SaveOutbound');
                }
            } else {
                // Create new conversation
                $insertData = [
                    'wa_number' => $waNumber,
                    'status' => 'open',
                    'last_message' => $lastMessageText,
                    'last_out_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix Creating NEW conversation with data: " . json_encode($insertData), 'outbound_log', 'SaveOutbound');
                }
                
                \Log::write("++ NEW CONV created for outbound: $waNumber", 'wa_debug', 'SaveOutbound');
                $conversationId = $db->insert('wa_conversations', $insertData);
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓ New conversation created with ID: " . ($conversationId ?: 'FAILED'), 'outbound_log', 'SaveOutbound');
                }
            }
            
            if (!$conversationId) {
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix !! CRITICAL: conversationId is NULL or 0", 'outbound_log', 'SaveOutbound');
                }
                \Log::write("!! FAILED to get/create Conversation ID", 'wa_error', 'SaveOutbound');
                return;
            }
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix ✓ Conversation ID confirmed: $conversationId", 'outbound_log', 'SaveOutbound');
            }
            
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
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Preparing to insert message to wa_messages_out", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix Message Data: " . json_encode($messageData), 'outbound_log', 'SaveOutbound');
            }
            
            $msgId = $db->insert('wa_messages_out', $messageData);
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix Insert result: " . ($msgId ? "SUCCESS - ID: $msgId" : "FAILED"), 'outbound_log', 'SaveOutbound');
            }
            
            if ($msgId) {
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ✓✓✓ MESSAGE SUCCESSFULLY SAVED TO DATABASE ✓✓✓", 'outbound_log', 'SaveOutbound');
                    \Log::write("$logPrefix Local ID: $msgId, Message ID: $messageId, Phone: $waNumber", 'outbound_log', 'SaveOutbound');
                }
                \Log::write("v MESSAGE SAVED ID #$msgId (WA ID: $messageId)", 'wa_debug', 'SaveOutbound');
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ======== END SAVE OUTBOUND MESSAGE (SUCCESS) ========", 'outbound_log', 'SaveOutbound');
                }
                
                return $msgId; // Return the Local DB ID
            } else {
                $dbError = $db->conn()->error ?? 'Unknown';
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix !! INSERT TO wa_messages_out FAILED", 'outbound_log', 'SaveOutbound');
                    \Log::write("$logPrefix Database Error: $dbError", 'outbound_log', 'SaveOutbound');
                }
                
                \Log::write("!! INSERT MSG FAILED: $dbError", 'wa_error', 'SaveOutbound');
                
                if (class_exists('\Log')) {
                    \Log::write("$logPrefix ======== END SAVE OUTBOUND MESSAGE (FAILED) ========", 'outbound_log', 'SaveOutbound');
                }
            }
            
            return null;
            
        } catch (\Throwable $e) {
            // Detailed exception logging
            $errorMsg = $e->getMessage();
            $errorFile = $e->getFile();
            $errorLine = $e->getLine();
            $errorTrace = $e->getTraceAsString();
            
            if (class_exists('\Log')) {
                \Log::write("$logPrefix !! EXCEPTION CAUGHT IN SAVE OUTBOUND", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix Exception Message: $errorMsg", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix Exception File: $errorFile", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix Exception Line: $errorLine", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix Stack Trace: $errorTrace", 'outbound_log', 'SaveOutbound');
                \Log::write("$logPrefix ======== END SAVE OUTBOUND MESSAGE (EXCEPTION) ========", 'outbound_log', 'SaveOutbound');
            }
            
            // Also log to PHP error log
            if (function_exists('error_log')) {
                error_log("saveOutboundMessage error: $errorMsg at $errorFile:$errorLine");
            }
            if (class_exists('\Log')) {
                \Log::write("!! EXCEPTION: $errorMsg", 'wa_error', 'SaveOutbound');
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
