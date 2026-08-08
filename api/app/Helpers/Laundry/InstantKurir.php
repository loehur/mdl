<?php

namespace App\Helpers\Laundry;

use App\Models\BiteshipClient;

/**
 * Activate / track Instant kurir (delivery_request + Biteship).
 * kas.jenis_transaksi = 10 (ongkir Instant).
 */
class InstantKurir
{
    const JENIS_TRANSAKSI = 10;

    /**
     * After kas paid (jt=10): create Biteship order and set request berjalan.
     *
     * @param object $db laundry DB (api db index 1)
     * @param object|array $kas kas row
     * @return array{ok:bool,message:string,id_request?:int,biteship_order_id?:string}
     */
    public static function activateAfterPayment($db, $kas)
    {
        $kas = self::toArray($kas);
        $jt = (int) ($kas['jenis_transaksi'] ?? 0);
        if ($jt !== self::JENIS_TRANSAKSI) {
            return ['ok' => false, 'message' => 'Not instant kurir kas'];
        }

        $refFinance = trim((string) ($kas['ref_finance'] ?? ''));
        $idRequest = (int) ($kas['ref_transaksi'] ?? 0);
        if ($idRequest <= 0 && $refFinance !== '') {
            $reqByPay = $db->get_where('delivery_request', ["payment_ref_finance" => $refFinance])->row_array();
            $idRequest = (int) ($reqByPay['id_request'] ?? 0);
        }
        if ($idRequest <= 0) {
            \Log::write("InstantKurir activate: missing id_request ref=$refFinance", 'webhook', 'Biteship');
            return ['ok' => false, 'message' => 'delivery_request not found'];
        }

        $req = $db->get_where('delivery_request', ['id_request' => $idRequest])->row_array();
        if (!$req) {
            return ['ok' => false, 'message' => 'delivery_request missing'];
        }

        $status = (string) ($req['delivery_status'] ?? '');
        $existingOrder = trim((string) ($req['biteship_order_id'] ?? ''));
        if ($existingOrder !== '') {
            if ($status === 'menunggu_pembayaran') {
                $db->update('delivery_request', [
                    'delivery_status' => 'berjalan',
                ], ['id_request' => $idRequest]);
            }
            return [
                'ok' => true,
                'message' => 'Already activated',
                'id_request' => $idRequest,
                'biteship_order_id' => $existingOrder,
            ];
        }

        if ($status !== 'menunggu_pembayaran' && $status !== 'berjalan') {
            return ['ok' => false, 'message' => 'Request status not activatable: ' . $status];
        }

        $payload = self::buildOrderPayload($db, $req);
        if (isset($payload['error'])) {
            \Log::write("InstantKurir payload err id=$idRequest " . $payload['error'], 'webhook', 'Biteship');
            return ['ok' => false, 'message' => $payload['error']];
        }

        $biteship = new BiteshipClient();
        $res = $biteship->createOrder($payload);
        $orderId = (string) ($res['id'] ?? '');
        $success = !empty($res['success']) || $orderId !== '';

        if (!$success || $orderId === '') {
            $msg = (string) ($res['message'] ?? $res['error'] ?? 'Biteship create order failed');
            \Log::write("InstantKurir create fail id=$idRequest msg=$msg raw=" . json_encode($res), 'webhook', 'Biteship');
            return ['ok' => false, 'message' => $msg];
        }

        $courier = is_array($res['courier'] ?? null) ? $res['courier'] : [];
        $upd = [
            'delivery_status' => 'berjalan',
            'biteship_order_id' => $orderId,
            'biteship_status' => (string) ($res['status'] ?? 'confirmed'),
            'waybill_id' => (string) ($courier['waybill_id'] ?? ''),
            'tracking_url' => (string) ($courier['link'] ?? ''),
            'driver_name' => (string) ($courier['driver_name'] ?? ''),
            'driver_phone' => (string) ($courier['driver_phone'] ?? ''),
        ];
        if ($refFinance !== '' && empty($req['payment_ref_finance'])) {
            $upd['payment_ref_finance'] = $refFinance;
        }

        $db->update('delivery_request', $upd, ['id_request' => $idRequest]);
        \Log::write("InstantKurir OK id=$idRequest order=$orderId", 'webhook', 'Biteship');

        return [
            'ok' => true,
            'message' => 'Order created',
            'id_request' => $idRequest,
            'biteship_order_id' => $orderId,
        ];
    }

    /**
     * Mark unpaid Instant request batal when QRIS expired/failed/deleted.
     */
    public static function cancelUnpaidByRefFinance($db, $refFinance)
    {
        $refFinance = trim((string) $refFinance);
        if ($refFinance === '') {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        return (bool) $db->update(
            'delivery_request',
            [
                'delivery_status' => 'batal',
                'catatan_batal' => 'Pembayaran QRIS expired/gagal',
                'selesaiTime' => $now,
            ],
            [
                'payment_ref_finance' => $refFinance,
                'delivery_status' => 'menunggu_pembayaran',
            ]
        );
    }

    /**
     * Cancel unpaid Instant by kas row (jt=10).
     */
    public static function cancelUnpaidByKas($db, $kas)
    {
        $kas = self::toArray($kas);
        if ((int) ($kas['jenis_transaksi'] ?? 0) !== self::JENIS_TRANSAKSI) {
            return false;
        }
        $refFinance = trim((string) ($kas['ref_finance'] ?? ''));
        $idRequest = (int) ($kas['ref_transaksi'] ?? 0);
        $ok = false;
        if ($refFinance !== '') {
            $ok = self::cancelUnpaidByRefFinance($db, $refFinance) || $ok;
        }
        if ($idRequest > 0) {
            $now = date('Y-m-d H:i:s');
            $ok = (bool) $db->update(
                'delivery_request',
                [
                    'delivery_status' => 'batal',
                    'catatan_batal' => 'Pembayaran QRIS expired/gagal',
                    'selesaiTime' => $now,
                ],
                [
                    'id_request' => $idRequest,
                    'delivery_status' => 'menunggu_pembayaran',
                ]
            ) || $ok;
        }
        return $ok;
    }

    /**
     * Apply Biteship webhook payload to delivery_request.
     * Jemput: never set selesai (staff links items).
     * Antar: delivered → selesai.
     */
    public static function applyWebhook($db, array $data)
    {
        $orderId = (string) ($data['order_id'] ?? $data['id'] ?? '');
        if ($orderId === '' && isset($data['order']) && is_array($data['order'])) {
            $orderId = (string) ($data['order']['id'] ?? '');
        }
        if ($orderId === '') {
            return ['ok' => false, 'message' => 'Missing order id'];
        }

        $req = $db->get_where('delivery_request', ['biteship_order_id' => $orderId])->row_array();
        if (!$req) {
            // fallback reference_id
            $refId = (string) ($data['reference_id'] ?? '');
            if ($refId !== '' && preg_match('/^DR(\d+)$/', $refId, $m)) {
                $req = $db->get_where('delivery_request', ['id_request' => (int) $m[1]])->row_array();
            }
        }
        if (!$req) {
            return ['ok' => false, 'message' => 'Request not found for order ' . $orderId];
        }

        $idRequest = (int) $req['id_request'];
        $jenis = strtolower((string) ($req['jenis'] ?? ''));
        $statusBs = strtolower((string) (
            $data['status']
            ?? ($data['order']['status'] ?? '')
            ?? ($data['courier_status'] ?? '')
        ));

        $courier = [];
        if (isset($data['courier']) && is_array($data['courier'])) {
            $courier = $data['courier'];
        } elseif (isset($data['order']['courier']) && is_array($data['order']['courier'])) {
            $courier = $data['order']['courier'];
        }

        $upd = [
            'biteship_status' => $statusBs !== '' ? $statusBs : (string) ($req['biteship_status'] ?? ''),
        ];
        if (!empty($courier['waybill_id'])) {
            $upd['waybill_id'] = (string) $courier['waybill_id'];
        }
        if (!empty($courier['link'])) {
            $upd['tracking_url'] = (string) $courier['link'];
        }
        if (!empty($courier['driver_name'])) {
            $upd['driver_name'] = (string) $courier['driver_name'];
        }
        if (!empty($courier['driver_phone'])) {
            $upd['driver_phone'] = (string) $courier['driver_phone'];
        }

        $localStatus = (string) ($req['delivery_status'] ?? '');
        $failStatuses = ['cancelled', 'canceled', 'returned', 'courier_not_found', 'rejected', 'disposed'];
        $shouldRefund = false;
        if (in_array($statusBs, $failStatuses, true) && $localStatus === 'berjalan') {
            $upd['delivery_status'] = 'batal';
            $upd['catatan_batal'] = 'Biteship: ' . $statusBs;
            $upd['selesaiTime'] = date('Y-m-d H:i:s');
            $shouldRefund = true;
        } elseif (in_array($statusBs, $failStatuses, true) && $localStatus === 'batal') {
            // Retry refund jika webhook ulang / gagal di run sebelumnya
            $shouldRefund = true;
        } elseif ($statusBs === 'delivered' && $jenis === 'antar' && $localStatus === 'berjalan') {
            $upd['delivery_status'] = 'selesai';
            $upd['selesaiTime'] = date('Y-m-d H:i:s');
        }
        // jemput + delivered: track only — selesai hanya dari Delivery staff

        $db->update('delivery_request', $upd, ['id_request' => $idRequest]);

        $refund = null;
        if ($shouldRefund) {
            $catatanPrefix = (string) ($upd['catatan_batal'] ?? $req['catatan_batal'] ?? '');
            $refund = self::refundInstantOngkirToSaldo($db, $req, $catatanPrefix);
        }

        $out = ['ok' => true, 'message' => 'Updated', 'id_request' => $idRequest, 'jenis' => $jenis];
        if (is_array($refund)) {
            $out['refund'] = $refund;
        }
        return $out;
    }

    /**
     * Kredit Saldo Deposit (jt=6, non-tunai) saat Instant batal setelah lunas.
     * metode_mutasi=2 agar tidak menambah Kas Kasir (hanya metode=1 yang masuk kasir).
     *
     * @return array{ok:bool,message:string,id_kas?:string,jumlah?:int,skipped?:bool}
     */
    public static function refundInstantOngkirToSaldo($db, array $req, $catatanBatalPrefix = '')
    {
        $idRequest = (int) ($req['id_request'] ?? 0);
        $layanan = strtolower((string) ($req['layanan'] ?? ''));
        if ($idRequest <= 0) {
            return ['ok' => false, 'message' => 'id_request invalid'];
        }
        if ($layanan !== '' && $layanan !== 'instant') {
            return ['ok' => true, 'message' => 'Not Instant — skip refund', 'skipped' => true];
        }

        $noteRefund = 'Refund Instant #' . $idRequest;

        // Idempotent: sudah ada kredit refund untuk request ini
        $existing = $db->get_where('kas', [
            'jenis_transaksi' => 6,
            'jenis_mutasi' => 1,
            'status_mutasi' => 3,
            'ref_transaksi' => (string) $idRequest,
            'note' => $noteRefund,
        ])->row_array();
        if ($existing) {
            return [
                'ok' => true,
                'message' => 'Already refunded',
                'skipped' => true,
                'id_kas' => (string) ($existing['id_kas'] ?? ''),
                'jumlah' => (int) ($existing['jumlah'] ?? 0),
            ];
        }

        // Harus ada kas Instant yang sudah lunas
        $paidKas = $db->get_where('kas', [
            'jenis_transaksi' => self::JENIS_TRANSAKSI,
            'status_mutasi' => 3,
            'ref_transaksi' => (string) $idRequest,
        ])->row_array();
        if (!$paidKas) {
            // fallback by payment_ref_finance
            $refPay = trim((string) ($req['payment_ref_finance'] ?? ''));
            if ($refPay !== '') {
                $paidKas = $db->get_where('kas', [
                    'jenis_transaksi' => self::JENIS_TRANSAKSI,
                    'status_mutasi' => 3,
                    'ref_finance' => $refPay,
                ])->row_array();
            }
        }
        if (!$paidKas) {
            \Log::write("InstantKurir refund skip unpaid id=$idRequest", 'webhook', 'Biteship');
            return ['ok' => true, 'message' => 'No paid Instant kas — skip refund', 'skipped' => true];
        }

        $jumlah = (int) ($req['ongkir'] ?? 0);
        if ($jumlah <= 0) {
            $jumlah = (int) ($paidKas['jumlah'] ?? 0);
        }
        if ($jumlah <= 0) {
            return ['ok' => false, 'message' => 'Ongkir amount empty'];
        }

        $idPelanggan = (int) ($req['id_pelanggan'] ?? $paidKas['id_client'] ?? 0);
        $idCabang = (int) ($req['id_cabang'] ?? $paidKas['id_cabang'] ?? 0);
        if ($idPelanggan <= 0 || $idCabang <= 0) {
            return ['ok' => false, 'message' => 'Missing pelanggan/cabang for refund'];
        }

        $idKas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
        $refFinance = date('YmdHis') . rand(0, 9) . rand(0, 9) . rand(0, 9);

        $ins = $db->insert('kas', [
            'id_kas' => $idKas,
            'id_cabang' => $idCabang,
            'jenis_mutasi' => 1,
            'jenis_transaksi' => 6,
            'metode_mutasi' => 2, // Non-tunai — tidak masuk Kas Kasir
            'note' => $noteRefund,
            'status_mutasi' => 3,
            'jumlah' => $jumlah,
            'id_user' => 0,
            'id_client' => $idPelanggan,
            'ref_transaksi' => (string) $idRequest,
            'ref_finance' => $refFinance,
        ]);

        if ($ins === false) {
            \Log::write("InstantKurir refund insert fail id=$idRequest", 'webhook', 'Biteship');
            return ['ok' => false, 'message' => 'Failed to insert refund kas'];
        }

        $catatan = trim((string) $catatanBatalPrefix);
        if ($catatan === '') {
            $catatan = (string) ($req['catatan_batal'] ?? 'Biteship: cancelled');
        }
        if (stripos($catatan, 'Saldo Deposit') === false) {
            $catatan = rtrim($catatan, " \t.|") . ' | Ongkir dikembalikan ke Saldo Deposit';
            $db->update('delivery_request', [
                'catatan_batal' => $catatan,
            ], ['id_request' => $idRequest]);
        }

        \Log::write(
            "InstantKurir refund OK id=$idRequest kas=$idKas jumlah=$jumlah",
            'webhook',
            'Biteship'
        );

        return [
            'ok' => true,
            'message' => 'Refunded to Saldo Deposit',
            'id_kas' => $idKas,
            'jumlah' => $jumlah,
        ];
    }

    /**
     * Filter rates list to instant-like services (bukan tarif jarak Sameday).
     * Utama: service_type = instant. Juga same_day / overnight bike bila ada.
     */
    public static function filterInstantPricing(array $pricing)
    {
        $out = [];
        foreach ($pricing as $row) {
            if (!is_array($row)) {
                continue;
            }
            $serviceType = strtolower((string) ($row['service_type'] ?? ''));
            $courierType = strtolower((string) (
                $row['courier_service_code']
                ?? $row['courier_type']
                ?? $row['type']
                ?? ''
            ));
            $serviceName = strtolower((string) ($row['courier_service_name'] ?? ''));
            $shippingType = strtolower((string) ($row['shipping_type'] ?? ''));
            $company = strtolower((string) ($row['courier_code'] ?? $row['company'] ?? ''));
            $availInstant = !empty($row['available_for_instant_delivery']);

            $instantCompanies = ['grab', 'gojek', 'paxel', 'lalamove', 'borzo', 'maxim', 'deliveree', 'anteraja'];
            $isInstantCompany = in_array($company, $instantCompanies, true);

            $isInstant = $serviceType === 'instant'
                || $shippingType === 'instant'
                || $availInstant
                || strpos($courierType, 'instant') !== false
                || strpos($serviceName, 'instant') !== false
                || strpos($courierType, 'same_day') !== false
                || strpos($courierType, 'sameday') !== false
                || strpos($serviceName, 'same day') !== false
                || ($isInstantCompany && in_array($serviceType, ['instant', 'same_day', 'overnight'], true))
                || ($isInstantCompany && $serviceType === '' && (
                    strpos($courierType, 'instant') !== false
                    || $courierType === 'instant'
                    || $courierType === 'same_day'
                    || $courierType === 'sameday'
                    || $courierType === 'hemat'
                    || $courierType === 'priority'
                ));

            // Tolak layanan parcel reguler (jne/tiki reg dll) meski company ikut ter-query
            if (in_array($serviceType, ['standard', 'economy'], true) && !$availInstant) {
                $isInstant = false;
            }

            if (!$isInstant) {
                continue;
            }
            $price = (int) ($row['price'] ?? $row['shipping_fee'] ?? 0);
            if ($price <= 0) {
                continue;
            }
            $out[] = [
                'courier_company' => (string) ($row['courier_code'] ?? $row['company'] ?? ''),
                'courier_type' => (string) ($row['courier_service_code'] ?? $row['courier_type'] ?? $row['type'] ?? ''),
                'courier_name' => trim(
                    (string) ($row['courier_name'] ?? $row['name'] ?? '')
                    . (
                        !empty($row['courier_service_name'])
                            ? (' ' . $row['courier_service_name'])
                            : ''
                    )
                ),
                'description' => (string) ($row['description'] ?? ''),
                'duration' => (string) ($row['duration'] ?? $row['shipment_duration_range'] ?? ''),
                'price' => $price,
                'service_type' => $serviceType !== '' ? $serviceType : 'instant',
            ];
        }
        return $out;
    }

    private static function buildOrderPayload($db, array $req)
    {
        $jenis = strtolower((string) ($req['jenis'] ?? ''));
        $idCabang = (int) ($req['id_cabang'] ?? 0);
        $idPelanggan = (int) ($req['id_pelanggan'] ?? 0);

        $cabang = $db->get_where('cabang', ['id_cabang' => $idCabang])->row_array();
        $pelanggan = $db->get_where('pelanggan', ['id_pelanggan' => $idPelanggan])->row_array();
        if (!$cabang || !$pelanggan) {
            return ['error' => 'Cabang/pelanggan tidak ditemukan'];
        }

        // Cabang: field nama + phone_number → catatan "MADINAH LAUNDRY, 0811678111"
        $cabName = trim((string) ($cabang['nama'] ?? $cabang['nama_cabang'] ?? 'MDL Laundry'));
        $cabPhoneRaw = trim((string) ($cabang['phone_number'] ?? ''));
        $cabPhone = self::normalizePhone($cabPhoneRaw !== '' ? $cabPhoneRaw : '08123456789');
        $cabNote = self::formatContactNote($cabName, $cabPhoneRaw !== '' ? $cabPhoneRaw : $cabPhone);
        $cabAddr = (string) ($cabang['alamat'] ?? 'Laundry');
        $cabLat = (float) ($cabang['latt'] ?? 0);
        $cabLon = (float) ($cabang['long'] ?? 0);

        // Customer: nama_pelanggan + nomor_pelanggan lengkap → "ANGGI, 086522115544"
        $pelName = trim((string) ($pelanggan['nama_pelanggan'] ?? 'Customer'));
        $pelPhoneRaw = trim((string) ($pelanggan['nomor_pelanggan'] ?? ''));
        $pelPhone = self::normalizePhone($pelPhoneRaw);
        if ($pelPhone === '') {
            $pelPhone = $cabPhone;
        }
        $pelNote = self::formatContactNote($pelName, $pelPhoneRaw !== '' ? $pelPhoneRaw : $pelPhone);
        $locAddr = trim((string) ($req['lokasi_nama'] ?? '') . ' — ' . (string) ($req['lokasi_detail'] ?? ''));
        if ($locAddr === '—') {
            $locAddr = (string) ($req['lokasi_detail'] ?? 'Alamat pelanggan');
        }
        $locLat = (float) ($req['lokasi_latt'] ?? 0);
        $locLon = (float) ($req['lokasi_longt'] ?? 0);

        if ($cabLat == 0.0 && $cabLon == 0.0) {
            return ['error' => 'Koordinat cabang belum diatur'];
        }
        if ($locLat == 0.0 && $locLon == 0.0) {
            return ['error' => 'Koordinat lokasi pelanggan belum lengkap'];
        }

        $company = (string) ($req['courier_company'] ?? '');
        $type = (string) ($req['courier_type'] ?? '');
        if ($company === '' || $type === '') {
            return ['error' => 'Kurir Instant belum dipilih'];
        }

        $items = self::buildItems($db, $req);

        // Antar: origin laundry → destination customer
        // Jemput: origin customer → destination laundry
        // origin_note = catatan pengirim; destination_note = catatan penerima
        if ($jenis === 'jemput') {
            $originName = $pelName;
            $originPhone = $pelPhone;
            $originAddr = $locAddr;
            $originLat = $locLat;
            $originLon = $locLon;
            $originNote = $pelNote;
            $destName = $cabName;
            $destPhone = $cabPhone;
            $destAddr = $cabAddr;
            $destLat = $cabLat;
            $destLon = $cabLon;
            $destNote = $cabNote;
            $note = 'Jemput laundry Instant #' . (int) $req['id_request'];
        } else {
            $originName = $cabName;
            $originPhone = $cabPhone;
            $originAddr = $cabAddr;
            $originLat = $cabLat;
            $originLon = $cabLon;
            $originNote = $cabNote;
            $destName = $pelName;
            $destPhone = $pelPhone;
            $destAddr = $locAddr;
            $destLat = $locLat;
            $destLon = $locLon;
            $destNote = $pelNote;
            $note = 'Antar laundry Instant #' . (int) $req['id_request'];
        }

        // Catatan customer (opsional) — append ke note sisi pelanggan + order_note
        $catatan = trim((string) ($req['catatan_kurir'] ?? ''));
        if ($catatan !== '') {
            if (function_exists('mb_substr')) {
                $catatan = mb_substr($catatan, 0, 150, 'UTF-8');
            } else {
                $catatan = substr($catatan, 0, 150);
            }
            $extra = 'Catatan: ' . $catatan;
            if ($jenis === 'jemput') {
                $originNote = trim($originNote . ($originNote !== '' ? ' | ' : '') . $extra);
            } else {
                $destNote = trim($destNote . ($destNote !== '' ? ' | ' : '') . $extra);
            }
            $note .= ' | ' . $extra;
        }

        return [
            'shipper_contact_name' => $cabName,
            'shipper_contact_phone' => $cabPhone,
            'shipper_organization' => 'MDL Laundry',
            'origin_contact_name' => $originName,
            'origin_contact_phone' => $originPhone,
            'origin_address' => $originAddr,
            'origin_note' => $originNote,
            'origin_coordinate' => [
                'latitude' => $originLat,
                'longitude' => $originLon,
            ],
            'destination_contact_name' => $destName,
            'destination_contact_phone' => $destPhone,
            'destination_address' => $destAddr,
            'destination_note' => $destNote,
            'destination_coordinate' => [
                'latitude' => $destLat,
                'longitude' => $destLon,
            ],
            'courier_company' => $company,
            'courier_type' => $type,
            'delivery_type' => 'now',
            'order_note' => $note . ' | Pengirim: ' . $originNote . ' | Penerima: ' . $destNote,
            'reference_id' => 'DR' . (int) $req['id_request'],
            'items' => $items,
        ];
    }

    /**
     * Format catatan kurir: "NAMA, 08xxxx"
     */
    private static function formatContactNote($name, $phone)
    {
        $name = strtoupper(trim((string) $name));
        $phone = trim((string) $phone);
        if ($name === '' && $phone === '') {
            return '';
        }
        if ($phone === '') {
            return $name;
        }
        if ($name === '') {
            return $phone;
        }
        return $name . ', ' . $phone;
    }

    private static function buildItems($db, array $req)
    {
        $idRequest = (int) ($req['id_request'] ?? 0);
        $jenis = strtolower((string) ($req['jenis'] ?? ''));
        $items = [];

        if ($jenis === 'antar') {
            $q = $db->query(
                "SELECT dri.id_penjualan, dri.no_ref, s.total
                 FROM delivery_request_item dri
                 LEFT JOIN sale s ON s.id_penjualan = dri.id_penjualan
                 WHERE dri.id_request = " . $idRequest
            );
            $rows = (is_object($q) && method_exists($q, 'result_array')) ? $q->result_array() : [];
            $totalValue = 0;
            $qty = 0;
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $totalValue += max(10000, (int) ($r['total'] ?? 10000));
                    $qty += 1;
                }
            }
            if ($qty > 0) {
                // Satu paket ramah kurir (hindari ID/angka di nama barang)
                $items[] = [
                    'name' => 'Pakaian Laundry',
                    'description' => 'Antar laundry' . ($qty > 1 ? (' (' . $qty . ' paket)') : ''),
                    'category' => 'fashion',
                    'value' => max(10000, $totalValue),
                    'quantity' => 1,
                    'weight' => max(1000, $qty * 1000),
                ];
            }
        }

        if (empty($items)) {
            $items[] = [
                'name' => 'Pakaian Laundry',
                'description' => $jenis === 'jemput' ? 'Jemput laundry' : 'Antar laundry',
                'category' => 'fashion',
                'value' => 50000,
                'quantity' => 1,
                'weight' => 1000,
            ];
        }
        return $items;
    }

    private static function normalizePhone($phone)
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (strpos($digits, '62') === 0) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }

    private static function toArray($row)
    {
        if (is_array($row)) {
            return $row;
        }
        if ($row instanceof \stdClass) {
            return get_object_vars($row);
        }
        if (is_object($row)) {
            // Hindari (array) cast pada object bertipe lain (null-byte keys)
            $vars = get_object_vars($row);
            return is_array($vars) ? $vars : [];
        }
        return [];
    }
}
