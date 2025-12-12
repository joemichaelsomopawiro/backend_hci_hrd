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
        Schema::table('creative_works', function (Blueprint $table) {
            // Change column type to include 'in_progress' status
            DB::statement("ALTER TABLE creative_works MODIFY COLUMN status ENUM(
                'draft',
                'in_progress',
                'submitted',
                'approved',
                'rejected',
                'revised'
            ) DEFAULT 'draft'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creative_works', function (Blueprint $table) {
            // Revert to original enum values
            DB::statement("ALTER TABLE creative_works MODIFY COLUMN status ENUM(
                'draft',
                'submitted',
                'approved',
                'rejected',
                'revised'
            ) DEFAULT 'draft'");
        });
    }
};

