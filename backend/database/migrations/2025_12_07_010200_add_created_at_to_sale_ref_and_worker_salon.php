<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sale_ref')) {
            if (!Schema::connection($this->connection)->hasColumn('sale_ref', 'created_at')) {
                Schema::connection($this->connection)->table('sale_ref', function (Blueprint $table) {
                    $table->timestamp('created_at')->useCurrent();
                });
            }
        }
        if (Schema::connection($this->connection)->hasTable('worker')) {
            if (!Schema::connection($this->connection)->hasColumn('worker', 'created_at')) {
                Schema::connection($this->connection)->table('worker', function (Blueprint $table) {
                    $table->timestamp('created_at')->useCurrent();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('sale_ref')) {
            if (Schema::connection($this->connection)->hasColumn('sale_ref', 'created_at')) {
                Schema::connection($this->connection)->table('sale_ref', function (Blueprint $table) {
                    $table->dropColumn('created_at');
                });
            }
        }
        if (Schema::connection($this->connection)->hasTable('worker')) {
            if (Schema::connection($this->connection)->hasColumn('worker', 'created_at')) {
                Schema::connection($this->connection)->table('worker', function (Blueprint $table) {
                    $table->dropColumn('created_at');
                });
            }
        }
    }
};

