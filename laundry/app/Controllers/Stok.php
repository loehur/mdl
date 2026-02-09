<?php

class Stok extends Controller
{
    public function __construct()
    {
        $this->session_cek();
        $this->operating_data();
    }

    public function index()
    {
        $id_cabang = intval($_SESSION[URL::SESSID]['user']['id_cabang'] ?? 0);
        $this->model('Log')->write("Stok Debug: ID Cabang = " . $id_cabang);
        
        // 1. Get List Barang
        // Use get_where which returns array results
        $barang = $this->db(0)->get_where('barang_data', "state = 1");
        
        if (empty($barang) || !is_array($barang)) {
            $this->model('Log')->write("Stok Debug: Barang empty or not array");
            $barang = [];
        } else {
            $this->model('Log')->write("Stok Debug: Found " . count($barang) . " barang items.");
        }
        
        // 2. Get Barang Masuk (IN)
        // target_id = id_cabang
        $res_in = $this->db(0)->get_cols_where('barang_mutasi', 
            "id_barang, SUM(denom * qty) as total_in", 
            "target_id = '$id_cabang' GROUP BY id_barang", 
            1
        );

        $in_map = [];
        if(!empty($res_in) && is_array($res_in)) {
            $this->model('Log')->write("Stok Debug: Found IN data count: " . count($res_in));
            foreach($res_in as $r) {
                // Check if keys exist (safety)
                if(isset($r['id_barang'])) {
                    $in_map[$r['id_barang']] = floatval($r['total_in'] ?? 0);
                }
            }
        } else {
            $this->model('Log')->write("Stok Debug: No IN data found.");
        }
        
        // 3. Get Barang Keluar (OUT)
        // source_id = id_cabang AND state != 2
        $res_out = $this->db(0)->get_cols_where('barang_mutasi', 
            "id_barang, SUM(denom * qty) as total_out", 
            "source_id = '$id_cabang' AND state != 2 GROUP BY id_barang", 
            1
        );

        $out_map = [];
        if(!empty($res_out) && is_array($res_out)) {
             $this->model('Log')->write("Stok Debug: Found OUT data count: " . count($res_out));
            foreach($res_out as $r) {
                if(isset($r['id_barang'])) {
                    $out_map[$r['id_barang']] = floatval($r['total_out'] ?? 0);
                }
            }
        } else {
            $this->model('Log')->write("Stok Debug: No OUT data found.");
        }
        
        // 4. Merge Data
        foreach($barang as &$b) {
            $id = $b['id_barang'];
            $b['total_in'] = $in_map[$id] ?? 0;
            $b['total_out'] = $out_map[$id] ?? 0;
            $b['stok'] = $b['total_in'] - $b['total_out'];
            
            // Format unit
            if (isset($b['unit'])) {
                $unit = $this->db(0)->get_where_row('barang_unit', "id = '{$b['unit']}'");
                $b['unit_nama'] = $unit['nama'] ?? '';
            }
            
            // Construct full name
            $brand = $b['brand'] ?? '';
            $model = $b['model'] ?? '';
            $desc = $b['description'] ?? '';
            $b['nama'] = strtoupper(trim("$brand $model $desc"));
        }
        unset($b);
        
        // 5. Urutkan dari stok terbanyak
        usort($barang, function($a, $b) {
            return ($b['stok'] ?? 0) <=> ($a['stok'] ?? 0);
        });
        
        $data_operasi = ['title' => 'Stok Barang'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('stok/index', [
            'data_operasi' => $data_operasi,
            'barang' => $barang
        ]);
    }
}
