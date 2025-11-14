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
        if (Schema::hasTable('programs')) {
        Schema::table('programs', function (Blueprint $table) {
                // Update status enum if status column exists
                if (Schema::hasColumn('programs', 'status')) {
                    try {
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'active', 'completed', 'cancelled', 'rejected', 'auto_closed'])->default('draft')->change();
                    } catch (\Exception $e) {
                        // Status enum might already be updated, skip
                    }
                }
                
                // Add columns only if they don't exist
                if (!Schema::hasColumn('programs', 'submission_notes')) {
            $table->text('submission_notes')->nullable();
                }
                if (!Schema::hasColumn('programs', 'submitted_at')) {
            $table->timestamp('submitted_at')->nullable();
                }
                if (!Schema::hasColumn('programs', 'submitted_by')) {
            $table->unsignedBigInteger('submitted_by')->nullable();
                }
                if (!Schema::hasColumn('programs', 'approval_notes')) {
            $table->text('approval_notes')->nullable();
                }
                if (!Schema::hasColumn('programs', 'approved_by')) {
            $table->unsignedBigInteger('approved_by')->nullable();
                }
                if (!Schema::hasColumn('programs', 'approved_at')) {
            $table->timestamp('approved_at')->nullable();
                }
                if (!Schema::hasColumn('programs', 'rejection_notes')) {
            $table->text('rejection_notes')->nullable();
                }
                if (!Schema::hasColumn('programs', 'rejected_by')) {
            $table->unsignedBigInteger('rejected_by')->nullable();
                }
                if (!Schema::hasColumn('programs', 'rejected_at')) {
            $table->timestamp('rejected_at')->nullable();
                }
            });
            
            // Add foreign keys separately to avoid issues
            Schema::table('programs', function (Blueprint $table) {
                if (Schema::hasColumn('programs', 'submitted_by') && 
                    !$this->foreignKeyExists('programs', 'programs_submitted_by_foreign')) {
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
                }
                if (Schema::hasColumn('programs', 'approved_by') && 
                    !$this->foreignKeyExists('programs', 'programs_approved_by_foreign')) {
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                }
                if (Schema::hasColumn('programs', 'rejected_by') && 
                    !$this->foreignKeyExists('programs', 'programs_rejected_by_foreign')) {
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
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            
            $table->dropColumn([
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
            
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft')->change();
        });
    }
};
