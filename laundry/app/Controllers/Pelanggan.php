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
        $this->jsonOut($this->api()->cekHp((string) ($_POST['f2'] ?? ''), (int) $this->id_cabang));
    }

    public function tambah()
    {
        $res = $this->api()->tambah($_POST, (int) $this->id_cabang);
        if (!empty($res['ok'])) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
        $this->jsonOut($res);
    }

    public function pilih()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $nama = (string) ($_POST['nama'] ?? '');
        $res = $this->api()->pilih($id, $nama, (int) $this->id_cabang);
        if (!empty($res['ok'])) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
        $this->jsonOut($res);
    }

    public function updateCell()
    {
        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? '');
        $value = $_POST['value'] ?? '';
        $canEditDisc = false;
        if ($mode === '5') {
            $this->session_cek(1); // disc khusus privilege tertinggi
            $canEditDisc = true;
        }
        $res = $this->api()->updateCell($id, $mode, $value, (int) $this->id_cabang, $canEditDisc);
        if (!empty($res['ok']) && in_array($mode, ['1', '2', '6'], true)) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
        echo empty($res['ok']) ? ($res['msg'] ?? 'Gagal update') : 0;
    }

    /** Simpan seluruh field via modal edit. */
    public function update()
    {
        $this->session_cek(); // akses halaman pelanggan sudah cukup; disc dikunci untuk privilege tertinggi
        $res = $this->api()->update($_POST, (int) $this->id_cabang, ((int) ($this->id_privilege ?? 0) === 100));
        if (!empty($res['ok'])) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }
        echo empty($res['ok']) ? ($res['msg'] ?? 'Gagal update') : 0;
    }

    /**
     * Cek sebelum simpan edit.
     * Nama harus UNIK di cabang (blokir); nomor boleh sama (hanya info).
     * @return void
     */
    public function cekEdit()
    {
        $this->jsonOut($this->api()->cekEdit($_POST, (int) $this->id_cabang));
    }

    /** @return PelangganApi */
    private function api()
    {
        return $this->helper('PelangganApi');
    }

    /** @param array<string,mixed> $payload */
    private function jsonOut(array $payload): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
