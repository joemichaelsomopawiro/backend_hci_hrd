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
        Schema::create('equipment_loan_promotion_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('pr_promotion_work_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loan_promotion_work');
    }
};
