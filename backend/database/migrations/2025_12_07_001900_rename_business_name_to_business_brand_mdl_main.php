<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        foreach (['user_business', 'user_businesses'] as $tbl) {
            if (Schema::connection($this->connection)->hasTable($tbl)) {
                $hasName = Schema::connection($this->connection)->hasColumn($tbl, 'business_name');
                $hasBrand = Schema::connection($this->connection)->hasColumn($tbl, 'business_brand');
                if ($hasName && !$hasBrand) {
                    try {
                        DB::connection($this->connection)->statement(
                            "ALTER TABLE `{$tbl}` CHANGE `business_name` `business_brand` VARCHAR(150) NULL"
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['user_business', 'user_businesses'] as $tbl) {
            if (Schema::connection($this->connection)->hasTable($tbl)) {
                $hasBrand = Schema::connection($this->connection)->hasColumn($tbl, 'business_brand');
                $hasName = Schema::connection($this->connection)->hasColumn($tbl, 'business_name');
                if ($hasBrand && !$hasName) {
                    try {
                        DB::connection($this->connection)->statement(
                            "ALTER TABLE `{$tbl}` CHANGE `business_brand` `business_name` VARCHAR(150) NULL"
                        );
                    } catch (\Throwable $e) {}
                }
            }
        }
    }
};

