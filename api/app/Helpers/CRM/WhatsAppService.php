<?php

namespace App\Helpers\CRM;

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

    private function generateExternalId(): string
    {
        // Used to reconcile outbound records across retries + webhook updates
        try {
            if (function_exists('random_bytes')) {
                return 'wa_' . bin2hex(random_bytes(8));
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return 'wa_' . str_replace('.', '', (string) uniqid((string) microtime(true), true));
    }
    
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
     * Antrekan free text (status queue) saat CSW yCloud & Fonnte tidak memungkinkan kirim —
     * dipakai endpoint /Laundry/WhatsApp/send bila last_in menolak keduanya tanpa hit API,
     * atau bisa dipanggil eksplisit. Cron ResendWAQueue mengirim saat CSW terbuka (24 jam).
     */
    public function queueFreeTextForCswRetry($to, $message, $replyToMessageId = null, $senderCode = null, $errorMessage = 'CSW closed — standby for resend within 24h')
    {
        $externalIdToUse = $this->generateExternalId();
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'text',
            'externalId' => $externalIdToUse,
            'text' => [
                'body' => $message,
            ],
        ];
        if ($replyToMessageId) {
            $payload['context'] = [
                'message_id' => $replyToMessageId,
            ];
        }

        try {
            return $this->saveOutboundQueueMessage($payload, null, $senderCode, $replyToMessageId, $errorMessage);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write('queueFreeTextForCswRetry: ' . $e->getMessage(), 'wa_error', 'SaveOutbound');
            }

            return null;
        }
    }

    public function sendFreeText($to, $message, $replyToMessageId = null, $senderCode = null, $externalId = null)
    {
        $externalIdToUse = !empty($externalId) ? (string)$externalId : $this->generateExternalId();
        $payload = [
            'from' => $this->formatPhoneNumber($this->whatsappNumber),
            'to' => $this->formatPhoneNumber($to),
            'type' => 'text',
            // Recommended id to reconcile webhook status with internal systems
            'externalId' => $externalIdToUse,
            'text' => [
                'body' => $message
            ]
        ];
        
        // Add context for quoted reply
        if ($replyToMessageId) {
            $payload['context'] = [
                'message_id' => $replyToMessageId
            ];
        }
        
        return $this->sendRequest('/whatsapp/messages', $payload, 'POST', null, $replyToMessageId, $senderCode);
    }
    
    public function sendTemplate($to, $templateName, $language = 'id', $parameters = [], $messageText = null)
    {        
        $components = [];
        
        // Add body parameters if provided
        if (!empty($parameters)) {
            $bodyParams = [];
            $isList = array_keys($parameters) === range(0, count($parameters) - 1);

            foreach ($parameters as $key => $param) {
                $item = [
                    'type' => 'text',
                    'text' => (string) $param,
                ];
                // Associative map → named template params (Meta / yCloud)
                if (!$isList && is_string($key) && $key !== '' && !ctype_digit((string) $key)) {
                    $item['parameter_name'] = $key;
                }
                $bodyParams[] = $item;
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
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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
                curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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
        
        // Save Path: api/uploads/whatsapp/YYYY/MM/ (web-accessible; must match URL /uploads/...)
        // From Helpers/CRM go up 3 levels → api/
        $relativePath = '/uploads/whatsapp/' . date('Y/m');
        $baseDir = __DIR__ . '/../../../uploads/whatsapp/' . date('Y/m');
        
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
            'audio/ogg; codecs=opus' => 'ogg',
            'audio/opus' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
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
    
    public function isWithinCsw($lastMessageAt)
    {
        if (empty($lastMessageAt)) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $hoursDiff = $this->diffHours($now, $lastMessageAt);
        
        return $hoursDiff <= 24;
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
     * @param string|null $replyToMessageId Optional reply to message ID
     * @param string|null $senderCode Optional sender code
     * @return array API response
     */
    private function sendRequest($endpoint, $payload, $method = 'POST', $messageText = null, $replyToMessageId = null, $senderCode = null)
    {
        $url = $this->baseUrl . $endpoint;
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-API-Key: ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $responseData = $response ? json_decode($response, true) : null;
            $success = $httpCode >= 200 && $httpCode < 300;

            // Retry hanya untuk: CURL error, 5xx, atau 0 (no response)
            $shouldRetry = false;
            if ($error) {
                $shouldRetry = true;
            } elseif ($httpCode >= 500 || $httpCode == 0) {
                $shouldRetry = true;
            }
            // Jangan retry 4xx (client error) - tidak akan berubah

            if ($success && isset($responseData['id'])) {
                $localId = null;
                try {
                    $localId = $this->saveOutboundMessage($payload, $responseData, $messageText, $senderCode, $replyToMessageId);
                } catch (\Throwable $e) {
                    if (class_exists('\Log')) {
                        \Log::write("!! EXCEPTION saving outbound: " . $e->getMessage(), 'wa_error', 'SaveOutbound');
                    }
                }
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'data' => $responseData,
                    'local_id' => $localId,
                    'raw_response' => $response
                ];
            }

            $to = $payload['to'] ?? 'unknown';

            if ($shouldRetry && $attempt < $maxAttempts) {
                if (class_exists('\Log')) {
                    \Log::write("!! Send FAIL (attempt $attempt/$maxAttempts) to $to: " . ($error ?: "HTTP $httpCode") . " - retrying in 1.5s...", 'wa_error', 'SendRequest');
                }
                usleep(1500000); // 1.5 sec
            } else {
                if (class_exists('\Log')) {
                    \Log::write("!! Send FAIL to $to after $attempt attempt(s): " . ($error ?: "HTTP $httpCode") . " | " . json_encode($responseData), 'wa_error', 'SendRequest');
                }
                // If yCloud timeout / 5xx / network error => queue for cron resend
                $shouldQueue = ($httpCode == 0) || (!empty($error)) || ($httpCode >= 500);
                if ($shouldQueue) {
                    try {
                        $queueError = $error ?: ('HTTP ' . (string)$httpCode);
                        $this->saveOutboundQueueMessage($payload, $messageText, $senderCode, $replyToMessageId, $queueError);
                    } catch (\Throwable $e) {
                        if (class_exists('\Log')) {
                            \Log::write("!! EXCEPTION queue insert outbound: " . $e->getMessage(), 'wa_error', 'SaveOutbound');
                        }
                    }
                } elseif ($this->isFreeTextPayload($payload) && $this->isYCloudCswApiError($responseData)) {
                    // CSW tertutup di API yCloud (4xx) — antrekan agar cron bisa kirim saat CSW terbuka
                    try {
                        $this->saveOutboundQueueMessage(
                            $payload,
                            $messageText,
                            $senderCode,
                            $replyToMessageId,
                            'CSW closed (yCloud API)'
                        );
                    } catch (\Throwable $e) {
                        if (class_exists('\Log')) {
                            \Log::write("!! EXCEPTION queue insert outbound (CSW): " . $e->getMessage(), 'wa_error', 'SaveOutbound');
                        }
                    }
                }
                return [
                    'success' => false,
                    'error' => $error ?: 'API error',
                    'http_code' => $httpCode,
                    'data' => $responseData,
                    'raw_response' => $response
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded',
            'http_code' => $httpCode ?? 0,
            'data' => $responseData ?? null,
            'raw_response' => $response ?? ''
        ];
    }

    private function isFreeTextPayload(array $payload): bool
    {
        return ($payload['type'] ?? '') === 'text' && isset($payload['text']['body']);
    }

    /**
     * Respons API yCloud: free text di luar jendela CSW (sama logika dengan WhatsApp::isYCloudFreeTextCswError).
     */
    private function isYCloudCswApiError(?array $responseData): bool
    {
        if (!is_array($responseData)) {
            return false;
        }
        $errorData = $responseData['error'] ?? null;
        if (!is_array($errorData)) {
            return false;
        }
        $errorCode = $errorData['code'] ?? '';
        $errorMsg = $errorData['message'] ?? '';
        $codeStr = is_scalar($errorCode) ? (string) $errorCode : '';
        $msgStr = is_string($errorMsg) ? $errorMsg : '';
        if (strpos($codeStr, '131047') !== false) {
            return true;
        }
        if ($msgStr !== '' && (stripos($msgStr, 'outside') !== false || stripos($msgStr, '24 hour') !== false || stripos($msgStr, '24-hour') !== false)) {
            return true;
        }

        return false;
    }

    /**
     * Insert/update outbound record in wa_messages_out with status=queue.
     * Used when yCloud times out/network errors so cron can resend later.
     */
    private function saveOutboundQueueMessage($payload, $messageText = null, $senderCode = null, $quotedMessageId = null, $errorMessage = null)
    {
        // Wrap everything in try-catch to prevent breaking main flow
        try {
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $externalId = $payload['externalId'] ?? null;

            if (!$waNumber || !$externalId) {
                // externalId is required for upsert / webhook reconciliation
                if (class_exists('\Log')) {
                    \Log::write("!! QUEUE INSERT skipped - Phone/ExternalId missing. Phone=" . ($waNumber ?: 'EMPTY') . " ExternalId=" . ($externalId ?: 'EMPTY'), 'wa_error', 'SaveOutbound');
                }
                return;
            }

            // Extract message content based on type
            $content = null;
            $templateParams = null;
            $mediaUrl = null;
            if ($messageType === 'text' && isset($payload['text']['body'])) {
                $content = $payload['text']['body'];
            } elseif ($messageType === 'template' && isset($payload['template']['name'])) {
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
                            $templateText = implode(' | ', $params);
                        }
                    }
                }
                $content = $templateText ?: ($payload['template']['name'] ?? null);
                if (isset($payload['template']['components'])) {
                    $templateParams = json_encode($payload['template']['components']);
                }
            } elseif (isset($payload[$messageType]['link'])) {
                $mediaUrl = $payload[$messageType]['link'];
                $content = $payload[$messageType]['caption'] ?? null;
            }

            $lastMessageText = $content ?: (($messageType === 'template') ? ('Template: ' . ($payload['template']['name'] ?? '')) : ('Media: ' . $messageType));
            $isPrivate = $this->checkPrivateWords($content ?? '', $messageText ?? '', $lastMessageText ?? '', 'queue_insert', '');

            // Create outbound message record
            $messageData = [
                'phone' => $waNumber,
                'wamid' => null,
                'message_id' => null,
                'type' => $messageType,
                'content' => $content,
                'template_params' => $templateParams,
                'media_url' => $mediaUrl,
                'sender_code' => $senderCode,
                'status' => 'queue',
                'private' => $isPrivate ? 1 : 0,
                'external_id' => $externalId,
                'error_message' => $errorMessage,
                'created_at' => date('Y-m-d H:i:s')
            ];

            if ($quotedMessageId !== null) {
                $messageData['quoted_message_id'] = $quotedMessageId;
            }

            // Upsert by external_id to prevent duplicates on retries
            $db = new \App\Core\DB(0);
            $existing = $db->get_where('wa_messages_out', ['external_id' => $externalId])->row();
            if ($existing) {
                $db->update('wa_messages_out', $messageData, ['external_id' => $externalId]);
                return (int)($existing->id ?? 0);
            }

            return $db->insert('wa_messages_out', $messageData);
        } catch (\Throwable $e) {
            if (class_exists('\Log')) {
                \Log::write("!! EXCEPTION in saveOutboundQueueMessage: " . $e->getMessage(), 'wa_error', 'SaveOutbound');
            }
            return null;
        }
    }
    
    /**
     * Save outbound message to wa_messages table
     * 
     * @param array $payload Request payload sent to API
     * @param array $response API response
     * @param string|null $messageText Optional pre-rendered message text (for templates)
     * @param string|null $senderCode Sender code
     * @param string|null $quotedMessageId Quoted/reply-to message ID
     */
    private function saveOutboundMessage($payload, $response, $messageText = null, $senderCode = null, $quotedMessageId = null)
    {       
        // Wrap everything in try-catch to prevent breaking the main flow
        try {
            // Validate essential data first
            $waNumber = $payload['to'] ?? null;
            $messageType = $payload['type'] ?? 'text';
            $externalId = $payload['externalId'] ?? null;
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
            
            // Check if message is private (for last_message formatting)
            $isPrivateForLastMessage = $this->checkPrivateWords($content ?? '', $messageText ?? '', $lastMessageText ?? '', 'last_message', '');

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
            $cust_id = $userData['cust_id'] ?? null;
            // Cabang from last sale — required so crew (filtered by assigned_user_id) can see outbound-only chats
            $assignedUserId = $userData['assigned_user_id'] ?? null;
            
            // Get or create conversation (NO CUSTOMER CREATION on Outbound)
            // Try find customer
            $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
            
            if ($conv && $conv->num_rows() > 0) {
                // ✅ RACE FIX: ALWAYS update last_message with outbound message!
                // This ensures outbound messages (auto-reply or manual) update last_message
                // AFTER inbound message is saved, preventing race condition
                
                // Format last_message based on private status
                if ($isPrivateForLastMessage) {
                    $lastMessageDisplay = 'o- 🔒 _Private Chat_';
                } else {
                    $lastMessageDisplay = 'o- ' . mb_substr($lastMessageText, 0, 50);
                }
                
                $convRow = $conv->row();
                $updateData = [
                    'last_message' => $lastMessageDisplay,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                if ($contactName) $updateData['contact_name'] = $contactName;
                if ($code) $updateData['code'] = $code;
                if ($cust_id !== null) $updateData['cust_id'] = $cust_id;
                // Fill assignment only when missing (do not overwrite an existing cabang assignment)
                $existingAssigned = $convRow->assigned_user_id ?? null;
                if (
                    $assignedUserId !== null && $assignedUserId !== ''
                    && ($existingAssigned === null || $existingAssigned === '')
                ) {
                    $updateData['assigned_user_id'] = $assignedUserId;
                }
                
                $db->update('wa_conversations', $updateData, ['wa_number' => $waNumber]);
            } else {
                // Create new conversation
                
                // Format last_message based on private status
                if ($isPrivateForLastMessage) {
                    $lastMessageDisplay = 'o- 🔒 _Private Chat_';
                } else {
                    $lastMessageDisplay = 'o- ' . mb_substr($lastMessageText, 0, 50);
                }
                
                $convData = [
                    'wa_number' => $waNumber,
                    'status' => 'closed',
                    'last_message' => $lastMessageDisplay,
                    'last_message_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if ($contactName) $convData['contact_name'] = $contactName;
                if ($code) $convData['code'] = $code;
                if ($cust_id !== null) $convData['cust_id'] = $cust_id;
                if ($assignedUserId !== null && $assignedUserId !== '') {
                    $convData['assigned_user_id'] = $assignedUserId;
                }
                
                $db->insert('wa_conversations', $convData);
            }
            // Conversation ID check removed as we don't use ID anymore
            
            
            // Try to get quoted message content if quotedMessageId is provided (SAFE)
            $quotedMessageBody = null;
            if ($quotedMessageId) {
                try {
                    $result = $db->get_where('wa_messages_in', ['wamid' => $quotedMessageId]);
                    if ($result && $result->num_rows() > 0) {
                        $quotedMsg = $result->row();
                        $quotedMessageBody = $quotedMsg->text ?? $quotedMsg->media_caption ?? null;
                    } else {
                        $result = $db->get_where('wa_messages_out', ['wamid' => $quotedMessageId]);
                        if ($result && $result->num_rows() > 0) {
                            $quotedMsg = $result->row();
                            $quotedMessageBody = $quotedMsg->content ?? null;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::write("ERROR fetching quoted message - WAMID: $quotedMessageId | " . $e->getMessage(), 'wa_error', 'Quote');
                }
            }
            
            // Check if message contains sensitive keywords (from Env::WA_PRIVATE_WORDS)
            $templateName = $payload['template']['name'] ?? '';
            $isPrivate = $this->checkPrivateWords($content ?? '', $messageText ?? '', $lastMessageText ?? '', 'db_insert', $templateName);

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
                'sender_code' => $senderCode,
                'status' => 'accepted', // Initial status when API accepted
                'private' => $isPrivate ? 1 : 0, // Set private flag if contains sensitive keywords
                'external_id' => $externalId,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Add quoted message fields only if they exist (safe for backward compatibility)
            if ($quotedMessageId !== null) {
                $messageData['quoted_message_id'] = $quotedMessageId;
            }
            if ($quotedMessageBody !== null) {
                $messageData['quoted_message_body'] = $quotedMessageBody;
            }
            
            // Upsert by external_id to reconcile retries/timeouts
            $msgId = null;
            if (!empty($externalId)) {
                $existingRow = $db->get_where('wa_messages_out', ['external_id' => $externalId])->row();
                if ($existingRow) {
                    $msgId = (int)($existingRow->id ?? 0);
                    $db->update('wa_messages_out', $messageData, ['external_id' => $externalId]);
                }
            }

            if (!$msgId) {
                $msgId = $db->insert('wa_messages_out', $messageData);
            }
            
            if (!$msgId) {
                $dbError = $db->conn()->error ?? 'Unknown';
                \Log::write("INSERT FAILED wa_messages_out | Phone: $waNumber | DB Error: $dbError | Data: " . json_encode($messageData), 'wa_error', 'SaveOutbound');
            } else {
                // ====== WEBSOCKET PUSH (CENTRALIZED) ======
                // Push to WebSocket for real-time UI update
                // This is the SINGLE SOURCE for all outbound messages (autoreply + manual)
                try {
                    // Get conversation data for WebSocket payload
                    $conv = $db->get_where('wa_conversations', ['wa_number' => $waNumber]);
                    $conversation_id = 0;
                    $kode_cabang = $code ?? '00';
                    $convStatus = 'closed';
                    $wsAssignedUserId = $assignedUserId;
                    
                    if ($conv && $conv->num_rows() > 0) {
                        $convRow = $conv->row();
                        $conversation_id = $convRow->id ?? 0;
                        $convStatus = $convRow->status ?? 'closed';
                        $existingAssigned = $convRow->assigned_user_id ?? null;
                        if ($existingAssigned !== null && $existingAssigned !== '') {
                            $wsAssignedUserId = $existingAssigned;
                        }
                        if (!empty($convRow->code)) {
                            $kode_cabang = $convRow->code;
                        }
                    }
                    
                    // Log WebSocket push with quote info

                    $wsPayload = [
                        'type' => 'agent_message_sent',
                        'target_id' => '0', // Broadcast — wa_server filters crew by assignment_user_id
                        'conversation_id' => $conversation_id,
                        'phone' => $waNumber,
                        'contact_name' => $contactName,
                        'kode_cabang' => $kode_cabang,
                        'cust_id' => $cust_id,
                        'status' => $convStatus,
                        'assignment_user_id' => $wsAssignedUserId,
                        'sender_id' => 0, // System/Auto = 0, or can be passed as parameter
                        'message' => [
                            'id' => $msgId, // Use local DB ID
                            'wamid' => $wamid,
                            'text' => $content,
                            'type' => $messageType,
                            'media_url' => $mediaUrl,
                            'sender_code' => $senderCode,
                            'quoted_message_id' => $quotedMessageId, // Reply-to reference
                            'quoted_message_body' => $quotedMessageBody, // Quoted message content
                            'time' => date('Y-m-d H:i:s'),
                            'status' => 'sent'
                        ]
                    ];
                    
                    // Send to WebSocket
                    $wsUrl = WaServer::incomingUrl();
                    $ch = curl_init($wsUrl);
                    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
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
                        \Log::write("WebSocket push failed: " . $wsError->getMessage(), 'wa_error', 'WebSocket');
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
     * Cek apakah teks mengandung WA_PRIVATE_WORDS (via EnvHelper).
     * @param string $content
     * @param string|null $messageText
     * @param string|null $lastMessageText
     * @param string $context Untuk log (last_message / db_insert)
     * @param string $extraText Template name atau teks tambahan
     * @return bool
     */
    private function checkPrivateWords($content, $messageText, $lastMessageText, $context = '', $extraText = '')
    {
        $textsToCheck = array_filter([$content, $messageText, $lastMessageText, $extraText], function ($t) {
            return $t !== null && $t !== '';
        });

        try {
            foreach ($textsToCheck as $t) {
                if (\EnvHelper::textContainsPrivateWord($t)) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            \Log::write("WA_PRIVATE check ERROR: " . $e->getMessage(), 'wa_error', 'Private');
        }

        return false;
    }
    

    
    /**
     * Send Image via WhatsApp
     * 
     * @param string $to Phone number
     * @param string $imageUrl URL to the image
     * @param string $caption Optional caption
     * @return array Response with success status and data
     */
    public function sendImage($to, $imageUrl, $caption = '', $senderCode = null)
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
            $response = $this->sendRequest('/whatsapp/messages', $payload, 'POST', null, null, $senderCode);
            
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
                'code' => null,
                'cust_id' => $customer->id_pelanggan,
            ];
            
            // Prefer cabang of latest sale (multi-cabang customers); else pelanggan.id_cabang
            $idCabang = null;
            $last_sale = $db->query("SELECT id_cabang FROM sale WHERE id_pelanggan = " . (int) $customer->id_pelanggan . " ORDER BY insertTime DESC LIMIT 1")->row();
            if ($last_sale && !empty($last_sale->id_cabang)) {
                $idCabang = $last_sale->id_cabang;
            } elseif (!empty($customer->id_cabang)) {
                $idCabang = $customer->id_cabang;
            }

            if ($idCabang) {
                $result['assigned_user_id'] = $idCabang;
                $cabang = $db->query("SELECT kode_cabang FROM cabang WHERE id_cabang = " . (int) $idCabang)->row();
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
