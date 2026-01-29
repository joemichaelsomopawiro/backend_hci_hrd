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
        Schema::create('pr_talents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title')->nullable(); // Gelar / Background Pendidikan
            $table->string('birth_place_date')->nullable(); // Tempat Tgl Lahir
            $table->text('expertise')->nullable(); // Keahlian
            $table->enum('type', ['host', 'guest', 'other'])->default('other');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_talents');
    }
};
