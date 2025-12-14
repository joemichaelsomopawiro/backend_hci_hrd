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
        // Check if table already exists
        if (!Schema::hasTable('produksi_works')) {
            Schema::create('produksi_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
                $table->foreignId('creative_work_id')->nullable()->constrained('creative_works')->onDelete('set null');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Produksi
                $table->enum('status', [
                    'pending',      // Menunggu diterima
                    'in_progress',   // Sedang dikerjakan
                    'completed'     // Selesai
                ])->default('pending');
                $table->json('equipment_list')->nullable(); // List alat yang dibutuhkan
                $table->json('equipment_requests')->nullable(); // ID equipment requests yang sudah dibuat
                $table->json('needs_list')->nullable(); // List kebutuhan lainnya
                $table->json('needs_requests')->nullable(); // ID needs requests yang sudah dibuat
                $table->text('notes')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                
                // Indexes
                $table->index('episode_id');
                $table->index('creative_work_id');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produksi_works');
    }
};
