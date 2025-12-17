<?php

class D_Gaji extends Controller
{
    function tetapkan($userID, $date, $data)
    {
        $table = "gaji_result";
        $do['errno'] = 0;
        if (count($data) > 0) {
            foreach ($data as $a) {
                $tipe = $a['tipe'];
                $ref = $a['ref'];
                $jumlah = $a['jumlah'];
                $qty = $a['qty'];

                $where = "id_karyawan = " . $userID . " AND tgl = '" . $date . "' AND ref = '" . $ref . "' AND tipe = " . $tipe;
                $cek = $this->db(0)->count_where('gaji_result', $where);
                if ($cek < 1) {
                    if ($jumlah <> 0) {
                        $data = [
                            'id_karyawan' => $userID,
                            'tgl' => $date,
                            'tipe' => $tipe,
                            'deskripsi' => $a['deskripsi'],
                            'ref' => $ref,
                            'jumlah' => $jumlah,
                            'qty' => $qty
                        ];
                        $do = $this->db(0)->insert($table, $data);
                    }
                } else {
                    if ($jumlah == 0 || $qty == 0) {
                        $do = $this->db(0)->delete('gaji_result', $where);
                    } else {
                        $set = ['jumlah' => $jumlah, 'qty' => $qty];
                        $do = $this->db(0)->update($table, $set, $where);
                    }
                }
            }
        }

        if ($do['errno'] == 0) {
            $return = 0;
        } else {
            $return = $do['error'];
        }
        return $return;
    }

    function data_olah($userID, $date, $book)
    {
        $data['kinerja'] = $this->data_kinerja($userID, $date, $book);
        $data['kasbon'] = $this->data_kasbon($userID, $date, $book);
        $data['setup'] = $this->data_setup();
        $data['data'] = $this->db(0)->get_where('gaji_pengali_data', "tgl = '" . $date . "'");
        $data['fix'] = $this->db(0)->get_where('gaji_result', "tgl = '" . $date . "' AND id_karyawan = " . $userID . " ORDER BY tipe ASC ");
        $data['r'] = $this->rekap_kinerja($data['kinerja'], $userID);

        return $data;
    }

    function data_setup()
    {
        $gaji['gaji_laundry'] = $this->db(0)->get('gaji_laundry');
        $gaji['pengali_list'] = $this->db(0)->get('gaji_pengali_jenis');
        $gaji['gaji_pengali'] = $this->db(0)->get('gaji_pengali');

        return $gaji;
    }

    function data_kinerja($userID, $date, $book)
    {
        $data_operasi = [];
        $data_terima = [];
        $data_kembali = [];

        //OPERASI
        if ($userID <> 0) {
            $where = "insertTime LIKE '" . $date . "%' AND id_user_operasi = " . $userID;
            $ops_data = $this->db($book)->get_where('operasi', $where, 'id_operasi');

            //OPERASI
            $join_where = "operasi.id_penjualan = sale.id_penjualan";
            $where = "sale.bin = 0 AND operasi.id_user_operasi = " . $userID . " AND operasi.insertTime LIKE '" . $date . "%'";
            $data_lain1 = $this->db($book)->innerJoin1_where('sale', 'operasi', $join_where, $where);

            foreach ($data_lain1 as $key => $dl1) {
                unset($ops_data[$dl1['id_operasi']]);
                $data_operasi[$key] = $dl1;
            }

            if (count($ops_data) > 0 && count($data_operasi) > 0) {
                //PENJUALAN TAHUN LALU
                foreach ($ops_data as $od) {
                    $where = "id_penjualan = " . $od['id_penjualan'];
                    $data_lalu = $this->db($book - 1)->get_where_row('sale', $where);

                    if (count($data_lalu) > 0) {
                        $new_data = array_merge($data_lalu, $od);
                        array_push($data_operasi, $new_data);
                    }
                }
            }

            //TERIMA
            $cols = "id_user, id_cabang, COUNT(id_user) as terima";
            $where = "id_user = " . $userID . " AND  insertTime LIKE '" . $date . "%' GROUP BY id_user, id_cabang";
            $data_lain2 = $this->db($book)->get_cols_where('sale', $cols, $where, 1);
            foreach ($data_lain2 as $dl2) {
                array_push($data_terima, $dl2);
            }

            //KEMBALI
            $cols = "id_user_ambil, id_cabang, COUNT(id_user_ambil) as kembali";
            $where = "id_user_ambil = " . $userID . " AND tgl_ambil LIKE '" . $date . "%' GROUP BY id_user_ambil, id_cabang";
            $data_lain3 = $this->db($book)->get_cols_where('sale', $cols, $where, 1);
            foreach ($data_lain3 as $dl3) {
                array_push($data_kembali, $dl3);
            }
        }

        $data['operasi'] = $data_operasi;
        $data['terima'] = $data_terima;
        $data['kembali'] = $data_kembali;

        return $data;
    }

    function data_kasbon($userID, $month, $book)
    {
        //KASBON
        $cols = "id_kas, jumlah, insertTime";
        $where = "jenis_transaksi = 5 AND jenis_mutasi = 2 AND status_mutasi = 3 AND insertTime LIKE '" . $month . "%' AND id_client = " . $userID;
        $data = $this->db($book)->get_cols_where('kas', $cols, $where, 1);

        foreach ($data as $key => $k) {
            $ref = $k['id_kas'];
            $where = "ref = '" . $ref . "' AND id_karyawan = " . $userID . " AND tgl = '" . $month . "' AND tipe = 2";
            $countPotong = $this->db(0)->count_where('gaji_result', $where);
            if ($countPotong == 1) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    function rekap_final($data, $dateOn, $id_user)
    {
        $aDate = strtotime($dateOn);
        $bDate = strtotime(date("Y-m"));
        $intervalDate = ($bDate - $aDate) / 60 / 60 / 24;
        $r = $data['r'];
        $r_pengali = [];
        $r_pengali_id = [];

        foreach ($data['setup']['gaji_pengali'] as $a) {
            $r_pengali[$a['id_karyawan']][$a['id_pengali']] = $a['gaji_pengali'];
            $r_pengali_id[$a['id_karyawan']][$a['id_pengali']] = $a['id_gaji_pengali'];
        }

        $pengali_list = $data['setup']['pengali_list'];

        $totalDapat = 0;
        $totalPotong = 0;
        $totalTerima = 0;

        $arrInject = array();
        $noInject = 0;

        $jenis_penjualan = $this->db(0)->get('penjualan_jenis');
        $jenis_layanan = $this->db(0)->get('layanan');

        if ($intervalDate < 60) {

            foreach ($r as $userID => $arrJenisJual) {
                $totalGajiLaundry = 0;
                foreach ($arrJenisJual as $jenisJualID => $arrLayanan) {
                    $id_penjualan = 0;
                    $penjualan = "Non";
                    foreach ($jenis_penjualan as $jp) {
                        if ($jp['id_penjualan_jenis'] == $jenisJualID) {
                            $id_penjualan = $jp['id_penjualan_jenis'];
                            $penjualan = $jp['penjualan_jenis'];
                        }
                    }

                    if ($penjualan == "Non") {
                        continue;
                    }

                    $id_layanan = 0;
                    foreach ($arrLayanan as $layananID => $arrCabang) {
                        $layanan = "Non";
                        $totalPerUser = 0;
                        foreach ($jenis_layanan as $dl) {
                            if ($dl['id_layanan'] == $layananID) {
                                $layanan = $dl['layanan'];
                                $id_layanan = $dl['id_layanan'];
                                foreach ($arrCabang as $cabangID => $c) {
                                    $totalPerUser = $totalPerUser + $c;
                                }
                            }
                        }

                        if ($layanan == "Non") {
                            continue;
                        }

                        $gaji_laundry = 0;
                        $bonus_target = 0;
                        $target = 0;
                        $max_target = 0;
                        foreach ($data['setup']['gaji_laundry'] as $gp) {
                            if ($gp['id_karyawan'] == $id_user && $gp['id_layanan'] == $id_layanan && $gp['jenis_penjualan'] == $id_penjualan) {
                                $gaji_laundry = $gp['gaji_laundry'];
                                $target = $gp['target'];
                                $bonus_target = $gp['bonus_target'];
                                $max_target = $gp['max_target'];
                            }
                        }

                        $bonus = 0;
                        $xBonus = 0;
                        if ($max_target <> 0) {
                            if ($totalPerUser <= $max_target) {
                                $max_target = $totalPerUser;
                            }
                        } else {
                            $max_target = $totalPerUser;
                        }

                        if ($target > 0) {
                            if ($totalPerUser > 0) {
                                $xBonus = floor($max_target / $target);
                                $bonus = $xBonus * $bonus_target;
                            }
                        }

                        $totalGajiLaundry = $gaji_laundry * $totalPerUser;

                        $noInject += 1;
                        $ref = "P" . $id_penjualan . "L" . $id_layanan;
                        $arrInject[$noInject] = array(
                            "tipe" => 1,
                            "ref" => $ref,
                            "deskripsi" => $penjualan . " " . $layanan,
                            "qty" => $totalPerUser,
                            "jumlah" => $totalGajiLaundry
                        );

                        if ($bonus >= 0) {
                            $noInject += 1;
                            $ref = "P" . $id_penjualan . "L" . $id_layanan . "-B";
                            $arrInject[$noInject] = array(
                                "tipe" => 1,
                                "ref" => $ref,
                                "deskripsi" => "Bonus " . $ref,
                                "qty" => $xBonus,
                                "jumlah" => $bonus
                            );
                        }
                    }
                }

                $totalTerima = 0;
                foreach ($data['kinerja']['terima'] as $a) {
                    if ($userID == $a['id_user']) {
                        $totalTerima = $totalTerima + $a['terima'];
                    }
                }

                if (isset($r_pengali[$id_user][1])) {
                    $feeTerima = $r_pengali[$id_user][1];
                } else {
                    $feeTerima = 0;
                }

                $totalFeeTerima = $totalTerima * $feeTerima;

                if ($totalFeeTerima >= 0) {
                    $totalDapat += $totalFeeTerima;

                    $noInject += 1;
                    $ref = "AL1";
                    $arrInject[$noInject] = array(
                        "tipe" => 1,
                        "ref" => $ref,
                        "deskripsi" => "Laundry Terima",
                        "qty" => $totalTerima,
                        "jumlah" => $totalFeeTerima
                    );
                }

                $totalKembali = 0;
                foreach ($data['kinerja']['kembali'] as $a) {
                    if ($userID == $a['id_user_ambil']) {
                        $totalKembali = $totalKembali + $a['kembali'];
                    }
                }

                if (isset($r_pengali[$id_user][2])) {
                    $feeKembali = $r_pengali[$id_user][2];
                } else {
                    $feeKembali = 0;
                }

                $totalFeeKembali = $totalKembali * $feeKembali;

                if ($totalFeeKembali >= 0) {
                    $noInject += 1;
                    $ref = "AL2";
                    $arrInject[$noInject] = array(
                        "tipe" => 1,
                        "ref" => $ref,
                        "deskripsi" => "Laundry Kembali",
                        "qty" => $totalKembali,
                        "jumlah" => $totalFeeKembali
                    );
                }
            }

            $dataPengali = $data['data'];
            if (count($dataPengali) > 0) {
                $feePTotal = 0;
                foreach ($dataPengali as $b) {
                    if ($b['id_karyawan'] == $id_user) {
                        $idPengali = $b['id_pengali'];
                        if (isset($r_pengali[$id_user][$idPengali])) {
                            $feeP = $r_pengali[$id_user][$idPengali];
                        } else {
                            $feeP = 0;
                        }

                        $pengaliJenis = "";
                        foreach ($pengali_list as $pl) {
                            if ($pl['id_pengali'] == $idPengali) {
                                $pengaliJenis = $pl['pengali_jenis'];
                            }
                        }

                        $qty = $b['qty'];
                        $feePTotal = $qty * $feeP;

                        $noInject += 1;
                        $ref = "HT" . $idPengali;
                        $arrInject[$noInject] = array(
                            "tipe" => 1,
                            "ref" => $ref,
                            "deskripsi" => $pengaliJenis,
                            "qty" => $qty,
                            "jumlah" => $feePTotal
                        );
                    }
                }
            }

            //POTONGAN
            if (count($data['kasbon']) > 0) {
                foreach ($data['kasbon'] as $uk) {
                    $potKasbon = $uk['jumlah'];
                    $id_kas = $uk['id_kas'];
                    $tgl = substr($uk['insertTime'], 0, 10);

                    $totalPotong += $potKasbon;
                    if ($potKasbon > 0) {
                        $noInject += 1;
                        $ref = $id_kas;
                        $arrInject[$noInject] = array(
                            "tipe" => 2,
                            "ref" => $ref,
                            "deskripsi" => "KB " . $tgl . "",
                            "qty" => 1,
                            "jumlah" => $potKasbon
                        );
                    }
                }
            }

            return $arrInject;
        }
    }

    function rekap_kinerja($kinerja, $userID)
    {
        $data_operasi = $kinerja['operasi'];
        $data_terima = $kinerja['terima'];
        $data_kembali = $kinerja['kembali'];
        $r = [];
        foreach ($data_operasi as $a) {
            $cabang = $a['id_cabang'];
            $jenis_operasi = $a['jenis_operasi'];
            $jenis = $a['id_penjualan_jenis'];

            if (isset($r[$userID][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
                $r[$userID][$jenis][$jenis_operasi][$cabang] =  $r[$userID][$jenis][$jenis_operasi][$cabang] + $a['qty'];
            } else {
                $r[$userID][$jenis][$jenis_operasi][$cabang] = $a['qty'];
            }
        }

        foreach ($data_terima as $a) {
            $cabang = $a['id_cabang'];
            $jenis_operasi = 9000;
            $jenis = "9000";

            if (isset($r[$userID][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
                $r[$userID][$jenis][$jenis_operasi][$cabang] =  $r[$userID][$jenis][$jenis_operasi][$cabang] + $a['terima'];
            } else {
                $r[$userID][$jenis][$jenis_operasi][$cabang] = $a['terima'];
            }
        }

        foreach ($data_kembali as $a) {
            $cabang = $a['id_cabang'];
            $jenis_operasi = 9001;
            $jenis = "9001";

            if (isset($r[$userID][$jenis][$jenis_operasi][$cabang]) ==  TRUE) {
                $r[$userID][$jenis][$jenis_operasi][$cabang] =  $r[$userID][$jenis][$jenis_operasi][$cabang] + $a['kembali'];
            } else {
                $r[$userID][$jenis][$jenis_operasi][$cabang] = $a['kembali'];
            }
        }

        return $r;
    }
}
