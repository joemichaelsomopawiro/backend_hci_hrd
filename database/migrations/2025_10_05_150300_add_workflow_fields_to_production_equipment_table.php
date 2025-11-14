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
        // Check if table exists
        if (!Schema::hasTable('production_equipment')) {
            return;
        }
        
        Schema::table('production_equipment', function (Blueprint $table) {
            // Check if status column exists and modify it
            if (Schema::hasColumn('production_equipment', 'status')) {
                try {
                    // Step 1: Temporarily expand enum to include all old and new values
                    DB::statement("ALTER TABLE `production_equipment` MODIFY `status` ENUM('available', 'requested', 'approved', 'rejected', 'assigned', 'in_use', 'maintenance', 'broken', 'returned') NOT NULL DEFAULT 'available'");
                } catch (\Exception $e) {
                    // Enum change failed, skip
                }
            }
            
            // Check if category column exists and modify it
            if (Schema::hasColumn('production_equipment', 'category')) {
                try {
            $table->enum('category', [
                'camera', 
                'lighting', 
                'audio', 
                'props', 
                'set_design', 
                'other'
            ])->change();
                } catch (\Exception $e) {
                    // Enum change failed, skip
                }
            }
            
            // Add new columns if they don't exist
            if (!Schema::hasColumn('production_equipment', 'requested_for_date')) {
            $table->timestamp('requested_for_date')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'return_date')) {
            $table->timestamp('return_date')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'approval_notes')) {
            $table->text('approval_notes')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'approved_by')) {
            $table->unsignedBigInteger('approved_by')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'approved_at')) {
            $table->timestamp('approved_at')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'rejection_reason')) {
            $table->text('rejection_reason')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'rejected_by')) {
            $table->unsignedBigInteger('rejected_by')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'rejected_at')) {
            $table->timestamp('rejected_at')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'assigned_at')) {
            $table->timestamp('assigned_at')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'assignment_notes')) {
            $table->text('assignment_notes')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'return_condition')) {
            $table->enum('return_condition', ['good', 'damaged', 'needs_maintenance'])->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'return_notes')) {
            $table->text('return_notes')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'returned_at')) {
            $table->timestamp('returned_at')->nullable();
            }
            if (!Schema::hasColumn('production_equipment', 'returned_by')) {
            $table->unsignedBigInteger('returned_by')->nullable();
            }
        });
            
        // Add foreign keys if columns exist and foreign keys don't exist
        if (Schema::hasColumn('production_equipment', 'approved_by')) {
            Schema::table('production_equipment', function (Blueprint $table) {
                if (!$this->foreignKeyExists('production_equipment', 'production_equipment_approved_by_foreign')) {
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
        if (Schema::hasColumn('production_equipment', 'rejected_by')) {
            Schema::table('production_equipment', function (Blueprint $table) {
                if (!$this->foreignKeyExists('production_equipment', 'production_equipment_rejected_by_foreign')) {
            $table->foreign('rejected_by')->references('id')->on('users')->onDelete('set null');
                }
            });
        }
        if (Schema::hasColumn('production_equipment', 'returned_by')) {
            Schema::table('production_equipment', function (Blueprint $table) {
                if (!$this->foreignKeyExists('production_equipment', 'production_equipment_returned_by_foreign')) {
            $table->foreign('returned_by')->references('id')->on('users')->onDelete('set null');
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
        Schema::table('production_equipment', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['returned_by']);
            
            $table->dropColumn([
                'requested_for_date',
                'return_date',
                'approval_notes',
                'approved_by',
                'approved_at',
                'rejection_reason',
                'rejected_by',
                'rejected_at',
                'assigned_at',
                'assignment_notes',
                'return_condition',
                'return_notes',
                'returned_at',
                'returned_by'
            ]);
            
            $table->enum('status', ['available', 'assigned', 'in_use', 'maintenance', 'broken'])->default('available')->change();
            $table->enum('category', ['camera', 'lighting', 'audio', 'props', 'other'])->change();
        });
    }
};
