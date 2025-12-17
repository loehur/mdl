<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('price')) {
            try {
                DB::connection($this->connection)->statement("ALTER TABLE `price` CHANGE COLUMN `enum` `product_id` VARCHAR(100) NOT NULL");
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('price')) {
            try {
                DB::connection($this->connection)->statement("ALTER TABLE `price` CHANGE COLUMN `product_id` `enum` VARCHAR(100) NOT NULL");
            } catch (\Throwable $e) {}
        }
    }
};

