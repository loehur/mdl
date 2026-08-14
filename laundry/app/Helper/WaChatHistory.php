<?php

/**
 * Sumber tunggal riwayat chat WA (in + out) untuk Laundry UI.
 * Gabung yCloud (wa_messages_*) + Fonnte (wa_fonnte_messages_*) sesuai timeline.
 * Dipakai Delivery customer_detail, Estimasi notifikasi permintaan, dll.
 */
class WaChatHistory
{
    /** Default per sumber (yCloud / Fonnte). */
    public const LIMIT_PER_SOURCE = 30;

    /**
     * Ambil pesan gabungan (ASC): N terbaru yCloud + N terbaru Fonnte, digabung timeline.
     * Exclude outgoing yCloud private.
     *
     * @param object $db Instance DB CRM (biasanya db(100))
     * @param int $limitPerSource Jumlah pesan terbaru per sumber (default 30)
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string}>
     */
    public function fetchMessages($db, string $phone, int $limitPerSource = self::LIMIT_PER_SOURCE): array
    {
        $digits = $this->matchDigits($phone);
        if ($digits === '') {
            return [];
        }

        $limitPerSource = max(1, min(100, (int) $limitPerSource));

        $ycloud = $this->fetchYcloudMessages($db, $digits, $limitPerSource);
        $fonnte = $this->fetchFonnteMessages($db, $digits, $limitPerSource);

        $merged = array_merge($ycloud, $fonnte);
        usort($merged, static function (array $a, array $b): int {
            $ta = (string) ($a['time'] ?? '');
            $tb = (string) ($b['time'] ?? '');
            if ($ta === $tb) {
                // Stabil: ycloud dulu lalu fonnte; id sebagai tie-break
                $sa = (string) ($a['source'] ?? '');
                $sb = (string) ($b['source'] ?? '');
                if ($sa !== $sb) {
                    return $sa <=> $sb;
                }
                return ((int) ($a['_id'] ?? 0)) <=> ((int) ($b['_id'] ?? 0));
            }

            return $ta <=> $tb;
        });

        foreach ($merged as &$row) {
            unset($row['_id']);
        }
        unset($row);

        return $merged;
    }

    /**
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string,_id:int}>
     */
    private function fetchYcloudMessages($db, string $digits, int $limit): array
    {
        $like = $this->phoneLikeSql($db, $digits, 'phone');
        $limit = (int) $limit;

        try {
            $messages = $db->query_array(
                "SELECT * FROM (
                    SELECT * FROM (
                       (SELECT
                           id,
                           text,
                           type,
                           'customer' AS sender,
                           created_at AS time,
                           status,
                           media_id,
                           media_url
                        FROM wa_messages_in
                        WHERE {$like})
                       UNION ALL
                       (SELECT
                           id,
                           COALESCE(content, '') AS text,
                           type,
                           'me' AS sender,
                           created_at AS time,
                           status,
                           NULL AS media_id,
                           media_url
                        FROM wa_messages_out
                        WHERE {$like}
                          AND COALESCE(`private`, 0) = 0)
                    ) AS combined_msgs
                    ORDER BY time DESC
                    LIMIT $limit
                 ) AS latest_msgs
                 ORDER BY time ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return $this->normalizeRows(is_array($messages) ? $messages : [], 'ycloud');
    }

    /**
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string,_id:int}>
     */
    private function fetchFonnteMessages($db, string $digits, int $limit): array
    {
        $like = $this->phoneLikeSql($db, $digits, 'phone');
        $limit = (int) $limit;

        try {
            $messages = $db->query_array(
                "SELECT * FROM (
                    SELECT * FROM (
                       (SELECT
                           id,
                           text,
                           type,
                           'customer' AS sender,
                           created_at AS time,
                           NULL AS status,
                           NULL AS media_id,
                           media_url
                        FROM wa_fonnte_messages_in
                        WHERE {$like})
                       UNION ALL
                       (SELECT
                           id,
                           COALESCE(text, '') AS text,
                           type,
                           'me' AS sender,
                           created_at AS time,
                           status,
                           NULL AS media_id,
                           media_url
                        FROM wa_fonnte_messages_out
                        WHERE {$like})
                    ) AS combined_msgs
                    ORDER BY time DESC
                    LIMIT $limit
                 ) AS latest_msgs
                 ORDER BY time ASC"
            );
        } catch (\Throwable $e) {
            // Tabel belum ada / migration belum jalan — jangan gagalkan UI
            return [];
        }

        return $this->normalizeRows(is_array($messages) ? $messages : [], 'fonnte');
    }

    /**
     * @param list<array<string,mixed>> $messages
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string,_id:int}>
     */
    private function normalizeRows(array $messages, string $source): array
    {
        $list = [];
        foreach ($messages as $m) {
            $list[] = [
                'sender' => (($m['sender'] ?? '') === 'me') ? 'me' : 'customer',
                'text' => (string) ($m['text'] ?? ''),
                'type' => (string) ($m['type'] ?? 'text'),
                'time' => (string) ($m['time'] ?? ''),
                'media_url' => !empty($m['media_url']) ? (string) $m['media_url'] : null,
                'media_id' => !empty($m['media_id']) ? (string) $m['media_id'] : null,
                'source' => $source,
                '_id' => (int) ($m['id'] ?? 0),
            ];
        }

        return $list;
    }

    /** Nasional 852… setelah buang +62 / 62 / 0. */
    public function matchDigits(string $phone, int $len = 9): string
    {
        $this->ensurePelangganByPhone();

        return PelangganByPhone::key($phone);
    }

    private function phoneLikeSql($db, string $nomor, string $column): string
    {
        $this->ensurePelangganByPhone();
        $esc = $db->escape($nomor);

        return PelangganByPhone::likeSql($esc, $column);
    }

    private function ensurePelangganByPhone(): void
    {
        if (!class_exists('PelangganByPhone', false)) {
            require_once __DIR__ . '/PelangganByPhone.php';
        }
    }
}
