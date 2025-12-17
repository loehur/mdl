<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('worker')) {
            Schema::connection($this->connection)->create('worker', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('item_id');
                $table->string('employee_id', 100);
                $table->index('item_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('worker');
    }
};

