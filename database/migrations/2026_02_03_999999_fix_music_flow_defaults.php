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
        // Fix users table - phone should be nullable
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone')->nullable()->change();
            });
        }

        // Fix programs table - start_date should be nullable
        if (Schema::hasTable('programs')) {
            Schema::table('programs', function (Blueprint $table) {
                $table->date('start_date')->nullable()->change();
            });
        }

        // Fix episodes table - air_date should be nullable
        if (Schema::hasTable('episodes')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->date('air_date')->nullable()->change();
            });
        }

        // Fix program_approvals table - add program_id if missing
        if (Schema::hasTable('program_approvals')) {
            Schema::table('program_approvals', function (Blueprint $table) {
                if (!Schema::hasColumn('program_approvals', 'program_id')) {
                    $table->foreignId('program_id')->nullable()->after('id')->constrained('programs')->onDelete('cascade');
                }
            });
        }

        // Fix quality_control_works table - ensure 'completed' status exists
        if (Schema::hasTable('quality_control_works')) {
            // Using raw SQL to ensure enum is updated correctly in MySQL
            DB::statement("ALTER TABLE quality_control_works MODIFY COLUMN status ENUM('pending', 'in_progress', 'passed', 'failed', 'revision_needed', 'approved', 'completed') DEFAULT 'pending'");
        }
        
        // Fix quality_controls table (the other table mentioned in controller)
        if (Schema::hasTable('quality_controls')) {
             DB::statement("ALTER TABLE quality_controls MODIFY COLUMN status ENUM('pending', 'in_progress', 'passed', 'failed', 'revision_needed', 'approved', 'completed', 'rejected') DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse needed for these minor fixes in this context
    }
};
