<?php
namespace App\Controllers\CMS;

use App\Core\Controller;

class Chat extends Controller
{
    // ... existing methods ...
    
    /**
     * Send Image via WhatsApp
     * Frontend POST: FormData with image file, conversation_id, user_id, caption (optional)
     */
    public function sendImage()
    {
        header('Content-Type: application/json');
        
        try {
            // Validate file upload
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['status' => false, 'message' => 'No image uploaded']);
                return;
            }
            
            $conversationId = $_POST['conversation_id'] ?? null;
            $userId = $_POST['user_id'] ?? null;
            $caption = $_POST['caption'] ?? '';
            
            if (!$conversationId) {
                echo json_encode(['status' => false, 'message' => 'Missing conversation_id']);
                return;
            }
            
            $db = $this->db(0);
            
            // Get conversation details
            $conversation = $db->get_where('wa_conversations', ['id' => $conversationId])->row();
            if (!$conversation) {
                echo json_encode(['status' => false, 'message' => 'Conversation not found']);
                return;
            }
            
            $waNumber = $conversation->wa_number;
            
            // Upload image to server
            $uploaded = $this->uploadImage($_FILES['image']);
            if (!$uploaded['success']) {
                echo json_encode(['status' => false, 'message' => $uploaded['error']]);
                return;
            }
            
            $mediaUrl = $uploaded['url'];
            $mediaPath = $uploaded['path'];
            
            // Send via WhatsApp Service
            if (!class_exists('\\App\\Helpers\\WhatsAppService')) {
                require_once __DIR__ . '/../../Helpers/WhatsAppService.php';
            }
            
            $waService = new \App\Helpers\WhatsAppService();
            $result = $waService->sendImage($waNumber, $mediaUrl, $caption);
            
            if ($result['success']) {
                // Save to database
                $messageData = [
                    'conversation_id' => $conversationId,
                    'phone' => $waNumber,
                    'type' => 'image',
                    'text' => $caption,
                    'media_url' => $mediaUrl,
                    'media_mime_type' => $_FILES['image']['type'],
                    'message_id' => $result['data']['id'] ?? null,
                    'wamid' => $result['data']['wamid'] ?? null,
                    'sender' => 'me',
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s')
                ];
                
                $msgId = $db->insert('wa_messages_out', $messageData);
                
                // Update conversation
                $db->update('wa_conversations', [
                    'last_out_at' => date('Y-m-d H:i:s'),
                    'last_message' => '📷 Image'
                ], ['id' => $conversationId]);
                
                // Broadcast via WebSocket
                $this->broadcastToWebSocket([
                    'type' => 'agent_message_sent',
                    'conversation_id' => $conversationId,
                    'message' => [
                        'id' => $msgId,
                        'wamid' => $result['data']['wamid'] ?? null,
                        'text' => $caption,
                        'type' => 'image',
                        'media_url' => $mediaUrl,
                        'sender' => 'me',
                        'time' => date('Y-m-d H:i:s'),
                        'status' => 'sent'
                    ],
                    'target_id' => $userId
                ]);
                
                echo json_encode([
                    'status' => true,
                    'message' => 'Image sent successfully',
                    'data' => [
                        'local_id' => $msgId,
                        'media_url' => $mediaUrl,
                        'wamid' => $result['data']['wamid'] ?? null
                    ]
                ]);
            } else {
                echo json_encode([
                    'status' => false,
                    'message' => $result['error'] ?? 'Failed to send image'
                ]);
            }
            
        } catch (\Exception $e) {
            \Log::write("sendImage ERROR: " . $e->getMessage(), 'cms_error', 'Chat');
            echo json_encode([
                'status' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Upload image to server
     */
    private function uploadImage($file)
    {
        try {
            // Validate size (max 5MB)
            if ($file['size'] > 5 * 1024 * 1024) {
                return ['success' => false, 'error' => 'File too large (max 5MB)'];
            }
            
            // Validate type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowedTypes)) {
                return ['success' => false, 'error' => 'Invalid file type'];
            }
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../../../uploads/wa_media/' . date('Y/m/');
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_') . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }
            
            // Generate URL
            $url = 'https://api.nalju.com/uploads/wa_media/' . date('Y/m/') . $filename;
            
            return [
                'success' => true,
                'path' => $uploadPath,
                'url' => $url
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Broadcast message to WebSocket server
     */
    private function broadcastToWebSocket($data)
    {
        $url = 'https://waserver.nalju.com/broadcast';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        
        curl_exec($ch);
        curl_close($ch);
    }
}
