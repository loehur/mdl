<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('staff')) {
            Schema::connection($this->connection)->create('staff', function (Blueprint $table) {
                $table->string('employee_id', 100);
                $table->string('name');
                $table->primary('employee_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('staff');
    }
};

