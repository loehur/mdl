<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        // Rename table 'usaha' -> 'business_list' jika ada
        if (Schema::connection($this->connection)->hasTable('usaha') &&
            !Schema::connection($this->connection)->hasTable('business_list')) {
            Schema::connection($this->connection)->rename('usaha', 'business_list');
        }
    }

    public function down(): void
    {
        // Kembalikan ke nama awal jika diperlukan
        if (Schema::connection($this->connection)->hasTable('business_list') &&
            !Schema::connection($this->connection)->hasTable('usaha')) {
            Schema::connection($this->connection)->rename('business_list', 'usaha');
        }
    }
};

