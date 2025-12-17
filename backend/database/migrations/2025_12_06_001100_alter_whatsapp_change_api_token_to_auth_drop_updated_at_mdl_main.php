<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'mdl_main';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('whatsapp')) {
            // Ganti kolom api_token menjadi auth
            if (Schema::connection($this->connection)->hasColumn('whatsapp', 'api_token') && !Schema::connection($this->connection)->hasColumn('whatsapp', 'auth')) {
                DB::connection($this->connection)->statement('ALTER TABLE `whatsapp` CHANGE `api_token` `auth` VARCHAR(255) NOT NULL');
            }
            // Hapus updated_at agar sesuai spesifikasi
            if (Schema::connection($this->connection)->hasColumn('whatsapp', 'updated_at')) {
                DB::connection($this->connection)->statement('ALTER TABLE `whatsapp` DROP COLUMN `updated_at`');
            }
            // Pastikan created_at ada
            if (!Schema::connection($this->connection)->hasColumn('whatsapp', 'created_at')) {
                DB::connection($this->connection)->statement('ALTER TABLE `whatsapp` ADD COLUMN `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
            }
        }
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('whatsapp')) {
            // Kembalikan perubahan jika diperlukan
            if (Schema::connection($this->connection)->hasColumn('whatsapp', 'auth') && !Schema::connection($this->connection)->hasColumn('whatsapp', 'api_token')) {
                DB::connection($this->connection)->statement('ALTER TABLE `whatsapp` CHANGE `auth` `api_token` VARCHAR(255) NOT NULL');
            }
            if (!Schema::connection($this->connection)->hasColumn('whatsapp', 'updated_at')) {
                DB::connection($this->connection)->statement('ALTER TABLE `whatsapp` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            }
        }
    }
};
