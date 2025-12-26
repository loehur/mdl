<?php
/**
 * Database Configuration Class
 * 
 * SECURITY WARNING:
 * Database credentials telah dipindahkan ke Config/Env.php (gitignored)
 * untuk keamanan. Tambahkan konstanta DB_HOST dan DB_CREDENTIALS di Env.php
 * 
 * Lihat template di bawah untuk cara setup di Env.php
 */

// Load Env.php to get DB constants
$envFile = __DIR__ . '/Env.php';
if (file_exists($envFile)) {
    require_once $envFile;
}

// Set defaults if constants not defined in Env.php
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}

if (!defined('DB_CREDENTIALS')) {
    define('DB_CREDENTIALS', [
        'dev' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ],
        'pro' => [
            0 => ["db" => "mdl_main", "user" => "root", "pass" => ""],
            1 => ["db" => "mdl_laundry", "user" => "root", "pass" => ""],
            2 => ["db" => "mdl_sale", "user" => "root", "pass" => ""],
            3 => ["db" => "mdl_resto", "user" => "root", "pass" => ""],
            4 => ["db" => "mdl_depot", "user" => "root", "pass" => ""],
            5 => ["db" => "mdl_salon", "user" => "root", "pass" => ""]
        ]
    ]);
}

class DBC
{
    const db_host = DB_HOST;
    const dbm = DB_CREDENTIALS;
}