<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Production Teams adalah tim produksi independen yang tidak terikat ke program tertentu.
     * Setiap tim dipimpin oleh 1 Producer dan minimal harus memiliki 1 orang untuk setiap 6 role bawahan.
     */
    public function up(): void
    {
        // Check if table already exists
        if (!Schema::hasTable('production_teams')) {
            Schema::create('production_teams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nama tim (contoh: "Tim Producer 1", "Tim Producer 2")
            $table->text('description')->nullable();
            $table->foreignId('producer_id')->constrained('users')->onDelete('cascade'); // Producer sebagai leader
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes(); // Soft delete untuk history
            
            $table->index(['producer_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_teams');
    }
};

