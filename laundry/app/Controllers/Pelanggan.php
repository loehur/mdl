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
        } elseif ($mode === '6') {
            $col = 'nomor_pelanggan_2';
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
        if ($up['errno'] == 0 && in_array($col, ['nama_pelanggan', 'nomor_pelanggan', 'nomor_pelanggan_2'], true)) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
    }

    /** Simpan seluruh field via modal edit. */
    public function update()
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            echo 'Pelanggan tidak valid';
            return;
        }

        $nama = trim((string) ($_POST['nama_pelanggan'] ?? ''));
        $nomor = preg_replace('/\D/', '', (string) ($_POST['nomor_pelanggan'] ?? ''));
        $nomor2 = preg_replace('/\D/', '', (string) ($_POST['nomor_pelanggan_2'] ?? ''));
        $alamat = trim((string) ($_POST['alamat'] ?? ''));
        $disc = (float) ($_POST['disc'] ?? 0);
        if ($disc > 100) {
            $disc = 100;
        }
        if ($disc < 0) {
            $disc = 0;
        }

        if ($nama === '' || $nomor === '') {
            echo 'Nama dan nomor HP tidak boleh kosong';
            return;
        }

        $this->session_cek(); // akses halaman pelanggan sudah cukup; disc dikunci untuk privilege tertinggi

        $where = $this->wCabang . ' AND id_pelanggan = ' . $id;
        $set = [
            'nama_pelanggan' => $nama,
            'nomor_pelanggan' => $nomor,
            'nomor_pelanggan_2' => $nomor2 !== '' ? $nomor2 : null,
            'alamat' => $alamat,
        ];
        // disc hanya boleh diubah oleh privilege tertinggi (seperti updateCell mode 5).
        if ((int) ($this->id_privilege ?? 0) === 100) {
            $set['disc'] = $disc;
        }
        $up = $this->db(0)->update('pelanggan', $set, $where);
        if (($up['errno'] ?? 1) != 0) {
            echo $up['error'] ?? 'Gagal update';
            return;
        }

        $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        echo 0;
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
