<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('user_businesses') &&
            !Schema::connection($this->connection)->hasTable('user_business')) {
            Schema::connection($this->connection)->rename('user_businesses', 'user_business');
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('user_business') &&
            !Schema::connection($this->connection)->hasTable('user_businesses')) {
            Schema::connection($this->connection)->rename('user_business', 'user_businesses');
        }
    }
};

