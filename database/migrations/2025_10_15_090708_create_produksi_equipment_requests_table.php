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
        Schema::create('produksi_equipment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('music_submissions')->onDelete('cascade');
            $table->json('equipment_list');
            $table->text('request_notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'in_use', 'returned'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->date('shooting_date')->nullable();
            $table->date('return_date')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_equipment_requests');
    }
};
