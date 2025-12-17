<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('ledger')) {
            try {
                if (Schema::connection($this->connection)->hasColumn('ledger', 'paid_amount')) {
                    DB::connection($this->connection)->statement('ALTER TABLE `ledger` DROP COLUMN `paid_amount`');
                }
            } catch (\Throwable $e) {}
            try {
                if (Schema::connection($this->connection)->hasColumn('ledger', 'remaining')) {
                    DB::connection($this->connection)->statement('ALTER TABLE `ledger` DROP COLUMN `remaining`');
                }
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('ledger')) {
            try {
                if (!Schema::connection($this->connection)->hasColumn('ledger', 'paid_amount')) {
                    DB::connection($this->connection)->statement('ALTER TABLE `ledger` ADD COLUMN `paid_amount` DECIMAL(16,2) NOT NULL DEFAULT 0');
                }
            } catch (\Throwable $e) {}
            try {
                if (!Schema::connection($this->connection)->hasColumn('ledger', 'remaining')) {
                    DB::connection($this->connection)->statement('ALTER TABLE `ledger` ADD COLUMN `remaining` DECIMAL(16,2) NOT NULL DEFAULT 0');
                }
            } catch (\Throwable $e) {}
        }
    }
};

