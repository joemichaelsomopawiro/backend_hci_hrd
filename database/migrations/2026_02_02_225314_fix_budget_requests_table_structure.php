<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            if (Schema::hasColumn('budget_requests', 'submission_id')) {
                $table->unsignedBigInteger('submission_id')->nullable()->change();
            }
            if (!Schema::hasColumn('budget_requests', 'program_id')) {
                $table->foreignId('program_id')->after('id')->nullable()->constrained('programs')->onDelete('cascade');
            }
            if (!Schema::hasColumn('budget_requests', 'request_type')) {
                $table->string('request_type')->after('program_id')->default('special');
            }
            if (!Schema::hasColumn('budget_requests', 'title')) {
                $table->string('title')->after('request_type')->nullable();
            }
            if (!Schema::hasColumn('budget_requests', 'requested_amount')) {
                if (Schema::hasColumn('budget_requests', 'amount')) {
                    // Use raw SQL for better MariaDB compatibility
                    DB::statement('ALTER TABLE `budget_requests` CHANGE COLUMN `amount` `requested_amount` DECIMAL(15,2) DEFAULT 0');
                } else {
                    $table->decimal('requested_amount', 15, 2)->after('description')->default(0);
                }
            }
            if (!Schema::hasColumn('budget_requests', 'processed_by')) {
                $table->foreignId('processed_by')->nullable()->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('budget_requests', 'payment_date')) {
                $table->date('payment_date')->nullable();
            }
            if (!Schema::hasColumn('budget_requests', 'payment_receipt')) {
                $table->string('payment_receipt')->nullable();
            }
            if (!Schema::hasColumn('budget_requests', 'payment_notes')) {
                $table->text('payment_notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            // Drop foreign keys first
            if (Schema::hasColumn('budget_requests', 'program_id')) {
                $table->dropForeign(['program_id']);
            }
            if (Schema::hasColumn('budget_requests', 'processed_by')) {
                $table->dropForeign(['processed_by']);
            }

            // Then drop columns
            $table->dropColumn(['program_id', 'request_type', 'title', 'processed_by', 'payment_date', 'payment_receipt', 'payment_notes']);

            // Revert submission_id to not nullable if needed, but usually we leave it
        });
    }
};
