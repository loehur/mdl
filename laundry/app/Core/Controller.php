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
            $sid = trim((string) ($s['id_penjualan'] ?? ''));
            if ($sid === '' || $sid === '0') {
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

        $escapedIds = array_map(function ($id) use ($db) {
            return "'" . $db->escape($id) . "'";
        }, array_values($saleIds));
        $idsIn = implode(',', $escapedIds);
        $ops = $db->get_where('operasi', $this->wCabang . " AND id_penjualan IN ($idsIn)");
        $opsMap = [];
        foreach ((array) $ops as $o) {
            $oid = trim((string) ($o['id_penjualan'] ?? ''));
            $jenis = (string) ($o['jenis_operasi'] ?? '');
            if ($oid !== '' && $jenis !== '') {
                $opsMap[$oid][$jenis] = true;
            }
        }
        foreach ($sales as $s) {
            $sid = trim((string) ($s['id_penjualan'] ?? ''));
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

        $deliveryCheck = $this->refDeliveryTuntasCheck($ref);
        if (!$deliveryCheck['ok']) {
            $this->model('Log')->write(
                "[tuntasOrder] SKIP ref=$ref: " . ($deliveryCheck['block'] ?: 'delivery')
                . ' — ' . ($deliveryCheck['message'] ?? '')
            );
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
     * Status binding surcas kurir (jemput/antar) per item di satu no_ref.
     *
     * @return array{
     *   sale_ids: int[],
     *   id_pelanggan: int,
     *   has_jemput: bool,
     *   has_antar: bool,
     *   bound_jemput: bool,
     *   bound_antar: bool,
     *   fully_bound: bool,
     *   surcas_rows: array
     * }
     */
    protected function refSurcasBindingStatus(string $ref): array
    {
        $ref = trim($ref);
        $empty = [
            'sale_ids' => [],
            'id_pelanggan' => 0,
            'has_jemput' => false,
            'has_antar' => false,
            'bound_jemput' => true,
            'bound_antar' => true,
            'fully_bound' => false,
            'surcas_rows' => [],
        ];
        if ($ref === '') {
            return $empty;
        }

        $this->helper('AntarTarif');
        $this->helper('SurcasKurir');
        $jenisAntar = (int) AntarTarif::SURCAS_JENIS_PENGANTARAN;
        $jenisJemput = (int) AntarTarif::SURCAS_JENIS_PENJEMPUTAN;

        $db = $this->db(0);
        $refSql = "'" . $db->escape($ref) . "'";
        $sales = $db->get_where('sale', $this->wCabang . " AND no_ref = $refSql AND bin = 0");
        if (!is_array($sales) || $sales === []) {
            return $empty;
        }

        $saleIds = [];
        $idPelanggan = 0;
        foreach ($sales as $s) {
            $sid = (int) ($s['id_penjualan'] ?? 0);
            if ($sid > 0) {
                $saleIds[$sid] = $sid;
            }
            if ($idPelanggan <= 0) {
                $idPelanggan = (int) ($s['id_pelanggan'] ?? 0);
            }
        }

        $surcas = $db->get_where(
            'surcas',
            $this->wCabang . " AND transaksi_jenis = 1 AND no_ref = $refSql"
            . " AND id_jenis_surcas IN ($jenisAntar, $jenisJemput)"
        );
        if (!is_array($surcas)) {
            $surcas = [];
        }

        $hasJemput = false;
        $hasAntar = false;
        foreach ($surcas as $sc) {
            $jid = (int) ($sc['id_jenis_surcas'] ?? 0);
            if ($jid === $jenisJemput) {
                $hasJemput = true;
            } elseif ($jid === $jenisAntar) {
                $hasAntar = true;
            }
        }

        $saleIdsList = array_values($saleIds);
        $boundJemput = true;
        $boundAntar = true;
        if ($hasJemput && $saleIdsList !== []) {
            $boundJ = SurcasKurir::boundSaleIds($db, $saleIdsList, $jenisJemput);
            foreach ($saleIdsList as $id) {
                if (!isset($boundJ[(int) $id])) {
                    $boundJemput = false;
                    break;
                }
            }
        }
        if ($hasAntar && $saleIdsList !== []) {
            $boundA = SurcasKurir::boundSaleIds($db, $saleIdsList, $jenisAntar);
            foreach ($saleIdsList as $id) {
                if (!isset($boundA[(int) $id])) {
                    $boundAntar = false;
                    break;
                }
            }
        }

        $hasKurirSurcas = $hasJemput || $hasAntar;
        if (!$hasKurirSurcas) {
            $fullyBound = false;
        } else {
            $fullyBound = (!$hasJemput || $boundJemput) && (!$hasAntar || $boundAntar);
        }

        return [
            'sale_ids' => $saleIdsList,
            'id_pelanggan' => $idPelanggan,
            'has_jemput' => $hasJemput,
            'has_antar' => $hasAntar,
            'bound_jemput' => $boundJemput,
            'bound_antar' => $boundAntar,
            'fully_bound' => $fullyBound,
            'surcas_rows' => $surcas,
        ];
    }

    /**
     * Ada delivery_request aktif milik pelanggan di cabang ini?
     * Status pending tidak dihitung — tidak memblokir tuntas.
     */
    protected function pelangganHasPendingDeliveryRequest(int $idPelanggan): bool
    {
        if ($idPelanggan <= 0) {
            return false;
        }

        $idCabang = (int) ($this->id_cabang ?? 0);
        $where = 'id_pelanggan = ' . $idPelanggan
            . " AND delivery_status IN ('berjalan','menunggu_pembayaran')";
        if ($idCabang > 0) {
            $where .= ' AND id_cabang = ' . $idCabang;
        }

        try {
            $count = $this->db(0)->count_where('delivery_request', $where);
            return is_numeric($count) && (int) $count > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Gate delivery/surcas sebelum ref dituntaskan.
     *
     * @return array{ok: bool, message: string, fully_bound: bool, block: string}
     */
    protected function refDeliveryTuntasCheck(string $ref): array
    {
        $ref = trim($ref);
        if ($ref === '') {
            return ['ok' => true, 'message' => '', 'fully_bound' => true, 'block' => ''];
        }

        $bind = $this->refSurcasBindingStatus($ref);
        $fullyBound = (bool) ($bind['fully_bound'] ?? false);

        if (!$fullyBound) {
            $idPel = (int) ($bind['id_pelanggan'] ?? 0);
            if ($this->pelangganHasPendingDeliveryRequest($idPel)) {
                return [
                    'ok' => false,
                    'message' => 'Selesaikan delivery request pelanggan yang masih menggantung sebelum menuntaskan nota.',
                    'fully_bound' => false,
                    'block' => 'pending_dr_pelanggan',
                ];
            }

            return ['ok' => true, 'message' => '', 'fully_bound' => false, 'block' => ''];
        }

        $needJemput = (bool) ($bind['has_jemput'] ?? false);
        $needAntar = (bool) ($bind['has_antar'] ?? false);
        if (!$needJemput && !$needAntar) {
            return ['ok' => true, 'message' => '', 'fully_bound' => true, 'block' => ''];
        }

        $db = $this->db(0);
        $reqIds = [];
        foreach ((array) ($bind['surcas_rows'] ?? []) as $sc) {
            $rid = (int) ($sc['id_delivery_request'] ?? 0);
            if ($rid > 0) {
                $reqIds[$rid] = $rid;
            }
        }

        if ($reqIds !== []) {
            $reqIn = implode(',', $reqIds);
            $aktif = $db->count_where(
                'delivery_request',
                "id_request IN ($reqIn) AND delivery_status IN ('berjalan','menunggu_pembayaran')"
            );
            if ((int) $aktif > 0) {
                return [
                    'ok' => false,
                    'message' => 'Delivery request terikat surcas masih berjalan — selesaikan terlebih dahulu.',
                    'fully_bound' => true,
                    'block' => 'active_dr_surcas',
                ];
            }
        }

        $saleIds = (array) ($bind['sale_ids'] ?? []);
        if ($saleIds === []) {
            return [
                'ok' => false,
                'message' => 'Data item nota tidak ditemukan.',
                'fully_bound' => true,
                'block' => 'no_sales',
            ];
        }
        $idsIn = implode(',', array_map('intval', $saleIds));

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

        $tunggu = [];
        if ($needJemput && !$hasJ) {
            $tunggu[] = 'jemput';
        }
        if ($needAntar && !$hasA) {
            $tunggu[] = 'antar';
        }
        if ($tunggu !== []) {
            return [
                'ok' => false,
                'message' => 'Menunggu delivery ' . implode('/', $tunggu) . ' selesai.',
                'fully_bound' => true,
                'block' => 'delivery_riwayat',
            ];
        }

        try {
            $rows = $db->query_array(
                "SELECT drq.jenis
                 FROM delivery_request_item dri
                 INNER JOIN delivery_request drq ON drq.id_request = dri.id_request
                 WHERE dri.id_penjualan IN ($idsIn)
                   AND drq.delivery_status IN ('berjalan','menunggu_pembayaran')"
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $j = strtolower((string) ($row['jenis'] ?? ''));
                    if ($needJemput && $j === 'jemput') {
                        return [
                            'ok' => false,
                            'message' => 'Delivery request jemput item nota masih berjalan.',
                            'fully_bound' => true,
                            'block' => 'active_dr_item',
                        ];
                    }
                    if ($needAntar && $j === 'antar') {
                        return [
                            'ok' => false,
                            'message' => 'Delivery request antar item nota masih berjalan.',
                            'fully_bound' => true,
                            'block' => 'active_dr_item',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // tabel request belum ada — cukup cek riwayat
        }

        return ['ok' => true, 'message' => '', 'fully_bound' => true, 'block' => ''];
    }

    /**
     * Ada surcas jemput (3) / antar (2)? Delivery jenis itu harus sudah selesai
     * (riwayat ada, request tidak masih berjalan) sebelum ref boleh dituntaskan.
     */
    protected function refDeliverySelesaiUntukTuntas($ref): bool
    {
        return $this->refDeliveryTuntasCheck((string) $ref)['ok'];
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
