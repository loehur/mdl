<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        $database = config('database.connections.mdl_main.database');
        try {
            $rows = DB::connection($this->connection)->select('SHOW TABLES');
        } catch (\Throwable $e) {
            return;
        }

        if (!$rows) {
            return;
        }

        $key = null;
        foreach ($rows as $r) {
            $arr = (array) $r;
            $keys = array_keys($arr);
            if (!empty($keys)) {
                $key = $keys[0];
                break;
            }
        }
        if (!$key) {
            $key = 'Tables_in_' . $database;
        }

        $keep = ['migrations', 'users', 'whatsapp'];

        foreach ($rows as $r) {
            $table = (string) ((array) $r)[$key] ?? '';
            if ($table === '' || in_array($table, $keep, true)) {
                continue;
            }
            try {
                $count = DB::connection($this->connection)->table($table)->count();
            } catch (\Throwable $e) {
                continue;
            }
            if ($count === 0) {
                try {
                    Schema::connection($this->connection)->dropIfExists($table);
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        // no-op
    }
};
