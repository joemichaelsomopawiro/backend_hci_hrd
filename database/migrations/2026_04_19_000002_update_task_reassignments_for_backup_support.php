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
        if (Schema::hasTable("task_reassignments")) {
            Schema::table("task_reassignments", function (Blueprint $table) {
                if (!Schema::hasColumn("task_reassignments", "episode_id")) {
                    $table->unsignedBigInteger("episode_id")->nullable()->after("task_id");
                }
                if (!Schema::hasColumn("task_reassignments", "role_key")) {
                    $table->string("role_key")->nullable()->after("episode_id");
                }
                
                // Make task_id nullable because backup might start before task record exists
                $table->unsignedBigInteger("task_id")->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable("task_reassignments")) {
            Schema::table("task_reassignments", function (Blueprint $table) {
                if (Schema::hasColumn("task_reassignments", "episode_id")) {
                    $table->dropColumn("episode_id");
                }
                if (Schema::hasColumn("task_reassignments", "role_key")) {
                    $table->dropColumn("role_key");
                }
                $table->unsignedBigInteger("task_id")->change();
            });
        }
    }
};
