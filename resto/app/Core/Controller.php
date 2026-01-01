<?php

require 'app/Config/URL.php';

class Controller extends URL
{
    public $id_user, $book, $nama_user, $id_cabang, $id_privilege, $wCabang;

    public function operating_data()
    {
        if (isset($_SESSION['resto_login'])) {
            if ($_SESSION['resto_login'] == true) {
                $this->id_user = $_SESSION['resto_user']['id_user'];
                $this->nama_user = $_SESSION['resto_user']['nama_user'];
                $this->id_cabang = $_SESSION['resto_user']['id_cabang'];
                $this->id_privilege = $_SESSION['resto_user']['id_privilege'];
                $this->wCabang = 'id_cabang = ' . $this->id_cabang;
                $this->book = $_SESSION['resto_user']['book'];
            }
        }
    }

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

    public function data($file)
    {
        require_once "app/Data/" . $file . ".php";
        return new $file();
    }

    public function db($db = 0)
    {
        $file = "M_DB";
        require_once "app/Models/" . $file . ".php";
        return new $file($db);
    }

    public function session_cek($admin = 0)
    {
        if (isset($_SESSION['resto_login'])) {
            if ($_SESSION['resto_login'] == False) {
                session_destroy();
                header("location: " . URL::BASE_URL . "Login");
            } else {
                if ($admin == 1) {
                    if ($_SESSION['resto_user']['id_privilege'] <> 100) {
                        session_destroy();
                        header("location: " . URL::BASE_URL . "Login");
                    }
                }
                if ($admin == 2) {
                    if ($_SESSION['resto_user']['id_privilege'] <> 100 && $_SESSION['resto_user']['id_privilege'] <> 12) {
                        session_destroy();
                        header("location: " . URL::BASE_URL . "Login");
                    }
                }
            }
        } else {
            header("location: " . URL::BASE_URL . "Login");
        }
    }

    public function parameter($data_user)
    {
        $_SESSION['resto_user'] = $data_user;
        $wCabang = "id_cabang = " . $data_user['id_cabang'];

        $_SESSION['resto_users'] = $this->db(0)->get_where('user', $wCabang . " AND en = 1", "id_user");
        $_SESSION['resto_cabang'] = $this->db(0)->get_where_row('cabang', $wCabang);
        $_SESSION['resto_cabangs'] = $this->db(0)->get('cabang', 'id_cabang');
        $_SESSION['resto_privilege'] = $this->db(0)->get('privilege', 'id_privilege');
        $_SESSION['resto_menu'] = $this->db(0)->get_where('menu_item', $wCabang . " ORDER BY freq DESC", 'id');
        $_SESSION['resto_menu_byKat'] = $this->db(0)->get_where('menu_item', $wCabang . " ORDER BY freq DESC", 'id_kategori', 1);
        $_SESSION['resto_kat'] = $this->db(0)->get_where('menu_kategori', $wCabang . " ORDER BY freq DESC", 'id');
    }

    public function dataSynchrone($id_user)
    {
        $where = "id_user = " . $id_user;
        $data_user = $this->db(0)->get_where_row('user', $where);
        $this->parameter($data_user);
        return $data_user;
    }

    function valid_number($number)
    {
        if (!is_numeric($number)) {
            $number = preg_replace('/[^0-9]/', '', $number);
        }

        if (substr($number, 0, 1) == '8') {
            if (strlen($number) >= 7 && strlen($number) <= 14) {
                $fix_number = "0" . $number;
                return $fix_number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 2) == '08') {
            if (strlen($number) >= 8 && strlen($number) <= 15) {
                return $number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 3) == '628') {
            if (strlen($number) >= 9 && strlen($number) <= 16) {
                $fix_number = "0" . substr($number, 2);
                return $fix_number;
            } else {
                return false;
            }
        } else if (substr($number, 0, 4) == '+628') {
            if (strlen($number) >= 10 && strlen($number) <= 17) {
                $fix_number = "0" . substr($number, 3);
                return $fix_number;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
