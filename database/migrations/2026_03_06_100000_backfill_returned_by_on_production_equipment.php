<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill returned_by for records that are already 'returned' but have null returned_by.
     * Set returned_by = requested_by (orang yang pinjam dianggap mengembalikan jika tidak tercatat).
     */
    public function up(): void
    {
        DB::table('production_equipment')
            ->where('status', 'returned')
            ->whereNull('returned_by')
            ->whereNotNull('requested_by')
            ->update(['returned_by' => DB::raw('requested_by')]);
    }

    /**
     * Reverse: we cannot reliably restore null returned_by.
     */
    public function down(): void
    {
        // No-op: backfill is a data fix, no need to reverse
    }
};
