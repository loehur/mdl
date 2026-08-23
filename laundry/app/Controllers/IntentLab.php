<?php

/**
 * Admin Tools — Intent Lab (cek klasifikasi + ajarkan intent ke DB)
 */
class IntentLab extends Controller
{
    public function __construct()
    {
        $this->operating_data();
    }

    private function dbMain()
    {
        return $this->db(100);
    }

    private function readCacheVersion(): string
    {
        $db = $this->dbMain();
        $row = $db->get_where_row('wa_autoreply_meta', "meta_key = 'cache_version'");
        if (!$row) {
            return '0';
        }

        return (string) ($row['meta_value'] ?? '0');
    }

    /**
     * Naikkan wa_autoreply_meta.cache_version (+1) agar API reload config intent dari DB.
     *
     * @return array{before:string,after:string}
     */
    private function bumpAutoreplyCache(): array
    {
        $db = $this->dbMain();
        $row = $db->get_where_row('wa_autoreply_meta', "meta_key = 'cache_version'");
        if (!$row) {
            $db->insert('wa_autoreply_meta', ['meta_key' => 'cache_version', 'meta_value' => '1']);

            return ['before' => '0', 'after' => '1'];
        }
        $before = (string) ($row['meta_value'] ?? '0');
        $next = (string) ((int) $before + 1);
        $db->update('wa_autoreply_meta', ['meta_value' => $next], "meta_key = 'cache_version'");

        return ['before' => $before, 'after' => $next];
    }

    /**
     * GET — baca cache_version saat ini (mdl_main.wa_autoreply_meta).
     */
    public function cacheVersion()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $this->jsonOut([
            'ok' => 1,
            'cache_version' => $this->readCacheVersion(),
        ]);
    }

    /**
     * POST — bump cache_version manual (+1), kembalikan before/after.
     */
    public function bumpCache()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $result = $this->bumpAutoreplyCache();
        $this->jsonOut([
            'ok' => 1,
            'message' => 'cache_version ' . $result['before'] . ' → ' . $result['after'],
            'cache_version_before' => $result['before'],
            'cache_version' => $result['after'],
        ]);
    }

    /** @return list<array{id:int|string,code:string}> */
    private function listActiveIntents(): array
    {
        $rows = $this->dbMain()->query_array(
            "SELECT id, code FROM wa_autoreply_intents WHERE is_active = 1 ORDER BY sort_order ASC, code ASC"
        );
        return is_array($rows) ? $rows : [];
    }

    public function index()
    {
        $this->session_cek(1);
        $data_operasi = ['title' => 'Intent Lab'];
        $this->view('layout', ['data_operasi' => $data_operasi]);
        $this->view('tools/intent_lab', [
            'intents' => $this->listActiveIntents(),
            'cache_version' => $this->readCacheVersion(),
        ]);
    }

    /**
     * POST JSON { text } → proxy ke API IntentCheck
     */
    public function check()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? $_POST['text'] ?? ''));
        if ($text === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'Teks kosong']);
            return;
        }

        try {
            // Regex DB lokal dulu — tidak bergantung API remote (sering timeout/error).
            $local = $this->localRegexClassify($text);
            if ($local !== null) {
                $this->jsonOut($local);
                return;
            }

            $api = $this->helper('IntentCheckApi');
            $res = $api->check($text);
            if (!is_array($res)) {
                $res = ['ok' => 0, 'message' => 'Respon API tidak valid'];
            }
            $res = $this->mergeLocalRegexClassify($text, $res);
            $res = $this->rejectClassifyIfExceedsChatMaxlength($text, $res);
            if (!isset($res['ok'])) {
                $res['ok'] = 0;
            }
            $this->jsonOut($res);
        } catch (\Throwable $e) {
            $this->jsonOut([
                'ok' => 0,
                'message' => 'Error Intent Lab: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * POST { text, intent } → API AI usulkan pattern + prompt_append
     */
    public function proposeTeach()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? ''));
        $intent = strtoupper(trim((string) ($data['intent'] ?? '')));
        if ($text === '' || $intent === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'text dan intent wajib']);
            return;
        }

        $api = $this->helper('IntentCheckApi');
        $res = $api->proposeTeach($text, $intent);
        if (!isset($res['ok'])) {
            $res['ok'] = false;
        }
        if ($res['ok'] === true) {
            $res['ok'] = 1;
        } elseif ($res['ok'] === false) {
            $res['ok'] = 0;
        }
        $this->jsonOut($res);
    }

    /**
     * POST { text, intent, pattern, ai_prompt, update_prompt=1 }
     * Simpan pattern (+ opsional replace ai_prompt) ke DB, bump cache, re-cek intent.
     */
    public function applyTeach()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? ''));
        $intentCode = strtoupper(trim((string) ($data['intent'] ?? '')));
        $pattern = trim((string) ($data['pattern'] ?? ''));
        $promptAppend = trim((string) ($data['prompt_append'] ?? ''));
        $aiPrompt = array_key_exists('ai_prompt', $data) ? (string) $data['ai_prompt'] : null;
        $patternId = (int) ($data['pattern_id'] ?? 0);
        $updatePrompt = !isset($data['update_prompt']) || !empty($data['update_prompt']);
        $addPattern = !isset($data['add_pattern']) || !empty($data['add_pattern']);

        if ($text === '' || $intentCode === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'text dan intent wajib']);
            return;
        }
        if ($addPattern && $pattern === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'pattern wajib']);
            return;
        }
        if ($addPattern) {
            $pattern = $this->normalizePatternForText($pattern, $text);
        }
        if ($addPattern && @preg_match($pattern, '') === false) {
            $this->jsonOut([
                'ok' => 0,
                'message' => 'Regex tidak valid: ' . preg_last_error_msg(),
            ]);
            return;
        }
        if ($addPattern && @preg_match($pattern, $text) !== 1) {
            $this->jsonOut([
                'ok' => 0,
                'message' => 'Pattern tidak match teks contoh. Perbaiki dulu sebelum aktifkan.',
            ]);
            return;
        }

        $db = $this->dbMain();
        $intent = $db->get_where_row(
            'wa_autoreply_intents',
            "code = '" . $db->escape($intentCode) . "' AND is_active = 1"
        );
        if (!$intent || empty($intent['id'])) {
            $this->jsonOut(['ok' => 0, 'message' => 'Intent tidak ditemukan']);
            return;
        }
        $intentId = (int) $intent['id'];

        $patternAdded = false;
        $patternUpdated = false;
        $promptUpdated = false;
        $patternDupSkipped = false;

        if ($addPattern) {
            if ($patternId > 0) {
                $existing = $db->query_array(
                    "SELECT id, note FROM wa_autoreply_patterns
                     WHERE id = {$patternId} AND intent_id = {$intentId} LIMIT 1"
                );
                if (!is_array($existing) || count($existing) === 0) {
                    $this->jsonOut([
                        'ok' => 0,
                        'message' => 'Pattern existing tidak ditemukan (id=' . $patternId . ')',
                    ]);
                    return;
                }
                $oldNote = trim((string) ($existing[0]['note'] ?? ''));
                $tag = 'Intent Lab widen: ' . mb_substr($text, 0, 80);
                $newNote = $oldNote !== '' ? ($oldNote . ' | ' . $tag) : $tag;
                $up = $db->update(
                    'wa_autoreply_patterns',
                    [
                        'pattern' => $pattern,
                        'is_active' => 1,
                        'note' => mb_substr($newNote, 0, 250),
                    ],
                    "id = {$patternId} AND intent_id = {$intentId}"
                );
                if (($up['errno'] ?? 1) != 0) {
                    $this->jsonOut([
                        'ok' => 0,
                        'message' => $up['error'] ?? 'Gagal ubah pattern',
                    ]);
                    return;
                }
                $patternUpdated = true;
            } else {
                $dup = $db->query_array(
                    "SELECT id FROM wa_autoreply_patterns WHERE intent_id = {$intentId} AND pattern = '" . $db->escape($pattern) . "' LIMIT 1"
                );
                if (is_array($dup) && count($dup) > 0) {
                    $dupId = (int) ($dup[0]['id'] ?? 0);
                    if ($dupId > 0) {
                        $existingDup = $db->query_array(
                            "SELECT note FROM wa_autoreply_patterns WHERE id = {$dupId} AND intent_id = {$intentId} LIMIT 1"
                        );
                        $oldNote = trim((string) ($existingDup[0]['note'] ?? ''));
                        $tag = 'Intent Lab teach: ' . mb_substr($text, 0, 80);
                        $newNote = $oldNote !== '' ? ($oldNote . ' | ' . $tag) : $tag;
                        $up = $db->update(
                            'wa_autoreply_patterns',
                            [
                                'pattern' => $pattern,
                                'is_active' => 1,
                                'note' => mb_substr($newNote, 0, 250),
                            ],
                            "id = {$dupId} AND intent_id = {$intentId}"
                        );
                        if (($up['errno'] ?? 1) == 0) {
                            $patternUpdated = true;
                        }
                    }
                    $patternDupSkipped = true;
                } else {
                    $max = $db->query_array(
                        "SELECT COALESCE(MAX(sort_order),0) AS m FROM wa_autoreply_patterns WHERE intent_id = {$intentId}"
                    );
                    $sort = (int) (($max[0]['m'] ?? 0) + 1);
                    $in = $db->insert('wa_autoreply_patterns', [
                        'intent_id' => $intentId,
                        'pattern' => $pattern,
                        'sort_order' => $sort,
                        'is_active' => 1,
                        'note' => 'Intent Lab teach: ' . mb_substr($text, 0, 120),
                    ]);
                    if (($in['errno'] ?? 1) != 0) {
                        $this->jsonOut([
                            'ok' => 0,
                            'message' => $in['error'] ?? 'Gagal insert pattern',
                        ]);
                        return;
                    }
                    $patternAdded = true;
                }
            }
        }

        if ($updatePrompt) {
            $current = (string) ($intent['ai_prompt'] ?? '');
            $newPrompt = $this->resolvePromptReplace($current, $aiPrompt, $promptAppend);
            if ($newPrompt !== $current) {
                $up = $db->update(
                    'wa_autoreply_intents',
                    ['ai_prompt' => $newPrompt],
                    "id = {$intentId}"
                );
                if (($up['errno'] ?? 1) != 0) {
                    $this->jsonOut([
                        'ok' => 0,
                        'message' => $up['error'] ?? 'Gagal update ai_prompt',
                    ]);
                    return;
                }
                $promptUpdated = true;
            }
        }

        $cacheBump = null;
        if ($patternAdded || $patternUpdated || $promptUpdated) {
            $cacheBump = $this->bumpAutoreplyCache();
        } elseif ($patternDupSkipped) {
            $cacheBump = $this->bumpAutoreplyCache();
        }

        // Re-cek: regex dari DB lokal (intent target dulu, lalu global)
        $check = $this->localRegexClassifyForIntent($text, $intentCode);
        if ($check === null) {
            $check = $this->localRegexClassify($text);
        }
        if ($check === null && $addPattern && $pattern !== '') {
            $check = $this->buildLocalRegexResult($text, $intentCode, $pattern);
        }
        if ($check === null) {
            $api = $this->helper('IntentCheckApi');
            $check = $api->check($text, $cacheBump !== null);
            if (is_array($check)) {
                $check = $this->rejectClassifyIfExceedsChatMaxlength($text, $check);
            }
        }
        $gotIntent = strtoupper((string) ($check['intent'] ?? ''));
        $verifyOk = ($gotIntent === $intentCode);

        $out = [
            'ok' => 1,
            'message' => 'Aktif',
            'saved_pattern' => $addPattern ? $pattern : null,
            'pattern_added' => $patternAdded,
            'pattern_updated' => $patternUpdated,
            'pattern_dup_skipped' => $patternDupSkipped,
            'prompt_updated' => $promptUpdated,
            'pattern_id' => $patternId > 0 ? $patternId : null,
            'target_intent' => $intentCode,
            'verify_intent' => $gotIntent,
            'verify_ok' => $verifyOk,
            'verify' => $check,
            'cache_version' => $this->readCacheVersion(),
        ];
        if ($cacheBump !== null) {
            $out['cache_version_before'] = $cacheBump['before'];
            $out['cache_version_bumped'] = true;
        }
        $this->jsonOut($out);
    }

    /**
     * POST { text, intent } → API AI usulkan keluarkan dari intent
     */
    public function proposeUntouch()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? ''));
        $intent = strtoupper(trim((string) ($data['intent'] ?? '')));
        if ($text === '' || $intent === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'text dan intent wajib']);
            return;
        }

        $api = $this->helper('IntentCheckApi');
        $res = $api->proposeUntouch($text, $intent);
        if (!isset($res['ok'])) {
            $res['ok'] = false;
        }
        if ($res['ok'] === true) {
            $res['ok'] = 1;
        } elseif ($res['ok'] === false) {
            $res['ok'] = 0;
        }
        $this->jsonOut($res);
    }

    /**
     * POST { text, intent, ai_prompt, pattern_ids[], deactivate_patterns=1, update_prompt=1 }
     * Nonaktifkan pattern yang match + replace ai_prompt, bump cache, re-cek.
     */
    public function applyUntouch()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? ''));
        $intentCode = strtoupper(trim((string) ($data['intent'] ?? '')));
        $promptAppend = trim((string) ($data['prompt_append'] ?? ''));
        $aiPrompt = array_key_exists('ai_prompt', $data) ? (string) $data['ai_prompt'] : null;
        $updatePrompt = !isset($data['update_prompt']) || !empty($data['update_prompt']);
        $deactivatePatterns = !isset($data['deactivate_patterns']) || !empty($data['deactivate_patterns']);

        $patternIds = $data['pattern_ids'] ?? [];
        if (!is_array($patternIds)) {
            $patternIds = [];
        }
        $patternIds = array_values(array_unique(array_filter(array_map('intval', $patternIds), static fn ($id) => $id > 0)));

        if ($text === '' || $intentCode === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'text dan intent wajib']);
            return;
        }
        if (!$deactivatePatterns && !$updatePrompt) {
            $this->jsonOut(['ok' => 0, 'message' => 'Centang minimal satu aksi']);
            return;
        }
        if ($deactivatePatterns && $patternIds === []) {
            $hasPrompt = $aiPrompt !== null || $promptAppend !== '';
            if (!$updatePrompt || !$hasPrompt) {
                $this->jsonOut([
                    'ok' => 0,
                    'message' => 'Tidak ada pattern untuk dinonaktifkan. Centang Update ai_prompt atau pilih pattern.',
                ]);
                return;
            }
        }

        $db = $this->dbMain();
        $intent = $db->get_where_row(
            'wa_autoreply_intents',
            "code = '" . $db->escape($intentCode) . "' AND is_active = 1"
        );
        if (!$intent || empty($intent['id'])) {
            $this->jsonOut(['ok' => 0, 'message' => 'Intent tidak ditemukan']);
            return;
        }
        $intentId = (int) $intent['id'];

        $patternsDeactivated = 0;
        $promptUpdated = false;
        $deactivatedIds = [];

        if ($deactivatePatterns && $patternIds !== []) {
            $idList = implode(',', $patternIds);
            $rows = $db->query_array(
                "SELECT p.id, p.note, i.code AS intent_code
                 FROM wa_autoreply_patterns p
                 INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
                 WHERE p.is_active = 1 AND p.id IN ({$idList})"
            );
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $pid = (int) ($row['id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $oldNote = trim((string) ($row['note'] ?? ''));
                    $srcIntent = strtoupper(trim((string) ($row['intent_code'] ?? '')));
                    $tag = 'Intent Lab untouch ' . $intentCode;
                    if ($srcIntent !== '' && $srcIntent !== $intentCode) {
                        $tag .= ' via ' . $srcIntent;
                    }
                    $tag .= ': ' . mb_substr($text, 0, 80);
                    $newNote = $oldNote !== '' ? ($oldNote . ' | ' . $tag) : $tag;
                    $up = $db->update(
                        'wa_autoreply_patterns',
                        [
                            'is_active' => 0,
                            'note' => mb_substr($newNote, 0, 250),
                        ],
                        "id = {$pid}"
                    );
                    if (($up['errno'] ?? 1) == 0) {
                        $patternsDeactivated++;
                        $deactivatedIds[] = $pid;
                    }
                }
            }
        }

        if ($updatePrompt) {
            $current = (string) ($intent['ai_prompt'] ?? '');
            $newPrompt = $this->resolvePromptReplace($current, $aiPrompt, $promptAppend);
            if ($newPrompt !== $current) {
                $up = $db->update(
                    'wa_autoreply_intents',
                    ['ai_prompt' => $newPrompt],
                    "id = {$intentId}"
                );
                if (($up['errno'] ?? 1) != 0) {
                    $this->jsonOut([
                        'ok' => 0,
                        'message' => $up['error'] ?? 'Gagal update ai_prompt',
                    ]);
                    return;
                }
                $promptUpdated = true;
            }
        }

        $cacheBump = null;
        if ($patternsDeactivated > 0 || $promptUpdated) {
            $cacheBump = $this->bumpAutoreplyCache();
        }

        $api = $this->helper('IntentCheckApi');
        $check = $api->check($text);
        if (is_array($check)) {
            $check = $this->rejectClassifyIfExceedsChatMaxlength($text, $check);
        }
        $gotIntent = strtoupper((string) ($check['intent'] ?? ''));
        $verifyOk = ($gotIntent !== $intentCode);

        $out = [
            'ok' => 1,
            'message' => 'Dikeluarkan',
            'patterns_deactivated' => $patternsDeactivated,
            'deactivated_ids' => $deactivatedIds,
            'prompt_updated' => $promptUpdated,
            'target_intent' => $intentCode,
            'verify_intent' => $gotIntent,
            'verify_ok' => $verifyOk,
            'verify' => $check,
            'cache_version' => $this->readCacheVersion(),
        ];
        if ($cacheBump !== null) {
            $out['cache_version_before'] = $cacheBump['before'];
            $out['cache_version_bumped'] = true;
        }
        $this->jsonOut($out);
    }

    /**
     * Full replace if $aiPrompt given; otherwise legacy append of $promptAppend.
     */
    private function resolvePromptReplace(string $current, ?string $aiPrompt, string $promptAppend): string
    {
        if ($aiPrompt !== null) {
            return $this->sanitizeInclusionOnlyPrompt($aiPrompt);
        }
        $promptAppend = trim($promptAppend);
        if ($promptAppend === '' || mb_stripos($current, $promptAppend) !== false) {
            return $current;
        }
        $sep = ($current !== '' && !preg_match('/\n\s*$/', $current)) ? "\n" : '';
        return $current . $sep . $promptAppend;
    }

    private function sanitizeInclusionOnlyPrompt(string $prompt): string
    {
        $helper = dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/IntentTeachHelper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                return \App\Helpers\Laundry\IntentTeachHelper::sanitizeInclusionOnlyPrompt($prompt);
            }
        }

        return trim($prompt);
    }

    /**
     * POST { text, intent? } — debug: pattern DB + match per baris (admin).
     */
    public function debugMatch()
    {
        $this->session_cek(1);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        $data = $this->readRequestData();
        $text = trim((string) ($data['text'] ?? ''));
        $intentCode = strtoupper(trim((string) ($data['intent'] ?? '')));
        if ($text === '') {
            $this->jsonOut(['ok' => 0, 'message' => 'text wajib']);
            return;
        }

        $db = $this->dbMain();
        $where = $intentCode !== '' ? " AND i.code = '" . $db->escape($intentCode) . "'" : '';
        $rows = $db->query_array(
            "SELECT p.id, p.pattern, p.is_active, p.note, i.code AS intent_code, i.chat_maxlength
             FROM wa_autoreply_patterns p
             INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
             WHERE i.is_active = 1{$where}
             ORDER BY i.sort_order ASC, i.id ASC, p.sort_order ASC, p.id ASC"
        );
        $textCheck = $this->normalizeTextForRegex($text);
        $messageLength = mb_strlen($text);
        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $raw = (string) ($row['pattern'] ?? '');
            $pat = $this->sanitizePatternString($raw);
            $valid = @preg_match($pat, '') !== false;
            $exceedsMaxlength = $this->intentExceedsChatMaxlength($row['chat_maxlength'] ?? null, $messageLength);
            $match = !$exceedsMaxlength && $valid && (
                @preg_match($pat, $textCheck) === 1 || @preg_match($pat, $text) === 1
            );
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'intent' => strtoupper((string) ($row['intent_code'] ?? '')),
                'is_active' => (int) ($row['is_active'] ?? 0),
                'chat_maxlength' => isset($row['chat_maxlength']) && $row['chat_maxlength'] !== '' && $row['chat_maxlength'] !== null
                    ? (int) $row['chat_maxlength'] : null,
                'exceeds_chat_maxlength' => $exceedsMaxlength,
                'pattern' => $raw,
                'pattern_sanitized' => $pat,
                'regex_valid' => $valid,
                'matches' => $match,
                'note' => (string) ($row['note'] ?? ''),
            ];
        }

        $this->jsonOut([
            'ok' => 1,
            'text' => $text,
            'text_normalized' => $textCheck,
            'intent_filter' => $intentCode !== '' ? $intentCode : null,
            'cache_version' => $this->readCacheVersion(),
            'local_classify' => $this->localRegexClassify($text),
            'local_classify_intent' => $intentCode !== ''
                ? $this->localRegexClassifyForIntent($text, $intentCode) : null,
            'patterns' => $items,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    /** Pesan melebihi chat_maxlength intent (DB). NULL/0 = tanpa batas. */
    private function intentExceedsChatMaxlength($chatMaxlength, int $messageLength): bool
    {
        $max = (int) ($chatMaxlength ?? 0);

        return $max > 0 && $messageLength > $max;
    }

    /**
     * Safety net: tolak hasil API yang melanggar chat_maxlength intent (mis. bypass PENUTUP produksi).
     *
     * @param array<string,mixed> $res
     * @return array<string,mixed>
     */
    private function rejectClassifyIfExceedsChatMaxlength(string $text, array $res): array
    {
        $intent = strtoupper(trim((string) ($res['intent'] ?? '')));
        if ($intent === '' || in_array($intent, ['FALSE', 'NONE'], true)) {
            return $res;
        }
        $row = $this->dbMain()->get_where_row(
            'wa_autoreply_intents',
            "code = '" . $this->dbMain()->escape($intent) . "' AND is_active = 1"
        );
        if (!$row || !$this->intentExceedsChatMaxlength($row['chat_maxlength'] ?? null, mb_strlen($text))) {
            return $res;
        }
        $max = (int) ($row['chat_maxlength'] ?? 0);
        $trace = is_array($res['trace'] ?? null) ? $res['trace'] : [];
        $trace[] = $intent . '→exceeds_chat_maxlength max=' . $max;

        return array_merge($res, [
            'intent' => 'FALSE',
            'source' => 'maxlength',
            'no_handler' => true,
            'trace' => $trace,
        ]);
    }

    private function buildLocalRegexResult(string $text, string $intentCode, string $pattern): ?array
    {
        $intentCode = strtoupper(trim($intentCode));
        $pattern = $this->sanitizePatternString($pattern);
        if ($pattern === '' || @preg_match($pattern, '') === false) {
            return null;
        }
        if (!$this->patternMatchesText($pattern, $text, $this->normalizeTextForRegex($text))) {
            return null;
        }
        $row = $this->dbMain()->get_where_row(
            'wa_autoreply_intents',
            "code = '" . $this->dbMain()->escape($intentCode) . "' AND is_active = 1"
        );
        if (!$row || $this->intentExceedsChatMaxlength($row['chat_maxlength'] ?? null, mb_strlen($text))) {
            return null;
        }

        return [
            'ok' => 1,
            'text' => $text,
            'intent' => $intentCode,
            'source' => 'regex',
            'case' => isset($row['case_value']) && $row['case_value'] !== '' && $row['case_value'] !== null
                ? (int) $row['case_value'] : null,
            'notify' => ((int) ($row['notify'] ?? 0)) === 1,
            'no_handler' => false,
            'ask' => null,
            'trace' => ['LAB_SAVED_PATTERN handler=' . $intentCode],
            'replies' => [],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function localRegexClassifyForIntent(string $text, string $intentCode): ?array
    {
        $intentCode = strtoupper(trim($intentCode));
        if ($intentCode === '') {
            return null;
        }
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $textCheck = $this->normalizeTextForRegex($text);
        $messageLength = mb_strlen($text);
        $db = $this->dbMain();
        $rows = $db->query_array(
            "SELECT i.code, i.case_value, i.notify, i.chat_maxlength, p.pattern
             FROM wa_autoreply_patterns p
             INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
             WHERE p.is_active = 1 AND i.is_active = 1 AND i.code = '" . $db->escape($intentCode) . "'
             ORDER BY p.sort_order ASC, p.id ASC"
        );
        if (!is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if ($this->intentExceedsChatMaxlength($row['chat_maxlength'] ?? null, $messageLength)) {
                continue;
            }
            $pattern = $this->sanitizePatternString((string) ($row['pattern'] ?? ''));
            if ($pattern === '' || @preg_match($pattern, '') === false) {
                continue;
            }
            if ($this->patternMatchesText($pattern, $text, $textCheck)) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                return [
                    'ok' => 1,
                    'text' => $text,
                    'intent' => $code,
                    'source' => 'regex',
                    'case' => isset($row['case_value']) && $row['case_value'] !== '' && $row['case_value'] !== null
                        ? (int) $row['case_value'] : null,
                    'notify' => ((int) ($row['notify'] ?? 0)) === 1,
                    'no_handler' => false,
                    'ask' => null,
                    'trace' => ['LAB_REGEX_MATCH handler=' . $code],
                    'replies' => [],
                ];
            }
        }

        return null;
    }

    private function sanitizePatternString(string $pattern): string
    {
        $helper = dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/IntentTeachHelper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                return \App\Helpers\Laundry\IntentTeachHelper::sanitizePatternString($pattern);
            }
        }
        $pattern = trim($pattern);
        $pattern = preg_replace('/\s+(?=\/[a-zA-Z]*$)/', '', $pattern) ?? $pattern;
        $pattern = preg_replace('/\\\\([?!.,;:])\\\\\/(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;
        $pattern = preg_replace('/\\\\([?!.,;:])\s*\\\\b(?=\/[a-zA-Z]*$)/', '\\\\$1', $pattern) ?? $pattern;

        return $pattern;
    }

    /**
     * Intent Lab — regex DB lokal menang atas API bila API jatuh ke AI/FALSE.
     *
     * @param array<string,mixed> $apiRes
     * @return array<string,mixed>
     */
    private function mergeLocalRegexClassify(string $text, array $apiRes): array
    {
        $local = $this->localRegexClassify($text);
        if ($local === null) {
            if (empty($apiRes['ok']) && empty($apiRes['message']) && empty($apiRes['error'])) {
                $apiRes['message'] = 'API gagal dan tidak ada pattern regex yang match teks ini.';
            }
            return $apiRes;
        }
        $local['ok'] = 1;
        if (!empty($apiRes['ok']) || empty($apiRes['message'])) {
            return $local;
        }
        $apiSource = (string) ($apiRes['source'] ?? '');
        $apiIntent = strtoupper(trim((string) ($apiRes['intent'] ?? '')));
        $localIntent = strtoupper(trim((string) ($local['intent'] ?? '')));
        if ($apiSource === 'regex' && $apiIntent === $localIntent) {
            return array_merge($apiRes, ['ok' => 1]);
        }
        $trace = is_array($apiRes['trace'] ?? null) ? $apiRes['trace'] : [];
        if (!empty($apiRes['message'])) {
            $trace[] = 'API: ' . (string) $apiRes['message'];
        } elseif ($apiIntent !== '' && $apiIntent !== $localIntent) {
            $trace[] = 'API=' . $apiIntent . ' source=' . ($apiSource !== '' ? $apiSource : '?')
                . ' → lab pakai regex ' . $localIntent;
        }
        return array_merge($apiRes, $local, [
            'ok' => 1,
            'trace' => array_merge($local['trace'] ?? [], $trace),
        ]);
    }

    /**
     * Scan pattern aktif dari DB (sama sumber dengan autoreply). Tanpa skip/remap produksi.
     *
     * @return array<string,mixed>|null
     */
    private function localRegexClassify(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $textCheck = $this->normalizeTextForRegex($text);
        $messageLength = mb_strlen($text);
        $db = $this->dbMain();
        $rows = $db->query_array(
            "SELECT i.code, i.case_value, i.notify, i.chat_maxlength, p.pattern
             FROM wa_autoreply_patterns p
             INNER JOIN wa_autoreply_intents i ON i.id = p.intent_id
             WHERE p.is_active = 1 AND i.is_active = 1
             ORDER BY i.sort_order ASC, i.id ASC, p.sort_order ASC, p.id ASC"
        );
        if (!is_array($rows)) {
            return null;
        }
        foreach ($rows as $row) {
            if ($this->intentExceedsChatMaxlength($row['chat_maxlength'] ?? null, $messageLength)) {
                continue;
            }
            $pattern = $this->sanitizePatternString((string) ($row['pattern'] ?? ''));
            if ($pattern === '' || @preg_match($pattern, '') === false) {
                continue;
            }
            if ($this->patternMatchesText($pattern, $text, $textCheck)) {
                $code = strtoupper(trim((string) ($row['code'] ?? '')));
                return [
                    'ok' => 1,
                    'text' => $text,
                    'intent' => $code,
                    'source' => 'regex',
                    'case' => isset($row['case_value']) && $row['case_value'] !== '' && $row['case_value'] !== null
                        ? (int) $row['case_value'] : null,
                    'notify' => ((int) ($row['notify'] ?? 0)) === 1,
                    'no_handler' => false,
                    'ask' => null,
                    'trace' => ['LAB_REGEX_MATCH handler=' . $code],
                    'replies' => [],
                ];
            }
        }

        return null;
    }

    private function patternMatchesText(string $pattern, string $text, ?string $textCheck = null): bool
    {
        $helper = dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/IntentTeachHelper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                return \App\Helpers\Laundry\IntentTeachHelper::patternMatchesText(
                    $pattern,
                    $text,
                    $textCheck ?? $this->normalizeTextForRegex($text)
                );
            }
        }
        $textCheck = $textCheck ?? $this->normalizeTextForRegex($text);
        if (@preg_match($pattern, $textCheck) === 1) {
            return true;
        }
        if (@preg_match($pattern, $text) === 1) {
            return true;
        }
        if (@preg_match($pattern, mb_strtolower($text)) === 1) {
            return true;
        }
        if (preg_match('/slesainya/u', $pattern) && preg_match('/selesainya/u', $text)) {
            $alt = preg_replace('/slesainya/u', 'selesainya', $pattern);
            if (is_string($alt) && @preg_match($alt, $textCheck) === 1) {
                return true;
            }
        }
        if (preg_match('/selesainya/u', $pattern) && preg_match('/slesainya/u', $text)) {
            $alt = preg_replace('/selesainya/u', 'slesainya', $pattern);
            if (is_string($alt) && @preg_match($alt, $textCheck) === 1) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTextForRegex(string $text): string
    {
        $t = preg_replace('/[*_~`]/', '', $text) ?? $text;
        $t = preg_replace('/^>\s*/m', '', $t) ?? $t;

        return strtolower(trim($t));
    }

    /**
     * PCRE: trailing \\b setelah ?!., di akhir string tidak pernah match — perbaiki sebelum simpan.
     */
    private function normalizePatternForText(string $pattern, string $text): string
    {
        $pattern = $this->sanitizePatternString($pattern);
        $text = trim($text);
        if ($pattern === '' || $text === '') {
            return $pattern;
        }
        if (@preg_match($pattern, $text) === 1) {
            return $pattern;
        }

        $helper = dirname(__DIR__, 3) . '/api/app/Helpers/Laundry/IntentTeachHelper.php';
        if (is_file($helper)) {
            require_once $helper;
            if (class_exists('\\App\\Helpers\\Laundry\\IntentTeachHelper')) {
                return \App\Helpers\Laundry\IntentTeachHelper::normalizePatternForText($pattern, $text);
            }
        }

        return $pattern;
    }

    /** @return array<string,mixed> */
    private function readRequestData(): array
    {
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
        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed> $data */
    private function jsonOut(array $data): void
    {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        if (array_key_exists('ok', $data)) {
            if ($data['ok'] === true) {
                $data['ok'] = 1;
            } elseif ($data['ok'] === false) {
                $data['ok'] = 0;
            }
        }
        $data = $this->sanitizeForJson($data);
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }
        $json = json_encode($data, $flags);
        if ($json === false || $json === '') {
            $json = json_encode([
                'ok' => 0,
                'message' => 'JSON encode gagal: ' . json_last_error_msg(),
            ]);
        }
        echo $json;
        exit;
    }

    /** @return mixed */
    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->sanitizeForJson($v);
            }
            return $out;
        }
        if (is_string($value)) {
            if (function_exists('mb_convert_encoding')) {
                $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                return is_string($clean) ? $clean : $value;
            }
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        }

        return $value;
    }
}
