<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('settings')) {
            Schema::connection($this->connection)->create('settings', function (Blueprint $table) {
                $table->string('enum')->primary();
                $table->text('value')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('settings');
    }
};
