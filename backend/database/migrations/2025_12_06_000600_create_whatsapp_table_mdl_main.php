<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('whatsapp')) {
            Schema::connection($this->connection)->create('whatsapp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('device_name');
                $table->string('api_token')->unique();
                $table->string('wa_status')->default('active');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('whatsapp');
    }
};

