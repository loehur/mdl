<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('sale_ref')) {
            Schema::connection($this->connection)->create('sale_ref', function (Blueprint $table) {
                $table->string('ref_id', 40);
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('customer_id');
                $table->enum('order_status', ['berjalan', 'batal', 'selesai'])->default('berjalan');
                $table->primary('ref_id');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sale_ref');
    }
};

