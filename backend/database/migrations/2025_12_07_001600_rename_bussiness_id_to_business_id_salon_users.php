<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('users')) {
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` CHANGE `bussiness_id` `business_id` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` DROP INDEX `uniq_business_role`');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` ADD UNIQUE `uniq_business_role` (`business_id`, `role`)');
            } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('users')) {
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` CHANGE `business_id` `bussiness_id` BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` DROP INDEX `uniq_business_role`');
            } catch (\Throwable $e) {}
            try {
                DB::connection($this->connection)->statement('ALTER TABLE `users` ADD UNIQUE `uniq_business_role` (`bussiness_id`, `role`)');
            } catch (\Throwable $e) {}
        }
    }
};

