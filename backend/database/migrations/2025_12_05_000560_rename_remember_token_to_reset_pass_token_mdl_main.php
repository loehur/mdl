<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasColumn('users', 'remember_token')) {
            DB::connection($this->connection)->statement('ALTER TABLE `users` CHANGE `remember_token` `reset_pass_token` VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasColumn('users', 'reset_pass_token')) {
            DB::connection($this->connection)->statement('ALTER TABLE `users` CHANGE `reset_pass_token` `remember_token` VARCHAR(100) NULL');
        }
    }
};

