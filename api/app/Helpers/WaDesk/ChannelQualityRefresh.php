<?php

namespace App\Helpers\WaDesk;

/** Refresh an UNKNOWN Meta phone's quality after a successful template send. */
final class ChannelQualityRefresh
{
    public static function scheduleAfterUnknownTemplateSend($db, array $channel): void
    {
        if (($channel['provider'] ?? '') !== 'meta') return;
        if (strtoupper(trim((string) ($channel['meta_quality_rating'] ?? ''))) !== 'UNKNOWN') return;

        $channelId = (int) ($channel['id'] ?? 0);
        $phoneNumberId = trim((string) ($channel['meta_phone_number_id'] ?? $channel['device_id'] ?? ''));
        if ($channelId <= 0 || $phoneNumberId === '') return;

        register_shutdown_function(static function () use ($db, $channelId, $phoneNumberId): void {
            // Under PHP-FPM this flushes the successful send response first, so
            // the browser never waits for the optional quality refresh.
            ignore_user_abort(true);
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            try {
                $result = (new Meta())->getPhoneQuality($phoneNumberId);
                if (!$result['success']) {
                    \Log::write('Meta quality refresh FAILED: channel=' . $channelId . ' phone_id=' . $phoneNumberId . ' http=' . (int) $result['http_code'] . ' err=' . $result['error'], 'wadesk', 'quality_refresh');
                    return;
                }
                $quality = strtoupper(trim((string) ($result['data']['quality_rating'] ?? '')));
                if (!in_array($quality, ['GREEN', 'YELLOW', 'RED', 'UNKNOWN', 'NA'], true)) {
                    \Log::write('Meta quality refresh SKIP: channel=' . $channelId . ' phone_id=' . $phoneNumberId . ' value=' . ($quality ?: '-'), 'wadesk', 'quality_refresh');
                    return;
                }
                $db->query('UPDATE wa_channels SET meta_quality_rating = ? WHERE id = ? AND provider = \'meta\'', [$quality, $channelId]);
                \Log::write('Meta quality refresh OK: channel=' . $channelId . ' phone_id=' . $phoneNumberId . ' quality=' . $quality . ' affected=' . (int) $db->affected_rows(), 'wadesk', 'quality_refresh');
            } catch (\Throwable $e) {
                \Log::write('Meta quality refresh EXCEPTION: channel=' . $channelId . ' phone_id=' . $phoneNumberId . ' err=' . $e->getMessage(), 'wadesk', 'quality_refresh');
            }
        });
    }
}
