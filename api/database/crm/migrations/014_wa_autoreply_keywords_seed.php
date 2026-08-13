<?php
/**
 * Seed wa_autoreply_* dari api/app/Config/AutoReplyKeywords.php
 *
 * Usage (dari root repo):
 *   c:\xampp82\php\php.exe api/database/crm/migrations/014_wa_autoreply_keywords_seed.php
 *   c:\xampp82\php\php.exe api/database/crm/migrations/014_wa_autoreply_keywords_seed.php --replace
 *
 * Jalankan SQL 014_wa_autoreply_keywords.sql dulu di mdl_main.
 */

$root = dirname(__DIR__, 3); // api/
require_once $root . '/app/Config/Env.php';
require_once $root . '/app/init.php';

$replace = in_array('--replace', $argv ?? [], true);

echo "Seeding AutoReplyKeywords (replace=" . ($replace ? 'yes' : 'no') . ")...\n";

$result = \App\Config\AutoReplyKeywordsLoader::seedFromFile($replace);
echo ($result['message'] ?? json_encode($result)) . "\n";
exit(!empty($result['ok']) ? 0 : 1);
