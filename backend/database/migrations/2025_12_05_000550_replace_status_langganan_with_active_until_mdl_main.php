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
            if (Schema::connection($this->connection)->hasColumn('users', 'status_langganan')) {
                $table->dropColumn('status_langganan');
            }
            if (!Schema::connection($this->connection)->hasColumn('users', 'active_until')) {
                $table->timestamp('active_until')->nullable()->after('langganan');
            }
        });

        DB::connection($this->connection)->statement('UPDATE `users` SET `active_until` = DATE_ADD(`created_at`, INTERVAL 1 MONTH) WHERE `active_until` IS NULL');
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('users', 'active_until')) {
                $table->dropColumn('active_until');
            }
            if (!Schema::connection($this->connection)->hasColumn('users', 'status_langganan')) {
                $table->string('status_langganan')->default('active')->after('langganan');
            }
        });
    }
};

