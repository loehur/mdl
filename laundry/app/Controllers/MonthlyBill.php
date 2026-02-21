<?php

/**
 * CRUD untuk postpaid_list (Monthly Bill)
 * Tabel di db(100) = mdl_main, cabang dari db(0) = mdl_laundry
 */
class MonthlyBill extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Monthly Bill'];

        $table = 'postpaid_list';
        $id_cabang = (int)$this->id_cabang;
        $where = "bisnis = 'laundry' AND id_cabang = $id_cabang";
        $order = 'code ASC';
        $data_main = $this->db(100)->get_where_order($table, $where, $order);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('data_list/monthly_bill', ['data_main' => $data_main]);
    }

    public function insert()
    {
        $this->session_cek(1);
        $table = 'postpaid_list';

        $data = [
            'bisnis' => 'laundry',
            'code' => $_POST['code'],
            'customer_id' => $_POST['customer_id'],
            'id_cabang' => (int)$this->id_cabang,
            'description' => $_POST['description'],
            'last_bill' => null,
            'en' => 1
        ];

        $in = $this->db(100)->insert($table, $data);
        if ($in['errno'] == 0) {
            echo 0;
        } else {
            echo $in['error'] ?? 'Insert failed';
        }
    }

    public function update()
    {
        $this->session_cek(1);
        $table = 'postpaid_list';
        $id = (int)$_POST['id'];
        $value = $_POST['value'];
        $mode = $_POST['mode'];

        $kolom = '';
        if ($mode == 1) {
            $kolom = 'code';
        } elseif ($mode == 2) {
            $kolom = 'customer_id';
        } elseif ($mode == 3) {
            $kolom = 'description';
        } elseif ($mode == 4) {
            $kolom = 'en';
        }

        if (empty($kolom)) {
            echo 'Invalid mode';
            return;
        }

        if ($kolom == 'en') {
            $value = (int)$value;
        }

        $set = [$kolom => $value];
        $where = "bill_id = $id AND bisnis = 'laundry' AND id_cabang = " . (int)$this->id_cabang;
        $up = $this->db(100)->update($table, $set, $where);
        if ($up['errno'] == 0) {
            echo 0;
        } else {
            echo $up['error'] ?? 'Update failed';
        }
    }

    public function delete()
    {
        $this->session_cek(1);
        $id = (int)$_POST['id'];
        $table = 'postpaid_list';
        $where = "bill_id = $id AND bisnis = 'laundry' AND id_cabang = " . (int)$this->id_cabang;
        $del = $this->db(100)->delete($table, $where);
        if ($del['errno'] == 0) {
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }
}
