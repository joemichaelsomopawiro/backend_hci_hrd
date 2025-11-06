<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add fields to episodes table for team assignment
        Schema::table('episodes', function (Blueprint $table) {
            // Check if column doesn't exist before adding
            if (!Schema::hasColumn('episodes', 'production_team_id')) {
                $table->foreignId('production_team_id')->nullable()->after('program_id')->constrained('production_teams')->onDelete('set null');
            }
            if (!Schema::hasColumn('episodes', 'team_assignment_notes')) {
                $table->text('team_assignment_notes')->nullable();
            }
            if (!Schema::hasColumn('episodes', 'team_assigned_by')) {
                $table->foreignId('team_assigned_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('episodes', 'team_assigned_at')) {
                $table->timestamp('team_assigned_at')->nullable();
            }
        });
        
        // Add fields to deadlines table for editing
        Schema::table('deadlines', function (Blueprint $table) {
            if (!Schema::hasColumn('deadlines', 'change_reason')) {
                $table->text('change_reason')->nullable();
            }
            if (!Schema::hasColumn('deadlines', 'changed_by')) {
                $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('deadlines', 'changed_at')) {
                $table->timestamp('changed_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropForeign(['production_team_id']);
            $table->dropForeign(['team_assigned_by']);
            $table->dropColumn([
                'production_team_id',
                'team_assignment_notes',
                'team_assigned_by',
                'team_assigned_at'
            ]);
        });
        
        Schema::table('deadlines', function (Blueprint $table) {
            $table->dropForeign(['changed_by']);
            $table->dropColumn([
                'change_reason',
                'changed_by',
                'changed_at'
            ]);
        });
    }
};
