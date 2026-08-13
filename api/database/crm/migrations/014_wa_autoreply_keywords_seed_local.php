<?php
/**
 * Local seed via root (bypass Env MODE=pro).
 * Usage: c:\xampp82\php\php.exe api/database/crm/migrations/014_wa_autoreply_keywords_seed_local.php
 */
$mysqli = new mysqli('localhost', 'root', '', 'mdl_main');
if ($mysqli->connect_error) {
    fwrite(STDERR, $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

$file = dirname(__DIR__, 3) . '/app/Config/AutoReplyKeywords.php';
$data = require $file;
if (!is_array($data) || $data === []) {
    fwrite(STDERR, "AutoReplyKeywords.php empty\n");
    exit(1);
}

$mysqli->query('DELETE FROM wa_autoreply_patterns');
$mysqli->query('DELETE FROM wa_autoreply_intents');

$esc = static function ($mysqli, $v) {
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    return "'" . $mysqli->real_escape_string((string) $v) . "'";
};

$sort = 0;
$intentCount = 0;
$patternCount = 0;

foreach ($data as $code => $cfg) {
    if (!is_array($cfg)) {
        continue;
    }
    $sort++;
    $code = strtoupper(trim((string) $code));
    $caseVal = array_key_exists('case', $cfg)
        ? ($cfg['case'] === null ? null : (int) $cfg['case'])
        : null;
    $notify = array_key_exists('notify', $cfg) ? ($cfg['notify'] ? 1 : 0) : null;
    $ai = isset($cfg['ai_prompt']) && is_string($cfg['ai_prompt']) ? $cfg['ai_prompt'] : null;

    $sql = sprintf(
        'INSERT INTO wa_autoreply_intents (code, sort_order, case_value, notify, ai_prompt, is_active) VALUES (%s,%d,%s,%s,%s,1)',
        $esc($mysqli, $code),
        $sort,
        $esc($mysqli, $caseVal),
        $esc($mysqli, $notify),
        $esc($mysqli, $ai)
    );
    if (!$mysqli->query($sql)) {
        fwrite(STDERR, "Intent {$code}: {$mysqli->error}\n");
        continue;
    }
    $intentId = (int) $mysqli->insert_id;
    $intentCount++;

    $psort = 0;
    foreach (($cfg['patterns'] ?? []) as $pat) {
        if (!is_string($pat) || $pat === '') {
            continue;
        }
        $psort++;
        $psql = sprintf(
            'INSERT INTO wa_autoreply_patterns (intent_id, pattern, sort_order, is_active) VALUES (%d,%s,%d,1)',
            $intentId,
            $esc($mysqli, $pat),
            $psort
        );
        if ($mysqli->query($psql)) {
            $patternCount++;
        } else {
            fwrite(STDERR, "Pattern {$code}#{$psort}: {$mysqli->error}\n");
        }
    }
}

$mysqli->query("INSERT INTO wa_autoreply_meta (meta_key, meta_value) VALUES ('cache_version','1')
    ON DUPLICATE KEY UPDATE meta_value = CAST(meta_value AS UNSIGNED) + 1");

echo "Seed OK: {$intentCount} intent, {$patternCount} pattern\n";
exit(0);
