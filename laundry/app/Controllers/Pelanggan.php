<?php

class Pelanggan extends Controller
{
    public function __construct()
    {
        $this->session_cek();
        $this->operating_data();
    }

    public function index()
    {
        $data_operasi = ['title' => 'Data Pelanggan'];
        $data_main = $this->db(0)->get_where_order(
            'pelanggan',
            $this->wCabang,
            'id_pelanggan DESC'
        );
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('pelanggan/index', [
            'data_main' => $data_main,
            'z' => ['page' => 'pelanggan'],
        ]);
    }

    public function cekHp()
    {
        $this->jsonOut($this->daftar()->cekHpFromPost());
    }

    public function tambah()
    {
        $this->jsonOut($this->daftar()->tambahFromPost());
    }

    public function pilih()
    {
        $this->jsonOut($this->daftar()->pilihFromPost());
    }

    public function updateCell()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? '');
        $value = $_POST['value'] ?? '';
        if ($id < 1) {
            echo 'Pelanggan tidak valid';
            return;
        }

        $col = null;
        if ($mode === '1') {
            $col = 'nama_pelanggan';
            $value = is_string($value) ? trim($value) : $value;
        } elseif ($mode === '2') {
            $col = 'nomor_pelanggan';
            $value = preg_replace('/\D/', '', (string) $value);
        } elseif ($mode === '4') {
            $col = 'alamat';
        } elseif ($mode === '5') {
            $this->session_cek(1);
            $col = 'disc';
            $value = (float) $value;
            if ($value > 100) {
                $value = 100;
            }
        }
        if ($col === null) {
            echo 'Mode tidak valid';
            return;
        }

        $where = $this->wCabang . ' AND id_pelanggan = ' . $id;
        $up = $this->db(0)->update('pelanggan', [$col => $value], $where);
        echo $up['errno'] == 0 ? 0 : ($up['error'] ?? 'Gagal update');
        if ($up['errno'] == 0 && in_array($col, ['nama_pelanggan', 'nomor_pelanggan'], true)) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
    }

    /** @return PelangganDaftar */
    private function daftar()
    {
        return $this->helper('PelangganDaftar');
    }

    /** @param array<string,mixed> $payload */
    private function jsonOut(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
