<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('user_businesses')) {
            Schema::connection($this->connection)->create('user_businesses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('business_id');
                $table->string('business_name');
                $table->string('area_code', 3)->nullable();
                $table->string('branch_code', 3)->nullable();
                $table->string('business_status')->default('active');
                $table->timestamps();
                $table->index(['user_id', 'business_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('user_businesses');
    }
};

