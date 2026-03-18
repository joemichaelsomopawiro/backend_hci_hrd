<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_equipment_transfers')) {
            return;
        }
        try {
            DB::statement('CREATE INDEX pe_transfers_episodes_index ON production_equipment_transfers (from_episode_id, to_episode_id)');
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate key')) {
                return;
            }
            throw $e;
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_equipment_transfers')) {
            Schema::table('production_equipment_transfers', function (Blueprint $table) {
                $table->dropIndex('pe_transfers_episodes_index');
            });
        }
    }
};
