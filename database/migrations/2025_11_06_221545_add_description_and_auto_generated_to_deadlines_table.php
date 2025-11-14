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
        if (Schema::hasTable('deadlines')) {
            Schema::table('deadlines', function (Blueprint $table) {
                // Add description field if not exists
                if (!Schema::hasColumn('deadlines', 'description')) {
                    $table->text('description')->nullable()->after('role');
                }
                
                // Add auto_generated field if not exists
                if (!Schema::hasColumn('deadlines', 'auto_generated')) {
                    $table->boolean('auto_generated')->default(true)->after('description');
                }
                
                // Add created_by field if not exists
                if (!Schema::hasColumn('deadlines', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('auto_generated')->constrained('users')->onDelete('set null');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('deadlines')) {
            Schema::table('deadlines', function (Blueprint $table) {
                if (Schema::hasColumn('deadlines', 'created_by')) {
                    $table->dropForeign(['created_by']);
                    $table->dropColumn('created_by');
                }
                if (Schema::hasColumn('deadlines', 'auto_generated')) {
                    $table->dropColumn('auto_generated');
                }
                if (Schema::hasColumn('deadlines', 'description')) {
                    $table->dropColumn('description');
                }
            });
        }
    }
};
