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
        Schema::table('pr_creative_works', function (Blueprint $table) {
            $table->boolean('budget_processed_by_ga')->default(false)->after('budget_approved_at');
            $table->timestamp('budget_processed_at')->nullable()->after('budget_processed_by_ga');
            $table->foreignId('budget_processed_by')->nullable()->constrained('users')->onDelete('set null')->after('budget_processed_at');
            $table->text('budget_processing_notes')->nullable()->after('budget_processed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pr_creative_works', function (Blueprint $table) {
            $table->dropForeign(['budget_processed_by']);
            $table->dropColumn([
                'budget_processed_by_ga',
                'budget_processed_at',
                'budget_processed_by',
                'budget_processing_notes'
            ]);
        });
    }
};
