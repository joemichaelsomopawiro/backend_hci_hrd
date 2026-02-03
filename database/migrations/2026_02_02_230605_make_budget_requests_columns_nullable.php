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
        Schema::table('budget_requests', function (Blueprint $table) {
            if (Schema::hasColumn('budget_requests', 'submission_id')) {
                $table->unsignedBigInteger('submission_id')->nullable()->change();
            }
            if (Schema::hasColumn('budget_requests', 'budget_type')) {
                $table->string('budget_type')->nullable()->change();
            }
            if (Schema::hasColumn('budget_requests', 'items')) {
                $table->json('items')->nullable()->change();
            }
            if (Schema::hasColumn('budget_requests', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->change();
            }
            if (Schema::hasColumn('budget_requests', 'description')) {
                $table->text('description')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            // Reverting to NOT NULL is risky if there's null data, so we leave it nullable
        });
    }
};
