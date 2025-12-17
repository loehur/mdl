<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        // Ensure business_list has enum column and includes beauty_salon
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            $hasEnum = Schema::connection($this->connection)->hasColumn('business_list', 'enum');
            $hasName = Schema::connection($this->connection)->hasColumn('business_list', 'name');
            if ($hasName && !$hasEnum) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` CHANGE `name` `enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = LOWER(`enum`)"
                    );
                } catch (\Throwable $e) {
                }
            } else if ($hasEnum) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` MODIFY `enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
            }
            try {
                DB::connection($this->connection)->statement(
                    "UPDATE `business_list` SET `enum` = 'beauty_salon' WHERE `enum` = 'salon'"
                );
            } catch (\Throwable $e) {
            }
        }

        // Ensure user_business has business_enum with beauty_salon and backfilled
        if (Schema::connection($this->connection)->hasTable('user_business')) {
            $hasEnumCol = Schema::connection($this->connection)->hasColumn('user_business', 'business_enum');
            $hasBizId = Schema::connection($this->connection)->hasColumn('user_business', 'business_id');
            if (!$hasEnumCol) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` ADD COLUMN `business_enum` ENUM('laundry','resto','depot','beauty_salon') NULL AFTER `business_id`"
                    );
                } catch (\Throwable $e) {
                }
                // Backfill from business_list
                if (Schema::connection($this->connection)->hasColumn('business_list', 'enum') && $hasBizId) {
                    try {
                        DB::connection($this->connection)->statement(
                            "UPDATE `user_business` ub JOIN `business_list` bl ON ub.`business_id` = bl.`id` SET ub.`business_enum` = bl.`enum` WHERE ub.`business_enum` IS NULL"
                        );
                    } catch (\Throwable $e) {
                    }
                } else if (Schema::connection($this->connection)->hasColumn('business_list', 'name') && $hasBizId) {
                    try {
                        DB::connection($this->connection)->statement(
                            "UPDATE `user_business` ub JOIN `business_list` bl ON ub.`business_id` = bl.`id` SET ub.`business_enum` = LOWER(bl.`name`) WHERE ub.`business_enum` IS NULL"
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }
            // Normalize values and finalize type
            try {
                DB::connection($this->connection)->statement(
                    "UPDATE `user_business` SET `business_enum` = 'beauty_salon' WHERE `business_enum` = 'salon'"
                );
            } catch (\Throwable $e) {
            }
            try {
                DB::connection($this->connection)->statement(
                    "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','beauty_salon') NOT NULL"
                );
            } catch (\Throwable $e) {
            }
            try {
                DB::connection($this->connection)->statement(
                    "ALTER TABLE `user_business` ADD INDEX (`business_enum`)"
                );
            } catch (\Throwable $e) {
            }
            if ($hasBizId) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `user_business` DROP COLUMN `business_id`");
                } catch (\Throwable $e) {
                }
            }
        }
    }

    public function down(): void
    {
        // Revert values only (types back to include 'salon')
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            if (Schema::connection($this->connection)->hasColumn('business_list', 'enum')) {
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
            }
        }
        if (Schema::connection($this->connection)->hasTable('user_business')) {
            if (Schema::connection($this->connection)->hasColumn('user_business', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_business` MODIFY `business_enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {
                }
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_business` SET `business_enum` = 'salon' WHERE `business_enum` = 'beauty_salon'"
                    );
                } catch (\Throwable $e) {
                }
            }
        }
    }
};
