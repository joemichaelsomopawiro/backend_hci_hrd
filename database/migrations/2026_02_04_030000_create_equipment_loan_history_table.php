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
        Schema::create('equipment_loan_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_loan_id')->constrained('equipment_loans')->onDelete('cascade');
            $table->enum('action', ['borrowed', 'returned']);
            $table->timestamp('action_date');
            $table->foreignId('performed_by')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable(); // Art & Set can add notes
            $table->timestamps();

            // Index for faster filtering
            $table->index(['equipment_loan_id', 'action_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loan_history');
    }
};
