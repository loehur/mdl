<?php

/**
 * CRUD untuk reminder
 * Tabel di db(100) = mdl_main
 */
class Reminder extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Reminder'];

        $table = 'reminder';
        $order = 'next_date ASC';
        $data_main = $this->db(100)->get_order($table, $order);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('data_list/reminder', ['data_main' => $data_main]);
    }

    public function insert()
    {
        $this->session_cek(1);
        $table = 'reminder';

        $data = [
            'name' => $_POST['name'],
            'note' => $_POST['note'] ?? '',
            'next_date' => $_POST['next_date'],
            'cycle' => (int)$_POST['cycle'],
            'cycle_type' => $_POST['cycle_type'],
            'range' => (int)$_POST['range'],
            'notif_number' => '085278114125,081268098300'
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
        $table = 'reminder';
        $id = (int)$_POST['id'];
        $value = $_POST['value'];
        $mode = $_POST['mode'];

        $kolom = '';
        if ($mode == 1) {
            $kolom = 'name';
        } elseif ($mode == 2) {
            $kolom = 'note';
        } elseif ($mode == 3) {
            $kolom = 'next_date';
        } elseif ($mode == 4) {
            $kolom = 'cycle';
        } elseif ($mode == 5) {
            $kolom = 'cycle_type';
        } elseif ($mode == 6) {
            $kolom = 'range';
        } elseif ($mode == 7) {
            $kolom = 'notif_number';
        }

        if (empty($kolom)) {
            echo 'Invalid mode';
            return;
        }

        if (in_array($kolom, ['cycle', 'range'])) {
            $value = (int)$value;
        }

        $set = [$kolom => $value];
        $where = "id = $id";
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
        $table = 'reminder';
        $where = "id = $id";
        $del = $this->db(100)->delete($table, $where);
        if ($del['errno'] == 0) {
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }
}
