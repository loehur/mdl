<?php

namespace App\Helpers\Payment;

/**
 * QRIS dinamis lokal. Nominal direservasi lintas modul pada mdl_main agar
 * mutasi QRIS BCA dapat dipasangkan cron tanpa collision.
 */
class QrisService
{
    public const GATEWAY_LOCAL_BCA = 'bca_qris_local';
    private const RESERVATION_DAYS = 6;
    private const STATIC_QRIS = '00020101021126650013ID.CO.BCA.WWW011893600014000476898802150008850047689880303UMI51440014ID.CO.QRIS.WWW0215ID10265749481090303UMI5204721053033605802ID5915MADINAH LAUNDRY6009PEKANBARU61052812562070703A0163040830';
    private $mainDb;

    public function __construct($mainDb = null) { $this->mainDb = $mainDb; }

    public function generate(int $nominal, string $refId, bool $uniqueTrx = false): array
    {
        $refId = trim($refId);
        if ($nominal < 1 || $refId === '') return $this->fail($refId, 'Nominal atau referensi pembayaran tidak valid');
        try {
            $reservation = $this->reserveAmount($nominal, $refId);
            $amount = (int) $reservation['amount'];
            return ['status' => true, 'failed' => false, 'message' => 'OK', 'gateway' => self::GATEWAY_LOCAL_BCA,
                'trx_id' => $refId, 'ref_id' => $refId, 'qr_string' => $this->buildDynamicQris($amount),
                'amount' => $amount, 'raw' => $reservation];
        } catch (\Throwable $e) { return $this->fail($refId, 'Gagal menyiapkan nominal QRIS: ' . $e->getMessage()); }
    }

    /** Status berubah hanya setelah cron BcaQrisConfirm membuat link. */
    public function checkStatus(string $trxId, int $nominal): array
    {
        $trxId = trim($trxId);
        $base = ['ok' => true, 'connection_error' => false, 'message' => 'Menunggu Mutasi QRIS',
            'gateway' => self::GATEWAY_LOCAL_BCA, 'trx_id' => $trxId, 'payment_status' => 'pending',
            'trx_status' => 'pending', 'raw' => null];
        try {
            $row = $this->db()->query("SELECT r.amount, r.state, l.id AS link_id FROM qris_nominal_reservations r
                LEFT JOIN bca_qris_link l ON l.entity_ref = r.entity_ref WHERE r.entity_ref = ? LIMIT 1", [$trxId])->row_array();
            if (!is_array($row)) return $base;
            if (!empty($row['link_id']) || ($row['state'] ?? '') === 'paid') return array_merge($base, ['message' => 'Pembayaran terkonfirmasi', 'payment_status' => 'paid', 'trx_status' => 'paid', 'raw' => $row]);
            if (($row['state'] ?? '') === 'expired') return array_merge($base, ['message' => 'QRIS kadaluarsa', 'payment_status' => 'expired', 'trx_status' => 'expired', 'raw' => $row]);
            return array_merge($base, ['raw' => $row]);
        } catch (\Throwable $e) { return array_merge($base, ['ok' => false, 'connection_error' => true, 'message' => 'Gagal membaca status QRIS lokal: ' . $e->getMessage()]); }
    }

    /** Membentuk tag 01=12, tag 54=nominal, dan CRC16-CCITT EMVCo. */
    public function buildDynamicQris(int $amount): string
    {
        $amountText = (string) max(1, $amount);
        $source = preg_replace('/6304[0-9A-Fa-f]{4}$/', '', self::STATIC_QRIS);
        $tags = $this->parseTlv((string) $source); $out = ''; $inserted = false;
        foreach ($tags as $tag) {
            if ($tag['id'] === '01') $tag['value'] = '12';
            if ($tag['id'] === '54') continue;
            if (!$inserted && $tag['id'] === '58') { $out .= '54' . str_pad((string) strlen($amountText), 2, '0', STR_PAD_LEFT) . $amountText; $inserted = true; }
            $out .= $tag['id'] . str_pad((string) strlen($tag['value']), 2, '0', STR_PAD_LEFT) . $tag['value'];
        }
        if (!$inserted) $out .= '54' . str_pad((string) strlen($amountText), 2, '0', STR_PAD_LEFT) . $amountText;
        return $out . '6304' . strtoupper(str_pad(dechex($this->crc16Ccitt($out . '6304')), 4, '0', STR_PAD_LEFT));
    }

    private function reserveAmount(int $baseAmount, string $refId): array
    {
        $db = $this->db();
        $db->query("UPDATE qris_nominal_reservations SET active_key = NULL, state = 'expired' WHERE active_key = 1 AND expires_at < NOW()");
        if (!$db->beginTransaction()) throw new \RuntimeException('Transaksi reservasi tidak dapat dimulai');
        try {
            $existing = $db->query('SELECT amount, state FROM qris_nominal_reservations WHERE entity_ref = ? AND active_key = 1 LIMIT 1 FOR UPDATE', [$refId])->row_array();
            if (is_array($existing)) { $db->commit(); return $existing; }
            for ($candidate = $baseAmount, $i = 0; $i < 10000; $candidate++, $i++) {
                try {
                    $db->insert('qris_nominal_reservations', ['entity_ref' => $refId, 'amount' => $candidate, 'state' => 'pending', 'active_key' => 1,
                        'expires_at' => date('Y-m-d H:i:s', strtotime('+' . self::RESERVATION_DAYS . ' days'))]);
                    $db->commit(); return ['amount' => $candidate, 'state' => 'pending'];
                } catch (\Throwable $e) { /* nominal aktif dipakai; lanjut +Rp1 */ }
            }
            throw new \RuntimeException('Nominal QRIS unik habis');
        } catch (\Throwable $e) { $db->rollback(); throw $e; }
    }

    private function db()
    {
        if ($this->mainDb) return $this->mainDb;
        $controller = new \App\Core\Controller();
        return $this->mainDb = $controller->db(0);
    }

    private function parseTlv(string $payload): array
    {
        $tags = []; $at = 0; $len = strlen($payload);
        while ($at + 4 <= $len) { $id = substr($payload, $at, 2); $size = (int) substr($payload, $at + 2, 2); $at += 4;
            if ($at + $size > $len) throw new \RuntimeException('Format QRIS statis tidak valid');
            $tags[] = ['id' => $id, 'value' => substr($payload, $at, $size)]; $at += $size; }
        return $tags;
    }

    private function crc16Ccitt(string $data): int
    {
        $crc = 0xFFFF;
        for ($i = 0, $n = strlen($data); $i < $n; $i++) { $crc ^= ord($data[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF; }
        return $crc;
    }

    private function fail(string $refId, string $message): array
    {
        return ['status' => false, 'failed' => true, 'message' => $message, 'gateway' => self::GATEWAY_LOCAL_BCA,
            'trx_id' => $refId, 'ref_id' => $refId, 'qr_string' => '', 'amount' => 0, 'raw' => null];
    }
}
