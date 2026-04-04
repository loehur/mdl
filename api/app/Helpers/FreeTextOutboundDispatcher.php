<?php

namespace App\Helpers;

/**
 * Alur pengiriman free text: CSW yCloud → (fallback) Fonnte → queue wa_messages_out.
 * Satu sumber kebenaran untuk /WhatsApp/send (mode free & template yang dibatalkan jadi free text) dan pemanggilan internal (mis. webhook IAK).
 */
class FreeTextOutboundDispatcher
{
    /**
     * @param \App\Core\DB $db db(0)
     * @param array|null $options e.g. ['template_cancelled' => true] untuk menyesuaikan pesan/data HTTP (alur template→free)
     * @return array{
     *   ok: bool,
     *   channel: 'ycloud'|'fonnte'|'queued'|'error',
     *   http_message: string,
     *   http_data?: mixed,
     *   http_code?: int
     * }
     */
    public static function dispatch(
        $db,
        WhatsAppService $wa,
        string $phone,
        string $messageText,
        ?string $senderCode = null,
        ?array $options = null
    ): array {
        $ph = preg_replace('/[^0-9]/', '', $phone);
        if (substr($ph, 0, 2) === '08') {
            $ph = '628' . substr($ph, 2);
        } elseif (substr($ph, 0, 1) === '8' && substr($ph, 0, 2) !== '62') {
            $ph = '62' . $ph;
        }
        $phone1 = $ph;
        $phone2 = '+' . $ph;

        $last_in_at = null;
        try {
            $qCust = $db->query(
                'SELECT last_in_at FROM wa_conversations WHERE wa_number IN (?, ?) LIMIT 1',
                [$phone1, $phone2]
            );
            if ($qCust->num_rows() > 0) {
                $last_in_at = $qCust->row()->last_in_at;
            }
        } catch (\Throwable $e) {
            $last_in_at = null;
        }

        $isWithinCsw = $wa->isWithinCsw($last_in_at);
        $hoursElapsed = 99999;
        if ($last_in_at) {
            $hoursElapsed = $wa->diffHours(date('Y-m-d H:i:s'), $last_in_at);
        }

        $fonnteLastIn = self::getFonnteCswLastInAt($db, $phone1);
        $fonnteHoursElapsed = 99999;
        if ($fonnteLastIn) {
            $fonnteHoursElapsed = $wa->diffHours(date('Y-m-d H:i:s'), $fonnteLastIn);
        }
        $isFonnteCswOpen = $wa->isWithinCsw($fonnteLastIn);

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
            $result = $wa->sendFreeText($phone, $messageText, null, $senderCode);
            if ($result['success']) {
                return self::finalizeDispatchResult(
                    [
                        'ok' => true,
                        'channel' => 'ycloud',
                        'http_message' => 'WhatsApp free text sent successfully',
                        'http_data' => [
                            'message_id' => $result['data']['id'] ?? null,
                            'status' => $result['data']['status'] ?? 'sent',
                            'mode' => 'free_text',
                            'to' => $phone,
                            'csw_status' => [
                                'within_csw' => true,
                                'hours_elapsed' => round($hoursElapsed, 2),
                            ],
                        ],
                    ],
                    $options,
                    $isFonnteCswOpen,
                    $hoursElapsed,
                    $fonnteHoursElapsed
                );
            }
            if (self::isYCloudFreeTextCswError($result)) {
                if ($isFonnteCswOpen) {
                    return self::finalizeDispatchResult(
                        self::sendViaFonnte(
                            $phone,
                            $messageText,
                            true,
                            $hoursElapsed,
                            $fonnteHoursElapsed,
                            $fonnteLastIn
                        ),
                        $options,
                        $isFonnteCswOpen,
                        $hoursElapsed,
                        $fonnteHoursElapsed
                    );
                }
                $bothCswClosedData['free_text_queued_for_resend'] = true;

                return self::finalizeDispatchResult(
                    [
                        'ok' => false,
                        'channel' => 'queued',
                        'http_message' => 'Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text message.',
                        'http_data' => $bothCswClosedData,
                        'http_code' => 400,
                    ],
                    $options,
                    $isFonnteCswOpen,
                    $hoursElapsed,
                    $fonnteHoursElapsed
                );
            }
            $errorMsg = self::extractYCloudFreeTextError($result);
            \Log::write('Failed to send free text: ' . json_encode($result), 'whatsapp', 'api');

            return self::finalizeDispatchResult(
                [
                    'ok' => false,
                    'channel' => 'error',
                    'http_message' => 'Failed to send WhatsApp message: ' . $errorMsg,
                    'http_data' => $result,
                    'http_code' => 500,
                ],
                $options,
                $isFonnteCswOpen,
                $hoursElapsed,
                $fonnteHoursElapsed
            );
        }

        if ($isFonnteCswOpen) {
            return self::finalizeDispatchResult(
                self::sendViaFonnte(
                    $phone,
                    $messageText,
                    false,
                    $hoursElapsed,
                    $fonnteHoursElapsed,
                    $fonnteLastIn
                ),
                $options,
                $isFonnteCswOpen,
                $hoursElapsed,
                $fonnteHoursElapsed
            );
        }

        $wa->queueFreeTextForCswRetry(
            $phone,
            $messageText,
            null,
            $senderCode,
            'CSW closed — yCloud & Fonnte (DB); message not sent to API'
        );
        $bothCswClosedData['free_text_queued_for_resend'] = true;

        return self::finalizeDispatchResult(
            [
                'ok' => false,
                'channel' => 'queued',
                'http_message' => 'Customer Service Window (CSW) expired for yCloud and Fonnte. Cannot send free text message.',
                'http_data' => $bothCswClosedData,
                'http_code' => 400,
            ],
            $options,
            $isFonnteCswOpen,
            $hoursElapsed,
            $fonnteHoursElapsed
        );
    }

    /**
     * Sesuaikan respons HTTP bila pengiriman dari konteks template yang dibatalkan (mode free menggantikan template).
     */
    private static function finalizeDispatchResult(
        array $res,
        ?array $options,
        bool $isFonnteCswOpen,
        float $hoursElapsedYcloud,
        float $hoursElapsedFonnte
    ): array {
        if (empty($options['template_cancelled'])) {
            return $res;
        }
        if (!empty($res['ok']) && ($res['channel'] ?? '') === 'ycloud') {
            $res['http_message'] = 'WhatsApp free text sent successfully (template cancelled)';
            $data = $res['http_data'] ?? [];
            $data['csw_status'] = [
                'ycloud_within_csw' => true,
                'fonnte_within_csw' => $isFonnteCswOpen,
                'hours_elapsed_ycloud' => round($hoursElapsedYcloud, 2),
                'hours_elapsed_fonnte' => round($hoursElapsedFonnte, 2),
                'note' => 'Template dibatalkan; terkirim sebagai free text (CSW yCloud dan/atau Fonnte terbuka)',
            ];
            $res['http_data'] = $data;

            return $res;
        }
        if (!empty($res['ok']) && ($res['channel'] ?? '') === 'fonnte') {
            $data = $res['http_data'] ?? [];
            $data['template_cancelled'] = true;
            $res['http_data'] = $data;

            return $res;
        }
        if (empty($res['ok']) && ($res['channel'] ?? '') === 'queued') {
            $data = $res['http_data'] ?? [];
            if (is_array($data)) {
                $data['template_cancelled'] = true;
            }
            $res['http_data'] = $data;

            return $res;
        }
        if (empty($res['ok']) && ($res['channel'] ?? '') === 'error' && (int) ($res['http_code'] ?? 0) === 500) {
            $msg = (string) ($res['http_message'] ?? '');
            if (strpos($msg, 'Failed to send WhatsApp message: ') === 0) {
                $rest = substr($msg, strlen('Failed to send WhatsApp message: '));
                $res['http_message'] = 'Template dibatalkan; free text gagal: ' . $rest;
            } elseif (strpos($msg, 'Failed to send WhatsApp message via Fonnte: ') === 0) {
                $rest = substr($msg, strlen('Failed to send WhatsApp message via Fonnte: '));
                $res['http_message'] = 'Template dibatalkan; free text gagal (Fonnte): ' . $rest;
            }

            return $res;
        }

        return $res;
    }

    /**
     * last_in_at dari wa_fonnte_csw (format phone: +628… atau 628…).
     */
    private static function getFonnteCswLastInAt($db, string $phone628): ?string
    {
        try {
            $phonePlus = '+' . $phone628;
            $q = $db->query(
                'SELECT last_in_at FROM wa_fonnte_csw WHERE phone IN (?, ?) ORDER BY id DESC LIMIT 1',
                [$phonePlus, $phone628]
            );
            if ($q->num_rows() > 0) {
                return (string) $q->row()->last_in_at;
            }
        } catch (\Throwable $e) {
            \Log::write('FreeTextOutboundDispatcher getFonnteCswLastInAt: ' . $e->getMessage(), 'whatsapp', 'api');
        }

        return null;
    }

    private static function isYCloudFreeTextCswError(array $result): bool
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

    private static function extractYCloudFreeTextError(array $result): string
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
     * @return array<string, mixed>
     */
    private static function sendViaFonnte(
        string $phone,
        string $messageText,
        bool $isWithinCswYcloud,
        float $hoursElapsedYcloud,
        float $hoursElapsedFonnte,
        ?string $fonnteLastIn
    ): array {
        $fonnte = new FonnteService();
        $fonnteResult = $fonnte->sendMessage($phone, $messageText);
        if ($fonnteResult['success']) {
            $note = $isWithinCswYcloud
                ? 'Sent via Fonnte after yCloud API rejected CSW'
                : 'Sent via Fonnte (yCloud CSW closed; Fonnte CSW open)';

            return [
                'ok' => true,
                'channel' => 'fonnte',
                'http_message' => 'WhatsApp free text sent via Fonnte',
                'http_data' => [
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
                ],
            ];
        }
        \Log::write('Fonnte free send failed: ' . json_encode($fonnteResult), 'whatsapp', 'api');

        return [
            'ok' => false,
            'channel' => 'error',
            'http_message' => 'Failed to send WhatsApp message via Fonnte: ' . ($fonnteResult['error'] ?? 'unknown'),
            'http_data' => ['fonnte' => $fonnteResult],
            'http_code' => 500,
        ];
    }
}
