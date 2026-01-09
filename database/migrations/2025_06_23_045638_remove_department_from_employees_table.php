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
        
        // Only drop column if it exists
        if (Schema::hasColumn('employees', 'department')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('department');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('department')->nullable();
        });
    }
};
