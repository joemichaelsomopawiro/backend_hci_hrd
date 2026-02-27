<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pr_episode_workflow_progress', function (Blueprint $row) {
            $row->datetime('deadline_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_episode_workflow_progress', function (Blueprint $row) {
            $row->dropColumn('deadline_at');
        });
    }
};
