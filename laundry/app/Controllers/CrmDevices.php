<?php

/**
 * Admin Tools — CRM device locks (unbind / unlock login device).
 * Tabel: mdl_main.crm_device_locks
 */
class CrmDevices extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    private function dbMain()
    {
        return $this->db(100);
    }

    public function index()
    {
        $this->session_cek(1);

        $locks = [];
        $dbReady = true;
        try {
            $this->dbMain()->query('SELECT 1 FROM crm_device_locks LIMIT 1');
            $locks = $this->fetchLocks();
        } catch (\Throwable $e) {
            $dbReady = false;
            $locks = [];
        }

        $this->view('layout', ['data_operasi' => ['title' => 'CRM Devices']]);
        $this->view('tools/crm_devices', [
            'locks' => $locks,
            'db_ready' => $dbReady,
        ]);
    }

    public function unbind()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $username = strtoupper(trim((string) ($_POST['username'] ?? '')));
        if ($username === '' || strlen($username) > 64) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Username tidak valid'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $db = $this->dbMain();
            $row = $db->get_where_row('crm_device_locks', "username = '" . $db->escape($username) . "'");
            if (!$row) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'message' => 'Lock tidak ditemukan'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $db->delete('crm_device_locks', "username = '" . $db->escape($username) . "'");
            echo json_encode([
                'ok' => true,
                'message' => 'Device ' . $username . ' berhasil di-unbind',
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'Gagal unbind: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @return list<array<string,mixed>> */
    private function fetchLocks(): array
    {
        $rows = $this->dbMain()->query_array(
            'SELECT username, device_id, locked_at, last_seen
             FROM crm_device_locks
             ORDER BY last_seen DESC, username ASC'
        );

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $username = strtoupper(trim((string) ($row['username'] ?? '')));
            $meta = $this->resolveUsernameMeta($username);
            $out[] = array_merge($row, [
                'username' => $username,
                'role' => $meta['role'],
                'label' => $meta['label'],
            ]);
        }

        return $out;
    }

    /** @return array{role:string,label:string} */
    private function resolveUsernameMeta(string $username): array
    {
        if ($username === '') {
            return ['role' => '-', 'label' => '-'];
        }

        $dbMain = $this->dbMain();
        $esc = $dbMain->escape($username);

        try {
            $admin = $dbMain->get_where_row('crm_users', "UPPER(username) = UPPER('" . $esc . "')");
            if (is_array($admin) && !empty($admin['username'])) {
                $name = trim((string) ($admin['name'] ?? ''));
                return [
                    'role' => 'Admin',
                    'label' => $name !== '' ? $name : (string) $admin['username'],
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $isNumeric = ctype_digit($username);
        $isPhoneLike = $isNumeric && strlen($username) >= 10;

        if ($isPhoneLike) {
            $driver = $this->resolveDriverLabel($username);
            if ($driver !== null) {
                return ['role' => 'Driver', 'label' => $driver];
            }
        }

        if ($isNumeric) {
            $cabang = $this->db(0)->get_where_row('cabang', 'id_cabang = ' . (int) $username);
            if (!empty($cabang['id_cabang'])) {
                $kode = trim((string) ($cabang['kode_cabang'] ?? ''));
                $nama = trim((string) ($cabang['nama'] ?? ''));
                $label = $kode !== '' ? $kode : ('Cabang #' . $username);
                if ($nama !== '') {
                    $label .= ' — ' . $nama;
                }

                return ['role' => 'Crew', 'label' => $label];
            }
        }

        $driver = $this->resolveDriverLabel($username);
        if ($driver !== null) {
            return ['role' => 'Driver', 'label' => $driver];
        }

        return ['role' => 'CRM', 'label' => $username];
    }

    private function resolveDriverLabel(string $username): ?string
    {
        $digits = preg_replace('/[^0-9]/', '', $username);
        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (str_starts_with($digits, '62') && strlen($digits) >= 11) {
            $digits = substr($digits, 2);
        }

        if ($digits === '' || strlen($digits) < 8 || !str_starts_with($digits, '8')) {
            return null;
        }

        $db = $this->db(0);
        $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(no_user,'+',''),'-',''),' ',''),'(',''),')','')";
        try {
            $row = $db->query_array(
                "SELECT nama_user FROM user WHERE en = 1 AND {$expr} LIKE '%" . $db->escape($digits) . "' LIMIT 1"
            );
            if (is_array($row) && !empty($row[0]['nama_user'])) {
                return trim((string) $row[0]['nama_user']);
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
