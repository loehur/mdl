<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('product')) {
            Schema::connection($this->connection)->create('product', function (Blueprint $table) {
                $table->id();
                $table->string('enum', 100)->unique();
                $table->string('name', 150);
                $table->enum('type', ['barang', 'jasa'])->default('barang');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('product');
    }
};

