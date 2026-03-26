<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_equipment_transfers')) {
            return;
        }

        Schema::table('production_equipment_transfers', function (Blueprint $table) {
            if (!Schema::hasColumn('production_equipment_transfers', 'to_user_id')) {
                $table->unsignedBigInteger('to_user_id')->nullable()->after('to_episode_id');
                $table->index('to_user_id');
            }
            if (!Schema::hasColumn('production_equipment_transfers', 'status')) {
                $table->string('status', 30)->default('accepted')->after('notes');
                $table->index('status');
            }
            if (!Schema::hasColumn('production_equipment_transfers', 'accepted_by')) {
                $table->unsignedBigInteger('accepted_by')->nullable()->after('status');
                $table->index('accepted_by');
            }
            if (!Schema::hasColumn('production_equipment_transfers', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('production_equipment_transfers')) {
            return;
        }

        Schema::table('production_equipment_transfers', function (Blueprint $table) {
            foreach (['to_user_id', 'status', 'accepted_by', 'accepted_at'] as $col) {
                if (Schema::hasColumn('production_equipment_transfers', $col)) {
                    // Drop indexes safely (names auto-generated) - Laravel will handle where possible.
                }
            }

            if (Schema::hasColumn('production_equipment_transfers', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
            if (Schema::hasColumn('production_equipment_transfers', 'accepted_by')) {
                $table->dropColumn('accepted_by');
            }
            if (Schema::hasColumn('production_equipment_transfers', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('production_equipment_transfers', 'to_user_id')) {
                $table->dropColumn('to_user_id');
            }
        });
    }
};

