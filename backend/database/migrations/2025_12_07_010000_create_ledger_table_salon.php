<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('ledger')) {
            Schema::connection($this->connection)->create('ledger', function (Blueprint $table) {
                $table->id();
                $table->string('type', 30)->index();
                $table->string('source', 100)->nullable();
                $table->string('target', 100)->nullable();
                $table->decimal('amount', 16, 2);

                $table->string('ref_id', 60)->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('ledger')) {
            Schema::connection($this->connection)->drop('ledger');
        }
    }
};
