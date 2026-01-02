<?php

class Pre extends Controller
{
    function bulan_ini($product_code)
    {
        $month = date("Y-m");
        $col = "price";
        $where = "insertTime LIKE '%" . $month . "%' AND product_code = '" . $product_code . "' AND tr_status <> 2 AND id_cabang = " . $_SESSION[URL::SESSID]['user']['id_cabang'];
        return $this->db(0)->sum_col_where('prepaid', $col, $where);
    }

    function get_post_month()
    {
        $month = date('Ym');
        $week = 1;
        $thi = date('d');
        if ($thi > 0 && $thi <= 5) {
            $week = 1;
        } else if ($thi > 5 && $thi <= 10) {
            $week = 2;
        } else if ($thi > 10 && $thi <= 15) {
            $week = 3;
        } else if ($thi > 15 && $thi <= 20) {
            $week = 4;
        } else if ($thi > 20 && $thi <= 25) {
            $week = 5;
        } else {
            $week = 6;
        }
        $month .= $week;
        return $month;
    }
}
