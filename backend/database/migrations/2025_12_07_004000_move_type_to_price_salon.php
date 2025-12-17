<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        $conn = $this->connection;
        if (Schema::connection($conn)->hasTable('price')) {
            if (!Schema::connection($conn)->hasColumn('price', 'type')) {
                try {
                    DB::connection($conn)->statement(
                        "ALTER TABLE `price` ADD COLUMN `type` ENUM('barang','jasa') NOT NULL DEFAULT 'barang' AFTER `product_id`"
                    );
                } catch (\Throwable $e) {}
            }
        }

        if (Schema::connection($conn)->hasTable('product')) {
            if (Schema::connection($conn)->hasColumn('product', 'type')) {
                try {
                    DB::connection($conn)->statement(
                        "ALTER TABLE `product` DROP COLUMN `type`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        $conn = $this->connection;
        if (Schema::connection($conn)->hasTable('price')) {
            if (Schema::connection($conn)->hasColumn('price', 'type')) {
                try {
                    DB::connection($conn)->statement(
                        "ALTER TABLE `price` DROP COLUMN `type`"
                    );
                } catch (\Throwable $e) {}
            }
        }

        if (Schema::connection($conn)->hasTable('product')) {
            if (!Schema::connection($conn)->hasColumn('product', 'type')) {
                try {
                    DB::connection($conn)->statement(
                        "ALTER TABLE `product` ADD COLUMN `type` ENUM('barang','jasa') NULL AFTER `name`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }
};

