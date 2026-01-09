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
        if (!Schema::hasTable('custom_roles')) {
            return;
        }
        
        Schema::table('custom_roles', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('custom_roles', 'department')) {
                $table->enum('department', ['hr', 'production', 'distribution', 'executive'])->nullable()->after('access_level');
            }
            if (!Schema::hasColumn('custom_roles', 'supervisor_id')) {
                $table->unsignedBigInteger('supervisor_id')->nullable()->after('department');
            }
        });
        
        // Add foreign key and indexes if columns exist
        if (Schema::hasColumn('custom_roles', 'supervisor_id')) {
            Schema::table('custom_roles', function (Blueprint $table) {
                // Check if foreign key doesn't exist
                $connection = Schema::getConnection();
                $database = $connection->getDatabaseName();
                $result = $connection->select(
                    "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?",
                    [$database, 'custom_roles', 'custom_roles_supervisor_id_foreign']
                );
                if (count($result) == 0) {
                    $table->foreign('supervisor_id')->references('id')->on('custom_roles')->onDelete('set null');
                }
            });
            
            // Add indexes
            Schema::table('custom_roles', function (Blueprint $table) {
                try {
                    $table->index(['department', 'is_active']);
                } catch (\Exception $e) {
                    // Index might already exist
                }
                try {
                    $table->index('supervisor_id');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_roles', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropIndex(['department', 'is_active']);
            $table->dropIndex(['supervisor_id']);
            $table->dropColumn(['department', 'supervisor_id']);
        });
    }
};
