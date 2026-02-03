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
            if (Schema::hasColumn('budget_requests', 'status')) {
                // Change enum to string to allow more statuses like 'paid'
                $table->string('status')->default('pending')->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            // Cannot easily revert to enum if there's new data, so we leave as string
        });
    }
};
