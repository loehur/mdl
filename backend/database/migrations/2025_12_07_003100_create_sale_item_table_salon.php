<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('sale_item')) {
            Schema::connection($this->connection)->create('sale_item', function (Blueprint $table) {
                $table->id();
                $table->string('ref_id', 40);
                $table->string('product_id', 100);
                $table->decimal('price', 12, 2);
                $table->unsignedInteger('qty');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sale_item');
    }
};

