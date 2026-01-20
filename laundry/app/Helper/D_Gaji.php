<?php

class D_Gaji extends Controller
{
    function tetapkan($userID, $date, $dataItems)
    {
        $table = "gaji_result";
        $do = ['errno' => 0];
        
        if (count($dataItems) < 1) {
            return 0;
        }
        
        $userID = (int)$userID;
        $dateEscaped = $this->db(0)->escape($date);
        
        // OPTIMASI 1: Fetch semua existing data dalam 1 query (bukan N query per item)
        $existingQuery = "SELECT id_karyawan, tgl, ref, tipe, jumlah, qty 
                          FROM gaji_result 
                          WHERE id_karyawan = " . $userID . " AND tgl = '" . $dateEscaped . "'";
        $existingRows = $this->db(0)->query($existingQuery);
        
        // Build lookup map untuk O(1) access: key = "ref|tipe"
        $existingMap = [];
        foreach ($existingRows as $row) {
            $key = $row['ref'] . '|' . $row['tipe'];
            $existingMap[$key] = $row;
        }
        
        // OPTIMASI 2: Kategorikan operasi ke dalam batch
        $toInsert = [];
        $toUpdate = [];
        $toDelete = [];
        
        foreach ($dataItems as $a) {
            $tipe = (int)$a['tipe'];
            $ref = $a['ref'];
            $jumlah = $a['jumlah'];
            $qty = $a['qty'];
            $deskripsi = $a['deskripsi'];
            
            $key = $ref . '|' . $tipe;
            $exists = isset($existingMap[$key]);
            
            if (!$exists) {
                // Data baru - insert jika jumlah != 0
                if ($jumlah != 0) {
                    $toInsert[] = [
                        'id_karyawan' => $userID,
                        'tgl' => $date,
                        'tipe' => $tipe,
                        'deskripsi' => $deskripsi,
                        'ref' => $ref,
                        'jumlah' => $jumlah,
                        'qty' => $qty
                    ];
                }
            } else {
                // Data sudah ada
                if ($jumlah == 0 || $qty == 0) {
                    // Delete jika jumlah/qty = 0
                    $toDelete[] = "(ref = '" . $this->db(0)->escape($ref) . "' AND tipe = " . $tipe . ")";
                } else {
                    // Update jika nilai berubah
                    $existing = $existingMap[$key];
                    if ($existing['jumlah'] != $jumlah || $existing['qty'] != $qty) {
                        $toUpdate[] = [
                            'ref' => $ref,
                            'tipe' => $tipe,
                            'jumlah' => $jumlah,
                            'qty' => $qty
                        ];
                    }
                }
            }
        }
        
        // OPTIMASI 3: Execute batch operations
        
        // Batch INSERT menggunakan multi-row insert
        if (count($toInsert) > 0) {
            $values = [];
            foreach ($toInsert as $row) {
                $values[] = "(" . $row['id_karyawan'] . ", '" . $this->db(0)->escape($row['tgl']) . "', " 
                          . $row['tipe'] . ", '" . $this->db(0)->escape($row['deskripsi']) . "', '"
                          . $this->db(0)->escape($row['ref']) . "', " . (float)$row['jumlah'] . ", " . (int)$row['qty'] . ")";
            }
            $insertSQL = "INSERT INTO " . $table . " (id_karyawan, tgl, tipe, deskripsi, ref, jumlah, qty) VALUES " . implode(", ", $values);
            $do = $this->db(0)->query($insertSQL);
        }
        
        // Batch UPDATE menggunakan CASE WHEN (single query untuk semua updates)
        if (count($toUpdate) > 0) {
            foreach ($toUpdate as $row) {
                $where = "id_karyawan = " . $userID . " AND tgl = '" . $dateEscaped . "' AND ref = '" 
                       . $this->db(0)->escape($row['ref']) . "' AND tipe = " . $row['tipe'];
                $set = ['jumlah' => $row['jumlah'], 'qty' => $row['qty']];
                $do = $this->db(0)->update($table, $set, $where);
            }
        }
        
        // Batch DELETE menggunakan OR conditions
        if (count($toDelete) > 0) {
            $deleteWhere = "id_karyawan = " . $userID . " AND tgl = '" . $dateEscaped . "' AND (" . implode(" OR ", $toDelete) . ")";
            $do = $this->db(0)->delete($table, $deleteWhere);
        }
        
        if (isset($do['errno']) && $do['errno'] == 0) {
            return 0;
        } else {
            return isset($do['error']) ? $do['error'] : 0;
        }
    }

    function data_olah($userID, $date)
    {
        $data['kinerja'] = $this->data_kinerja($userID, $date);
        $data['kasbon'] = $this->data_kasbon($userID, $date);
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

    function data_kinerja($userID, $date)
    {
        $data_operasi = [];
        $data_terima = [];
        $data_kembali = [];

        //OPERASI
        if ($userID <> 0) {
            $join_where = "operasi.id_penjualan = sale.id_penjualan";
            $where = "sale.bin = 0 AND operasi.insertTime LIKE '" . $date . "%' AND operasi.id_user_operasi = " . $userID . " AND operasi.insertTime LIKE '" . $date . "%'";
            $data_operasi = $this->db(0)->innerJoin1_where('sale', 'operasi', $join_where, $where);


            //TERIMA
            $cols = "id_user, id_cabang, COUNT(id_user) as terima";
            $where = "id_user = " . $userID . " AND  insertTime LIKE '" . $date . "%' GROUP BY id_user, id_cabang";
            $data_lain2 = $this->db(0)->get_cols_where('sale', $cols, $where, 1);
            foreach ($data_lain2 as $dl2) {
                array_push($data_terima, $dl2);
            }

            //KEMBALI
            $cols = "id_user_ambil, id_cabang, COUNT(id_user_ambil) as kembali";
            $where = "id_user_ambil = " . $userID . " AND tgl_ambil LIKE '" . $date . "%' GROUP BY id_user_ambil, id_cabang";
            $data_lain3 = $this->db(0)->get_cols_where('sale', $cols, $where, 1);
            foreach ($data_lain3 as $dl3) {
                array_push($data_kembali, $dl3);
            }
        }

        $data['operasi'] = $data_operasi;
        $data['terima'] = $data_terima;
        $data['kembali'] = $data_kembali;

        return $data;
    }

    function data_kasbon($userID, $month)
    {
        //KASBON
        $cols = "id_kas, jumlah, insertTime";
        $where = "jenis_transaksi = 5 AND jenis_mutasi = 2 AND status_mutasi = 3 AND insertTime LIKE '" . $month . "%' AND id_client = " . $userID;
        $data = $this->db(0)->get_cols_where('kas', $cols, $where, 1);

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
                    $id_penjualan = "0";
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
