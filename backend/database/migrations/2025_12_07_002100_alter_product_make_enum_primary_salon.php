<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('product')) {
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` DROP INDEX `product_enum_unique`');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` DROP PRIMARY KEY');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` DROP COLUMN `id`');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement("ALTER TABLE `product` MODIFY `enum` VARCHAR(100) NOT NULL");
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD PRIMARY KEY (`enum`)');
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('product')) {
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` DROP PRIMARY KEY');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD PRIMARY KEY (`id`)');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD UNIQUE `product_enum_unique` (`enum`)');
            } catch (\Throwable $e) {}
        }
    }
};

