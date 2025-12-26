<?php

class WAGenerator extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    public function get_nota($ref)
    {
        // 1. Get Transaction Data
        $where = "no_ref = '$ref' AND tuntas = 0";
        $transactions = $this->db(0)->get_where('sale', $where);

        if (empty($transactions)) {
            return "Transaction not found";
        }

        // AUTO-LOAD MASTER DATA IF MISSING (For API/Webhook context)
        if (!isset($this->itemGroup) || empty($this->itemGroup)) {
            $this->itemGroup = $this->db(0)->get('item_group');
        }
        if (!isset($this->dLayanan) || empty($this->dLayanan)) {
            $this->dLayanan = $this->db(0)->get('layanan');
        }
        if (!isset($this->dDurasi) || empty($this->dDurasi)) {
            $this->dDurasi = $this->db(0)->get('durasi');
        }
        if (!isset($this->dSatuan) || empty($this->dSatuan)) {
            $this->dSatuan = $this->db(0)->get('satuan');
        }
        if (!isset($this->dPenjualan) || empty($this->dPenjualan)) {
            // Join with satuan for convenience if needed, or just raw
            $this->dPenjualan = $this->db(0)->get('penjualan_jenis');
        }

        // 2. Get Customer & Head Info from first transaction
        $firstItem = $transactions[0];
        $id_pelanggan = $firstItem['id_pelanggan'];
        $id_user = $firstItem['id_user'];
        $insertTime = $firstItem['insertTime'];

        // Customer
        $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '$id_pelanggan'");
        $nama_pelanggan = $pelanggan ? $pelanggan['nama_pelanggan'] : 'Unknown';

        // CS / User
        $user = $this->db(0)->get_where_row('user', "id_user = '$id_user'");
        $nama_user = $user ? $user['nama_user'] : 'Unknown';
        $cs_code = strtoupper(substr($nama_user, 0, 2)) . substr($id_user, -1);

        // Branch
        // Assuming fetch from $this->dCabang (populated by operating_data) or query
        // Since operating_data loads session user's branch, but the transaction might be from another branch?
        // Usually references are unique global or per branch. 
        // Logic in view: $this->dCabang['kode_cabang'].
        // If calling from different context, getting branch from transaction is safer if column exists.
        // penjualan table has 'id_cabang'.
        $id_cabang = $firstItem['id_cabang'];
        $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = '$id_cabang'");
        $kode_cabang = $cabang ? $cabang['kode_cabang'] : '00';

        // 3. Build List Items (ListNotif)
        $listNotif = "";
        $subTotal = 0;

        // Pre-fetch related masters if not set (operating_data usually sets them from session, but let's be safe or use what's there)
        // Accessing $this->itemGroup, $this->dDurasi etc.
        // If session data is missing (e.g. cron job), we might need to fetch. 
        // operating_data handles fetching if session[data][layanan] is set.
        // But if session is empty (e.g. API call), we might face issues.
        // Ideally we check if $this->dLayanan is set.
        
        // Let's assume standard web context for now as per "called from any controller" implies user session exists.

        foreach ($transactions as $a) {
            $id = $a['id_penjualan'];
            $f3 = $a['id_item_group']; // category
            $f5 = $a['list_layanan'];
            $f11 = $a['id_durasi'];
            $f6 = $a['qty'];
            $f7 = $a['harga']; // price per item
            $f16 = $a['min_order'];
            $f10 = $a['id_penjualan_jenis'];
            $f14 = $a['diskon_qty'];
            $f15 = $a['diskon_partner'];
            $member = $a['member'];
            
            // Qty Logic
            $qty_real = ($f6 < $f16) ? $f16 : $f6;
            
            // Satuan
            $satuan = "";
            if (isset($this->dPenjualan)) {
                foreach ($this->dPenjualan as $l) {
                    if ($l['id_penjualan_jenis'] == $f10) {
                        foreach ($this->dSatuan as $sa) {
                            if ($sa['id_satuan'] == $l['id_satuan']) {
                                $satuan = $sa['nama_satuan'];
                            }
                        }
                    }
                }
            }

            $show_qty = "";
            if ($f6 < $f16) {
                 $show_qty = $f6 . $satuan . " (Min. " . $f16 . $satuan . ")";
            } else {
                 $show_qty = $f6 . $satuan;
            }

            // Kategori
            $kategori = "";
            if (isset($this->itemGroup)) {
                foreach ($this->itemGroup as $b) {
                    if ($b['id_item_group'] == $f3) {
                        $kategori = $b['item_kategori'];
                    }
                }
            }

            // Layanan
            $list_layanan_print = "";
            if (strlen($f5) > 0) {
                $arrLayanan = unserialize($f5);
                if (is_array($arrLayanan)) {
                    foreach ($arrLayanan as $b) {
                         if (isset($this->dLayanan)) {
                            foreach ($this->dLayanan as $c) {
                                if ($b == $c['id_layanan']) {
                                    $list_layanan_print .= " " . $c['layanan'];
                                }
                            }
                         }
                    }
                }
            }

            // Durasi
            $durasi = "";
            if (isset($this->dDurasi)) {
                foreach ($this->dDurasi as $b) {
                    if ($b['id_durasi'] == $f11) {
                        $durasi = strtoupper($b['durasi']);
                    }
                }
            }

            // Calculate Total
            $total = $f7 * $qty_real;
            if ($member == 0) {
                if ($f14 > 0 && $f15 == 0) {
                    $total = $total - ($total * ($f14 / 100));
                } else if ($f14 == 0 && $f15 > 0) {
                     $total = $total - ($total * ($f15 / 100));
                } else if ($f14 > 0 && $f15 > 0) {
                    $total = $total - ($total * ($f14 / 100));
                    $total = $total - ($total * ($f15 / 100));
                }
            } else {
                $total = 0; // Member logic in view sets total to 0 for display in some parts, but subTotal counts?
                // Wait, view says: if ($member == 0) ... else { $total = 0; } $subTotal = $subTotal + $total;
                // So if member, price is 0 in this context?
                // Yes, line 336: $total = 0.
            }
            $subTotal += $total;

            $show_total_notif = "";
            if ($member == 0) {
                 // Simplified from view: if discount, use format ~original~ discounted
                 // For now just taking final total logic
                 // Line 349: $show_total_notif = "~" . number_format($f7 * $qty_real) . "~ " . number_format($total) . " ";
                if ($f14 > 0 || $f15 > 0) {
                     $show_total_notif = "~" . number_format($f7 * $qty_real) . "~ " . number_format($total);
                } else {
                     $show_total_notif = "" . number_format($total);
                }
            } else {
                $show_total_notif = "MEMBER";
            }

            // Build Item String
            // Format: "\n" . $kategori . " " . $show_qty . "\n" . ltrim($list_layanan_print) . " " . ucwords(strtolower($durasi)) . "\n_R" . $id . "_ " . $show_total_notif . "\n"
            $listNotif .= "\n" . $kategori . " " . $show_qty . "\n" . ltrim($list_layanan_print) . " " . ucwords(strtolower($durasi)) . "\n_R" . $id . "_ " . $show_total_notif . "\n";
        }

        // 4. Surcas (Surcharges)
        $surcas = $this->db(0)->get_where('surcas', "no_ref = '$ref'");
        if (!empty($surcas)) {
             // Need master surcas_jenis
             $surcas_jenis_master = $this->db(0)->get('surcas_jenis'); 
             // Or use $this->surcas from operating_data (it calls it surcas too, confusing naming)
             // operating_data: $this->surcas = $_SESSION...['surcas']
             
             foreach ($surcas as $sca) {
                  $surcasName = "";
                  $id_jenis = $sca['id_jenis_surcas'];
                  foreach ($surcas_jenis_master as $sc) {
                      if ($sc['id_surcas_jenis'] == $id_jenis) {
                          $surcasName = $sc['surcas_jenis'];
                      }
                  }
                  $jumlahCas = $sca['jumlah'];
                  $subTotal += $jumlahCas;
                  $listNotif .= "\n#S" . $sca['id_surcas'] . " " . $surcasName . " " . number_format($jumlahCas) . "\n";
             }
        }

        // 5. Payments (Kas)
        // View: $data['kas'] loop.
        // Query kas
        $kas = $this->db(0)->get_where('kas', "ref_transaksi = '$ref'");
        $dibayar = 0;
        foreach ($kas as $k) {
             if ($k['status_mutasi'] == 3) { // 3 paid
                  $dibayar += $k['jumlah'];
             }
        }

        // 6. Total Text
        // $sisaTagihan = intval($subTotal) - $dibayar;
        // Logic: if ($sisaTagihanFinal < 1) ...
        // We simplified:
        $sisa = $subTotal - $dibayar;
        if ($sisa <= 0) {
            $totalText = "*Total/Sisa 0. LUNAS*";
        } else {
            $totalText = "*Total/Sisa " . number_format($sisa) . "*";
        }

        // 7. Final Output
        // <span id="<?= $ref ? >"><?= strtoupper($nama_pelanggan) ? > _#<?= $this->dCabang['kode_cabang'] ? >-<?= $cs_code ? >_<?= "\n" . $listNotif . "\n" . $totalText . "\n" ? ><?= URL::HOST_URL ? >/I/i/<?= $id_pelanggan ? ></span>
        
        $output = "*" . strtoupper($nama_pelanggan) . "* _#" . $kode_cabang . "-" . $cs_code . "_\n" . $listNotif . "\n" . $totalText . "\n" . URL::HOST_URL . "/I/i/" . $id_pelanggan;

        // Post-processing
        $output = str_replace("<sup>2</sup>", "²", $output);
        $output = str_replace("<sup>3</sup>", "³", $output);

        // Prepare template parameters for WhatsApp template message (when CSW is not open)
        // IMPORTANT: WhatsApp template params cannot have newlines, tabs, or >4 consecutive spaces
        // Clean the parameters
        $cleanOrderList = $listNotif;
        // Step 1: Replace single newlines with pipes
        $cleanOrderList = str_replace(["\n", "\r", "\t"], " | ", $cleanOrderList);
        // Step 2: Clean multiple spaces
        $cleanOrderList = preg_replace('/\s{2,}/', ' ', $cleanOrderList);
        // Step 3: Remove leading/trailing pipes
        $cleanOrderList = trim($cleanOrderList, ' |');
        // Step 4: Final trim
        $cleanOrderList = trim($cleanOrderList);
        
        $cleanTotalBill = str_replace(["\n", "\r", "\t", "*", "Total/Sisa "], "", $totalText); // Remove formatting and prefix
        $cleanTotalBill = trim($cleanTotalBill);
        
        $templateParams = [
            'customer' => strtoupper($nama_pelanggan) . " _#" . $kode_cabang . "-" . $cs_code . "_",
            'order_list' => $cleanOrderList,
            //'total_bill' => $cleanTotalBill,
            'invoice_link' => URL::HOST_URL . "/I/i/" . $id_pelanggan
        ];

        return json_encode([
            'text' => $output,
            'template_params' => $templateParams
        ]);
    }

    public function get_selesai_text($id_penjualan, $karyawan)
    {
        // Get sale data
        $sale = $this->db(0)->get_where_row('sale', "id_penjualan = '$id_penjualan'");
        
        if (empty($sale)) {
            return json_encode(['text' => 'Data not found']);
        }

        $id_pelanggan = $sale['id_pelanggan'];
        $id_cabang = $sale['id_cabang'];

        // Get customer
        $pelanggan = $this->db(0)->get_where_row('pelanggan', "id_pelanggan = '$id_pelanggan'");
        $nama_pelanggan = $pelanggan ? $pelanggan['nama_pelanggan'] : 'Unknown';

        // Get staff who completed the task (karyawan parameter)
        $user = $this->db(0)->get_where_row('user', "id_user = '$karyawan'");
        $nama_user = $user ? $user['nama_user'] : 'Unknown';
        $karyawan_code = strtoupper(substr($nama_user, 0, 2)) . substr($karyawan, -1);

        // Get branch
        $cabang = $this->db(0)->get_where_row('cabang', "id_cabang = '$id_cabang'");
        $kode_cabang = $cabang ? $cabang['kode_cabang'] : '00';

        // Logic Total Notif (Same as get_nota)
        // 1. Calculate Transaction Total
        // We need all items for this Ref? Or just this single item finished?
        // Usually 'Selesai' notification is per item or per transaction?
        // In Antrian controller, it seems to call this per item status update.
        // But if we want "Total/Sisa", we need the whole transaction (Ref).
        
        $ref = $sale['no_ref'];
        
        // Fetch all items in this transaction to calc total bill
        $all_items = $this->db(0)->get_where('sale', "no_ref = '$ref'");
        $subTotal = 0;
        
        foreach ($all_items as $a) {
             // Basic Total calc
             $qty = ($a['qty'] < $a['min_order']) ? $a['min_order'] : $a['qty'];
             $price = $a['harga'];
             $total = $qty * $price;
             
             // Discounts
             if ($a['member'] == 0) {
                 if ($a['diskon_qty'] > 0) $total -= ($total * ($a['diskon_qty']/100));
                 if ($a['diskon_partner'] > 0) $total -= ($total * ($a['diskon_partner']/100));
             } else {
                 $total = 0; // Member logic
             }
             $subTotal += $total;
        }
        
        // Surcas
        $surcas = $this->db(0)->get_where('surcas', "no_ref = '$ref'");
        if (!empty($surcas)) {
            foreach ($surcas as $sc) {
                $subTotal += $sc['jumlah'];
            }
        }
        
        // Payments
        $kas = $this->db(0)->get_where('kas', "ref_transaksi = '$ref'");
        $dibayar = 0;
        foreach ($kas as $k) {
             if ($k['status_mutasi'] == 3) { // 3 paid
                  $dibayar += $k['jumlah'];
             }
        }
        
        // Final Text
        $sisa = $subTotal - $dibayar;
        $totalNotif = "";
        if ($sisa <= 0) {
            $totalNotif = "*Total/Sisa 0. LUNAS*";
        } else {
            $totalNotif = "*Total/Sisa " . number_format($sisa) . "*";
        }

        // Build text with actual values (no placeholders)
        $output = "*" . strtoupper($nama_pelanggan) . "* _#" . $kode_cabang . "-" . $karyawan_code . "_ \n#" . $id_penjualan . " Selesai. \n" . $totalNotif . " \n" . URL::HOST_URL . "/I/i/" . $id_pelanggan;

        return json_encode(['text' => $output]);
    }
}
