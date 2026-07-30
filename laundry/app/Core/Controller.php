<?php

require 'app/Config/URL.php';

class Controller extends URL
{
    use Attributes;

    public function view($file, $data = [])
    {
        $this->operating_data();
        require_once "app/Views/" . $file . ".php";
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

        $rows = $db->get_where('kas', $where);
        if (empty($rows)) {
            return;
        }

        $refFinances = [];
        foreach ($rows as $r) {
            if (!empty($r['ref_finance'])) {
                $refFinances[$r['ref_finance']] = true;
            }
        }

        $del = $db->delete('kas', $where);
        if (isset($del['errno']) && $del['errno'] != 0) {
            $this->model('Log')->write("[hapusKasPembayaranPengecekanOrder] Delete kas error ref=$ref: " . ($del['error'] ?? ''));
            return;
        }

        foreach (array_keys($refFinances) as $rf) {
            $rfEsc = $db->escape($rf);
            try {
                $this->db(100)->delete('wh_midtrans', "ref_id = '$rfEsc'");
            } catch (\Throwable $e) {
            }
        }
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
