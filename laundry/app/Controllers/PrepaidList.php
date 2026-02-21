<?php

/**
 * CRUD untuk prepaid_list
 * Tabel di db(100) = mdl_main
 * Filter by id_cabang session, bisnis hardcode laundry
 */
class PrepaidList extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Prepaid'];

        $table = 'prepaid_list';
        $id_cabang = (int)$this->id_cabang;
        $where = "bisnis = 'laundry' AND id_cabang = $id_cabang";
        $order = 'product_name ASC';
        $data_main = $this->db(100)->get_where_order($table, $where, $order);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('data_list/prepaid_list', ['data_main' => $data_main]);
    }

    public function insert()
    {
        $this->session_cek(1);
        $table = 'prepaid_list';

        $data = [
            'bisnis' => 'laundry',
            'product_code' => $_POST['product_code'],
            'product_name' => $_POST['product_name'],
            'customer_id' => $_POST['customer_id'],
            'nominal' => (int)$_POST['nominal'],
            'id_cabang' => (int)$this->id_cabang,
            'description' => $_POST['description'] ?? '',
            'monthly_limit' => (int)($_POST['monthly_limit'] ?? 0)
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
        $table = 'prepaid_list';
        $id = (int)$_POST['id'];
        $value = $_POST['value'];
        $mode = $_POST['mode'];

        $kolom = '';
        if ($mode == 1) {
            $kolom = 'product_code';
        } elseif ($mode == 2) {
            $kolom = 'product_name';
        } elseif ($mode == 3) {
            $kolom = 'customer_id';
        } elseif ($mode == 4) {
            $kolom = 'nominal';
        } elseif ($mode == 5) {
            $kolom = 'description';
        } elseif ($mode == 6) {
            $kolom = 'monthly_limit';
        }

        if (empty($kolom)) {
            echo 'Invalid mode';
            return;
        }

        if (in_array($kolom, ['nominal', 'monthly_limit'])) {
            $value = (int)$value;
        }

        $set = [$kolom => $value];
        $where = "pre_id = $id AND bisnis = 'laundry' AND id_cabang = " . (int)$this->id_cabang;
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
        $table = 'prepaid_list';
        $where = "pre_id = $id AND bisnis = 'laundry' AND id_cabang = " . (int)$this->id_cabang;
        $del = $this->db(100)->delete($table, $where);
        if ($del['errno'] == 0) {
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }
}
