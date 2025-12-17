<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        DB::connection($this->connection)->statement('ALTER TABLE `users` MODIFY `langganan` VARCHAR(255) DEFAULT "standard"');
        DB::connection($this->connection)->statement('UPDATE `users` SET `langganan` = "standard" WHERE `langganan` IS NULL OR `langganan` = "standar"');
    }

    public function down(): void
    {
        DB::connection($this->connection)->statement('ALTER TABLE `users` MODIFY `langganan` VARCHAR(255) DEFAULT "standar"');
    }
};

