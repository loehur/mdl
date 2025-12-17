<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('users', 'pin_reset_expire')) {
                $table->dropColumn('pin_reset_expire');
            }
        });
        if (Schema::connection($this->connection)->hasColumn('users', 'pin_reset')) {
            DB::connection($this->connection)->statement('ALTER TABLE `users` DROP COLUMN `pin_reset`');
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('users', 'pin_reset')) {
                $table->string('pin_reset', 100)->nullable()->after('password');
            }
            if (!Schema::connection($this->connection)->hasColumn('users', 'pin_reset_expire')) {
                $table->timestamp('pin_reset_expire')->nullable()->after('pin_reset');
            }
        });
    }
};
