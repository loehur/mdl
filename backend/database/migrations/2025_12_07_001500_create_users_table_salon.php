<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('users')) {
            Schema::connection($this->connection)->create('users', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bussiness_id');
                $table->string('password');
                $table->string('role');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('users');
    }
};

