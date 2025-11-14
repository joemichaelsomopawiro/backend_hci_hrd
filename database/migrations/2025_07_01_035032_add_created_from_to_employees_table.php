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
        if (!Schema::hasTable('employees')) {
            return;
        }
        
        // Only add column if it doesn't exist
        if (!Schema::hasColumn('employees', 'created_from')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('created_from')->nullable()->after('manager_id')
                      ->comment('Source of employee creation: manual, attendance_machine, import, etc');
                
                $table->index('created_from');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['created_from']);
            $table->dropColumn('created_from');
        });
    }
};
