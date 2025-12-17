<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('staff') && !Schema::connection($this->connection)->hasTable('employee')) {
            Schema::connection($this->connection)->rename('staff', 'employee');
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee') && !Schema::connection($this->connection)->hasTable('staff')) {
            Schema::connection($this->connection)->rename('employee', 'staff');
        }
    }
};

