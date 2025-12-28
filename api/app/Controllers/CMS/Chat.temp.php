    public function resolveCase()
    {
        try {
            // Handle CORS if needed (usually handled by router/middleware, but added for safety)
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit;
            }
            
            $body = json_decode(file_get_contents('php://input'), true);
            $phone = $body['phone'] ?? null;
            $caseVal = $body['case'] ?? null;
            $userId = $_SERVER['HTTP_USER_ID'] ?? $body['user_id'] ?? null;
            
            if (!$phone) {
                $this->error('Phone required');
            }
            if ($caseVal === null) {
                $this->error('Case value required');
            }
            
            $db = $this->db(0);
            
            // Fetch existing cases
            $existing = $db->query("SELECT conv_case FROM wa_conversations WHERE wa_number = '$phone'")->row();
            $caseList = [];
            $modified = false;
            
            if ($existing && isset($existing->conv_case)) {
                $raw = $existing->conv_case;
                if (is_string($raw) && (strpos(trim($raw), '[') === 0)) {
                    $caseList = json_decode($raw, true) ?? [];
                } elseif (is_numeric($raw)) {
                     // Legacy
                     $caseList[] = ['case' => (int)$raw, 'status' => 'open', 'timestamp' => date('Y-m-d H:i:s')];
                }
            }
            
            $targetCase = (int)$caseVal;
            
            // Find and close
            foreach ($caseList as &$item) {
                if (isset($item['case']) && (int)$item['case'] === $targetCase) {
                    // Only update if not already closed
                    if (($item['status'] ?? 'open') !== 'closed') {
                        $item['status'] = 'closed';
                        $item['resolved_at'] = date('Y-m-d H:i:s');
                        $item['resolved_by'] = $userId;
                        $modified = true;
                    }
                }
            }
            
            if ($modified) {
                $jsonCase = json_encode($caseList);
                $db->update('wa_conversations', 
                    ['conv_case' => $jsonCase], 
                    ['wa_number' => $phone]
                );
                
                // Push WebSocket
                $payload = [
                    'type' => 'case_resolved',
                    'phone' => $phone,
                    'case' => $targetCase,
                    'sender_id' => $userId
                ];
                
                \Log::write("Pushing case resolved to WebSocket: " . json_encode($payload), 'cms_ws', 'Chat');
                $this->pushToWebSocket($payload);
                
                $this->success(['case' => $targetCase], 'Case resolved successfully');
            } else {
                // If not found or already closed, just return success to update UI
                $this->success(['case' => $targetCase], 'Case already resolved or not found');
            }
            
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => false, 
                'message' => "Server Error: " . $e->getMessage()
            ]);
            exit;
        }
    }
