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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('episode_number');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->date('air_date'); // Tanggal tayang
            $table->date('production_date')->nullable(); // Tanggal produksi
            $table->enum('status', ['draft', 'in_production', 'post_production', 'ready_to_air', 'aired', 'cancelled'])->default('draft');
            $table->text('script')->nullable(); // Script episode
            $table->json('talent_data')->nullable(); // Data narasumber dan host
            $table->string('location')->nullable(); // Lokasi syuting
            $table->text('notes')->nullable(); // Catatan khusus
            $table->json('production_notes')->nullable(); // Catatan produksi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
