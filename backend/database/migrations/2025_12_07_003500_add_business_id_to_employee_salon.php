<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee') && !Schema::connection($this->connection)->hasColumn('employee', 'business_id')) {
            Schema::connection($this->connection)->table('employee', function (Blueprint $table) {
                $table->unsignedBigInteger('business_id')->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('employee') && Schema::connection($this->connection)->hasColumn('employee', 'business_id')) {
            Schema::connection($this->connection)->table('employee', function (Blueprint $table) {
                $table->dropColumn('business_id');
            });
        }
    }
};

