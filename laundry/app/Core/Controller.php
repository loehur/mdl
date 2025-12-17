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
}
