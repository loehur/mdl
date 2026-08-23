<?php

require 'app/Config/URL.php';

class Controller extends URL
{
    use Attributes;

    public function view($file, $data = [])
    {
        $this->operating_data();
        require "app/Views/" . $file . ".php";
    }

    public function model($file)
    {
        require_once "app/Models/" . $file . ".php";
        return new $file();
    }

    public function helper($file)
    {
        require_once "app/Helper/" . $file . ".php";
        return new $file();
    }

    public function db($db = 0)
    {
        require_once "app/Core/DB.php";
        return DB::getInstance($db);
    }

    /**
     * Restore $_SESSION from auth cookie (MDLSESSID) when PHP session expired but cookie still valid.
     * @return bool true if session login restored
     */
    public function restore_session_from_cookie()
    {
        if (!empty($_SESSION[URL::SESSID]['login'])) {
            return true;
        }

        if (!isset($_COOKIE[URL::SESSID]) || $_COOKIE[URL::SESSID] === '' || $_COOKIE[URL::SESSID] === '0') {
            return false;
        }

        try {
            $cookie_value = $this->model("Enc")->dec_2($_COOKIE[URL::SESSID]);
            $user_data = @unserialize($cookie_value);
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($user_data) || empty($user_data['username']) || empty($user_data['no_user']) || empty($user_data['device'])) {
            return false;
        }

        $username = $this->model("Enc")->username($user_data['no_user']);
        $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($username !== $user_data['username'] || $user_data['device'] !== $device) {
            return false;
        }

        $_SESSION[URL::SESSID]['login'] = true;
        $_SESSION[URL::SESSID]['training'] = [
            'active' => false,
            'id_cabang_origin' => (int) ($user_data['id_cabang'] ?? 0),
        ];
        $this->parameter($user_data);
        $this->save_auth_cookie($user_data);
        return !empty($_SESSION[URL::SESSID]['login']) && !empty($_SESSION[URL::SESSID]['user']['id_user']);
    }

    public function save_auth_cookie($data_user)
    {
        $data_user['device'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $cookie_value = $this->model("Enc")->enc_2(serialize($data_user));
        setcookie(URL::SESSID, $cookie_value, time() + 86400, "/");
    }

    public function session_cek($admin = 0)
    {
        $loggedIn = !empty($_SESSION[URL::SESSID]['login']);

        // Session PHP hilang tapi cookie masih aktif → restore di sini, jangan redirect
        if (!$loggedIn) {
            $loggedIn = $this->restore_session_from_cookie();
        }

        if (!$loggedIn) {
            header("Location: " . URL::BASE_URL . "Login");
            exit;
        }

        if ($admin == 1) {
            if (($_SESSION[URL::SESSID]['user']['id_privilege'] ?? null) <> 100) {
                session_destroy();
                header("Location: " . URL::BASE_URL . "Login");
                exit;
            }
        }
        if ($admin == 2) {
            $priv = $_SESSION[URL::SESSID]['user']['id_privilege'] ?? null;
            if ($priv <> 100 && $priv <> 12) {
                session_destroy();
                header("Location: " . URL::BASE_URL . "Login");
                exit;
            }
        }
    }

    /** Privilege 12 = driver/kurir */
    public function isDriverPrivilege(): bool
    {
        return (int) ($this->id_privilege ?? $_SESSION[URL::SESSID]['user']['id_privilege'] ?? 0) === 12;
    }

    /**
     * Blok tambah cart/order baru untuk driver.
     * Saat ini dinonaktifkan: priv 12 boleh membuka/menambah order.
     * @return bool true jika diblok (response sudah dikirim)
     */
    public function blockDriverCartAdd(bool $asJson = false): bool
    {
        return false;
    }

    /**
     * Pembayaran order (kas jenis_transaksi = 1) dengan status_mutasi = 2 masih menunggu verifikasi.
     * Dipanggil saat order dituntaskan agar entri pending tidak tertinggal.
     * QRIS paid/pending/unknown tidak dihapus.
     */
    protected function hapusKasPembayaranPengecekanOrder($ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return;
        }

        $db = $this->db(0);
        $refSql = "'" . $db->escape($ref) . "'";
        $where = $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = $refSql AND status_mutasi = 2";

        $result = $this->deleteKasSafe($where, false);
        if (!$result['ok']) {
            $this->model('Log')->write("[hapusKasPembayaranPengecekanOrder] Delete kas error ref=$ref: " . ($result['error'] ?? ''));
            return;
        }

        $kept = (int) $result['kept_paid'] + (int) $result['kept_pending'] + (int) $result['kept_unknown'] + (int) $result['kept_lunas'];
        if ($kept > 0) {
            $this->model('Log')->write(
                "[hapusKasPembayaranPengecekanOrder] QRIS dilindungi ref=$ref"
                . " deleted={$result['deleted']} paid={$result['kept_paid']}"
                . " pending={$result['kept_pending']} unknown={$result['kept_unknown']}"
            );
        }
    }

    /**
     * Cek ulang di server: ref boleh dituntaskan hanya jika semua item cabang ini
     * lunas, layanan terakhir selesai, sudah diambil, dan delivery jemput/antar selesai.
     */
    protected function refEligibleTuntas($ref): bool
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return false;
        }

        $db = $this->db(0);
        $refSql = "'" . $db->escape($ref) . "'";
        $sales = $db->get_where('sale', $this->wCabang . " AND no_ref = $refSql AND bin = 0");
        if (!is_array($sales) || $sales === []) {
            $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: sale tidak ada");
            return false;
        }

        $pending = 0;
        $saleIds = [];
        $subTotal = 0;
        foreach ($sales as $s) {
            $sid = (int) ($s['id_penjualan'] ?? 0);
            if ($sid <= 0) {
                $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: id_penjualan tidak valid");
                return false;
            }
            $saleIds[$sid] = $sid;
            if ((int) ($s['tuntas'] ?? 0) === 0) {
                $pending++;
            }
            if ((int) ($s['id_user_ambil'] ?? 0) <= 0) {
                $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: item #$sid belum ambil");
                return false;
            }
            $subTotal += $this->saleItemSubtotal($s);
        }
        if ($pending < 1) {
            return false;
        }

        $idsIn = implode(',', $saleIds);
        $ops = $db->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($idsIn)");
        $opsMap = [];
        foreach ((array) $ops as $o) {
            $oid = (int) ($o['id_penjualan'] ?? 0);
            $jenis = (string) ($o['jenis_operasi'] ?? '');
            if ($oid > 0 && $jenis !== '') {
                $opsMap[$oid][$jenis] = true;
            }
        }
        foreach ($sales as $s) {
            $sid = (int) ($s['id_penjualan'] ?? 0);
            $list = @unserialize($s['list_layanan'] ?? '', ['allowed_classes' => false]);
            if (!is_array($list) || $list === []) {
                $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: item #$sid list_layanan tidak valid");
                return false;
            }
            $endLayanan = (string) end($list);
            if ($endLayanan === '' || empty($opsMap[$sid][$endLayanan])) {
                $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: item #$sid layanan terakhir belum selesai");
                return false;
            }
        }

        $surcas = $db->get_where('surcas', $this->wCabang . " AND no_ref = $refSql");
        foreach ((array) $surcas as $sc) {
            $subTotal += (int) ($sc['jumlah'] ?? 0);
        }

        $kas = $db->get_where(
            'kas',
            $this->wCabang . " AND jenis_transaksi = 1 AND ref_transaksi = $refSql"
        );
        $totalBayar = 0;
        foreach ((array) $kas as $ka) {
            if ((int) ($ka['status_mutasi'] ?? 0) === 3) {
                $totalBayar += (int) ($ka['jumlah'] ?? 0);
            }
        }
        if (((int) round($subTotal) - $totalBayar) >= 1) {
            $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: belum lunas");
            return false;
        }

        if (!$this->refDeliverySelesaiUntukTuntas($ref)) {
            $this->model('Log')->write("[tuntasOrder] SKIP ref=$ref: delivery jemput/antar belum selesai");
            return false;
        }

        return true;
    }

    protected function saleItemSubtotal(array $sale): int
    {
        if ((int) ($sale['member'] ?? 0) !== 0) {
            return 0;
        }
        $qty = round((float) ($sale['qty'] ?? 0), 2);
        $minOrder = round((float) ($sale['min_order'] ?? 0), 2);
        $qtyReal = ($qty < $minOrder) ? $minOrder : $qty;
        $total = (float) ($sale['harga'] ?? 0) * $qtyReal;
        $diskonQty = (float) ($sale['diskon_qty'] ?? 0);
        $diskonPartner = (float) ($sale['diskon_partner'] ?? 0);
        if ($diskonQty > 0) {
            $total -= $total * ($diskonQty / 100);
        }
        if ($diskonPartner > 0) {
            $total -= $total * ($diskonPartner / 100);
        }
        return (int) round($total);
    }

    /**
     * Ada surcas jemput (3) / antar (2)? Delivery jenis itu harus sudah selesai
     * (riwayat ada, request tidak masih berjalan) sebelum ref boleh dituntaskan.
     */
    protected function refDeliverySelesaiUntukTuntas($ref): bool
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return true;
        }

        $this->helper('AntarTarif');
        $jenisAntar = (int) AntarTarif::SURCAS_JENIS_PENGANTARAN;
        $jenisJemput = (int) AntarTarif::SURCAS_JENIS_PENJEMPUTAN;

        $db = $this->db(0);
        $refSql = "'" . $db->escape($ref) . "'";
        $surcas = $db->get_where(
            'surcas',
            $this->wCabang . " AND transaksi_jenis = 1 AND no_ref = $refSql"
            . " AND id_jenis_surcas IN ($jenisAntar, $jenisJemput)"
        );
        if (!is_array($surcas) || $surcas === []) {
            return true;
        }

        $needJemput = false;
        $needAntar = false;
        $reqIds = [];
        foreach ($surcas as $sc) {
            $jid = (int) ($sc['id_jenis_surcas'] ?? 0);
            if ($jid === $jenisJemput) {
                $needJemput = true;
            } elseif ($jid === $jenisAntar) {
                $needAntar = true;
            }
            $rid = (int) ($sc['id_delivery_request'] ?? 0);
            if ($rid > 0) {
                $reqIds[$rid] = $rid;
            }
        }

        if ($reqIds !== []) {
            $reqIn = implode(',', $reqIds);
            $aktif = $db->count_where(
                'delivery_request',
                "id_request IN ($reqIn) AND delivery_status IN ('berjalan','menunggu_pembayaran','pending')"
            );
            if ((int) $aktif > 0) {
                return false;
            }
        }

        $sales = $db->get_where('sale', $this->wCabang . " AND no_ref = $refSql AND bin = 0");
        $saleIds = [];
        foreach ((array) $sales as $s) {
            $sid = (int) ($s['id_penjualan'] ?? 0);
            if ($sid > 0) {
                $saleIds[$sid] = $sid;
            }
        }
        if ($saleIds === []) {
            return !$needJemput && !$needAntar;
        }
        $idsIn = implode(',', $saleIds);

        $hasJ = false;
        $hasA = false;
        $riwayat = $db->get_where('delivery_riwayat', "id_penjualan IN ($idsIn)");
        foreach ((array) $riwayat as $dr) {
            $jenis = strtolower((string) ($dr['jenis'] ?? ''));
            if ($jenis === 'jemput') {
                $hasJ = true;
            } elseif ($jenis === 'antar') {
                $hasA = true;
            }
        }
        if ($needJemput && !$hasJ) {
            return false;
        }
        if ($needAntar && !$hasA) {
            return false;
        }

        try {
            $rows = $db->query_array(
                "SELECT drq.jenis
                 FROM delivery_request_item dri
                 INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
                 WHERE dri.id_penjualan IN ($idsIn)
                   AND drq.delivery_status IN ('berjalan','menunggu_pembayaran','pending')"
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $j = strtolower((string) ($row['jenis'] ?? ''));
                    if ($needJemput && $j === 'jemput') {
                        return false;
                    }
                    if ($needAntar && $j === 'antar') {
                        return false;
                    }
                }
            }
        } catch (\Throwable $e) {
            // tabel request belum ada — cukup cek riwayat
        }

        return true;
    }

    /**
     * Qty / min order: tampil maks. 2 desimal; bilangan bulat dari DB tanpa ".00" / ",00".
     */
    public function fmtDecMax2($v)
    {
        $n = round((float) str_replace(',', '.', (string) $v), 2);
        $s = number_format($n, 2, '.', '');
        if (strpos($s, '.') !== false) {
            $s = rtrim($s, '0');
            $s = rtrim($s, '.');
        }
        return $s !== '' ? $s : '0';
    }
}
