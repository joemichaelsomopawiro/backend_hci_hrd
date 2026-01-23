<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Singers table - Master data untuk penyanyi
     * Ini adalah data master biasa, BUKAN User dengan role
     */
    public function up(): void
    {
        Schema::create('singers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Nama penyanyi
            $table->string('stage_name')->nullable(); // Nama panggung (optional)
            $table->text('bio')->nullable(); // Biografi singkat
            $table->string('photo_url')->nullable(); // Link foto penyanyi
            $table->string('genre')->nullable(); // Genre musik
            $table->text('social_media')->nullable(); // JSON untuk social media links
            $table->boolean('is_active')->default(true); // Status aktif
            $table->timestamps();
            
            // Indexes
            $table->index('name');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('singers');
    }
};
