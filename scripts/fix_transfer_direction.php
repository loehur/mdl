<?php
/**
 * Script to check transfer records for data consistency.
 * 
 * Bug: Transfer dari Kas Besar ke Kas Kasir malah menambah saldo Kas Besar.
 * Expected: cash_source should equal transfer_from (the source account).
 * 
 * Run from CLI: php fix_transfer_direction.php
 */

$apiPath = __DIR__ . '/../api';
if (!file_exists($apiPath . '/app/init.php')) {
    die("Cannot find api/app/init.php\n");
}
require_once $apiPath . '/app/init.php';

$db = \App\Core\DB::getInstance(4); // salon database

echo "=== Transfer Direction Check ===\n\n";

$transfers = $db->query(
    "SELECT id, salon_id, amount, cash_source, transfer_from, transfer_to, description, transaction_date 
     FROM cash_transactions 
     WHERE transaction_type = 'transfer' 
     ORDER BY id DESC 
     LIMIT 100"
)->result_array();

$inconsistent = [];
foreach ($transfers as $t) {
    if (($t['cash_source'] ?? '') !== ($t['transfer_from'] ?? '')) {
        $inconsistent[] = $t;
    }
}

if (empty($inconsistent)) {
    echo "All " . count($transfers) . " transfer records have consistent data (cash_source = transfer_from).\n";
    exit(0);
}

echo "Found " . count($inconsistent) . " transfer(s) with inconsistent data:\n";
foreach ($inconsistent as $t) {
    echo "  ID {$t['id']}: cash_source={$t['cash_source']}, transfer_from={$t['transfer_from']}, transfer_to={$t['transfer_to']} - {$t['description']}\n";
}
echo "\nIf balance is wrong, these records may need manual correction.\n";
