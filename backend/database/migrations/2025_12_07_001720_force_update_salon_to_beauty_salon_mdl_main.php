<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        // business_list: ensure enum includes beauty_salon, then update values using LOWER()
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            if (Schema::connection($this->connection)->hasColumn('business_list', 'enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','beauty_salon','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = 'beauty_salon' WHERE LOWER(`enum`) = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
            } elseif (Schema::connection($this->connection)->hasColumn('business_list', 'name')) {
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `name` = 'beauty_salon' WHERE LOWER(`name`) = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
            }
        }

        // user_business: ensure enum includes beauty_salon, then update values using LOWER()
        if (Schema::connection($this->connection)->hasTable('user_business')) {
            if (Schema::connection($this->connection)->hasColumn('user_business', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','beauty_salon','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_business` SET `business_enum` = 'beauty_salon' WHERE LOWER(`business_enum`) = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        // Revert to include 'salon' and set values back if needed
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            if (Schema::connection($this->connection)->hasColumn('business_list', 'enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','salon','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = 'salon' WHERE LOWER(`enum`) = 'beauty_salon'"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
            }
        }
        if (Schema::connection($this->connection)->hasTable('user_business')) {
            if (Schema::connection($this->connection)->hasColumn('user_business', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','salon','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_business` SET `business_enum` = 'salon' WHERE LOWER(`business_enum`) = 'beauty_salon'"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
            }
        }
    }
};
