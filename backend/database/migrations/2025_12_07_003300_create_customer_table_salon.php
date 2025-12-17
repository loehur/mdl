<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('customer')) {
            Schema::connection($this->connection)->create('customer', function (Blueprint $table) {
                $table->string('customer_id', 100);
                $table->string('name');
                $table->string('phone_number', 30);
                $table->primary('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('customer');
    }
};

