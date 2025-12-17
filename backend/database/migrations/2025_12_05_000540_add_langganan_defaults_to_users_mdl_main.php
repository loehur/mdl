<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('users', 'langganan')) {
                $table->string('langganan')->default('standar')->after('password');
            }
            if (!Schema::connection($this->connection)->hasColumn('users', 'status_langganan')) {
                $table->string('status_langganan')->default('active')->after('langganan');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('users', 'status_langganan')) {
                $table->dropColumn('status_langganan');
            }
            if (Schema::connection($this->connection)->hasColumn('users', 'langganan')) {
                $table->dropColumn('langganan');
            }
        });
    }
};

