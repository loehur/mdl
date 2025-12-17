<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        Schema::connection($this->connection)->dropIfExists('kota');
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('kota')) {
            Schema::connection($this->connection)->create('kota', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }
    }
};

