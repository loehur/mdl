<?php

/**
 * CRM Setting — CRUD balas cepat kustom untuk chat CRM.
 * Tabel: mdl_main.crm_quick_replies
 */
class CrmQuickReplies extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    private function dbMain()
    {
        return $this->db(100);
    }

    /** @return string|null */
    private function normalizeShortcut(string $raw)
    {
        $s = strtolower(trim($raw));
        if ($s === '') {
            return null;
        }
        if ($s[0] !== '/') {
            $s = '/' . $s;
        }
        if (!preg_match('/^\/[a-z0-9][a-z0-9\-_]*$/', $s)) {
            return null;
        }
        if ($s === '/rekening' || str_ends_with($s, '-location')) {
            return null;
        }

        return $s;
    }

    public function index()
    {
        $this->session_cek(1);

        $rows = [];
        $dbReady = true;
        try {
            $this->dbMain()->query('SELECT 1 FROM crm_quick_replies LIMIT 1');
            $rows = $this->dbMain()->query_array(
                'SELECT id, shortcut, title, message, sort_order, is_active, created_at, updated_at
                 FROM crm_quick_replies
                 ORDER BY sort_order ASC, id ASC'
            );
            if (!is_array($rows)) {
                $rows = [];
            }
        } catch (\Throwable $e) {
            $dbReady = false;
            $rows = [];
        }

        $this->view('layout', ['data_operasi' => ['title' => 'Quick Replies']]);
        $this->view('crm_setting/quick_replies', [
            'rows' => $rows,
            'db_ready' => $dbReady,
        ]);
    }

    public function insert()
    {
        $this->session_cek(1);

        $shortcut = $this->normalizeShortcut((string) ($_POST['shortcut'] ?? ''));
        if ($shortcut === null) {
            echo 'Shortcut wajib (contoh: /promo). Huruf kecil, angka, - dan _. Tidak boleh /rekening atau *-location.';
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($title === '' || $message === '') {
            echo 'Judul dan pesan wajib diisi';
            return;
        }

        $max = $this->dbMain()->query_array('SELECT COALESCE(MAX(sort_order), 0) AS m FROM crm_quick_replies');
        $sort = (int) (($max[0]['m'] ?? 0) + 1);

        $in = $this->dbMain()->insert('crm_quick_replies', [
            'shortcut' => $shortcut,
            'title' => mb_substr($title, 0, 128),
            'message' => $message,
            'sort_order' => $sort,
            'is_active' => 1,
        ]);

        if (($in['errno'] ?? 1) == 0) {
            echo 0;
        } else {
            echo $in['error'] ?? 'Insert failed';
        }
    }

    public function update()
    {
        $this->session_cek(1);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }

        $mode = (string) ($_POST['mode'] ?? '');
        $value = $_POST['value'] ?? '';
        $set = [];

        if ($mode === 'shortcut') {
            $shortcut = $this->normalizeShortcut((string) $value);
            if ($shortcut === null) {
                echo 'Shortcut tidak valid';
                return;
            }
            $set['shortcut'] = $shortcut;
        } elseif ($mode === 'title') {
            $title = trim((string) $value);
            if ($title === '') {
                echo 'Judul kosong';
                return;
            }
            $set['title'] = mb_substr($title, 0, 128);
        } elseif ($mode === 'message') {
            $message = trim((string) $value);
            if ($message === '') {
                echo 'Pesan kosong';
                return;
            }
            $set['message'] = $message;
        } elseif ($mode === 'sort_order') {
            $set['sort_order'] = (int) $value;
        } elseif ($mode === 'is_active') {
            $set['is_active'] = ((int) $value) ? 1 : 0;
        } else {
            echo 'Invalid mode';
            return;
        }

        $up = $this->dbMain()->update('crm_quick_replies', $set, "id = $id");
        if (($up['errno'] ?? 1) == 0) {
            echo 0;
        } else {
            echo $up['error'] ?? 'Update failed';
        }
    }

    public function delete()
    {
        $this->session_cek(1);

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }

        $del = $this->dbMain()->delete('crm_quick_replies', "id = $id");
        if (($del['errno'] ?? 1) == 0) {
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }
}
