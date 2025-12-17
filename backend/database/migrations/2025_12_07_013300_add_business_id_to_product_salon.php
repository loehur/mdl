<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    protected $connection = 'salon';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('product')) {
            if (!Schema::connection($this->connection)->hasColumn('product', 'business_id')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `product` ADD COLUMN `business_id` INT NOT NULL DEFAULT 0 AFTER `product_id`"
                    );
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `product` ADD INDEX (`business_id`)"
                    );
                } catch (\Throwable $e) {}
            }
        }
        // Best-effort backfill using latest sale usage
        try {
            $map = DB::connection($this->connection)->select(
                "SELECT si.product_id as pid, r.business_id as bid\n                 FROM sale_item si\n                 JOIN sale_ref r ON r.ref_id = si.ref_id\n                 WHERE r.business_id IS NOT NULL\n                 ORDER BY si.id DESC"
            );
            $seen = [];
            foreach ($map as $row) {
                $pid = (string) ($row->pid ?? '');
                $bid = (int) ($row->bid ?? 0);
                if ($pid === '' || isset($seen[$pid])) continue;
                $seen[$pid] = true;
                DB::connection($this->connection)->table('product')
                    ->where('product_id', $pid)
                    ->update(['business_id' => $bid]);
            }
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (Schema::connection($this->connection)->hasTable('product')) {
            if (Schema::connection($this->connection)->hasColumn('product', 'business_id')) {
                try {
                    DB::connection($this->connection)->statement(
                        "ALTER TABLE `product` DROP COLUMN `business_id`"
                    );
                } catch (\Throwable $e) {}
            }
        }
    }
};

