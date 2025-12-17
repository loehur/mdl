<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    private array $allowed = ['laundry','resto','depot','salon'];

    public function up(): void
    {
        // Ensure user_businesses has business_enum and backfill from business_id
        if (Schema::connection($this->connection)->hasTable('user_businesses')) {
            if (!Schema::connection($this->connection)->hasColumn('user_businesses', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_businesses` ADD COLUMN `business_enum` ENUM('laundry','resto','depot','salon') NULL AFTER `business_id`"
                    );
                } catch (\Throwable $e) {}
            }
            // Backfill using current mapping from business_list.id -> lower(name)
            if (Schema::connection($this->connection)->hasColumn('user_businesses', 'business_id') &&
                Schema::connection($this->connection)->hasTable('business_list') &&
                Schema::connection($this->connection)->hasColumn('business_list', 'id') &&
                Schema::connection($this->connection)->hasColumn('business_list', 'name')) {
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `user_businesses` ub JOIN `business_list` bl ON ub.`business_id` = bl.`id` SET ub.`business_enum` = LOWER(bl.`name`) WHERE ub.`business_enum` IS NULL"
                    );
                } catch (\Throwable $e) {}
            }
        }

        // Change business_list to enum primary (no id)
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            $hasName = Schema::connection($this->connection)->hasColumn('business_list', 'name');
            $hasEnum = Schema::connection($this->connection)->hasColumn('business_list', 'enum');
            $hasId = Schema::connection($this->connection)->hasColumn('business_list', 'id');

            if ($hasName && !$hasEnum) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `business_list` CHANGE `name` `enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {}
                try {
                    DB::connection($this->connection)->statement(
                        "UPDATE `business_list` SET `enum` = LOWER(`enum`)"
                    );
                } catch (\Throwable $e) {}
            }

            // Drop primary key and id, then set primary to enum
            if ($hasId) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `business_list` DROP PRIMARY KEY");
                } catch (\Throwable $e) {}
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `business_list` DROP COLUMN `id`");
                } catch (\Throwable $e) {}
            }
            // Add primary key on enum
            if (Schema::connection($this->connection)->hasColumn('business_list', 'enum')) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `business_list` ADD PRIMARY KEY (`enum`)");
                } catch (\Throwable $e) {}
            }
        }

        // Finalize user_businesses: drop business_id, make business_enum NOT NULL and index
        if (Schema::connection($this->connection)->hasTable('user_businesses')) {
            if (Schema::connection($this->connection)->hasColumn('user_businesses', 'business_id')) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `user_businesses` DROP COLUMN `business_id`");
                } catch (\Throwable $e) {}
            }
            if (Schema::connection($this->connection)->hasColumn('user_businesses', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `user_businesses` MODIFY `business_enum` ENUM('laundry','resto','depot','salon') NOT NULL"
                    );
                } catch (\Throwable $e) {}
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `user_businesses` ADD INDEX (`business_enum`)");
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(): void
    {
        // Attempt to revert: recreate id in business_list and name column
        if (Schema::connection($this->connection)->hasTable('business_list')) {
            $hasEnum = Schema::connection($this->connection)->hasColumn('business_list', 'enum');
            if ($hasEnum && !Schema::connection($this->connection)->hasColumn('business_list', 'name')) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `business_list` DROP PRIMARY KEY");
                } catch (\Throwable $e) {}
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `business_list` CHANGE `enum` `name` VARCHAR(100) NOT NULL");
                } catch (\Throwable $e) {}
                if (!Schema::connection($this->connection)->hasColumn('business_list', 'id')) {
                    try {
                        DB::connection($this->connection)->statement("ALTER TABLE `business_list` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)");
                    } catch (\Throwable $e) {}
                }
            }
        }

        // Recreate business_id in user_businesses and drop business_enum
        if (Schema::connection($this->connection)->hasTable('user_businesses')) {
            if (!Schema::connection($this->connection)->hasColumn('user_businesses', 'business_id')) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `user_businesses` ADD COLUMN `business_id` BIGINT UNSIGNED NULL AFTER `user_id`");
                } catch (\Throwable $e) {}
            }
            if (Schema::connection($this->connection)->hasColumn('user_businesses', 'business_enum')) {
                try {
                    DB::connection($this->connection)->statement("ALTER TABLE `user_businesses` DROP COLUMN `business_enum`");
                } catch (\Throwable $e) {}
            }
        }
    }
};

