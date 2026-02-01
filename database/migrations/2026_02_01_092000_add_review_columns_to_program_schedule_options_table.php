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
        Schema::table('program_schedule_options', function (Blueprint $table) {
            if (!Schema::hasColumn('program_schedule_options', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null')->after('submission_notes');
            }
            if (!Schema::hasColumn('program_schedule_options', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('program_schedule_options', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('program_schedule_options', 'selected_option_index')) {
                $table->integer('selected_option_index')->nullable()->after('review_notes');
            }
            if (!Schema::hasColumn('program_schedule_options', 'approved_schedule')) {
                $table->json('approved_schedule')->nullable()->after('selected_option_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('program_schedule_options', function (Blueprint $table) {
            $table->dropColumn([
                'reviewed_by',
                'reviewed_at',
                'review_notes',
                'selected_option_index',
                'approved_schedule'
            ]);
        });
    }
};
