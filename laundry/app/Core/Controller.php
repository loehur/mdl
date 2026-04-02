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

    public function session_cek($admin = 0)
    {
        if (isset($_SESSION[URL::SESSID])) {
            if ($_SESSION[URL::SESSID]['login'] == False) {
                session_destroy();
                header("location: " . URL::BASE_URL . "Login");
            } else {
                if ($admin == 1) {
                    if ($_SESSION[URL::SESSID]['user']['id_privilege'] <> 100) {
                        session_destroy();
                        header("location: " . URL::BASE_URL . "Login");
                    }
                }
                if ($admin == 2) {
                    if ($_SESSION[URL::SESSID]['user']['id_privilege'] <> 100 && $_SESSION[URL::SESSID]['user']['id_privilege'] <> 12) {
                        session_destroy();
                        header("location: " . URL::BASE_URL . "Login");
                    }
                }
            }
        } else {
            header("location: " . URL::BASE_URL . "Login");
        }
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
}
