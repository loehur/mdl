<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('users')) {
            Schema::connection($this->connection)->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('phone_number')->unique();
                $table->string('username')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('users');
    }
};
