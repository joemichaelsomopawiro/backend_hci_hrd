<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template default alat per program (untuk quick pick saat tim setting pinjam).
     * items: [{"name":"Sony A7iv","qty":2},{"name":"Lensa 70-200","qty":1}]
     */
    public function up(): void
    {
        Schema::create('program_equipment_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->string('name')->nullable(); // optional label e.g. "Default Hope Music Outdoor"
            $table->json('items')->nullable(); // [{"name":"Sony A7iv","qty":2}, ...]
            $table->timestamps();

            $table->unique('program_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_equipment_templates');
    }
};
