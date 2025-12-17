<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('price')) {
            Schema::connection($this->connection)->create('price', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->string('enum', 100);
                $table->decimal('price', 12, 2);
                $table->index(['business_id']);
                $table->unique(['business_id', 'enum']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('price');
    }
};
