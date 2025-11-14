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
        // Check if table exists
        if (!Schema::hasTable('schedules')) {
            return;
        }
        
        Schema::table('schedules', function (Blueprint $table) {
            // Check if status column exists and modify it
            if (Schema::hasColumn('schedules', 'status')) {
                try {
                    $table->enum('status', [
                'draft', 
                'pending_approval', 
                'approved',
                'rejected',
                'in_progress', 
                'completed', 
                'cancelled',
                'overdue'
                    ])->default('draft')->change();
                } catch (\Exception $e) {
                    // Enum change failed, skip
                }
            }
            
            // Add new columns if they don't exist
            if (!Schema::hasColumn('schedules', 'submission_notes')) {
                $table->text('submission_notes')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'approval_notes')) {
                $table->text('approval_notes')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'rejection_notes')) {
                $table->text('rejection_notes')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('schedules', 'completed_at')) {
                $table->timestamp('completed_at')->nullable();
            }
        });
        
        // Add foreign keys if columns exist and foreign keys don't exist
        if (Schema::hasColumn('schedules', 'submitted_by')) {
            Schema::table('schedules', function (Blueprint $table) {
                if (!$this->foreignKeyExists('schedules', 'schedules_submitted_by_foreign')) {
                    $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
        if (Schema::hasColumn('schedules', 'approved_by')) {
            Schema::table('schedules', function (Blueprint $table) {
                if (!$this->foreignKeyExists('schedules', 'schedules_approved_by_foreign')) {
                    $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
        if (Schema::hasColumn('schedules', 'rejected_by')) {
            Schema::table('schedules', function (Blueprint $table) {
                if (!$this->foreignKeyExists('schedules', 'schedules_rejected_by_foreign')) {
                    $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
    }
    
    private function foreignKeyExists($table, $keyName)
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        $result = $connection->select(
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
        Schema::table('schedules', function (Blueprint $table) {
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
                'rejected_at',
                'completed_at'
            ]);
            
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft')->change();
        });
    }
};
