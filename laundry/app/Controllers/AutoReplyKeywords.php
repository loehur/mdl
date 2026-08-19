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

    /** Checkbox POST: ada & bukan '0' → 1. */
    private function postFlag(string $key): int
    {
        if (!isset($_POST[$key])) {
            return 0;
        }
        $v = $_POST[$key];
        if ($v === '' || $v === '0' || $v === 0 || $v === false) {
            return 0;
        }

        return 1;
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
        ]);
    }

    /** Unduh seluruh AI prompt yang terisi dalam satu dokumen Markdown. */
    public function downloadAiPrompts()
    {
        $this->session_cek(1);

        $intents = $this->dbMain()->query_array(
            "SELECT code, ai_prompt
             FROM wa_autoreply_intents
             WHERE ai_prompt IS NOT NULL AND TRIM(ai_prompt) <> ''
             ORDER BY sort_order ASC, id ASC"
        );
        if (!is_array($intents)) {
            $intents = [];
        }

        $content = "# AI Prompts — Auto Reply Keywords\n\n";
        $content .= "Dibuat: " . date('Y-m-d H:i:s') . "\n\n";
        foreach ($intents as $intent) {
            $code = strtoupper(trim((string) ($intent['code'] ?? '')));
            $prompt = trim((string) ($intent['ai_prompt'] ?? ''));
            if ($code === '' || $prompt === '') {
                continue;
            }
            $content .= "=== " . $code . " ===\n\n" . $prompt . "\n\n";
        }

        $fileName = 'autoreply-ai-prompts-' . date('Ymd-His') . '.md';
        header('Content-Type: text/markdown; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF" . $content;
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

        $maxRaw = trim((string) ($_POST['chat_maxlength'] ?? ''));
        $chatMaxlength = ($maxRaw === '') ? null : max(0, (int) $maxRaw);
        if ($chatMaxlength === 0) {
            $chatMaxlength = null;
        }

        $set = [
            'code' => $code,
            'sort_order' => $sort,
            'is_active' => $isActive,
            'note' => $note !== '' ? $note : null,
            'ai_prompt' => $aiPrompt,
            'case_value' => $caseValue,
            'notify' => $notify,
            'chat_maxlength' => $chatMaxlength,
            'is_admin' => $this->postFlag('is_admin'),
            'is_karyawan' => $this->postFlag('is_karyawan'),
            'is_pelanggan' => $this->postFlag('is_pelanggan'),
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
            'is_admin' => $this->postFlag('is_admin'),
            'is_karyawan' => $this->postFlag('is_karyawan'),
            'is_pelanggan' => $this->postFlag('is_pelanggan'),
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
     * Preview / terapkan gabung pattern keyword sederhana jadi 1 record per intent.
     * POST { intent_id } atau { all: 1 }  + apply=1 untuk mengeksekusi.
     */
    public function compactPatterns()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $this->helper('IntentPatternBag');

        $raw = file_get_contents('php://input');
        $data = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        if ($data === []) {
            $data = $_POST;
        }

        $apply = !empty($data['apply']);
        $all = !empty($data['all']);
        $intentId = (int) ($data['intent_id'] ?? 0);

        $intents = [];
        if ($all) {
            $rows = $this->dbMain()->query_array(
                "SELECT id, code FROM wa_autoreply_intents ORDER BY sort_order ASC, id ASC"
            );
            $intents = is_array($rows) ? $rows : [];
        } elseif ($intentId > 0) {
            $one = $this->dbMain()->get_where_row('wa_autoreply_intents', "id = {$intentId}");
            if ($one) {
                $intents[] = $one;
            }
        } else {
            echo json_encode(['ok' => 0, 'message' => 'intent_id atau all wajib'], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($intents === []) {
            echo json_encode(['ok' => 0, 'message' => 'Intent tidak ditemukan'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $plans = [];
        $db = $this->dbMain();
        $changed = false;
        $deletedTotal = 0;
        $intentsTouched = 0;

        foreach ($intents as $intent) {
            $iid = (int) ($intent['id'] ?? 0);
            $code = (string) ($intent['code'] ?? '');
            if ($iid <= 0) {
                continue;
            }
            $patRows = $db->query_array(
                "SELECT id, pattern, is_active FROM wa_autoreply_patterns WHERE intent_id = {$iid} ORDER BY sort_order ASC, id ASC"
            );
            if (!is_array($patRows)) {
                $patRows = [];
            }
            $plan = IntentPatternBag::compactRows($patRows);
            $plan['intent_id'] = $iid;
            $plan['intent'] = $code;

            if ($apply && !empty($plan['needed'])) {
                $keeperId = (int) ($plan['keeper_id'] ?? 0);
                $merged = (string) ($plan['merged_pattern'] ?? '');
                $deleteIds = $plan['delete_ids'] ?? [];
                if ($keeperId > 0 && $merged !== '' && @preg_match($merged, '') !== false) {
                    $up = $db->update(
                        'wa_autoreply_patterns',
                        [
                            'pattern' => $merged,
                            'note' => 'Rapikan: gabung keyword sederhana',
                        ],
                        "id = {$keeperId} AND intent_id = {$iid}"
                    );
                    if (($up['errno'] ?? 1) != 0) {
                        $plan['apply_error'] = $up['error'] ?? 'Gagal update keeper';
                        $plans[] = $plan;
                        continue;
                    }
                    $deleted = 0;
                    foreach ($deleteIds as $did) {
                        $did = (int) $did;
                        if ($did <= 0 || $did === $keeperId) {
                            continue;
                        }
                        $del = $db->delete(
                            'wa_autoreply_patterns',
                            "id = {$did} AND intent_id = {$iid}"
                        );
                        if (($del['errno'] ?? 1) == 0) {
                            $deleted++;
                        }
                    }
                    $plan['deleted'] = $deleted;
                    $plan['applied'] = true;
                    $deletedTotal += $deleted;
                    $intentsTouched++;
                    $changed = true;
                }
            }

            $plans[] = $plan;
        }

        if ($changed) {
            $this->bumpCache();
        }

        $needed = array_values(array_filter($plans, static fn ($p) => !empty($p['needed'])));

        echo json_encode([
            'ok' => 1,
            'applied' => $apply,
            'intents_touched' => $intentsTouched,
            'patterns_deleted' => $deletedTotal,
            'needed_count' => count($needed),
            'plans' => $needed,
            'message' => $apply
                ? ($intentsTouched > 0
                    ? "Dirapikan: {$intentsTouched} intent, hapus {$deletedTotal} pattern duplikat."
                    : 'Tidak ada yang perlu dirapikan.')
                : (count($needed) > 0
                    ? count($needed) . ' intent bisa dirapikan.'
                    : 'Tidak ada pattern keyword sederhana yang bisa digabung.'),
        ], JSON_UNESCAPED_UNICODE);
    }
}
