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
                DB::connection($this->connection)->statement("ALTER TABLE `product` CHANGE COLUMN `enum` `product_id` VARCHAR(100) NOT NULL");
            } catch (\Throwable $e) {
                try {
                    DB::connection($this->connection)->statement('ALTER TABLE `product` DROP PRIMARY KEY');
                } catch (\Throwable $e2) {}
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `product` CHANGE COLUMN `enum` `product_id` VARCHAR(100) NOT NULL");
                } catch (\Throwable $e3) {}
                try {
                    DB::connection($this->connection)->statement('ALTER TABLE `product` ADD PRIMARY KEY (`product_id`)');
                } catch (\Throwable $e4) {}
            }
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD PRIMARY KEY (`product_id`)');
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('product')) {
            try {
                DB::connection($this->connection)->statement("ALTER TABLE `product` CHANGE COLUMN `product_id` `enum` VARCHAR(100) NOT NULL");
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `product` ADD PRIMARY KEY (`enum`)');
            } catch (\Throwable $e) {}
        }
    }
};

