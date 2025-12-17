<?php

class KasModel extends Controller
{
    use Attributes;

    public function __construct()
    {
        $this->db(0); // Initialize DB connection
    }

    public function bayarMulti($data_rekap, $dibayar, $id_pelanggan, $id_cabang, $id_user, $metode = 2, $note = "", $jenis_mutasi = 1)
    {
        $total_dibayar = 0;

        $use_bayar = true;
        if ($dibayar == 0) {
            $use_bayar = false;
        }

        $minute = date('Y-m-d H:');

        if (count($data_rekap) == 0) {
            return false;
        }

        if ($metode == 1) {
            if ($note == "") {
                $note = "CASH";
            }
        } else {
            if ($note == "") {  
                return "Pembayaran Non Tunai wajib memilih Tujuan Bayar";
            } else {
                if ($use_bayar) {
                    if ($note == "QRIS" && $dibayar < 1000) {
                        return "QRIS minimal 1.000";
                    }
                    if ($note <> "QRIS" && $dibayar < 10000) {
                        return "Pembayaran Transfer minimal 10.000";
                    }
                }
            }
        }

        arsort($data_rekap);
        $ref_f = (date('Y') - 2024) . date('mdHis') . rand(0, 9) . rand(0, 9) . $id_cabang;

        foreach ($data_rekap as $key => $value) {
            if ($use_bayar && $dibayar == 0) {
                return "Pembayaran 0 tidak dilanjutkan";
            }

            $xNoref = $key;
            $jumlah = $value;

            if ($jumlah == 0) {
                continue;
            }

            $ref = substr($xNoref, 2);
            $tipe = substr($xNoref, 0, 1);

            if ($use_bayar) {
                if ($dibayar < $jumlah) {
                    $jumlah = $dibayar;
                }
            } else {
                $jumlah = $value;
            }

            if ($metode == 3) {
                $sisaSaldo = $this->helper('Saldo')->getSaldoTunai($id_pelanggan);
                if ($sisaSaldo > 0) {
                    if ($jumlah > $sisaSaldo) {
                        $jumlah = $sisaSaldo;
                    }
                } else {
                    return "Saldo tidak cukup";
                }
                $jenis_mutasi = 2;
            }

            $status_mutasi = 2;
            switch ($metode) {
                case "2":
                    $status_mutasi = 2;
                    break;
                default:
                    $status_mutasi = 3;
                    break;
            }

            $jt = $tipe == "M" ? 3 : 1;
            $setOne = "ref_transaksi = '" . $ref . "' AND jumlah = " . $jumlah . " AND insertTime LIKE '%" . $minute . "%'";
            $wCabang = "id_cabang = " . $id_cabang;
            $where = $wCabang . " AND " . $setOne;
            $data_main = $this->db(date('Y'))->count_where('kas', $where);

            if ($data_main < 1) {
                $data = [
                    'id_cabang' => $id_cabang,
                    'jenis_mutasi' => $jenis_mutasi,
                    'jenis_transaksi' => $jt,
                    'ref_transaksi' => $ref,
                    'metode_mutasi' => $metode,
                    'note' => $note,
                    'status_mutasi' => $status_mutasi,
                    'jumlah' => $jumlah,
                    'id_user' => $id_user,
                    'id_client' => $id_pelanggan,
                    'ref_finance' => $ref_f,
                    'insertTime' => $GLOBALS['now']
                ];
                $do = $this->db(date('Y'))->insert('kas', $data);
                if ($do['errno'] == 0) {
                    if ($use_bayar) {
                        $dibayar -= $jumlah;
                    }
                    $total_dibayar += $jumlah;
                } else {
                    $this->model('Log')->write("[KasModel::bayarMulti] Insert Kas Error: " . $do['error']);
                    return $do['error'];
                }
            } else {
                return "Pembayaran dengan jumlah yang sama terkunci, lakukan di jam berikutnya.";
            }
        }

        if ($total_dibayar > 0 && $metode == 2 && $note <> "QRIS") {
            $bank_acc_id = isset(URL::MOOTA_BANK_ID[$note]) ? URL::MOOTA_BANK_ID[$note] : '';
            if(empty($bank_acc_id)){
                 $this->model('Log')->write("[KasModel::bayarMulti] Moota Error: Bank ID not found in URL::MOOTA_BANK_ID for note: $note");
                 return 0; // Or handle error? existing logic just returns 0 on success/ignore
            }

            //update kas dengan payment_gateway moota
            $set = [
                'payment_gateway' => 'moota',
            ];
            $where = "ref_finance = '" . $ref_f . "'";
            for ($year = 2021; $year <= date('Y'); $year++) {
                $up = $this->db($year)->update('kas', $set, $where);
                if ($up['errno'] <> 0) {
                   $this->model('Log')->write("[KasModel::bayarMulti] Update Kas Error for year {$year}: " . $up['error']);
                   return $up['error'];
                }
            }
                        
            //insert into wh_moota
            $data_wh_moota = [
                'trx_id' => $ref_f,
                'bank_id' => $bank_acc_id,
                'amount' => $total_dibayar,
                'target' => 'kas_laundry',
                'book' => date('Y'),
                'state' => 'pending'
            ];
            
            $do = $this->db(100)->insert('wh_moota', $data_wh_moota);
            if ($do['errno'] != 0) {
               $this->model('Log')->write("[KasModel::bayarMulti] Insert Moota Error: " . $do['error']);
               return $do['error'];
            }
        }

        return 0;
    }
}
