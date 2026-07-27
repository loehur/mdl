<?php

class KasModel extends Controller
{
    use Attributes;

    public function __construct()
    {
        $this->db(0); // Initialize DB connection
    }

    public function bayarMulti($data_rekap, $dibayar, $id_pelanggan, $id_cabang, $id_user, $metode = 2, $note = "", $jenis_mutasi = 1, $id_saldo_client = 0)
    {
        $total_dibayar = 0;
        $metodeInt = (int) $metode;

        $use_bayar = true;
        if ($dibayar == 0) {
            $use_bayar = false;
        }

        if (count($data_rekap) == 0) {
            return false;
        }

        if ($metodeInt === 1) {
            if ($note == "") {
                $note = "CASH";
            }
        } elseif ($metodeInt === 3) {
            if ($note == "") {
                $note = "SALDO";
            }
        } else {
            if ($note == "") {
                return "Pembayaran Non Tunai wajib memilih Tujuan Bayar";
            }
            if ($use_bayar) {
                if ($note == "QRIS" && $dibayar < 1000) {
                    return "QRIS minimal 1.000";
                }
                if ($note <> "QRIS" && $dibayar < 10000) {
                    return "Pembayaran Transfer minimal 10.000";
                }
            }
        }

        // Saldo tunai: bayar order laundry (T#/U#) dulu, baru topup paket member (M#)
        if ($metodeInt === 3) {
            uksort($data_rekap, function ($a, $b) {
                $ta = substr((string) $a, 0, 1);
                $tb = substr((string) $b, 0, 1);
                $rank = function ($t) {
                    if ($t === 'T' || $t === 'U') return 0;
                    if ($t === 'M') return 1;
                    return 2;
                };
                $ra = $rank($ta);
                $rb = $rank($tb);
                if ($ra !== $rb) {
                    return $ra - $rb;
                }
                return strcmp((string) $a, (string) $b);
            });
        } else {
            ksort($data_rekap);
        }

        $ref_f = (date('Y') - 2024) . date('mdHis') . rand(0, 9) . rand(0, 9) . $id_cabang;

        foreach ($data_rekap as $key => $value) {
            if ($use_bayar && $dibayar == 0) {
                break;
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

            $jumlah = intval(round($jumlah));
            if ($jumlah <= 0) {
                continue;
            }

            $kasIdClient = (int) $id_pelanggan;

            if ($metodeInt === 3) {
                $idSaldo = ((int) $id_saldo_client > 0) ? (int) $id_saldo_client : (int) $id_pelanggan;
                $kasIdClient = $idSaldo;
                $q_cr = "id_client = '$idSaldo' AND jenis_transaksi = 6 AND jenis_mutasi = 1 AND status_mutasi = 3";
                $topup = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr) ?? 0;
                $q_cr_out = "id_client = '$idSaldo' AND jenis_transaksi = 6 AND jenis_mutasi = 2 AND status_mutasi = 3";
                $topup_out = $this->db(0)->sum_col_where('kas', 'jumlah', $q_cr_out) ?? 0;
                $q_use = "id_client = '$idSaldo' AND metode_mutasi = 3 AND jenis_mutasi = 2";
                $usage = $this->db(0)->sum_col_where('kas', 'jumlah', $q_use) ?? 0;
                $sisaSaldo = intval(round($topup - $topup_out - $usage));

                if ($sisaSaldo <= 0) {
                    if ($total_dibayar > 0) {
                        continue;
                    }
                    return "Saldo tidak cukup";
                }

                if ($jumlah > $sisaSaldo) {
                    $jumlah = $sisaSaldo;
                }

                if ($jumlah <= 0) {
                    continue;
                }

                $jenis_mutasi = 2;
            }

            $status_mutasi = 2;
            switch ($metodeInt) {
                case 2:
                    $status_mutasi = 2;
                    break;
                default:
                    $status_mutasi = 3;
                    break;
            }

            $jt = $tipe == "M" ? 3 : 1;
            $wCabang = "id_cabang = " . $id_cabang;

            if ($metodeInt === 2 && $status_mutasi == 2) {
                $refEsc = $this->db(0)->escape($ref);
                $pendingWhere = $wCabang . " AND jenis_transaksi = " . intval($jt)
                    . " AND ref_transaksi = '" . $refEsc . "'"
                    . " AND metode_mutasi = 2 AND status_mutasi = 2";
                if ($this->db(0)->count_where('kas', $pendingWhere) >= 1) {
                    return "Masih ada pembayaran yang menunggu verifikasi untuk transaksi ini. Batalkan pembayaran pending atau tunggu hingga berhasil/gagal sebelum membuat pembayaran baru.";
                }
            }

            $setOne = "ref_transaksi = '" . $ref . "' AND metode_mutasi = " . $metodeInt . " AND jenis_mutasi = " . $jenis_mutasi . " AND status_mutasi = " . $status_mutasi . " AND jumlah = " . $jumlah;
            $where = $wCabang . " AND " . $setOne;
            $data_main = $this->db(0)->count_where('kas', $where);

            $id_kas = (date('Y') - 2020) . substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
            if ($data_main < 1) {
                $data = [
                    'id_kas' => $id_kas,
                    'id_cabang' => $id_cabang,
                    'jenis_mutasi' => $jenis_mutasi,
                    'jenis_transaksi' => $jt,
                    'ref_transaksi' => $ref,
                    'metode_mutasi' => $metodeInt,
                    'note' => $note,
                    'status_mutasi' => $status_mutasi,
                    'jumlah' => $jumlah,
                    'id_user' => $id_user,
                    'id_client' => $kasIdClient,
                    'ref_finance' => $ref_f,
                    'insertTime' => $GLOBALS['now']
                ];
                $do = $this->db(0)->insert('kas', $data);
                if ($do['errno'] == 0) {
                    if ($use_bayar) {
                        $dibayar -= $jumlah;
                    }
                    $total_dibayar += $jumlah;
                } else {
                    $this->model('Log')->write("[KasModel::bayarMulti] Insert Kas Error: " . $do['error']);
                    if ($total_dibayar > 0) {
                        break;
                    }
                    return $do['error'];
                }
            }
        }

        if ($metodeInt === 3 && $total_dibayar <= 0) {
            return "Saldo tidak cukup";
        }

        return 0;
    }
}
