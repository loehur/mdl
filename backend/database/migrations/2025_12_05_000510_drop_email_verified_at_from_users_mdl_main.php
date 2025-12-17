<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('users', 'email_verified_at')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->dropColumn('email_verified_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasColumn('users', 'email_verified_at')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable();
            });
        }
    }
};

