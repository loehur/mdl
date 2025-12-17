<?php

class Saldo extends Controller
{

    function kasCabang($id_cabang = 0)
    {

        if ($id_cabang == 0) {
            $cabangs = $this->db(0)->get("cabang");

            foreach ($cabangs as $a) {
                $kredit = 0;
                $where_kredit = "id_cabang = " . $a['id_cabang'] . " AND jenis_mutasi = 1 AND metode_mutasi = 1 AND status_mutasi = 3";
                $cols_kredit = "SUM(jumlah) as jumlah";

                $debit = 0;
                $where_debit = "id_cabang = " . $a['id_cabang'] . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND status_mutasi <> 4";
                $cols_debit = "SUM(jumlah) as jumlah";

                
                        $jumlah_kredit = isset($this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah'] : 0;
                        $kredit += $jumlah_kredit;

                        $jumlah_debit = isset($this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah'] : 0;
                        $debit += $jumlah_debit;
                

                $saldo[$a['id_cabang']] = $kredit - $debit;
            }
        } else {
            $kredit = 0;
            $where_kredit = "id_cabang = " . $id_cabang . " AND jenis_mutasi = 1 AND metode_mutasi = 1 AND status_mutasi = 3";
            $cols_kredit = "SUM(jumlah) as jumlah";

            $debit = 0;
            $where_debit = "id_cabang = " . $id_cabang . " AND jenis_mutasi = 2 AND metode_mutasi = 1 AND status_mutasi <> 4";
            $cols_debit = "SUM(jumlah) as jumlah";

            
                        $jumlah_kredit = isset($this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_kredit, $where_kredit, 0)['jumlah'] : 0;
                        $kredit += $jumlah_kredit;

                        $jumlah_debit = isset($this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah']) ? $this->db(0)->get_cols_where('kas', $cols_debit, $where_debit, 0)['jumlah'] : 0;
                        $debit += $jumlah_debit;
            

            $saldo[$id_cabang] = $kredit - $debit;
        }

        return $saldo;
    }

    function getSaldoTunai($id_pelanggan)
    {
        //SALDO DEPOSIT
        $saldo = 0;
        $pakai = 0;

        // FIX: use db(0) directly instead of year iteration
        // Kredit (top-up)
        $where = "id_client = " . $id_pelanggan . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3";
        $topup = $this->db(0)->sum_col_where('kas', 'jumlah', $where) ?? 0;

        // Kredit Out (corrections/reversal)
        $where2 = "id_client = " . $id_pelanggan . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3";
        $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', $where2) ?? 0;

        // Debit (usage)
        $where3 = "id_client = " . $id_pelanggan . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
        $pakai = $this->db(0)->sum_col_where('kas', 'jumlah', $where3) ?? 0;

        $sisaSaldo = $topup - $topup_out - $pakai;
        return $sisaSaldo;
    }

    public function saldoMember($idPelanggan, $idHarga)
    {
        //SALDO
        $saldo = 0;
        $where = "bin = 0 AND id_pelanggan = " . $idPelanggan . " AND id_harga = " . $idHarga;
        $cols = "SUM(qty) as saldo";
        $data = $this->db(0)->get_cols_where('member', $cols, $where, 0);
        $saldoManual = $data['saldo'];

        //DIPAKAI - FIX: use db(0) directly
        $where = "id_pelanggan = " . $idPelanggan . " AND member = 1 AND bin = 0 AND id_harga = " . $idHarga;
        $cols = "SUM(qty) as saldo";
        $saldoPengurangan = 0;

        $data2 = $this->db(0)->get_cols_where('sale', $cols, $where, 0);
        if (isset($data2['saldo']) && is_numeric($data2['saldo'])) {
            $saldoPengurangan = $data2['saldo'];
        }

        $saldo = $saldoManual - $saldoPengurangan;
        return $saldo;
    }

    function unit_by_idHarga($id_harga)
    {
        $unit = "";
        $harga = $this->db(0)->get("harga");
        $penjualan_jenis = $this->db(0)->get('penjualan_jenis');
        $satuan = $this->db(0)->get('satuan');

        foreach ($harga as $a) {
            if ($a['id_harga'] == $id_harga) {
                foreach ($penjualan_jenis as $dp) {
                    if ($dp['id_penjualan_jenis'] == $a['id_penjualan_jenis']) {
                        foreach ($satuan as $ds) {
                            if ($ds['id_satuan'] == $dp['id_satuan']) {
                                $unit = $ds['nama_satuan'];
                            }
                        }
                    }
                }
            }
        }
        return $unit;
    }
}
