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
        $existingRows = $this->db(0)->query_array($existingQuery);
        if (!is_array($existingRows)) {
            $existingRows = [];
        }
        
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
        $gaji['gaji_laundry'] = $this->db(0)->get('gaji_laundry_ref');
        if (!is_array($gaji['gaji_laundry'])) {
            $gaji['gaji_laundry'] = $gaji['gaji_laundry'] ? iterator_to_array($gaji['gaji_laundry']) : [];
        }
        $gaji['pengali_list'] = $this->db(0)->get('gaji_pengali_jenis');
        $gaji['gaji_pengali'] = $this->db(0)->get('gaji_pengali');

        $pengaliRefRaw = $this->db(0)->get('gaji_pengali_ref');
        if (!is_array($pengaliRefRaw)) {
            $pengaliRefRaw = $pengaliRefRaw ? iterator_to_array($pengaliRefRaw) : [];
        }
        $gaji['pengali_ref'] = [1 => 0, 2 => 0];
        foreach ($pengaliRefRaw as $pr) {
            $idP = (int) ($pr['id_pengali'] ?? 0);
            if ($idP === 1 || $idP === 2) {
                $gaji['pengali_ref'][$idP] = (int) ($pr['gaji_pengali'] ?? 0);
            }
        }

        return $gaji;
    }

    function data_kinerja($userID, $date)
    {
        $data_operasi = [];
        $data_terima = [];
        $data_kembali = [];
        $exTrainSale = $this->sqlExcludeTrainingCabang('sale.id_cabang');
        $exTrain = $this->sqlExcludeTrainingCabang('id_cabang');

        //OPERASI
        if ($userID <> 0) {
            $join_where = "operasi.id_penjualan = sale.id_penjualan";
            $where = "{$exTrainSale}sale.bin = 0 AND operasi.insertTime LIKE '" . $date . "%' AND operasi.id_user_operasi = " . $userID . " AND operasi.insertTime LIKE '" . $date . "%'";
            $data_operasi = $this->db(0)->innerJoin1_where('sale', 'operasi', $join_where, $where);


            //TERIMA
            $cols = "id_user, id_cabang, COUNT(id_user) as terima";
            $where = "{$exTrain}id_user = " . $userID . " AND  insertTime LIKE '" . $date . "%' GROUP BY id_user, id_cabang";
            $data_lain2 = $this->db(0)->get_cols_where('sale', $cols, $where, 1);
            foreach ($data_lain2 as $dl2) {
                array_push($data_terima, $dl2);
            }

            //KEMBALI
            $cols = "id_user_ambil, id_cabang, COUNT(id_user_ambil) as kembali";
            $where = "{$exTrain}id_user_ambil = " . $userID . " AND tgl_ambil LIKE '" . $date . "%' GROUP BY id_user_ambil, id_cabang";
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
                            if ($gp['id_layanan'] == $id_layanan && $gp['jenis_penjualan'] == $id_penjualan) {
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

                $feeTerima = isset($data['setup']['pengali_ref'][1])
                    ? (int) $data['setup']['pengali_ref'][1]
                    : 0;

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

                $feeKembali = isset($data['setup']['pengali_ref'][2])
                    ? (int) $data['setup']['pengali_ref'][2]
                    : 0;

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
                        $idPengali = (int) $b['id_pengali'];

                        $pengaliJenis = "";
                        foreach ($pengali_list as $pl) {
                            if ((int) $pl['id_pengali'] == $idPengali) {
                                $pengaliJenis = $pl['pengali_jenis'];
                            }
                        }

                        // Jaga malam (5) / Cuci (6): max(fee snapshot, fee karyawan)
                        if ($idPengali === 5) {
                            $malam = $this->hitungJumlahGajiMalam((int) $id_user, $dateOn);
                            $qty = (int) $malam['qty'];
                            $feePTotal = (int) $malam['jumlah'];
                            if ($qty < 1 && (int) ($b['qty'] ?? 0) > 0) {
                                $qty = (int) $b['qty'];
                                $feeP = $this->feeEfektifSnapshot(
                                    $this->feeMalamDariPendapatan(null),
                                    (int) ($malam['fee_karyawan'] ?? 0)
                                );
                                $feePTotal = $qty * $feeP;
                            }
                        } elseif ($idPengali === 6) {
                            $cuci = $this->hitungJumlahGajiCuci((int) $id_user, $dateOn);
                            $qty = (int) $cuci['qty'];
                            $feePTotal = (int) $cuci['jumlah'];
                            if ($qty < 1 && (int) ($b['qty'] ?? 0) > 0) {
                                $qty = (int) $b['qty'];
                                $feeP = $this->feeEfektifSnapshot(
                                    $this->feeCuciDariPendapatan(null),
                                    (int) ($cuci['fee_karyawan'] ?? 0)
                                );
                                $feePTotal = $qty * $feeP;
                            }
                        } else {
                            if (isset($r_pengali[$id_user][$idPengali])) {
                                $feeP = $r_pengali[$id_user][$idPengali];
                            } else {
                                $feeP = 0;
                            }
                            $qty = (int) $b['qty'];
                            if ($idPengali === 4) {
                                $qty = 1; // Tunjangan: qty maksimal 1
                            }
                            $feePTotal = $qty * $feeP;
                        }

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

    /**
     * Load rumus fee snapshot (cache per request).
     * @return array{malam:array{pengali:float,clamp_min:int,clamp_max:int},cuci:array{pengali:float,clamp_min:int,clamp_max:int}}
     */
    private function getFeeFormulas(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $defaults = [
            'malam' => ['pengali' => 1.0, 'clamp_min' => 14000, 'clamp_max' => 32000],
            'cuci' => ['pengali' => 4.0, 'clamp_min' => 65000, 'clamp_max' => 85000],
        ];
        $cache = $defaults;

        try {
            $raw = $this->db(0)->get('gaji_fee_formula');
            if (!is_array($raw)) {
                $raw = $raw ? iterator_to_array($raw) : [];
            }
            foreach ($raw as $row) {
                $kode = (string) ($row['kode'] ?? '');
                if (!isset($defaults[$kode])) {
                    continue;
                }
                $pengali = (float) ($row['pengali'] ?? $defaults[$kode]['pengali']);
                if ($pengali <= 0) {
                    $pengali = (float) $defaults[$kode]['pengali'];
                }
                $min = (int) ($row['clamp_min'] ?? $defaults[$kode]['clamp_min']);
                $max = (int) ($row['clamp_max'] ?? $defaults[$kode]['clamp_max']);
                if ($min > $max) {
                    $min = $defaults[$kode]['clamp_min'];
                    $max = $defaults[$kode]['clamp_max'];
                }
                $cache[$kode] = [
                    'pengali' => $pengali,
                    'clamp_min' => $min,
                    'clamp_max' => $max,
                ];
            }
        } catch (\Throwable $e) {
            // tabel belum ada → pakai default
            $cache = $defaults;
        }

        return $cache;
    }

    /**
     * Fee dari pendapatan snapshot: round((p/1000)*pengali) lalu clamp.
     * Null/missing pendapatan → clamp_min.
     */
    private function feeDariPendapatanDenganFormula($totalPendapatan, string $kode): int
    {
        $f = $this->getFeeFormulas()[$kode] ?? ['pengali' => 1.0, 'clamp_min' => 0, 'clamp_max' => 0];
        $min = (int) $f['clamp_min'];
        $max = (int) $f['clamp_max'];
        $pengali = (float) $f['pengali'];

        if ($totalPendapatan === null || $totalPendapatan === '') {
            return $min;
        }

        $fee = (int) round((((float) $totalPendapatan) / 1000) * $pengali);
        if ($fee < $min) {
            return $min;
        }
        if ($fee > $max) {
            return $max;
        }
        return $fee;
    }

    /**
     * Fee jaga malam per malam dari total pendapatan snapshot.
     * round((pendapatan/1000)*pengali), clamp dari gaji_fee_formula. Null → clamp_min.
     */
    public function feeMalamDariPendapatan($totalPendapatan): int
    {
        return $this->feeDariPendapatanDenganFormula($totalPendapatan, 'malam');
    }

    /**
     * Fee absen Cuci per hari dari total pendapatan snapshot.
     * round((pendapatan/1000)*pengali), clamp dari gaji_fee_formula. Null → clamp_min.
     */
    public function feeCuciDariPendapatan($totalPendapatan): int
    {
        return $this->feeDariPendapatanDenganFormula($totalPendapatan, 'cuci');
    }

    /**
     * Fee gaji_pengali per karyawan (Rp), 0 jika belum di-set.
     */
    public function getFeePengaliKaryawan(int $idUser, int $idPengali): int
    {
        $idUser = (int) $idUser;
        $idPengali = (int) $idPengali;
        if ($idUser < 1 || $idPengali < 1) {
            return 0;
        }
        $row = $this->db(0)->get_where_row(
            'gaji_pengali',
            'id_karyawan = ' . $idUser . ' AND id_pengali = ' . $idPengali
        );
        if (!is_array($row) || !isset($row['gaji_pengali'])) {
            return 0;
        }
        return (int) $row['gaji_pengali'];
    }

    /**
     * Fee efektif: max(fee global snapshot, fee karyawan). Fee karyawan 0 = abaikan.
     */
    public function feeEfektifSnapshot(int $feeGlobal, int $feeKaryawan): int
    {
        $feeGlobal = (int) $feeGlobal;
        $feeKaryawan = (int) $feeKaryawan;
        if ($feeKaryawan > $feeGlobal) {
            return $feeKaryawan;
        }
        return $feeGlobal;
    }

    /**
     * Qty absen untuk preview/penetapan pengali (exclude cabang training).
     *
     * @return array{cuci:int,malam:int,harian:int}
     */
    public function countAbsenPengali(int $id_user, string $dateYm): array
    {
        $id_user = (int) $id_user;
        $dateYm = substr((string) $dateYm, 0, 7);
        $empty = ['cuci' => 0, 'malam' => 0, 'harian' => 0];
        if ($id_user < 1 || !preg_match('/^\d{4}-\d{2}$/', $dateYm)) {
            return $empty;
        }

        $exTrain = $this->sqlExcludeTrainingCabang('id_cabang');
        $dateEsc = $this->db(0)->escape($dateYm);
        $rows = $this->db(0)->query_array(
            "SELECT
                CASE
                    WHEN jenis = 0 THEN 'cuci'
                    WHEN jenis = 1 THEN 'malam'
                    WHEN jenis IN (2, 3) THEN 'harian'
                    ELSE 'lain'
                END AS tipe,
                COUNT(*) AS qty
             FROM absen
             WHERE {$exTrain}id_karyawan = $id_user
               AND tanggal LIKE '{$dateEsc}%'
             GROUP BY CASE
                    WHEN jenis = 0 THEN 'cuci'
                    WHEN jenis = 1 THEN 'malam'
                    WHEN jenis IN (2, 3) THEN 'harian'
                    ELSE 'lain'
                END"
        );
        if (!is_array($rows)) {
            return $empty;
        }
        foreach ($rows as $row) {
            $tipe = $row['tipe'] ?? '';
            if (isset($empty[$tipe])) {
                $empty[$tipe] = (int) ($row['qty'] ?? 0);
            }
        }
        return $empty;
    }

    /**
     * Baris gaji_pengali_data + preview absen (malam/cuci/harian/tunjangan) jika belum ditetapkan.
     *
     * @param array $dataPengali rows gaji_pengali_data
     * @return list<array{id_karyawan:int,id_pengali:int,qty:int,id_pengali_data:int,is_preview:bool}>
     */
    public function mergePengaliPreviewRows(array $dataPengali, int $id_user, string $dateYm): array
    {
        $id_user = (int) $id_user;
        $rows = [];
        $shown = [];
        foreach ($dataPengali as $b) {
            if ((int) ($b['id_karyawan'] ?? 0) !== $id_user) {
                continue;
            }
            $idPengali = (int) ($b['id_pengali'] ?? 0);
            $shown[$idPengali] = true;
            $qtyRow = (int) ($b['qty'] ?? 0);
            if ($idPengali === 4) {
                $qtyRow = 1; // Tunjangan: qty maksimal 1
            }
            $rows[] = [
                'id_karyawan' => $id_user,
                'id_pengali' => $idPengali,
                'qty' => $qtyRow,
                'id_pengali_data' => (int) ($b['id_pengali_data'] ?? 0),
                'is_preview' => false,
            ];
        }

        if ($id_user < 1) {
            return $rows;
        }

        $absen = $this->countAbsenPengali($id_user, $dateYm);
        $previewMap = [
            6 => (int) ($absen['cuci'] ?? 0),
            5 => (int) ($absen['malam'] ?? 0),
            3 => (int) ($absen['harian'] ?? 0),
            4 => 1, // tunjangan bulanan — selalu preview sebelum tetapkan
        ];
        foreach ($previewMap as $idPengali => $qty) {
            if (isset($shown[$idPengali])) {
                continue;
            }
            if ($idPengali !== 4 && $qty < 1) {
                continue;
            }
            $rows[] = [
                'id_karyawan' => $id_user,
                'id_pengali' => $idPengali,
                'qty' => $qty,
                'id_pengali_data' => 0,
                'is_preview' => true,
            ];
        }

        return $rows;
    }

    /**
     * Hitung qty & jumlah gaji jaga malam dari absen (group by id_cabang)
     * × max(fee snapshot cabang, fee karyawan id_pengali=5).
     *
     * @return array{qty:int,jumlah:int,fee_display:int,fee_karyawan:int,by_cabang:array}
     */
    public function hitungJumlahGajiMalam($id_user, $dateYm): array
    {
        $id_user = (int) $id_user;
        $min = (int) ($this->getFeeFormulas()['malam']['clamp_min'] ?? 14000);
        $feeKaryawan = $this->getFeePengaliKaryawan($id_user, 5);
        $fallback = $this->feeEfektifSnapshot($min, $feeKaryawan);
        $result = $this->hitungJumlahGajiAbsenSnapshot(
            $id_user,
            $dateYm,
            1,
            function ($pendapatan) use ($feeKaryawan) {
                $global = $this->feeMalamDariPendapatan($pendapatan);
                return $this->feeEfektifSnapshot($global, $feeKaryawan);
            },
            $fallback
        );
        $result['fee_karyawan'] = $feeKaryawan;
        return $result;
    }

    /**
     * Hitung qty & jumlah gaji Cuci dari absen jenis=0 (group by id_cabang)
     * × max(fee snapshot cabang, fee karyawan id_pengali=6).
     *
     * @return array{qty:int,jumlah:int,fee_display:int,fee_karyawan:int,by_cabang:array}
     */
    public function hitungJumlahGajiCuci($id_user, $dateYm): array
    {
        $id_user = (int) $id_user;
        $min = (int) ($this->getFeeFormulas()['cuci']['clamp_min'] ?? 65000);
        $feeKaryawan = $this->getFeePengaliKaryawan($id_user, 6);
        $fallback = $this->feeEfektifSnapshot($min, $feeKaryawan);
        $result = $this->hitungJumlahGajiAbsenSnapshot(
            $id_user,
            $dateYm,
            0,
            function ($pendapatan) use ($feeKaryawan) {
                $global = $this->feeCuciDariPendapatan($pendapatan);
                return $this->feeEfektifSnapshot($global, $feeKaryawan);
            },
            $fallback
        );
        $result['fee_karyawan'] = $feeKaryawan;
        return $result;
    }

    /**
     * Generic: absen by jenis, fee per cabang dari snapshot bulan lalu.
     *
     * @param callable $feeFn function($pendapatan): int
     * @return array{qty:int,jumlah:int,fee_display:int,by_cabang:array}
     */
    private function hitungJumlahGajiAbsenSnapshot($id_user, $dateYm, $jenisAbsen, $feeFn, $feeFallback): array
    {
        $id_user = (int) $id_user;
        $jenisAbsen = (int) $jenisAbsen;
        $feeFallback = (int) $feeFallback;
        $dateYm = substr((string) $dateYm, 0, 7);
        $empty = ['qty' => 0, 'jumlah' => 0, 'fee_display' => $feeFallback, 'by_cabang' => []];
        if ($id_user < 1 || !preg_match('/^\d{4}-\d{2}$/', $dateYm)) {
            return $empty;
        }

        $periodeLalu = date('Y-m', strtotime($dateYm . '-01 -1 month'));
        $dateEsc = $this->db(0)->escape($dateYm);
        $exTrain = $this->sqlExcludeTrainingCabang('id_cabang');
        $rows = $this->db(0)->query_array(
            "SELECT id_cabang, COUNT(*) AS qty
             FROM absen
             WHERE {$exTrain}id_karyawan = $id_user AND jenis = $jenisAbsen AND tanggal LIKE '{$dateEsc}%'
             GROUP BY id_cabang"
        );
        if (!is_array($rows) || count($rows) < 1) {
            return $empty;
        }

        $totalQty = 0;
        $totalJumlah = 0;
        $byCabang = [];
        $fees = [];

        foreach ($rows as $row) {
            $idCabang = (int) ($row['id_cabang'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($qty < 1 || $this->isTrainingCabangId($idCabang)) {
                continue;
            }
            $pendapatan = $this->getSnapshotTotalPendapatanCabang($idCabang, $periodeLalu);
            $fee = (int) $feeFn($pendapatan);
            $sub = $qty * $fee;
            $totalQty += $qty;
            $totalJumlah += $sub;
            $fees[] = $fee;
            $byCabang[] = [
                'id_cabang' => $idCabang,
                'qty' => $qty,
                'fee' => $fee,
                'jumlah' => $sub,
                'pendapatan' => $pendapatan,
            ];
        }

        $feeDisplay = $feeFallback;
        if (count($fees) === 1) {
            $feeDisplay = $fees[0];
        } elseif ($totalQty > 0) {
            $feeDisplay = (int) round($totalJumlah / $totalQty);
        }

        return [
            'qty' => $totalQty,
            'jumlah' => $totalJumlah,
            'fee_display' => $feeDisplay,
            'by_cabang' => $byCabang,
        ];
    }

    /**
     * total_pendapatan snapshot mode=2 untuk cabang + periode, atau null jika belum ada.
     */
    public function getSnapshotTotalPendapatanCabang($id_cabang, $periode)
    {
        $id_cabang = (int) $id_cabang;
        if ($id_cabang < 1) {
            return null;
        }
        $periodeEsc = $this->db(0)->escape($periode);
        $row = $this->db(0)->get_where_row(
            'rekap_snapshot',
            "periode = '$periodeEsc' AND mode = 2 AND id_cabang = $id_cabang"
        );
        if (!is_array($row) || !isset($row['total_pendapatan'])) {
            return null;
        }
        return (int) $row['total_pendapatan'];
    }
}
