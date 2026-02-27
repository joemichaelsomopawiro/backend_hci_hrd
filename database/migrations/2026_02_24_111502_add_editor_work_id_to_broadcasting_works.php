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
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->foreignId('editor_work_id')->nullable()->after('episode_id')->constrained('editor_works')->onDelete('set null');
            $table->index('editor_work_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $table) {
            $table->dropForeign(['editor_work_id']);
            $table->dropIndex(['editor_work_id']);
            $table->dropColumn('editor_work_id');
        });
    }
};
