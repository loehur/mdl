<?php

/**
 * Admin Tools — CRUD Auto Reply Keywords (intent + patterns)
 * Tabel di db(100) = mdl_main: wa_autoreply_intents, wa_autoreply_patterns, wa_autoreply_meta
 */
class AutoReplyKeywords extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    private function dbMain()
    {
        return $this->db(100);
    }

    private function bumpCache(): void
    {
        $db = $this->dbMain();
        $row = $db->get_where_row('wa_autoreply_meta', "meta_key = 'cache_version'");
        if (!$row) {
            $db->insert('wa_autoreply_meta', ['meta_key' => 'cache_version', 'meta_value' => '1']);
            return;
        }
        $next = (string) ((int) ($row['meta_value'] ?? 0) + 1);
        $db->update('wa_autoreply_meta', ['meta_value' => $next], "meta_key = 'cache_version'");
    }

    private function keywordsFilePath(): string
    {
        return dirname(__DIR__, 3) . '/api/app/Config/AutoReplyKeywords.php';
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Auto Reply Keywords'];

        $intents = [];
        $dbReady = true;
        $source = 'db';
        try {
            $intents = $this->dbMain()->query_array(
                "SELECT i.*,
                        (SELECT COUNT(*) FROM wa_autoreply_patterns p WHERE p.intent_id = i.id) AS pattern_count,
                        (SELECT COUNT(*) FROM wa_autoreply_patterns p WHERE p.intent_id = i.id AND p.is_active = 1) AS pattern_active
                 FROM wa_autoreply_intents i
                 ORDER BY i.sort_order ASC, i.id ASC"
            );
            if ($intents === false) {
                $dbReady = false;
                $intents = [];
                $source = 'error';
            } elseif (count($intents) === 0) {
                $source = 'empty';
            }
        } catch (\Throwable $e) {
            $dbReady = false;
            $intents = [];
            $source = 'error';
        }

        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/autoreply_keywords', [
            'intents' => $intents,
            'db_ready' => $dbReady,
            'source' => $source,
            'file_exists' => is_file($this->keywordsFilePath()),
        ]);
    }

    public function detail($id = 0)
    {
        $this->session_cek(1);
        $id = (int) $id;
        if ($id <= 0) {
            header('Location: ' . URL::BASE_URL . 'AutoReplyKeywords');
            return;
        }

        $intent = $this->dbMain()->get_where_row('wa_autoreply_intents', "id = $id");
        if (!$intent) {
            header('Location: ' . URL::BASE_URL . 'AutoReplyKeywords');
            return;
        }

        $patterns = $this->dbMain()->get_where_order(
            'wa_autoreply_patterns',
            "intent_id = $id",
            'sort_order ASC, id ASC'
        );
        if (!is_array($patterns)) {
            $patterns = [];
        }

        $data_operasi = ['title' => 'Intent ' . $intent['code']];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/autoreply_keyword_detail', [
            'intent' => $intent,
            'patterns' => $patterns,
        ]);
    }

    public function updateIntent()
    {
        $this->session_cek(1);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }

        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if ($code === '' || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            echo 'Code wajib (A-Z, 0-9, underscore)';
            return;
        }

        $sort = (int) ($_POST['sort_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $note = trim((string) ($_POST['note'] ?? ''));
        $aiPrompt = (string) ($_POST['ai_prompt'] ?? '');
        $aiPrompt = $aiPrompt === '' ? null : $aiPrompt;

        $caseRaw = trim((string) ($_POST['case_value'] ?? ''));
        $caseValue = ($caseRaw === '') ? null : (int) $caseRaw;

        $notifyRaw = trim((string) ($_POST['notify'] ?? ''));
        $notify = null;
        if ($notifyRaw === '1') {
            $notify = 1;
        } elseif ($notifyRaw === '0') {
            $notify = 0;
        }

        $set = [
            'code' => $code,
            'sort_order' => $sort,
            'is_active' => $isActive,
            'note' => $note !== '' ? $note : null,
            'ai_prompt' => $aiPrompt,
            'case_value' => $caseValue,
            'notify' => $notify,
        ];

        $up = $this->dbMain()->update('wa_autoreply_intents', $set, "id = $id");
        if (($up['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $up['error'] ?? 'Update failed';
        }
    }

    public function insertIntent()
    {
        $this->session_cek(1);
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        if ($code === '' || !preg_match('/^[A-Z0-9_]+$/', $code)) {
            echo 'Code wajib (A-Z, 0-9, underscore)';
            return;
        }

        $max = $this->dbMain()->query_array('SELECT COALESCE(MAX(sort_order),0) AS m FROM wa_autoreply_intents');
        $sort = (int) (($max[0]['m'] ?? 0) + 1);

        $in = $this->dbMain()->insert('wa_autoreply_intents', [
            'code' => $code,
            'sort_order' => $sort,
            'is_active' => 1,
            'ai_prompt' => null,
            'case_value' => null,
            'notify' => null,
            'note' => trim((string) ($_POST['note'] ?? '')) ?: null,
        ]);

        if (($in['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $in['error'] ?? 'Insert failed';
        }
    }

    public function deleteIntent()
    {
        $this->session_cek(1);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }
        $del = $this->dbMain()->delete('wa_autoreply_intents', "id = $id");
        if (($del['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }

    public function insertPattern()
    {
        $this->session_cek(1);
        $intentId = (int) ($_POST['intent_id'] ?? 0);
        $pattern = trim((string) ($_POST['pattern'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($intentId <= 0 || $pattern === '') {
            echo 'Intent & pattern wajib';
            return;
        }
        if (@preg_match($pattern, '') === false) {
            echo 'Regex tidak valid: ' . preg_last_error_msg();
            return;
        }

        $max = $this->dbMain()->query_array(
            "SELECT COALESCE(MAX(sort_order),0) AS m FROM wa_autoreply_patterns WHERE intent_id = $intentId"
        );
        $sort = (int) (($max[0]['m'] ?? 0) + 1);

        $in = $this->dbMain()->insert('wa_autoreply_patterns', [
            'intent_id' => $intentId,
            'pattern' => $pattern,
            'sort_order' => $sort,
            'is_active' => 1,
            'note' => $note !== '' ? $note : null,
        ]);
        if (($in['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $in['error'] ?? 'Insert failed';
        }
    }

    public function updatePattern()
    {
        $this->session_cek(1);
        $id = (int) ($_POST['id'] ?? 0);
        $mode = (string) ($_POST['mode'] ?? '');
        $value = $_POST['value'] ?? '';

        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }

        $set = [];
        if ($mode === 'pattern') {
            $pattern = trim((string) $value);
            if ($pattern === '') {
                echo 'Pattern kosong';
                return;
            }
            if (@preg_match($pattern, '') === false) {
                echo 'Regex tidak valid: ' . preg_last_error_msg();
                return;
            }
            $set['pattern'] = $pattern;
        } elseif ($mode === 'note') {
            $set['note'] = trim((string) $value) !== '' ? trim((string) $value) : null;
        } elseif ($mode === 'sort_order') {
            $set['sort_order'] = (int) $value;
        } elseif ($mode === 'is_active') {
            $set['is_active'] = ((int) $value) ? 1 : 0;
        } else {
            echo 'Invalid mode';
            return;
        }

        $up = $this->dbMain()->update('wa_autoreply_patterns', $set, "id = $id");
        if (($up['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $up['error'] ?? 'Update failed';
        }
    }

    public function deletePattern()
    {
        $this->session_cek(1);
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo 'Invalid id';
            return;
        }
        $del = $this->dbMain()->delete('wa_autoreply_patterns', "id = $id");
        if (($del['errno'] ?? 1) == 0) {
            $this->bumpCache();
            echo 0;
        } else {
            echo $del['error'] ?? 'Delete failed';
        }
    }

    /** Test regex terhadap teks contoh */
    public function testPattern()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $pattern = (string) ($_POST['pattern'] ?? '');
        $text = (string) ($_POST['text'] ?? '');
        if ($pattern === '') {
            echo json_encode(['ok' => 0, 'message' => 'Pattern kosong']);
            return;
        }
        $valid = @preg_match($pattern, $text);
        if ($valid === false) {
            echo json_encode(['ok' => 0, 'message' => 'Regex invalid: ' . preg_last_error_msg()]);
            return;
        }
        echo json_encode([
            'ok' => 1,
            'match' => $valid === 1,
            'message' => $valid === 1 ? 'MATCH' : 'no match',
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Seed dari api/app/Config/AutoReplyKeywords.php
     * POST replace=1 untuk timpa ulang
     */
    public function seed()
    {
        $this->session_cek(1);
        $replace = !empty($_POST['replace']);
        $path = $this->keywordsFilePath();
        if (!is_file($path)) {
            echo 'File tidak ditemukan: AutoReplyKeywords.php';
            return;
        }

        $data = require $path;
        if (!is_array($data) || $data === []) {
            echo 'File kosong / invalid';
            return;
        }

        $db = $this->dbMain();
        if (!$replace) {
            $cnt = $db->query_array('SELECT COUNT(*) AS c FROM wa_autoreply_intents');
            if ((int) ($cnt[0]['c'] ?? 0) > 0) {
                echo 'DB sudah berisi data. Centang Replace untuk timpa dari file.';
                return;
            }
        } else {
            $db->query('DELETE FROM wa_autoreply_patterns');
            $db->query('DELETE FROM wa_autoreply_intents');
        }

        $intentCount = 0;
        $patternCount = 0;
        $sort = 0;
        foreach ($data as $code => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $sort++;
            $code = strtoupper(trim((string) $code));
            $row = [
                'code' => $code,
                'sort_order' => $sort,
                'is_active' => 1,
                'ai_prompt' => isset($cfg['ai_prompt']) && is_string($cfg['ai_prompt']) ? $cfg['ai_prompt'] : null,
                'case_value' => null,
                'notify' => null,
            ];
            if (array_key_exists('case', $cfg)) {
                $row['case_value'] = $cfg['case'] === null ? null : (int) $cfg['case'];
            }
            if (array_key_exists('notify', $cfg)) {
                $row['notify'] = $cfg['notify'] ? 1 : 0;
            }

            $in = $db->insert('wa_autoreply_intents', $row);
            $intentId = (int) ($in['insert_id'] ?? 0);
            if (($in['errno'] ?? 1) != 0 || $intentId <= 0) {
                continue;
            }
            $intentCount++;
            $psort = 0;
            foreach (($cfg['patterns'] ?? []) as $pat) {
                if (!is_string($pat) || $pat === '') {
                    continue;
                }
                $psort++;
                $pin = $db->insert('wa_autoreply_patterns', [
                    'intent_id' => $intentId,
                    'pattern' => $pat,
                    'sort_order' => $psort,
                    'is_active' => 1,
                ]);
                if (($pin['errno'] ?? 1) == 0) {
                    $patternCount++;
                }
            }
        }

        $this->bumpCache();
        echo 0; // success — UI reloads; counts in flash via query string optional
        // Also print counts for non-zero responses? Reminder uses 0 only.
        // Put counts in a custom success: still echo 0 and rely on reload.
        // For UX, echo JSON if wanted — keep Reminder style: 0 = OK
        // Store message in session briefly? Skip — page shows new rows.
    }
}
