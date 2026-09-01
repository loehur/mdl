<?php
namespace App\Helpers\Payment;

use App\Core\DB;
use App\Helpers\BcaMutasiMatcher;
use App\Helpers\BcaQrisMatcher;
use App\Helpers\BcaScrapper;

class ManualBindService
{
    private const DAYS = 6;
    private const ENTITY = 'manual_bind';

    public static function create(string $method, int $amount, string $phone): array
    {
        $method = strtolower(trim($method)); $amount = (int) $amount;
        if (!in_array($method, ['bca','qris'], true) || $amount < 1) return ['ok'=>false,'message'=>'Metode atau nominal tidak valid'];
        $db = DB::getInstance(0); self::expire($db);
        if (!self::isAmountAvailable($method, $amount, $db)) return ['ok'=>false,'message'=>'Nominal Rp'.number_format($amount,0,',','.').' tidak tersedia'];
        $code = 'BND-'.strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $insert = $db->insert('payment_manual_binds', ['bind_code'=>$code,'payment_method'=>$method,'amount'=>$amount,'status'=>'pending','requested_by_phone'=>$phone,'expires_at'=>date('Y-m-d H:i:s',strtotime('+'.self::DAYS.' days'))]);
        // DB::insert() returns the new numeric ID, or false on failure (not an errno array).
        if ($insert === false) return ['ok'=>false,'message'=>'Gagal membuat bind'];
        return ['ok'=>true,'code'=>$code,'amount'=>$amount,'method'=>$method,'expires_at'=>date('d/m/Y H:i',strtotime('+'.self::DAYS.' days'))];
    }
    public static function list(string $method, string $phone): array
    {
        $db=DB::getInstance(0); self::expire($db); $rows=$db->query('SELECT bind_code, amount, created_at, expires_at FROM payment_manual_binds WHERE requested_by_phone = ? AND payment_method = ? AND status = ? ORDER BY created_at DESC',[$phone,strtolower($method),'pending'])->result_array(); return is_array($rows)?$rows:[];
    }
    public static function cancel(string $code, string $phone): bool { $db=DB::getInstance(0); $r=$db->update('payment_manual_binds',['status'=>'cancelled','cancelled_at'=>date('Y-m-d H:i:s')],['bind_code'=>strtoupper($code),'requested_by_phone'=>$phone,'status'=>'pending']); return $r === true && $db->affected_rows() > 0; }
    public static function status(string $code, string $phone): ?array { $db=DB::getInstance(0); self::expire($db); $r=$db->query('SELECT bind_code, payment_method, amount, status, created_at, expires_at, paid_at FROM payment_manual_binds WHERE bind_code=? AND requested_by_phone=? LIMIT 1',[strtoupper($code),$phone])->row_array(); return is_array($r)?$r:null; }
    public static function confirmPendingBca($db): int
    {
        self::expire($db); $rows=$db->query("SELECT * FROM payment_manual_binds WHERE status='pending' AND payment_method='bca' AND expires_at >= NOW() ORDER BY created_at ASC")->result_array(); $n=0;
        foreach ($rows?:[] as $r) { $m=BcaMutasiMatcher::findUnlinkedMatch($db,(string)$r['amount'],date('Y-m-d',strtotime('-6 days')),date('Y-m-d'),false,(string)$r['created_at']); if (!$m) continue; if (!BcaMutasiMatcher::bindMutasi($db,(int)$m['id'],self::ENTITY,(string)$r['bind_code'],$r['amount'],$m['nominal']??null)) continue; $db->update('payment_manual_binds',['status'=>'paid','bca_mutasi_id'=>(int)$m['id'],'paid_at'=>date('Y-m-d H:i:s')],['id'=>(int)$r['id'],'status'=>'pending']); $n++; }
        return $n;
    }
    public static function confirmPendingQris($db): int
    {
        self::expire($db); $rows=$db->query("SELECT * FROM payment_manual_binds WHERE status='pending' AND payment_method='qris' AND expires_at >= NOW() ORDER BY created_at ASC")->result_array(); $n=0;
        foreach ($rows?:[] as $r) { $q=BcaQrisMatcher::findUnlinkedMatch($db,(string)$r['amount'],date('Y-m-d',strtotime('-6 days')),date('Y-m-d'),false); if (!$q) continue; if (!BcaQrisMatcher::bindQris($db,(int)$q['id'],self::ENTITY,(string)$r['bind_code'],$r['amount'],$q['nominal']??null)) continue; $db->update('payment_manual_binds',['status'=>'paid','bca_qris_id'=>(int)$q['id'],'paid_at'=>date('Y-m-d H:i:s')],['id'=>(int)$r['id'],'status'=>'pending']); $n++; }
        return $n;
    }
    private static function expire($db): void { $db->query("UPDATE payment_manual_binds SET status='expired' WHERE status='pending' AND expires_at < NOW()"); }
    public static function isAmountAvailable(string $method, int $amount, $db = null): bool
    { $db=$db?:DB::getInstance(0); $tol=1000; $r=$db->query("SELECT id FROM payment_manual_binds WHERE status='pending' AND payment_method=? AND amount BETWEEN ? AND ? LIMIT 1",[$method,$amount-$tol,$amount+$tol])->row_array(); if ($r) return false; if ($method==='qris') { $r=$db->query("SELECT id FROM qris_nominal_reservations WHERE state='pending' AND amount BETWEEN ? AND ? LIMIT 1",[$amount-$tol,$amount+$tol])->row_array(); return empty($r); } try { foreach (BcaUniqueNominal::collectUsedAmounts(DB::getInstance(6), DB::getInstance(4), DB::getInstance(1), DB::getInstance(2)) as $used) if (abs($amount-(int)$used) <= $tol) return false; } catch (\Throwable $e) {} return true; }
}
