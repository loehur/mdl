<?php

/**
 * Riwayat chat WA (in + out) untuk Laundry UI — YCloud multi-line only.
 */
class WaChatHistory
{
    /** Default limit. */
    public const LIMIT_PER_SOURCE = 30;

    /**
     * @param object $db Instance DB CRM (biasanya db(100))
     * @param int $limitPerSource Jumlah pesan terbaru (default 30)
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string}>
     */
    public function fetchMessages($db, string $phone, int $limitPerSource = self::LIMIT_PER_SOURCE): array
    {
        $digits = $this->matchDigits($phone);
        if ($digits === '') {
            return [];
        }

        $limitPerSource = max(1, min(100, (int) $limitPerSource));

        $merged = $this->fetchYcloudMessages($db, $digits, $limitPerSource);
        usort($merged, function (array $a, array $b): int {
            return $this->compareTimeline($a, $b);
        });

        foreach ($merged as &$row) {
            unset($row['_id'], $row['inboxid'], $row['reply_inboxid']);
        }
        unset($row);

        return $merged;
    }

    /**
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string,_id:int,inboxid:int,reply_inboxid:int}>
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
                           media_url,
                           NULL AS inboxid,
                           NULL AS reply_inboxid
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
                           media_url,
                           NULL AS inboxid,
                           NULL AS reply_inboxid
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
     * @param list<array<string,mixed>> $messages
     * @return list<array{sender:string,text:string,type:string,time:string,media_url:?string,media_id:?string,source:string,_id:int,inboxid:int,reply_inboxid:int}>
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
                'inboxid' => (int) ($m['inboxid'] ?? 0),
                'reply_inboxid' => (int) ($m['reply_inboxid'] ?? 0),
            ];
        }

        return $list;
    }

    /**
     * Urutan chat: waktu, lalu pasangan Fonnte (inbox → balasannya),
     * jangan pakai id lintas tabel in/out.
     *
     * @param array<string,mixed> $a
     * @param array<string,mixed> $b
     */
    private function compareTimeline(array $a, array $b): int
    {
        $ta = (string) ($a['time'] ?? '');
        $tb = (string) ($b['time'] ?? '');
        if ($ta !== $tb) {
            return $ta <=> $tb;
        }

        $ka = $this->timelineTieKey($a);
        $kb = $this->timelineTieKey($b);
        if ($ka !== $kb) {
            return $ka <=> $kb;
        }

        $sa = (string) ($a['source'] ?? '');
        $sb = (string) ($b['source'] ?? '');
        if ($sa !== $sb) {
            return $sa <=> $sb;
        }

        $da = (($a['sender'] ?? '') === 'me') ? 1 : 0;
        $db = (($b['sender'] ?? '') === 'me') ? 1 : 0;
        if ($da !== $db) {
            return $da <=> $db;
        }

        return ((int) ($a['_id'] ?? 0)) <=> ((int) ($b['_id'] ?? 0));
    }

    /**
     * Kunci pasangan saat detik sama.
     * Customer inbox X →  {group:X, seq:0}; balasan reply_inboxid X → {group:X, seq:1}.
     *
     * @param array<string,mixed> $row
     * @return array{0:int,1:int}
     */
    private function timelineTieKey(array $row): array
    {
        $isMe = (($row['sender'] ?? '') === 'me');
        $inbox = (int) ($row['inboxid'] ?? 0);
        $reply = (int) ($row['reply_inboxid'] ?? 0);
        if (!$isMe && $inbox > 0) {
            return [$inbox, 0];
        }
        if ($isMe && $reply > 0) {
            return [$reply, 1];
        }
        // Tanpa inboxid: pelanggan dulu, laundry kemudian (jangan campur id in/out)
        return [$isMe ? PHP_INT_MAX : 0, $isMe ? 1 : 0];
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
