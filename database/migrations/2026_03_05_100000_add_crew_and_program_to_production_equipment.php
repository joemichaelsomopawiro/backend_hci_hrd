<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add crew leader, crew members, program_id to production_equipment.
     * Setting team borrows; shooting team returns (returned_by).
     */
    public function up(): void
    {
        if (!Schema::hasTable('production_equipment')) {
            return;
        }

        Schema::table('production_equipment', function (Blueprint $table) {
            if (!Schema::hasColumn('production_equipment', 'program_id')) {
                $table->unsignedBigInteger('program_id')->nullable()->after('episode_id');
            }
            if (!Schema::hasColumn('production_equipment', 'crew_leader_id')) {
                $table->unsignedBigInteger('crew_leader_id')->nullable()->after('requested_by');
            }
            if (!Schema::hasColumn('production_equipment', 'crew_member_ids')) {
                $table->json('crew_member_ids')->nullable()->after('crew_leader_id'); // [user_id, ...]
            }
            if (!Schema::hasColumn('production_equipment', 'scheduled_date')) {
                $table->date('scheduled_date')->nullable()->after('request_notes');
            }
            if (!Schema::hasColumn('production_equipment', 'scheduled_time')) {
                $table->time('scheduled_time')->nullable()->after('scheduled_date');
            }
        });

        Schema::table('production_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('production_equipment', 'program_id')) {
                $table->foreign('program_id')->references('id')->on('programs')->onDelete('set null');
            }
            if (Schema::hasColumn('production_equipment', 'crew_leader_id')) {
                $table->foreign('crew_leader_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_equipment', function (Blueprint $table) {
            if (Schema::hasColumn('production_equipment', 'program_id')) {
                $table->dropForeign(['program_id']);
                $table->dropColumn('program_id');
            }
            if (Schema::hasColumn('production_equipment', 'crew_leader_id')) {
                $table->dropForeign(['crew_leader_id']);
                $table->dropColumn('crew_leader_id');
            }
            if (Schema::hasColumn('production_equipment', 'crew_member_ids')) {
                $table->dropColumn('crew_member_ids');
            }
            if (Schema::hasColumn('production_equipment', 'scheduled_date')) {
                $table->dropColumn('scheduled_date');
            }
            if (Schema::hasColumn('production_equipment', 'scheduled_time')) {
                $table->dropColumn('scheduled_time');
            }
        });
    }
};
