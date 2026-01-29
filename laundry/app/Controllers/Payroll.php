<?php

class Payroll extends Controller
{
    public function __construct()
    {
        $this->session_cek();
        $this->operating_data();
    }

    /**
     * Halaman utama Payroll
     * Menampilkan daftar payroll berdasarkan periode
     */
    public function index()
    {
        $data_operasi = ['title' => 'Payroll Management'];

        // Default periode: bulan sekarang
        $period = isset($_GET['period']) ? trim($_GET['period']) : date('Y-m');
        
        // Validasi format periode
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = date('Y-m');
        }

        // Ambil data payroll dari db(100) untuk business='laundry' dan periode tertentu
        $payrolls = $this->db(100)->get_where('payroll', "period = '" . $this->db(100)->escape($period) . "' AND business = 'laundry'");
        
        // Jika ada data, ambil info karyawan dari db(0)
        $payrollData = [];
        if (!empty($payrolls)) {
            foreach ($payrolls as $p) {
                $employeeId = $p['employee_id'] ?? 0;
                $user = $this->db(0)->get_where_row('user', "id_user = " . (int)$employeeId);
                
                $payrollData[] = [
                    'id' => $p['id'] ?? 0,
                    'employee_id' => $employeeId,
                    'employee_name' => $user['nama_user'] ?? '-',
                    'period' => $p['period'] ?? '',
                    'amount' => $p['amount'] ?? 0,
                    'bank_code' => $p['bank_code'] ?? '',
                    'bank_acc_number' => $p['bank_acc_number'] ?? '',
                    'bank_acc_name' => $p['bank_acc_name'] ?? '',
                    'state' => $p['state'] ?? 'pending'
                ];
            }
        }

        // Ambil daftar karyawan aktif untuk bulk add to payroll
        $karyawan = $this->db(0)->get_cols_where('user', 'id_user, nama_user', 'en = 1', 1);

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('payroll/index', [
            'period' => $period,
            'payrolls' => $payrollData,
            'karyawan' => $karyawan,
            'total_amount' => array_sum(array_column($payrollData, 'amount'))
        ]);
    }

    /**
     * Add to Payroll - Single User
     * POST: user_id, date (Y-m)
     */
    public function add_single()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $userID = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $date = isset($_POST['date']) ? trim($_POST['date']) : '';

        if ($userID < 1) {
            echo json_encode(['ok' => false, 'msg' => 'User ID tidak valid']);
            return;
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'msg' => 'Periode tidak valid. Gunakan format YYYY-MM']);
            return;
        }

        // User/karyawan ada di db(0) — pastikan ada sebelum hitung gaji
        $user = $this->db(0)->get_where_row('user', "id_user = " . $userID);
        if (!$user) {
            echo json_encode(['ok' => false, 'msg' => 'Karyawan tidak ditemukan di db(0).']);
            return;
        }

        // Total gaji diterima dari gaji_result (db 0): total tipe 1 - total tipe 2
        $gr = $this->db(0)->get_where('gaji_result', "id_karyawan = " . $userID . " AND tgl = '" . $this->db(0)->escape($date) . "'");
        $totalGaji = 0;
        $totalPotong = 0;
        foreach ($gr as $r) {
            if ((int)$r['tipe'] === 1) {
                $totalGaji += (float)$r['jumlah'];
            } else {
                $totalPotong += (float)$r['jumlah'];
            }
        }
        $amount = $totalGaji - $totalPotong;

        if ($amount <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Total gaji diterima nol atau minus. Tetapkan gaji dulu.']);
            return;
        }

        // Rekening dari user db(0): bank_code, bank_account_number, bank_account_name
        $period = $date; // YYYY-MM
        
        // Jika bank_code kosong/null, set Cash dan kosongkan rekening
        if (empty($user['bank_code'])) {
            $bank_code = 'Cash';
            $bank_acc_number = '';
            $bank_acc_name = '';
        } else {
            $bank_code = trim($user['bank_code']);
            $bank_acc_number = isset($user['bank_account_number']) ? trim($user['bank_account_number']) : '';
            $bank_acc_name = isset($user['bank_account_name']) ? trim($user['bank_account_name']) : '';
        }

        // Cek sudah ada payroll untuk employee_id + period
        $existing = $this->db(100)->get_where_row('payroll', "employee_id = " . $userID . " AND period = '" . $period . "'");
        if ($existing) {
            $currentState = isset($existing['state']) ? strtolower($existing['state']) : '';
            
            // Jika sudah approved, tidak bisa update
            if ($currentState === 'approved') {
                echo json_encode(['ok' => false, 'msg' => 'Payroll periode ' . $period . ' untuk karyawan ini sudah di-approve. Tidak bisa diubah lagi.']);
                return;
            }
            
            // State masih draft: update amount & rekening
            $set = [
                'amount' => $amount,
                'bank_code' => $bank_code,
                'bank_acc_number' => $bank_acc_number,
                'bank_acc_name' => $bank_acc_name,
                'state' => 'draft'
            ];
            $where = "employee_id = " . $userID . " AND period = '" . $period . "'";
            $do = $this->db(100)->update('payroll', $set, $where);
            if (isset($do['errno']) && $do['errno'] == 0) {
                echo json_encode(['ok' => true, 'msg' => 'Payroll periode ' . $period . ' berhasil diperbarui.', 'amount' => $amount]);
            } else {
                echo json_encode(['ok' => false, 'msg' => 'Gagal update: ' . (isset($do['error']) ? $do['error'] : 'Unknown')]);
            }
            return;
        }

        $row = [
            'employee_id' => $userID,
            'period' => $period,
            'amount' => $amount,
            'bank_code' => $bank_code,
            'bank_acc_number' => $bank_acc_number,
            'bank_acc_name' => $bank_acc_name,
            'business' => 'laundry',
            'state' => 'draft'
        ];

        $do = $this->db(100)->insert('payroll', $row);
        if (isset($do['errno']) && $do['errno'] == 0) {
            echo json_encode(['ok' => true, 'msg' => 'Berhasil ditambahkan ke payroll.', 'amount' => $amount]);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Gagal simpan: ' . (isset($do['error']) ? $do['error'] : 'Unknown')]);
        }
    }

    /**
     * Add to Payroll - Bulk (All Active Employees)
     * POST: date (Y-m)
     */
    public function add_bulk()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $date = isset($_POST['date']) ? trim($_POST['date']) : '';

        if (!preg_match('/^\d{4}-\d{2}$/', $date)) {
            echo json_encode(['ok' => false, 'msg' => 'Periode tidak valid. Gunakan format YYYY-MM']);
            return;
        }

        // Ambil semua karyawan aktif
        $karyawan = $this->db(0)->get_cols_where('user', 'id_user', 'en = 1', 1);
        
        if (empty($karyawan)) {
            echo json_encode(['ok' => false, 'msg' => 'Tidak ada karyawan aktif']);
            return;
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        foreach ($karyawan as $k) {
            $userID = (int)$k['id_user'];
            
            // Cek apakah sudah approved
            $existing = $this->db(100)->get_where_row('payroll', "employee_id = " . $userID . " AND period = '" . $date . "'");
            if ($existing) {
                $currentState = isset($existing['state']) ? strtolower($existing['state']) : '';
                if ($currentState === 'approved') {
                    $skipped++;
                    continue;
                }
            }

            // Ambil data user
            $user = $this->db(0)->get_where_row('user', "id_user = " . $userID);
            if (!$user) {
                $failed++;
                continue;
            }

            // Hitung gaji
            $gr = $this->db(0)->get_where('gaji_result', "id_karyawan = " . $userID . " AND tgl = '" . $this->db(0)->escape($date) . "'");
            $totalGaji = 0;
            $totalPotong = 0;
            foreach ($gr as $r) {
                if ((int)$r['tipe'] === 1) {
                    $totalGaji += (float)$r['jumlah'];
                } else {
                    $totalPotong += (float)$r['jumlah'];
                }
            }
            $amount = $totalGaji - $totalPotong;

            if ($amount <= 0) {
                $skipped++;
                continue;
            }

            // Rekening
            if (empty($user['bank_code'])) {
                $bank_code = 'Cash';
                $bank_acc_number = '';
                $bank_acc_name = '';
            } else {
                $bank_code = trim($user['bank_code']);
                $bank_acc_number = isset($user['bank_account_number']) ? trim($user['bank_account_number']) : '';
                $bank_acc_name = isset($user['bank_account_name']) ? trim($user['bank_account_name']) : '';
            }

            // Insert atau update
            if ($existing) {
                $set = [
                    'amount' => $amount,
                    'bank_code' => $bank_code,
                    'bank_acc_number' => $bank_acc_number,
                    'bank_acc_name' => $bank_acc_name,
                    'state' => 'draft'
                ];
                $where = "employee_id = " . $userID . " AND period = '" . $date . "'";
                $do = $this->db(100)->update('payroll', $set, $where);
            } else {
                $row = [
                    'employee_id' => $userID,
                    'period' => $date,
                    'amount' => $amount,
                    'bank_code' => $bank_code,
                    'bank_acc_number' => $bank_acc_number,
                    'bank_acc_name' => $bank_acc_name,
                    'business' => 'laundry',
                    'state' => 'draft'
                ];
                $do = $this->db(100)->insert('payroll', $row);
            }

            if (isset($do['errno']) && $do['errno'] == 0) {
                $success++;
            } else {
                $failed++;
                $errors[] = $user['nama_user'] ?? "ID: $userID";
            }
        }

        $msg = "Berhasil: $success, Gagal: $failed, Dilewati: $skipped";
        if (!empty($errors)) {
            $msg .= " | Error: " . implode(', ', array_slice($errors, 0, 5));
        }

        echo json_encode([
            'ok' => true,
            'msg' => $msg,
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped
        ]);
    }

    /**
     * Export CSV Flip: export data payroll ke format CSV Flip
     * GET: period (YYYY-MM)
     */
    public function export_csv_flip()
    {
        $period = isset($_GET['period']) ? trim($_GET['period']) : date('Y-m');
        
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            die('Periode tidak valid. Gunakan format YYYY-MM');
        }

        // Query payroll dari db(100) berdasarkan period dan business='laundry'
        // Hanya export yang sudah approved
        $payrolls = $this->db(100)->get_where('payroll', "period = '" . $this->db(100)->escape($period) . "' AND business = 'laundry' AND state = 'approved'");
        
        if (empty($payrolls)) {
            die('Tidak ada data payroll untuk periode ' . $period);
        }

        // Header CSV - format asli dengan semua kolom termasuk No
        $csv = "No,Bank Tujuan,Nomor Rekening Tujuan,Nominal,Berita Transfer (Opsional),Email Penerima (Opsional),Nama Penerima (Opsional),ID Unik Transaksi (Opsional),Berita Transfer Tambahan (Opsional)\n";

        $no = 1;
        foreach ($payrolls as $p) {
            $bank_code = isset($p['bank_code']) ? trim($p['bank_code']) : '';
            $bank_acc_number = isset($p['bank_acc_number']) ? trim($p['bank_acc_number']) : '';
            $bank_acc_name = isset($p['bank_acc_name']) ? trim($p['bank_acc_name']) : '';
            $amount = isset($p['amount']) ? (float)$p['amount'] : 0;
            $id_payroll = isset($p['id']) ? (int)$p['id'] : 0;

            // Skip jika data tidak lengkap (bank_code, bank_acc_number, atau bank_acc_name kosong)
            if (empty($bank_code) || empty($bank_acc_number) || empty($bank_acc_name)) {
                continue;
            }

            // Ambil flip_code dari tabel banks di db(100) berdasarkan bank_code
            $flip_code = '';
            $bank = $this->db(100)->get_where_row('banks', "bank_code = '" . $this->db(100)->escape($bank_code) . "'");
            if ($bank && isset($bank['flip_code'])) {
                $flip_code = trim($bank['flip_code']);
            }

            // Skip jika flip_code tidak ditemukan
            if (empty($flip_code)) {
                continue;
            }

            // Format data sesuai header
            $row = [
                $no,
                $flip_code,
                $bank_acc_number,
                number_format($amount, 0, '', ''), // Nominal
                $id_payroll . "-" . $period, // Berita Transfer
                '', // Email Penerima
                $bank_acc_name, // Nama Penerima
                $id_payroll, // ID Unik Transaksi
                '' // Berita Transfer Tambahan
            ];
            
            $csv .= implode(',', $row) . "\n";
            $no++;
        }

        // Set header untuk download CSV
        $filename = 'payroll_flip_' . $period . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Convert line endings dari LF ke CRLF (Windows format)
        $csv = str_replace("\n", "\r\n", $csv);
        
        echo $csv;
        exit();
    }

    /**
     * Approve payroll (draft -> approved)
     * POST: payroll_id
     */
    public function approve()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payroll_id = isset($_POST['payroll_id']) ? (int)$_POST['payroll_id'] : 0;

        if ($payroll_id < 1) {
            echo json_encode(['ok' => false, 'msg' => 'Payroll ID tidak valid']);
            return;
        }

        // Cekstate current
        $payroll = $this->db(100)->get_where_row('payroll', "id = " . $payroll_id);
        if (!$payroll) {
            echo json_encode(['ok' => false, 'msg' => 'Payroll tidak ditemukan']);
            return;
        }

        $currentState = isset($payroll['state']) ? strtolower($payroll['state']) : '';
        
        if ($currentState !== 'draft') {
            echo json_encode(['ok' => false, 'msg' => 'Hanya payroll dengan status DRAFT yang bisa di-approve']);
            return;
        }

        $set = ['state' => 'approved'];
        $where = "id = " . $payroll_id;
        $do = $this->db(100)->update('payroll', $set, $where);

        if (isset($do['errno']) && $do['errno'] == 0) {
            echo json_encode(['ok' => true, 'msg' => 'Payroll berhasil di-approve']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Gagal approve payroll']);
        }
    }

    /**
     * Approve all payroll drafts for a period (draft -> approved)
     * POST: period (YYYY-MM)
     */
    public function approve_all()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $period = isset($_POST['period']) ? trim($_POST['period']) : '';

        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            echo json_encode(['ok' => false, 'msg' => 'Periode tidak valid. Gunakan format YYYY-MM']);
            return;
        }

        // Ambil semua payroll dengan state='draft' untuk periode ini
        $drafts = $this->db(100)->get_where('payroll', "period = '" . $this->db(100)->escape($period) . "' AND business = 'laundry' AND state = 'draft'");

        if (empty($drafts)) {
            echo json_encode(['ok' => false, 'msg' => 'Tidak ada payroll dengan status DRAFT untuk periode ' . $period]);
            return;
        }

        $success = 0;
        $failed = 0;

        foreach ($drafts as $d) {
            $payroll_id = (int)$d['id'];
            $set = ['state' => 'approved'];
            $where = "id = " . $payroll_id;
            $do = $this->db(100)->update('payroll', $set, $where);

            if (isset($do['errno']) && $do['errno'] == 0) {
                $success++;
            } else {
                $failed++;
            }
        }

        $msg = "Berhasil approve: $success";
        if ($failed > 0) {
            $msg .= ", Gagal: $failed";
        }

        echo json_encode([
            'ok' => true,
            'msg' => $msg,
            'success' => $success,
            'failed' => $failed
        ]);
    }

    /**
     * Delete payroll entry
     * POST: payroll_id
     */
    public function delete()
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $payroll_id = isset($_POST['payroll_id']) ? (int)$_POST['payroll_id'] : 0;

        if ($payroll_id < 1) {
            echo json_encode(['ok' => false, 'msg' => 'Payroll ID tidak valid']);
            return;
        }

        // Cek apakah sudah approved
        $payroll = $this->db(100)->get_where_row('payroll', "id = " . $payroll_id);
        if ($payroll && isset($payroll['state']) && strtolower($payroll['state']) === 'approved') {
            echo json_encode(['ok' => false, 'msg' => 'Tidak dapat menghapus payroll yang sudah di-approve']);
            return;
        }

        $where = "id = " . $payroll_id;
        $do = $this->db(100)->delete('payroll', $where);

        if (isset($do['errno']) && $do['errno'] == 0) {
            echo json_encode(['ok' => true, 'msg' => 'Payroll berhasil dihapus']);
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Gagal menghapus payroll']);
        }
    }
}
