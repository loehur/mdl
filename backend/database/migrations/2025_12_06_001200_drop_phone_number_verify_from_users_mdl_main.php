<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('users', 'phone_number_verify')) {
                $table->dropColumn('phone_number_verify');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('users', 'phone_number_verify')) {
                $table->boolean('phone_number_verify')->default(0)->after('phone_number');
            }
        });
    }
};
