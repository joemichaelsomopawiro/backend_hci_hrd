<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fix assigned_to_role field in episodes table
     * Change from foreignId to string to match model usage
     */
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // Drop the foreignId column and recreate as string
            $table->dropColumn('assigned_to_role');
        });
        
        Schema::table('episodes', function (Blueprint $table) {
            $table->string('assigned_to_role', 50)->nullable()->after('current_workflow_state');
            $table->index('assigned_to_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['assigned_to_role']);
            $table->dropColumn('assigned_to_role');
        });
        
        Schema::table('episodes', function (Blueprint $table) {
            $table->foreignId('assigned_to_role')->nullable()->after('current_workflow_state');
            $table->index('assigned_to_role');
        });
    }
};

