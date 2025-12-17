<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('ledger')) {
            if (!Schema::connection($this->connection)->hasColumn('ledger', 'business_id')) {
                Schema::connection($this->connection)->table('ledger', function (Blueprint $table) {
                    $table->unsignedBigInteger('business_id')->default(0)->after('id');
                    $table->index('business_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('ledger')) {
            if (Schema::connection($this->connection)->hasColumn('ledger', 'business_id')) {
                Schema::connection($this->connection)->table('ledger', function (Blueprint $table) {
                    $table->dropIndex(['business_id']);
                    $table->dropColumn('business_id');
                });
            }
        }
    }
};

