<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add columns needed for ProductionTeamAssignment members
     * This allows the same table to be used for both:
     * 1. ProductionTeam members (with production_team_id)
     * 2. ProductionTeamAssignment members (with assignment_id)
     */
    public function up(): void
    {
        Schema::table('production_team_members', function (Blueprint $table) {
            // Add assignment_id if it doesn't exist (for assignment-based team members)
            if (!Schema::hasColumn('production_team_members', 'assignment_id')) {
                $table->foreignId('assignment_id')->nullable()->after('production_team_id')
                    ->constrained('production_teams_assignment')->onDelete('cascade');
            }
            
            // Add role_notes if it doesn't exist
            if (!Schema::hasColumn('production_team_members', 'role_notes')) {
                $table->text('role_notes')->nullable()->after('role');
            }
            
            // Add status column if it doesn't exist (for assignment members)
            // Note: This might conflict with existing structure, so we check first
            if (!Schema::hasColumn('production_team_members', 'status')) {
                $table->enum('status', [
                    'assigned',     // Ditugaskan
                    'confirmed',    // Konfirmasi hadir
                    'present',      // Hadir
                    'absent',       // Tidak hadir
                    'completed'     // Selesai
                ])->default('assigned')->after('role_notes');
            }
            
            // Make production_team_id nullable if it's not already (to support assignment-based members)
            // We'll do this carefully to avoid breaking existing data
            if (Schema::hasColumn('production_team_members', 'production_team_id')) {
                // Check if column is nullable, if not, we need to make it nullable
                // But we'll do this in a separate migration if needed to avoid data issues
            }
        });
        
        // Add unique constraint for assignment_id + user_id if it doesn't exist
        // This is needed to prevent duplicate assignments
        // We use try-catch to handle case where constraint already exists
        try {
            Schema::table('production_team_members', function (Blueprint $table) {
                $table->unique(['assignment_id', 'user_id'], 'unique_assignment_user');
            });
        } catch (\Exception $e) {
            // Constraint might already exist, ignore
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_team_members', function (Blueprint $table) {
            // Drop unique constraint if exists
            try {
                $table->dropUnique('unique_assignment_user');
            } catch (\Exception $e) {
                // Constraint might not exist, ignore
            }
            
            // Drop columns if they exist
            if (Schema::hasColumn('production_team_members', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('production_team_members', 'role_notes')) {
                $table->dropColumn('role_notes');
            }
            if (Schema::hasColumn('production_team_members', 'assignment_id')) {
                try {
                    $table->dropForeign(['assignment_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist, ignore
                }
                $table->dropColumn('assignment_id');
            }
        });
    }
};
