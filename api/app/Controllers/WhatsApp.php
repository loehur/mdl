<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Helpers\FonnteService;
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
        
        // Business Logic: Free text — CSW yCloud (wa_conversations); jika tertutup / API tolak CSW, coba CSW Fonnte (wa_fonnte_csw) + Fonnte API
        if ($messageMode === 'free') {
            if (empty($body['message'])) {
                $this->error('Message content is required for free text mode', 400);
            }

            $messageText = $body['message'];
            $decodedMsg = json_decode($body['message'], true);
            if ($decodedMsg && isset($decodedMsg['text'])) {
                $messageText = $decodedMsg['text'];
            }

            $fonnteLastIn = $this->getFonnteCswLastInAt($db, $phone1);
            $fonnteHoursElapsed = 99999;
            if ($fonnteLastIn) {
                $fonnteHoursElapsed = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $fonnteLastIn);
            }
            $isFonnteCswOpen = $this->whatsappService->isWithinCsw($fonnteLastIn);

            $bothCswClosedData = [
                'csw_expired' => true,
                'ycloud_open' => false,
                'fonnte_open' => false,
                'hours_elapsed_ycloud' => round($hoursElapsed, 2),
                'hours_elapsed_fonnte' => round($fonnteHoursElapsed, 2),
                'last_in_at_ycloud' => $last_in_at ?? 'No previous message',
                'last_in_at_fonnte' => $fonnteLastIn ?? 'No previous message',
                'phone_sent' => $phone,
                'suggestion' => 'chat ke Laundry Bot dulu ya',
            ];

            if ($isWithinCsw) {
                $result = $this->whatsappService->sendFreeText($phone, $messageText, null, $senderCode);
                if ($result['success']) {
                    $this->success([
                        'message_id' => $result['data']['id'] ?? null,
                        'status' => $result['data']['status'] ?? 'sent',
                        'mode' => 'free_text',
                        'to' => $phone,
                        'csw_status' => [
                            'within_csw' => true,
                            'hours_elapsed' => round($hoursElapsed, 2),
                        ],
                    ], 'WhatsApp free text sent successfully');
                }
                if ($this->isYCloudFreeTextCswError($result)) {
                    if ($isFonnteCswOpen) {
                        $this->sendFreeTextViaFonnte($phone, $messageText, $isWithinCsw, $hoursElapsed, $fonnteHoursElapsed, $fonnteLastIn);
                    }
                    // Pesan sudah diantrekan di WhatsAppService::sendRequest (status queue) untuk resend saat CSW terbuka
                    $bothCswClosedData['free_text_queued_for_resend'] = true;
                    $this->error(
                        'Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text message.',
                        400,
                        $bothCswClosedData
                    );
                }
                $errorMsg = $this->extractYCloudFreeTextError($result);
                \Log::write('Failed to send free text: ' . json_encode($result), 'whatsapp', 'api');
                $this->error('Failed to send WhatsApp message: ' . $errorMsg, 500, $result);
            }

            if ($isFonnteCswOpen) {
                $this->sendFreeTextViaFonnte($phone, $messageText, $isWithinCsw, $hoursElapsed, $fonnteHoursElapsed, $fonnteLastIn);
            }

            // CSW tertutup di DB untuk yCloud & Fonnte — belum kirim ke API; antrekan untuk cron (24 jam)
            $this->whatsappService->queueFreeTextForCswRetry(
                $phone,
                $messageText,
                null,
                $senderCode,
                'CSW closed — yCloud & Fonnte (DB); message not sent to API'
            );
            $bothCswClosedData['free_text_queued_for_resend'] = true;

            $this->error(
                'Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text message.',
                400,
                $bothCswClosedData
            );
        }
        
        // Business Logic: Template mode — jika CSW yCloud ATAU CSW Fonnte terbuka: batalkan template, kirim free text saja
        if ($messageMode === 'template') {
            $fonnteLastInTpl = $this->getFonnteCswLastInAt($db, $phone1);
            $fonnteHoursElapsedTpl = 99999;
            if ($fonnteLastInTpl) {
                $fonnteHoursElapsedTpl = $this->whatsappService->diffHours(date('Y-m-d H:i:s'), $fonnteLastInTpl);
            }
            $isFonnteCswOpenTpl = $this->whatsappService->isWithinCsw($fonnteLastInTpl);

            $eitherCswOpen = $isWithinCsw || $isFonnteCswOpenTpl;

            if ($eitherCswOpen) {
                if (empty($body['message'])) {
                    $this->error(
                        'Saat CSW yCloud atau Fonnte terbuka, pengiriman template dibatalkan — field message wajib diisi untuk free text.',
                        400,
                        [
                            'csw_ycloud_open' => $isWithinCsw,
                            'csw_fonnte_open' => $isFonnteCswOpenTpl,
                            'template_cancelled' => true,
                        ]
                    );
                }

                $freeTextMsg = $body['message'];
                $decodedFree = json_decode($body['message'], true);
                if ($decodedFree && isset($decodedFree['text'])) {
                    $freeTextMsg = $decodedFree['text'];
                }

                $bothCswClosedTpl = [
                    'csw_expired' => true,
                    'ycloud_open' => false,
                    'fonnte_open' => false,
                    'hours_elapsed_ycloud' => round($hoursElapsed, 2),
                    'hours_elapsed_fonnte' => round($fonnteHoursElapsedTpl, 2),
                    'last_in_at_ycloud' => $last_in_at ?? 'No previous message',
                    'last_in_at_fonnte' => $fonnteLastInTpl ?? 'No previous message',
                    'phone_sent' => $phone,
                    'template_cancelled' => true,
                ];

                if ($isWithinCsw) {
                    $result = $this->whatsappService->sendFreeText($phone, $freeTextMsg, null, $senderCode);
                    if ($result['success']) {
                        $this->success([
                            'message_id' => $result['data']['id'] ?? null,
                            'status' => $result['data']['status'] ?? 'sent',
                            'mode' => 'free_text',
                            'to' => $phone,
                            'csw_status' => [
                                'ycloud_within_csw' => true,
                                'fonnte_within_csw' => $isFonnteCswOpenTpl,
                                'hours_elapsed_ycloud' => round($hoursElapsed, 2),
                                'hours_elapsed_fonnte' => round($fonnteHoursElapsedTpl, 2),
                                'note' => 'Template dibatalkan; terkirim sebagai free text (CSW yCloud dan/atau Fonnte terbuka)',
                            ],
                        ], 'WhatsApp free text sent successfully (template cancelled)');
                    }
                    if ($this->isYCloudFreeTextCswError($result)) {
                        if ($isFonnteCswOpenTpl) {
                            $this->sendFreeTextViaFonnte(
                                $phone,
                                $freeTextMsg,
                                $isWithinCsw,
                                $hoursElapsed,
                                $fonnteHoursElapsedTpl,
                                $fonnteLastInTpl
                            );
                        }
                        $bothCswClosedTpl['free_text_queued_for_resend'] = true;
                        $this->error(
                            'Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text message.',
                            400,
                            $bothCswClosedTpl
                        );
                    }
                    $errorMsg = $this->extractYCloudFreeTextError($result);
                    \Log::write('Template→free text failed: ' . json_encode($result), 'whatsapp', 'api');
                    $this->error('Template dibatalkan; free text gagal: ' . $errorMsg, 500, $result);
                }

                if ($isFonnteCswOpenTpl) {
                    $this->sendFreeTextViaFonnte(
                        $phone,
                        $freeTextMsg,
                        $isWithinCsw,
                        $hoursElapsed,
                        $fonnteHoursElapsedTpl,
                        $fonnteLastInTpl
                    );
                }
            }

            // CSW tertutup di yCloud DAN Fonnte — kirim template (yCloud)
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
                    'ycloud_within_csw' => false,
                    'fonnte_within_csw' => false,
                    'hours_elapsed_ycloud' => round($hoursElapsed, 2),
                    'hours_elapsed_fonnte' => round($fonnteHoursElapsedTpl, 2),
                    'note' => 'Template via yCloud: CSW tertutup di yCloud dan Fonnte (jendela 24 jam)',
                ],
            ], 'WhatsApp template sent successfully');
        }
        
        $this->error('Invalid message_mode. Use "free" or "template"', 400);
    }

    /**
     * last_in_at dari wa_fonnte_csw (format phone: +628… atau 628…).
     */
    private function getFonnteCswLastInAt($db, string $phone628): ?string
    {
        try {
            $phonePlus = '+' . $phone628;
            $q = $db->query(
                "SELECT last_in_at FROM wa_fonnte_csw WHERE phone IN (?, ?) ORDER BY id DESC LIMIT 1",
                [$phonePlus, $phone628]
            );
            if ($q->num_rows() > 0) {
                return (string) $q->row()->last_in_at;
            }
        } catch (\Throwable $e) {
            \Log::write('getFonnteCswLastInAt: ' . $e->getMessage(), 'whatsapp', 'api');
        }

        return null;
    }

    private function isYCloudFreeTextCswError(array $result): bool
    {
        $errorData = $result['data']['error'] ?? null;
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

    private function extractYCloudFreeTextError(array $result): string
    {
        $errorData = $result['data']['error'] ?? null;
        if (is_array($errorData)) {
            return (string) ($errorData['message'] ?? $errorData['code'] ?? json_encode($errorData));
        }
        if (is_string($errorData)) {
            return $errorData;
        }

        return (string) ($result['error'] ?? 'Failed to send');
    }

    /**
     * Kirim teks bebas via Fonnte; success()/error() menghentikan eksekusi.
     */
    private function sendFreeTextViaFonnte(
        string $phone,
        string $messageText,
        bool $isWithinCswYcloud,
        float $hoursElapsedYcloud,
        float $hoursElapsedFonnte,
        ?string $fonnteLastIn
    ): void {
        $fonnte = new FonnteService();
        $fonnteResult = $fonnte->sendMessage($phone, $messageText);
        if ($fonnteResult['success']) {
            $note = $isWithinCswYcloud
                ? 'Sent via Fonnte after yCloud API rejected CSW'
                : 'Sent via Fonnte (yCloud CSW closed; Fonnte CSW open)';
            $this->success([
                'message_id' => $fonnteResult['data']['id'][0] ?? ($fonnteResult['data']['requestid'] ?? null),
                'status' => $fonnteResult['data']['process'] ?? 'sent',
                'mode' => 'fonnte',
                'to' => $phone,
                'csw_status' => [
                    'ycloud_within_csw' => $isWithinCswYcloud,
                    'fonnte_within_csw' => true,
                    'hours_elapsed_ycloud' => round($hoursElapsedYcloud, 2),
                    'hours_elapsed_fonnte' => round($hoursElapsedFonnte, 2),
                    'last_in_at_fonnte' => $fonnteLastIn,
                    'note' => $note,
                ],
            ], 'WhatsApp free text sent via Fonnte');
        }
        \Log::write('Fonnte free send failed: ' . json_encode($fonnteResult), 'whatsapp', 'api');
        $this->error(
            'Failed to send WhatsApp message via Fonnte: ' . ($fonnteResult['error'] ?? 'unknown'),
            500,
            ['fonnte' => $fonnteResult]
        );
    }
}
