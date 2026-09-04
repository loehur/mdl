<?php

class Saldo extends Controller
{

    function getSaldoTunai($id_pelanggan)
    {
        //SALDO DEPOSIT
        $saldo = 0;
        $pakai = 0;
        $data = [];
        $data3 = [];

       
        //Kredit
        $where = "id_client = " . $id_pelanggan . " AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
        $cols = "id_client, SUM(jumlah) as saldo";
        $ks = $this->db(0)->get_cols_where('kas', $cols, $where, 1);
        if (count($ks) > 0) {
            foreach ($ks as $ksv) {
                array_push($data, $ksv);
            }
        }

        //Kredit
        $where2 = "id_client = " . $id_pelanggan . " AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3 GROUP BY id_client ORDER BY saldo DESC";
        $cols = "id_client, SUM(jumlah) as saldo";
        $ks2 = $this->db(0)->get_cols_where('kas', $cols, $where2, 1);
        if (count($ks2) > 0) {
            foreach ($ks2 as $ksv2) {
                array_push($data3, $ksv2);
            }
        }


        //Debit
        if (count($data) > 0) {
            foreach ($data as $a) {
                $idPelanggan = $a['id_client'];
                $saldo = $a['saldo'];
                $where = "id_client = " . $idPelanggan . " AND metode_mutasi = 3 AND jenis_mutasi = 2";
                $cols = "SUM(jumlah) as pakai";
               
                $data2 = $this->db(0)->get_cols_where('kas', $cols, $where, 0);
                if (isset($data2['pakai'])) {
                    $pakai += $data2['pakai'];
                }
            }
        }

        if (count($data3) > 0) {
            foreach ($data3 as $a3) {
                $idPelanggan = $a3['id_client'];
                $pakai += $a3['saldo'];
            }
        }

        $sisaSaldo = $saldo - $pakai;
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

        //DIPAKAI
        $where = "id_pelanggan = " . $idPelanggan . " AND member = 1 AND bin = 0 AND id_harga = " . $idHarga;
        $cols = "SUM(qty) as saldo";
        $saldoPengurangan = 0;

        $data2 = $this->db(0)->get_cols_where('sale', $cols, $where, 0);
        if (isset($data2['saldo']) && is_numeric($data2['saldo'])) {
            $saldoPengurangan += $data2['saldo'];
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
