<?php

class User extends Controller
{
    function pin_today($username, $otp)
    {
        if ($_SERVER['REMOTE_ADDR'] == '127.0.0.1' || $_SERVER['REMOTE_ADDR'] == '::1') {
            $where = "username = '" . $username . "' AND en = 1";
        } else {
            // Cek OTP dan apakah belum expired (5 menit validity)
            $now = date('Y-m-d H:i:s');
            $where = "username = '" . $username . "' AND otp = '" . $otp . "' AND otp_active >= '" . $now . "' AND en = 1";
        }
        return $this->db(0)->get_where_row('user', $where);
    }

    function pin_admin_today($otp)
    {
        // Cek OTP admin dan apakah belum expired (5 menit validity)
        $now = date('Y-m-d H:i:s');
        $where = "id_privilege = 100 AND otp = '" . $otp . "' AND otp_active >= '" . $now . "' AND en = 1";
        return $this->db(0)->count_where('user', $where);
    }

    function get_data_user($username)
    {
        $where = "username = '" . $username . "' AND en = 1";
        return $this->db(0)->get_where_row('user', $where);
    }

    /**
     * Login via Access Key (user.access_key, 4 digit plaintext).
     */
    function by_access_key($username, $accessKey)
    {
        $accessKey = trim((string) $accessKey);
        if (!preg_match('/^\d{4}$/', $accessKey)) {
            return null;
        }
        $username = $this->db(0)->escape($username);
        $keyEsc = $this->db(0)->escape($accessKey);
        $where = "username = '" . $username . "' AND access_key = '" . $keyEsc . "' AND en = 1";
        $row = $this->db(0)->get_where_row('user', $where);
        if (!is_array($row) || empty($row['id_user'])) {
            return null;
        }
        return $row;
    }

    /**
     * Verifikasi Access Key milik id_user tertentu (untuk Terima/Pakai, dll).
     */
    function by_id_access_key($idUser, $accessKey)
    {
        $idUser = (int) $idUser;
        $accessKey = trim((string) $accessKey);
        if ($idUser < 1 || !preg_match('/^\d{4}$/', $accessKey)) {
            return null;
        }
        $keyEsc = $this->db(0)->escape($accessKey);
        $row = $this->db(0)->get_where_row(
            'user',
            "id_user = $idUser AND access_key = '" . $keyEsc . "' AND en = 1"
        );
        if (!is_array($row) || empty($row['id_user'])) {
            return null;
        }
        return $row;
    }

    function last_login($username)
    {
        $where = "username = '" . $username . "'";
        $dateTime = date('Y-m-d H:i:s');
        $set = ['last_login' => $dateTime, 'book' => date('Y')];
        $this->db(0)->update('user', $set, $where);
    }
}
