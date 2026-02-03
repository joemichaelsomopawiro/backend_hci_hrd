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
            if (!Schema::hasColumn('budget_requests', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('approval_notes');
            }
            if (!Schema::hasColumn('budget_requests', 'payment_schedule')) {
                $table->date('payment_schedule')->nullable()->after('payment_method');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            if (Schema::hasColumn('budget_requests', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('budget_requests', 'payment_schedule')) {
                $table->dropColumn('payment_schedule');
            }
        });
    }
};
