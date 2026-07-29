<?php

/**
 * Import data pelanggan dari CSV (admin Tools)
 */
class ImportPelanggan extends Controller
{
    private const MAX_FILE_BYTES = 2097152; // 2MB
    private const MAX_ROWS = 2000;

    public function __construct()
    {
        $this->operating_data();
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Import Pelanggan'];

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('data_list/import_pelanggan', []);
    }

    public function downloadSample()
    {
        $this->session_cek(1);

        $filename = 'sample_import_pelanggan.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
        fputcsv($out, ['nama_pelanggan', 'nomor_pelanggan', 'alamat']);
        fputcsv($out, ['Budi Santoso', '081234567890', 'Jl. Merdeka 1']);
        fputcsv($out, ['Siti Aminah', '081298765432', '']);
        fputcsv($out, ['Andi Wijaya', '08111222333', 'Komplek Melati Blok A2']);
        fclose($out);
        exit();
    }

    public function import()
    {
        $this->session_cek(1);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if (!isset($_FILES['csv']) || !is_array($_FILES['csv'])) {
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['File CSV tidak ditemukan'], 'message' => 'Upload gagal']);
            return;
        }

        $file = $_FILES['csv'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['Gagal upload file'], 'message' => 'Upload gagal']);
            return;
        }

        if (($file['size'] ?? 0) > self::MAX_FILE_BYTES) {
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['Ukuran file maksimal 2MB'], 'message' => 'File terlalu besar']);
            return;
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['File upload tidak valid'], 'message' => 'Upload gagal']);
            return;
        }

        $handle = fopen($tmp, 'r');
        if ($handle === false) {
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['Tidak dapat membaca file CSV'], 'message' => 'Gagal baca file']);
            return;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            echo json_encode(['ok' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => ['File CSV kosong'], 'message' => 'Header tidak valid']);
            return;
        }

        // Strip BOM from first header cell
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }
        $header = array_map(function ($h) {
            return strtolower(trim((string) $h));
        }, $header);

        $required = ['nama_pelanggan', 'nomor_pelanggan'];
        $map = [];
        foreach ($header as $i => $col) {
            $map[$col] = $i;
        }
        foreach ($required as $col) {
            if (!array_key_exists($col, $map)) {
                fclose($handle);
                echo json_encode([
                    'ok' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => ['Header CSV wajib: nama_pelanggan,nomor_pelanggan,alamat'],
                    'message' => 'Header tidak valid'
                ]);
                return;
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNum = 1; // header
        $dataRows = 0;
        $idCabang = (int) $this->id_cabang;
        $db = $this->db(0);

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            $dataRows++;

            if ($dataRows > self::MAX_ROWS) {
                $errors[] = "Baris melebihi batas maksimal " . self::MAX_ROWS . " data";
                break;
            }

            // Skip completely empty rows
            $allEmpty = true;
            foreach ($row as $cell) {
                if (trim((string) $cell) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) {
                continue;
            }

            $nama = trim((string) ($row[$map['nama_pelanggan']] ?? ''));
            $nomorRaw = (string) ($row[$map['nomor_pelanggan']] ?? '');
            $nomor = preg_replace('/\D/', '', $nomorRaw);
            $alamat = '';
            if (array_key_exists('alamat', $map)) {
                $alamat = trim((string) ($row[$map['alamat']] ?? ''));
            }

            if ($nama === '' || $nomor === '') {
                $errors[] = "Baris $rowNum: nama dan nomor HP wajib diisi";
                $skipped++;
                continue;
            }

            $namaEsc = $db->escape($nama);
            $where = $this->wCabang . " AND nama_pelanggan = '" . $namaEsc . "'";
            $exists = $db->count_where('pelanggan', $where);
            if ($exists > 0) {
                $errors[] = "Baris $rowNum: nama " . strtoupper($nama) . " sudah digunakan (dilewati)";
                $skipped++;
                continue;
            }

            $data = [
                'id_cabang' => $idCabang,
                'nama_pelanggan' => $nama,
                'nomor_pelanggan' => $nomor,
            ];
            if ($alamat !== '') {
                $data['alamat'] = $alamat;
            }

            $do = $db->insert('pelanggan', $data);
            if (($do['errno'] ?? 0) <> 0) {
                $errors[] = "Baris $rowNum: gagal simpan (" . ($do['error'] ?? 'error') . ")";
                $skipped++;
                continue;
            }

            $imported++;
        }

        fclose($handle);

        if ($imported > 0) {
            $this->dataSynchrone($_SESSION[URL::SESSID]['user']['id_user']);
        }

        if ($dataRows === 0 && empty($errors)) {
            $errors[] = 'Tidak ada baris data untuk diimpor';
        }
        $ok = $imported > 0 ? 1 : 0;

        $parts = [];
        if ($imported > 0) {
            $parts[] = "$imported berhasil";
        }
        if ($skipped > 0) {
            $parts[] = "$skipped dilewati";
        }
        $message = empty($parts) ? 'Tidak ada data yang diimpor' : ('Import selesai: ' . implode(', ', $parts));

        echo json_encode([
            'ok' => $ok,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
        ]);
    }
}
