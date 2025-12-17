<?php

namespace App\Modules\Beauty_Salon\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    private function generateId(): string
    {
        $prefix = (int) date('Y') - 2024;
        $suffix = date('mdHis') . (string) random_int(0, 9);
        return (string) $prefix . $suffix;
    }

    public function create(Request $request)
    {
        $businessId = (int) ($request->input('business_id') ?? 0);
        $userId = (int) ($request->input('id_user') ?? 0);
        $customerId = (string) ($request->input('customer_id') ?? '');
        $name = trim((string) ($request->input('name') ?? ''));
        $phone = trim((string) ($request->input('phone_number') ?? ''));
        $items = $request->input('items');

        if ($businessId <= 0 || $userId <= 0 || !is_array($items) || empty($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $u = DB::connection('salon')->table('users')
            ->select(['id', 'business_id', 'role'])
            ->where('id', $userId)
            ->first();
        if (!$u || (int) $u->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        // Resolve customer
        if ($customerId === '') {
            if ($name === '' || $phone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Nama dan nomor HP wajib',
                ], 422);
            }
            $customerId = $this->generateId();
            DB::connection('salon')->table('customer')->insert([
                'customer_id' => $customerId,
                'name' => $name,
                'phone_number' => $phone,
                'business_id' => $businessId,
            ]);
        } else {
            $exists = DB::connection('salon')->table('customer')
                ->where('customer_id', $customerId)
                ->where('business_id', $businessId)
                ->exists();
            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer tidak ditemukan',
                ], 404);
            }
        }

        // Validate items and collect price
        $lineItems = [];
        foreach ($items as $it) {
            $pid = (string) ($it['product_id'] ?? '');
            $qty = (int) ($it['qty'] ?? 0);
            if ($pid === '' || $qty <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item tidak valid',
                ], 422);
            }
            $priceRow = DB::connection('salon')->table('product')
                ->select(['price', 'type'])
                ->where('product_id', $pid)
                ->first();
            if (!$priceRow) {
                return response()->json([
                    'success' => false,
                    'message' => 'Harga tidak ditemukan untuk produk',
                ], 422);
            }
            $lineItems[] = [
                'product_id' => $pid,
                'price' => (float) $priceRow->price,
                'qty' => $qty,
                'type' => in_array(strtolower((string) ($priceRow->type ?? 'barang')), ['barang', 'jasa'], true)
                    ? (string) $priceRow->type : 'barang',
            ];
        }

        // Create sale
        $refId = $this->generateId();
        try {
            DB::connection('salon')->beginTransaction();
            DB::connection('salon')->table('sale_ref')->insert([
                'ref_id' => $refId,
                'business_id' => $businessId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'order_status' => 'berjalan',
            ]);
            foreach ($lineItems as $li) {
                DB::connection('salon')->table('sale_item')->insert([
                    'ref_id' => $refId,
                    'product_id' => $li['product_id'],
                    'type' => (string) ($li['type'] ?? 'barang'),
                    'price' => (float) $li['price'],
                    'qty' => (int) $li['qty'],
                ]);
            }
            DB::connection('salon')->commit();
        } catch (\Throwable $e) {
            DB::connection('salon')->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat order',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order dibuat',
            'ref_id' => $refId,
            'items' => $lineItems,
        ]);
    }

    public function listRunning(Request $request)
    {
        $businessId = (int) $request->query('business_id');
        if ($businessId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'business_id wajib',
            ], 422);
        }
        $rows = DB::connection('salon')->table('sale_ref as r')
            ->leftJoin('customer as c', 'c.customer_id', '=', 'r.customer_id')
            ->select(['r.ref_id', 'r.customer_id', 'r.order_status', 'r.created_at', 'c.name as customer_name', 'c.phone_number'])
            ->where('r.business_id', $businessId)
            ->where('r.order_status', 'berjalan')
            ->orderBy('r.ref_id', 'desc')
            ->limit(200)
            ->get();
        $items = [];
        foreach ($rows as $r) {
            $cnt = DB::connection('salon')->table('sale_item')
                ->where('ref_id', $r->ref_id)
                ->count();
            $sum = DB::connection('salon')->table('sale_item')
                ->where('ref_id', $r->ref_id)
                ->selectRaw('SUM(price * qty) as total_amount')
                ->value('total_amount');
            $items[] = [
                'ref_id' => (string) ($r->ref_id ?? ''),
                'customer_name' => (string) ($r->customer_name ?? ''),
                'phone_number' => (string) ($r->phone_number ?? ''),
                'total_items' => (int) $cnt,
                'total_amount' => (float) ($sum ?? 0),
                'order_date' => (string) ($r->created_at ?? ''),
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ]);
    }

    public function listHistory(Request $request)
    {
        $businessId = (int) $request->query('business_id');
        $date = (string) ($request->query('date') ?? '');
        $startDate = (string) ($request->query('start_date') ?? '');
        $endDate = (string) ($request->query('end_date') ?? '');
        if ($businessId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'business_id wajib',
            ], 422);
        }
        $start = null;
        $end = null;
        if ($date !== '') {
            $start = $date . ' 00:00:00';
            $end = $date . ' 23:59:59';
        } elseif ($startDate !== '' || $endDate !== '') {
            $sd = $startDate !== '' ? $startDate : $endDate;
            $ed = $endDate !== '' ? $endDate : $startDate;
            $start = $sd . ' 00:00:00';
            $end = $ed . ' 23:59:59';
        } else {
            $today = date('Y-m-d');
            $start = $today . ' 00:00:00';
            $end = $today . ' 23:59:59';
        }
        $qb = DB::connection('salon')->table('sale_ref as r')
            ->leftJoin('customer as c', 'c.customer_id', '=', 'r.customer_id')
            ->select(['r.ref_id', 'r.customer_id', 'r.order_status', 'r.created_at', 'c.name as customer_name', 'c.phone_number'])
            ->where('r.business_id', $businessId)
            ->where('r.order_status', 'selesai');
        if ($start && $end) {
            $qb = $qb->whereBetween('r.created_at', [$start, $end]);
        }
        $rows = $qb->orderBy('r.created_at', 'desc')->limit(500)->get();
        $items = [];
        foreach ($rows as $r) {
            $cnt = DB::connection('salon')->table('sale_item')
                ->where('ref_id', $r->ref_id)
                ->count();
            $sum = DB::connection('salon')->table('sale_item')
                ->where('ref_id', $r->ref_id)
                ->selectRaw('SUM(price * qty) as total_amount')
                ->value('total_amount');
            $items[] = [
                'ref_id' => (string) ($r->ref_id ?? ''),
                'customer_name' => (string) ($r->customer_name ?? ''),
                'phone_number' => (string) ($r->phone_number ?? ''),
                'total_items' => (int) $cnt,
                'total_amount' => (float) ($sum ?? 0),
                'order_date' => (string) ($r->created_at ?? ''),
            ];
        }
        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => count($items),
            'range' => [
                'start' => $start,
                'end' => $end,
            ],
        ]);
    }

    public function detail(Request $request)
    {
        $businessId = (int) $request->query('business_id');
        $refId = (string) $request->query('ref_id');
        if ($businessId <= 0 || $refId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Parameter tidak valid',
            ], 422);
        }
        $ref = DB::connection('salon')->table('sale_ref as r')
            ->leftJoin('customer as c', 'c.customer_id', '=', 'r.customer_id')
            ->select(['r.ref_id', 'r.business_id', 'r.user_id', 'r.customer_id', 'r.order_status', 'c.name as customer_name', 'c.phone_number'])
            ->where('r.ref_id', $refId)
            ->where('r.business_id', $businessId)
            ->first();
        if (!$ref) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan',
            ], 404);
        }
        $rows = DB::connection('salon')->table('sale_item as si')
            ->leftJoin('product as p', 'p.product_id', '=', 'si.product_id')
            ->select(['si.id as item_id', 'si.product_id', 'si.price', 'si.qty', 'p.name', DB::raw('si.type as type')])
            ->where('si.ref_id', $refId)
            ->limit(1000)
            ->get();
        $items = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $line = (float) ($r->price ?? 0) * (int) ($r->qty ?? 0);
            $total += $line;
            $items[] = [
                'item_id' => (int) ($r->item_id ?? 0),
                'product_id' => (string) ($r->product_id ?? ''),
                'name' => (string) ($r->name ?? ''),
                'type' => (string) ($r->type ?? ''),
                'price' => (float) ($r->price ?? 0),
                'qty' => (int) ($r->qty ?? 0),
                'amount' => (float) $line,
            ];
        }
        $payRows = DB::connection('salon')->table('ledger')
            ->select(['amount', 'created_at'])
            ->where('business_id', $businessId)
            ->where('ref_id', $refId)
            ->where('type', 'sale')
            ->orderBy('created_at', 'asc')
            ->limit(1000)
            ->get();
        $payments = [];
        $paidTotal = 0.0;
        foreach ($payRows as $p) {
            $amt = (float) ($p->amount ?? 0);
            $paidTotal += $amt;
            $payments[] = [
                'paid_amount' => $amt,
                'created_at' => (string) ($p->created_at ?? ''),
            ];
        }
        $remaining = $total - $paidTotal;
        if ($remaining < 0) $remaining = 0.0;
        $rid = (string) $refId;
        $year = 2024 + (int) substr($rid, 0, 1);
        $sfx = substr($rid, 1);
        $mm = (int) substr($sfx, 0, 2);
        $dd = (int) substr($sfx, 2, 2);
        $HH = (int) substr($sfx, 4, 2);
        $ii = (int) substr($sfx, 6, 2);
        $ss = (int) substr($sfx, 8, 2);
        $orderDate = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $mm, $dd, $HH, $ii, $ss);

        return response()->json([
            'success' => true,
            'ref_id' => (string) $ref->ref_id,
            'order_date' => $orderDate,
            'customer' => [
                'customer_id' => (string) ($ref->customer_id ?? ''),
                'name' => (string) ($ref->customer_name ?? ''),
                'phone_number' => (string) ($ref->phone_number ?? ''),
            ],
            'items' => $items,
            'total' => $total,
            'payments' => $payments,
            'paid_total' => $paidTotal,
            'remaining' => $remaining,
        ]);
    }

    public function pay(Request $request)
    {
        $businessId = (int) ($request->input('business_id') ?? 0);
        $userId = (int) ($request->input('id_user') ?? 0);
        $refId = (string) ($request->input('ref_id') ?? '');
        $amount = (float) ($request->input('amount') ?? 0);
        if ($businessId <= 0 || $userId <= 0 || $refId === '' || $amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
            ], 422);
        }
        $u = DB::connection('salon')->table('users')
            ->select(['id', 'business_id'])
            ->where('id', $userId)
            ->first();
        if (!$u || (int) $u->business_id !== $businessId) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }
        $ref = DB::connection('salon')->table('sale_ref')
            ->select(['ref_id', 'business_id', 'order_status', 'customer_id'])
            ->where('ref_id', $refId)
            ->where('business_id', $businessId)
            ->first();
        if (!$ref) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan',
            ], 404);
        }
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
        $remainingBefore = $total - $paidTotal;
        if ($remainingBefore < 0) $remainingBefore = 0.0;
        $finalized = false;
        $amountToRecord = $amount;
        if ($amountToRecord > $remainingBefore) {
            $amountToRecord = $remainingBefore;
        }
        $paidEnoughAfter = ($paidTotal + $amountToRecord) >= $total;
        if ($paidEnoughAfter) {
            DB::connection('salon')->table('sale_ref')
                ->where('ref_id', $refId)
                ->where('business_id', $businessId)
                ->update(['order_status' => 'selesai']);
            $finalized = true;
        }
        $change = $amount - $amountToRecord;
        if ($change < 0) $change = 0.0;
        try {
            DB::connection('salon')->table('ledger')->insert([
                'business_id' => $businessId,
                'type' => 'sale',
                'source' => (string) ($ref->customer_id ?? ''),
                'target' => 'cashier',
                'amount' => (float) $amountToRecord,
                'ref_id' => $refId,
            ]);
        } catch (\Throwable $e) {
        }
        return response()->json([
            'success' => true,
            'message' => 'Pembayaran diproses',
            'paid_enough' => $paidEnoughAfter,
            'finalized' => $finalized,
            'total' => $total,
            'change' => $change,
        ]);
    }
}
