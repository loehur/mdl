<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        DB::connection($this->connection)->statement('ALTER TABLE `users` MODIFY `active_until` DATE NULL');
    }

    public function down(): void
    {
        DB::connection($this->connection)->statement('ALTER TABLE `users` MODIFY `active_until` TIMESTAMP NULL');
    }
};
