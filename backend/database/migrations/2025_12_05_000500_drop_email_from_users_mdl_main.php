<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('users', 'email')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasColumn('users', 'email')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->string('email')->unique()->nullable();
            });
        }
    }
};

