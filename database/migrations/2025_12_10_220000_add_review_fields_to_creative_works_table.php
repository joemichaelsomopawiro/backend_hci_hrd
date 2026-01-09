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
        Schema::table('creative_works', function (Blueprint $table) {
            // Review fields untuk Producer
            $table->boolean('script_approved')->nullable()->after('script_content');
            $table->boolean('storyboard_approved')->nullable()->after('storyboard_data');
            $table->boolean('budget_approved')->nullable()->after('budget_data');
            $table->text('script_review_notes')->nullable()->after('script_approved');
            $table->text('storyboard_review_notes')->nullable()->after('storyboard_approved');
            $table->text('budget_review_notes')->nullable()->after('budget_approved');
            
            // Field untuk budget khusus yang perlu Manager Program approval
            $table->boolean('requires_special_budget_approval')->default(false)->after('budget_review_notes');
            $table->text('special_budget_reason')->nullable()->after('requires_special_budget_approval');
            $table->foreignId('special_budget_approval_id')->nullable()->after('special_budget_reason');
            
            // Field untuk shooting schedule cancellation
            $table->boolean('shooting_schedule_cancelled')->default(false)->after('shooting_schedule');
            $table->text('shooting_cancellation_reason')->nullable()->after('shooting_schedule_cancelled');
            $table->datetime('shooting_schedule_new')->nullable()->after('shooting_cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creative_works', function (Blueprint $table) {
            $table->dropColumn([
                'script_approved',
                'storyboard_approved',
                'budget_approved',
                'script_review_notes',
                'storyboard_review_notes',
                'budget_review_notes',
                'requires_special_budget_approval',
                'special_budget_reason',
                'special_budget_approval_id',
                'shooting_schedule_cancelled',
                'shooting_cancellation_reason',
                'shooting_schedule_new'
            ]);
        });
    }
};

