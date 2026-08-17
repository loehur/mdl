<?php

/**
 * Notif group Delivery Fonnte saat semua item terikat request Antar
 * (non-pending) sudah selesai laundry (notif tipe=2).
 */
class DeliverySiapGroupNotify
{
    private const MARKER = 'siap_group_ok=1';

    /**
     * Panggil setelah layanan terakhir item berhasil disimpan.
     *
     * @param object $db laundry db
     * @param object|null $log Model Log (opsional)
     */
    public static function maybeNotify($db, $log, int $idPenjualan): void
    {
        if ($idPenjualan <= 0) {
            return;
        }

        try {
            $requests = $db->query_array(
                "SELECT DISTINCT drq.id_request, drq.id_pelanggan, drq.id_cabang,
                        drq.catatan_kurir, drq.sekalian_jemput
                 FROM delivery_request_item dri
                 INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
                 WHERE dri.id_penjualan = " . (int) $idPenjualan . "
                   AND drq.jenis = 'antar'
                   AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')"
            );
            if (!is_array($requests) || $requests === []) {
                return;
            }

            foreach ($requests as $req) {
                self::notifyIfAllItemsReady($db, $log, $req);
            }
        } catch (\Throwable $e) {
            self::writeLog($log, '[DeliverySiapGroupNotify] ' . $e->getMessage());
        }
    }

    /**
     * @param array $req
     */
    private static function notifyIfAllItemsReady($db, $log, array $req): void
    {
        $idRequest = (int) ($req['id_request'] ?? 0);
        if ($idRequest <= 0) {
            return;
        }

        $catatan = (string) ($req['catatan_kurir'] ?? '');
        if (strpos($catatan, self::MARKER) !== false) {
            return;
        }

        $items = $db->query_array(
            'SELECT id_penjualan FROM delivery_request_item WHERE id_request = ' . $idRequest
        );
        if (!is_array($items) || $items === []) {
            return;
        }

        $ids = [];
        foreach ($items as $row) {
            $sid = (int) ($row['id_penjualan'] ?? 0);
            if ($sid > 0) {
                $ids[$sid] = $sid;
            }
        }
        if ($ids === []) {
            return;
        }

        $inList = [];
        foreach ($ids as $sid) {
            $inList[] = "'" . $db->escape((string) $sid) . "'";
        }
        $doneRows = $db->query_array(
            'SELECT DISTINCT no_ref FROM notif
             WHERE tipe = 2 AND no_ref IN (' . implode(',', $inList) . ')'
        );
        $done = [];
        if (is_array($doneRows)) {
            foreach ($doneRows as $r) {
                $sid = (int) ($r['no_ref'] ?? 0);
                if ($sid > 0) {
                    $done[$sid] = true;
                }
            }
        }
        foreach ($ids as $sid) {
            if (!isset($done[$sid])) {
                return;
            }
        }

        $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);
        $idCabang = (int) ($req['id_cabang'] ?? 0);
        $nama = 'PELANGGAN';
        if ($idPelanggan > 0) {
            $pel = $db->get_where_row('pelanggan', 'id_pelanggan = ' . $idPelanggan);
            if (is_array($pel) && trim((string) ($pel['nama_pelanggan'] ?? '')) !== '') {
                $nama = strtoupper(trim((string) $pel['nama_pelanggan']));
            }
        }
        $kode = $idCabang > 0 ? (string) $idCabang : '-';
        if ($idCabang > 0) {
            $cab = $db->get_where_row('cabang', 'id_cabang = ' . $idCabang);
            if (is_array($cab) && trim((string) ($cab['kode_cabang'] ?? '')) !== '') {
                $kode = trim((string) $cab['kode_cabang']);
            }
        }

        $jenisLabel = !empty($req['sekalian_jemput']) ? 'Antar & Jemput' : 'Antar';
        $text = $nama . ' - ' . $kode . "\n*" . $jenisLabel . "*\n#" . $idRequest . ' siap diantar';

        if (!class_exists('FonnteService')) {
            // Dipanggil dari controller yang sudah load helper
            return;
        }
        $groupId = FonnteService::driverGroupId();
        if ($groupId === '') {
            return;
        }
        $send = FonnteService::sendToGroup($groupId, $text);
        if (empty($send['success'])) {
            self::writeLog(
                $log,
                '[DeliverySiapGroupNotify] gagal kirim #' . $idRequest . ' — ' . ($send['error'] ?? 'unknown')
            );
            return;
        }

        $newCatatan = trim($catatan);
        $newCatatan = $newCatatan === '' ? self::MARKER : ($newCatatan . ' | ' . self::MARKER);
        $db->update(
            'delivery_request',
            ['catatan_kurir' => mb_substr($newCatatan, 0, 150)],
            'id_request = ' . $idRequest
        );
        self::writeLog($log, '[DeliverySiapGroupNotify] ok request=#' . $idRequest);
    }

    private static function writeLog($log, string $msg): void
    {
        try {
            if ($log && method_exists($log, 'write')) {
                $log->write($msg);
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
