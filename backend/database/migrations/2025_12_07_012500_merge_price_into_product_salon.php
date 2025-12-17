<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        // Add fields to product
        if (Schema::connection($this->connection)->hasTable('product')) {
            if (!Schema::connection($this->connection)->hasColumn('product', 'type')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `product` ADD COLUMN `type` ENUM('barang','jasa') NOT NULL DEFAULT 'barang' AFTER `name`"
                    );
                } catch (\Throwable $e) {}
            }
            if (!Schema::connection($this->connection)->hasColumn('product', 'price')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `product` ADD COLUMN `price` DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER `type`"
                    );
                } catch (\Throwable $e) {}
            }
        }

        // Backfill from price (choose any row per product)
        try {
            $rows = DB::connection($this->connection)->table('price')->select(['product_id','price','type'])->get();
            foreach ($rows as $r) {
                $pid = (string) ($r->product_id ?? '');
                if ($pid === '') continue;
                $t = (string) ($r->type ?? 'barang');
                if (!in_array(strtolower($t), ['barang','jasa'], true)) $t = 'barang';
                DB::connection($this->connection)->table('product')
                    ->where('product_id', $pid)
                    ->update(['type' => $t, 'price' => (float) ($r->price ?? 0)]);
            }
        } catch (\Throwable $e) {}

        // Drop price table
        try {
            Schema::connection($this->connection)->dropIfExists('price');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // Recreate price table minimal, and clear product fields
        try {
            Schema::connection($this->connection)->create('price', function ($table) {
                $table->integer('business_id');
                $table->string('product_id');
                $table->decimal('price', 12, 2);
                $table->enum('type', ['barang','jasa'])->default('barang');
            });
        } catch (\Throwable $e) {}
        try {
            DB::connection($this->connection)->statement("ALTER TABLE `product` DROP COLUMN `price`");
        } catch (\Throwable $e) {}
        try {
            DB::connection($this->connection)->statement("ALTER TABLE `product` DROP COLUMN `type`");
        } catch (\Throwable $e) {}
    }
};

