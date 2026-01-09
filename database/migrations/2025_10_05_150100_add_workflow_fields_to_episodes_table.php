<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('episodes')) {
            // Update existing data first to match new enum values
            if (Schema::hasColumn('episodes', 'status')) {
                // Step 1: Temporarily expand enum to include both old and new values
                Schema::table('episodes', function (Blueprint $table) {
                    try {
                        $table->enum('status', [
                            // Old values
                            'planning',
                            'ready_to_produce',
                            'post_production',
                            'ready_to_air',
                            // New values
                            'draft', 
                            'rundown_pending_approval', 
                            'rundown_rejected',
                            'approved_for_production',
                            'in_production', 
                            'script_overdue',
                            'production_overdue',
                            'ready_for_review',
                            'reviewed',
                            'aired',
                            'cancelled'
                        ])->default('draft')->change();
                    } catch (\Exception $e) {
                        // Enum might already be updated, continue
                    }
                });
                
                // Step 2: Map old status values to new ones
                DB::table('episodes')->where('status', 'planning')->update(['status' => 'draft']);
                DB::table('episodes')->where('status', 'ready_to_produce')->update(['status' => 'approved_for_production']);
                // 'in_production' stays the same
                DB::table('episodes')->where('status', 'post_production')->update(['status' => 'ready_for_review']);
                DB::table('episodes')->where('status', 'ready_to_air')->update(['status' => 'reviewed']);
                // 'aired' and 'cancelled' stay the same
            }
            
        Schema::table('episodes', function (Blueprint $table) {
                // Step 3: Update status enum to final values only
                if (Schema::hasColumn('episodes', 'status')) {
                    try {
            $table->enum('status', [
                'draft', 
                'rundown_pending_approval', 
                'rundown_rejected',
                'approved_for_production',
                'in_production', 
                'script_overdue',
                'production_overdue',
                'ready_for_review',
                'reviewed',
                'aired',
                'cancelled'
            ])->default('draft')->change();
                    } catch (\Exception $e) {
                        // Status enum might already be updated, skip
                    }
                }
            
                // Add columns only if they don't exist
                if (!Schema::hasColumn('episodes', 'production_deadline')) {
            $table->timestamp('production_deadline')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'script_deadline')) {
            $table->timestamp('script_deadline')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'production_started_at')) {
            $table->timestamp('production_started_at')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'production_completed_at')) {
            $table->timestamp('production_completed_at')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'submission_notes')) {
            $table->text('submission_notes')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'submitted_at')) {
            $table->timestamp('submitted_at')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'submitted_by')) {
            $table->unsignedBigInteger('submitted_by')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'approval_notes')) {
            $table->text('approval_notes')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'approved_by')) {
            $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'approved_at')) {
            $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'rejection_notes')) {
            $table->text('rejection_notes')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'rejected_by')) {
            $table->unsignedBigInteger('rejected_by')->nullable();
                }
                if (!Schema::hasColumn('episodes', 'rejected_at')) {
            $table->timestamp('rejected_at')->nullable();
                }
            });
            
            // Add foreign keys separately to avoid issues
            Schema::table('episodes', function (Blueprint $table) {
                if (Schema::hasColumn('episodes', 'submitted_by') && 
                    !$this->foreignKeyExists('episodes', 'episodes_submitted_by_foreign')) {
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
                }
                if (Schema::hasColumn('episodes', 'approved_by') && 
                    !$this->foreignKeyExists('episodes', 'episodes_approved_by_foreign')) {
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                }
                if (Schema::hasColumn('episodes', 'rejected_by') && 
                    !$this->foreignKeyExists('episodes', 'episodes_rejected_by_foreign')) {
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
                }
        });
        }
    }
    
    private function foreignKeyExists($table, $keyName)
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
            [$database, $table, $keyName]
        );
        return count($result) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            
            $table->dropColumn([
                'production_deadline',
                'script_deadline',
                'production_started_at',
                'production_completed_at',
                'submission_notes',
                'submitted_at',
                'submitted_by',
                'approval_notes',
                'approved_by',
                'approved_at',
                'rejection_notes',
                'rejected_by',
                'rejected_at'
            ]);
            
            $table->enum('status', ['draft', 'in_production', 'ready_for_review', 'reviewed', 'aired', 'cancelled'])->default('draft')->change();
        });
    }
};
