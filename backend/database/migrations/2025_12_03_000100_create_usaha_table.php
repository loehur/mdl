<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('usaha')) {
            Schema::connection($this->connection)->create('usaha', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });

            DB::connection($this->connection)->table('usaha')->insert([
                ['name' => 'Laundry', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Resto', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Depot', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Salon', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('usaha');
    }
};
