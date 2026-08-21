<?php

namespace App\Helpers\CRM;

use App\Config\WaLines;

/**
 * Pengiriman free text YCloud multi-line (admin/cs).
 * CSW tertutup: JANGAN insert wa_messages_out (status=queue).
 */
class FreeTextOutboundDispatcher
{
    /**
     * @param \App\Core\DB $db db(0)
     * @param array|null $options e.g. ['template_cancelled' => true, 'line_key' => 'cs']
     * @return array{ok:bool,channel:string,http_message:string,http_data?:mixed,http_code?:int}
     */
    public static function dispatch(
        $db,
        WhatsAppService $wa,
        string $phone,
        string $messageText,
        ?string $senderCode = null,
        ?array $options = null
    ): array {
        if (!class_exists(CrmChatMergeHelper::class)) {
            require_once __DIR__ . '/CrmChatMergeHelper.php';
        }

        $csw = CrmChatMergeHelper::getCswStatus($db, $phone);
        $requestedLine = is_array($options) ? ($options['line_key'] ?? null) : null;
        $lineKey = CrmChatMergeHelper::resolveReplyLine($csw, $requestedLine ?? 'auto');

        if ($lineKey === null) {
            return self::finalizeDispatchResult(
                [
                    'ok' => false,
                    'channel' => 'csw_closed',
                    'http_message' => 'Customer Service Window (CSW) expired for all lines. Cannot send free text message.',
                    'http_data' => [
                        'csw_expired' => true,
                        'line_csw' => $csw['line_csw'] ?? [],
                        'phone_sent' => $phone,
                        'suggestion' => 'chat ke Laundry Bot dulu ya',
                    ],
                    'http_code' => 400,
                ],
                $options,
                $csw
            );
        }

        $lineRow = $csw['line_csw'][$lineKey] ?? [];
        $lastIn = $lineRow['last_in_at'] ?? null;
        $hoursElapsed = $lastIn ? $wa->diffHours(date('Y-m-d H:i:s'), $lastIn) : 99999;

        $result = $wa->sendFreeText($phone, $messageText, null, $senderCode, null, $lineKey);
        if ($result['success']) {
            return self::finalizeDispatchResult(
                [
                    'ok' => true,
                    'channel' => $lineKey,
                    'http_message' => 'WhatsApp free text sent successfully',
                    'http_data' => [
                        'message_id' => $result['data']['id'] ?? null,
                        'status' => $result['data']['status'] ?? 'sent',
                        'mode' => 'free_text',
                        'to' => $phone,
                        'line_key' => $lineKey,
                        'csw_status' => [
                            'within_csw' => true,
                            'line_key' => $lineKey,
                            'hours_elapsed' => round($hoursElapsed, 2),
                        ],
                    ],
                ],
                $options,
                $csw
            );
        }

        if (self::isYCloudFreeTextCswError($result)) {
            foreach (WaLines::all() as $altKey => $_line) {
                if ($altKey === $lineKey) {
                    continue;
                }
                if (empty($csw['line_csw'][$altKey]['open'])) {
                    continue;
                }
                $retry = $wa->sendFreeText($phone, $messageText, null, $senderCode, null, $altKey);
                if ($retry['success']) {
                    return self::finalizeDispatchResult(
                        [
                            'ok' => true,
                            'channel' => $altKey,
                            'http_message' => 'WhatsApp free text sent successfully',
                            'http_data' => [
                                'message_id' => $retry['data']['id'] ?? null,
                                'status' => $retry['data']['status'] ?? 'sent',
                                'mode' => 'free_text',
                                'to' => $phone,
                                'line_key' => $altKey,
                                'note' => 'Sent via alternate line after CSW reject on first line',
                            ],
                        ],
                        $options,
                        $csw
                    );
                }
            }

            return self::finalizeDispatchResult(
                [
                    'ok' => false,
                    'channel' => 'csw_closed',
                    'http_message' => 'Customer Service Window (CSW) expired. Cannot send free text message.',
                    'http_data' => [
                        'csw_expired' => true,
                        'line_csw' => $csw['line_csw'] ?? [],
                        'phone_sent' => $phone,
                    ],
                    'http_code' => 400,
                ],
                $options,
                $csw
            );
        }

        $errorMsg = self::extractYCloudFreeTextError($result);
        if (class_exists('\\Log')) {
            \Log::write('Failed to send free text: ' . json_encode($result), 'whatsapp', 'api');
        }

        return self::finalizeDispatchResult(
            [
                'ok' => false,
                'channel' => 'error',
                'http_message' => 'Failed to send WhatsApp message: ' . $errorMsg,
                'http_data' => $result,
                'http_code' => 500,
            ],
            $options,
            $csw
        );
    }

    private static function finalizeDispatchResult(array $res, ?array $options, array $csw): array
    {
        if (empty($options['template_cancelled'])) {
            return $res;
        }
        if (!empty($res['ok'])) {
            $res['http_message'] = 'WhatsApp free text sent successfully (template cancelled)';
            $data = $res['http_data'] ?? [];
            if (is_array($data)) {
                $data['template_cancelled'] = true;
                $data['line_csw'] = $csw['line_csw'] ?? [];
            }
            $res['http_data'] = $data;
        } elseif (empty($res['ok']) && in_array(($res['channel'] ?? ''), ['csw_closed'], true)) {
            $data = $res['http_data'] ?? [];
            if (is_array($data)) {
                $data['template_cancelled'] = true;
            }
            $res['http_data'] = $data;
        }

        return $res;
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

        return $msgStr !== '' && (stripos($msgStr, 'outside') !== false || stripos($msgStr, '24 hour') !== false || stripos($msgStr, '24-hour') !== false);
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
}
