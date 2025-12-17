<?php

namespace App\Modules\MDL\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TokopayController extends Controller
{
    public function webhook(Request $request)
    {
        $raw = $request->getContent();
        $sig = (string) ($request->header('X-Signature') ?? $request->header('x-signature') ?? '');
        $secret = (string) (env('TOKOPAY_WEBHOOK_SECRET', '') ?? '');
        if ($secret !== '' && $sig !== '') {
            $calc = hash_hmac('sha256', $raw, $secret);
            if (!hash_equals($calc, $sig)) {
                return response()->json(['success' => false], 403);
            }
        }
        $p = $request->all();
        $status = strtolower((string) ($p['status'] ?? $p['transaction_status'] ?? $p['payment_status'] ?? ''));
        $ok = in_array($status, ['success', 'paid', 'completed', 'settlement'], true);
        if (!$ok) {
            return response()->json(['success' => true, 'ignored' => true]);
        }
        $refId = (string) ($p['ref_id'] ?? $p['merchant_ref'] ?? $p['order_id'] ?? '');
        if ($refId === '') {
            return response()->json(['success' => false, 'message' => 'ref_id kosong'], 422);
        }
        $amountRaw = $p['amount'] ?? ($p['paid_amount'] ?? ($p['gross_amount'] ?? 0));
        $amount = (float) $amountRaw;
        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'amount tidak valid'], 422);
        }
        $ref = DB::connection('salon')->table('sale_ref')
            ->select(['ref_id', 'business_id', 'order_status', 'customer_id'])
            ->where('ref_id', $refId)
            ->first();
        if (!$ref) {
            return response()->json(['success' => false, 'message' => 'order tidak ditemukan'], 404);
        }
        $businessId = (int) ($ref->business_id ?? 0);
        $sum = DB::connection('salon')->table('sale_item')
            ->where('ref_id', $refId)
            ->selectRaw('SUM(price * qty) as total_amount')
            ->value('total_amount');
        $total = (float) ($sum ?? 0);
        $sumPaid = DB::connection('salon')->table('ledger')
            ->where('business_id', $businessId)
            ->where('ref_id', $refId)
            ->where('type', 'sale')
            ->selectRaw('SUM(amount) as paid_total')
            ->value('paid_total');
        $paidTotal = (float) ($sumPaid ?? 0);
        $remaining = $total - $paidTotal;
        if ($remaining < 0) $remaining = 0.0;
        $record = $amount;
        if ($record > $remaining) $record = $remaining;
        if ($record <= 0) {
            DB::connection('salon')->table('sale_ref')
                ->where('ref_id', $refId)
                ->where('business_id', $businessId)
                ->update(['order_status' => 'selesai']);
            return response()->json(['success' => true, 'finalized' => true]);
        }
        DB::connection('salon')->table('ledger')->insert([
            'business_id' => $businessId,
            'type' => 'sale',
            'source' => 'tokopay',
            'target' => 'payment_gateway',
            'amount' => (float) $record,
            'ref_id' => $refId,
        ]);
        $sumPaid2 = DB::connection('salon')->table('ledger')
            ->where('business_id', $businessId)
            ->where('ref_id', $refId)
            ->where('type', 'sale')
            ->selectRaw('SUM(amount) as paid_total')
            ->value('paid_total');
        $paidTotal2 = (float) ($sumPaid2 ?? 0);
        $finalized = $paidTotal2 >= $total && $total > 0;
        if ($finalized) {
            DB::connection('salon')->table('sale_ref')
                ->where('ref_id', $refId)
                ->where('business_id', $businessId)
                ->update(['order_status' => 'selesai']);
        }
        return response()->json([
            'success' => true,
            'recorded' => $record,
            'finalized' => $finalized,
        ]);
    }
}

