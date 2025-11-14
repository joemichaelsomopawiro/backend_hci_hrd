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
        Schema::table('program_team', function (Blueprint $table) {
            // Step 1: Drop foreign key constraints
            $table->dropForeign(['program_id']);
            $table->dropForeign(['team_id']);
            
            // Step 2: Drop unique constraint
            $table->dropUnique(['program_id', 'team_id']);
            
            // Step 3: Re-create foreign key constraints (without unique)
            $table->foreign('program_id')->references('id')->on('programs')->onDelete('cascade');
            $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_team', function (Blueprint $table) {
            // Re-add unique constraint
            $table->unique(['program_id', 'team_id']);
        });
    }
};
