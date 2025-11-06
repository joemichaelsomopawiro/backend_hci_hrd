<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Production Equipment table - Equipment requests and management
     */
    public function up(): void
    {
        Schema::create('production_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->json('equipment_list'); // List alat yang diminta
            $table->text('request_notes')->nullable(); // Catatan permintaan
            $table->enum('status', [
                'pending',         // Menunggu approval
                'approved',        // Disetujui
                'rejected',        // Ditolak
                'in_use',          // Sedang digunakan
                'returned'         // Sudah dikembalikan
            ])->default('pending');
            
            // Request Information
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            // Usage Information
            $table->timestamp('assigned_at')->nullable(); // Tanggal alat diberikan
            $table->timestamp('returned_at')->nullable(); // Tanggal alat dikembalikan
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null'); // User yang menggunakan alat
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('status');
            $table->index('requested_by');
            $table->index('approved_by');
            $table->index('assigned_to');
            $table->index('requested_at');
            $table->index(['episode_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_equipment');
    }
};














