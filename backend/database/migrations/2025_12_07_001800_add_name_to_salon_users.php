<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('users')) {
            // Add column if not exists; set default to 'Administrator'
            $hasName = Schema::connection($this->connection)->hasColumn('users', 'name');
            if (!$hasName) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `users` ADD COLUMN `name` VARCHAR(150) NOT NULL DEFAULT 'Administrator' AFTER `role`"
                    );
                } catch (\Throwable $e) {}
                // Ensure existing NULLs (if any via legacy) are backfilled
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `users` SET `name` = 'Administrator' WHERE `name` IS NULL OR `name` = ''"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('users')) {
            if (Schema::connection($this->connection)->hasColumn('users', 'name')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `users` DROP COLUMN `name`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }
};

