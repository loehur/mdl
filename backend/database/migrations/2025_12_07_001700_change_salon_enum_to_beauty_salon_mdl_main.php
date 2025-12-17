<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        // Update business_list enum values and type
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            $hasEnum = Schema::connection($this->connection)->hasColumn('business_list', 'enum');
            if ($hasEnum) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = 'beauty_salon' WHERE `enum` = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
            } else {
                // Legacy schema: 'name' column (string). Keep name but align user_business mapping below.
                // Optionally normalize 'Salon' naming to 'Beauty Salon'.
                if (Schema::connection($this->connection)->hasColumn('business_list', 'name')) {
                    try {
                        DB::connection($this->connection)->statement(
                            "UPDATE `business_list` SET `name` = 'Beauty Salon' WHERE LOWER(`name`) = 'salon'"
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        // Update user_business business_enum type and values (current table name: user_business)
        if (Schema::connection($this->connection)->hasTable('user_business')) {
            if (Schema::connection($this->connection)->hasColumn('user_business', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_business` SET `business_enum` = 'beauty_salon' WHERE `business_enum` = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
            }
        }

        // Also handle legacy table name 'user_businesses' if exists
        if (Schema::connection($this->connection)->hasTable('user_businesses')) {
            if (Schema::connection($this->connection)->hasColumn('user_businesses', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_businesses` MODIFY `business_enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_businesses` SET `business_enum` = 'beauty_salon' WHERE `business_enum` = 'salon'"
                    );
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        // Revert values and types back to include 'salon'
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            $hasEnum = Schema::connection($this->connection)->hasColumn('business_list', 'enum');
            if ($hasEnum) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = 'salon' WHERE `enum` = 'beauty_salon'"
                    );
                } catch (\Throwable $e) {
                }
            } else {
                if (Schema::connection($this->connection)->hasColumn('business_list', 'name')) {
                    try {
                        DB::connection($this->connection)->statement(
                            "UPDATE `business_list` SET `name` = 'Salon' WHERE LOWER(`name`) = 'beauty salon'"
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }
        }

        foreach (['user_business', 'user_businesses'] as $tbl) {
            if (Schema::connection($this->connection)->hasTable($tbl)) {
                if (Schema::connection($this->connection)->hasColumn($tbl, 'business_enum')) {
                    try {
                        DB::connection($this->connection)->statement(
                            "ALTER TABLE `{$tbl}` MODIFY `business_enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                        );
                    } catch (\Throwable $e) {
                    }
                    try {
                        DB::connection($this->connection)->statement(
                            "UPDATE `{$tbl}` SET `business_enum` = 'salon' WHERE `business_enum` = 'beauty_salon'"
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }
        }
    }
};
