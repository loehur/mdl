/**
 * Send Image via WhatsApp
 * Add this method to: api/app/Helpers/WhatsAppService.php
 * 
 * @param string $to Phone number
 * @param string $imageUrl URL to the image
 * @param string $caption Optional caption
 * @return array Response with success status and data
 */
public function sendImage($to, $imageUrl, $caption = '')
{
    $payload = [
        'to' => $to,
        'type' => 'image',
        'image' => [
            'link' => $imageUrl
        ]
    ];
    
    if ($caption) {
        $payload['image']['caption'] = $caption;
    }
    
    $response = $this->sendRequest('/messages', $payload);
    
    // Parse response
    if ($response['httpCode'] == 200 || $response['httpCode'] == 201) {
        $data = json_decode($response['body'], true);
        
        if (isset($data['id']) || isset($data['message_id'])) {
            // Also save to outbound log
            $responseData = [
                'id' => $data['id'] ?? $data['message_id'] ?? null,
                'wamid' => $data['wamid'] ?? null,
                'status' => $data['status'] ?? 'sent'
            ];
            
            $this->saveOutboundMessage($payload, $responseData);
            
            return [
                'success' => true,
                'data' => $responseData
            ];
        }
    }
    
    // Error
    return [
        'success' => false,
        'error' => $response['error'] ?? 'Failed to send image',
        'httpCode' => $response['httpCode']
    ];
}
