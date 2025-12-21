<?php

class User extends Controller
{
    function pin_today($username, $otp)
    {
        $today = date("Ymd");
        if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
            $where = "username = '" . $username . "' AND en = 1";
        } else {
            $where = "username = '" . $username . "' AND otp = '" . $otp . "' AND otp_active = '" . $today . "' AND en = 1";
        }
        return $this->db(0)->get_where_row('user', $where);
    }

    function pin_admin_today($otp)
    {
        $today = date("Ymd");
        $where = "id_privilege = 100 AND otp = '" . $otp . "' AND otp_active = '" . $today . "' AND en = 1";
        return $this->db(0)->count_where('user', $where);
    }

    function get_data_user($username)
    {
        $where = "username = '" . $username . "' AND en = 1";
        return $this->db(0)->get_where_row('user', $where);
    }

    function last_login($username)
    {
        $where = "username = '" . $username . "'";
        $dateTime = date('Y-m-d H:i:s');
        $set = ['last_login' => $dateTime, 'book' => date('Y')];
        $this->db(0)->update('user', $set, $where);
    }
}
