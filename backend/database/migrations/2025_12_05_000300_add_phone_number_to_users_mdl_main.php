<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasColumn('users', 'phone_number')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->string('phone_number')->unique()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('users', 'phone_number')) {
            Schema::connection($this->connection)->table('users', function (Blueprint $table) {
                $table->dropColumn('phone_number');
            });
        }
    }
};
