<?php

namespace App\Helpers\WaDesk;

/** Daily per-number counters used for fair template channel selection. */
final class ChannelDailyStats
{
    public static function wibDate(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    }

    /** Count a template only after the provider has accepted the send. */
    public static function recordTemplateSent($db, int $channelId): void
    {
        if ($channelId <= 0) return;
        $db->query(
            'INSERT INTO wa_channel_daily_stats (channel_id, stat_date, template_sent_count)
             VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE template_sent_count = template_sent_count + 1',
            [$channelId, self::wibDate()]
        );
    }
}
