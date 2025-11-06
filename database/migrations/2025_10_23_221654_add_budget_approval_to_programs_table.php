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
        Schema::table('programs', function (Blueprint $table) {
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->boolean('budget_approved')->default(false);
            $table->text('budget_notes')->nullable();
            $table->foreignId('budget_approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('budget_approved_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'budget_amount',
                'budget_approved',
                'budget_notes',
                'budget_approved_by',
                'budget_approved_at'
            ]);
        });
    }
};
