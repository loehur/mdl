<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sale_item')) {
            if (!Schema::connection($this->connection)->hasColumn('sale_item', 'type')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `sale_item` ADD COLUMN `type` ENUM('barang','jasa') NOT NULL DEFAULT 'barang' AFTER `product_id`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('sale_item')) {
            if (Schema::connection($this->connection)->hasColumn('sale_item', 'type')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `sale_item` DROP COLUMN `type`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }
};

