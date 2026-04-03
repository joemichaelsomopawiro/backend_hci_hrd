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
        Schema::table('workflow_states', function (Blueprint $table) {
            if (!Schema::hasColumn('workflow_states', 'performing_user_id')) {
                $table->foreignId('performing_user_id')->nullable()->after('assigned_to_user_id')->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_states', function (Blueprint $table) {
            $table->dropForeign(['performing_user_id']);
            $table->dropColumn('performing_user_id');
        });
    }
};
