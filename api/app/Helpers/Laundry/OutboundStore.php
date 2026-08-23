<?php

namespace App\Helpers\Laundry;

/**
 * Outbound intent dari CRM — kirim info dinamis ke pelanggan (TAGIHAN, STATUS, …).
 */
class OutboundStore
{
    /**
     * @param array{wa_number?:string,phone?:string,cust_id?:int,id_pelanggan?:int} $input
     * @return array{ok:bool,message?:string,cooldown?:bool,outcome?:string}
     */
    public static function sendTagihan(array $input): array
    {
        $waNumber = trim((string) ($input['wa_number'] ?? $input['phone'] ?? ''));
        $custId = (int) ($input['cust_id'] ?? $input['id_pelanggan'] ?? 0);

        if ($waNumber === '' && $custId <= 0) {
            return ['ok' => false, 'message' => 'wa_number atau cust_id wajib'];
        }

        if ($waNumber === '' && $custId > 0) {
            $pel = PelangganLokasiStore::findPelanggan($custId);
            if ($pel === null) {
                return ['ok' => false, 'message' => 'Pelanggan tidak ditemukan'];
            }
            $waNumber = trim((string) ($pel['nomor_pelanggan'] ?? ''));
            if ($waNumber === '') {
                return ['ok' => false, 'message' => 'Nomor pelanggan belum lengkap'];
            }
        }

        if (!class_exists('\\App\\Models\\WAReplies')) {
            require_once __DIR__ . '/../../Models/WAReplies.php';
        }

        $replies = new \App\Models\WAReplies();
        return $replies->sendTagihanFromCrm($waNumber, $custId > 0 ? $custId : null);
    }
}
