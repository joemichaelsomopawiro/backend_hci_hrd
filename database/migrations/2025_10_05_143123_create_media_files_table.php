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
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_url')->nullable(); // URL untuk cloud storage
            $table->string('mime_type');
            $table->bigInteger('file_size'); // Size dalam bytes
            $table->enum('file_type', ['thumbnail', 'bts_video', 'talent_photo', 'script', 'rundown', 'other'])->default('other');
            $table->foreignId('program_id')->constrained('programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->onDelete('cascade');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Metadata file (dimensions, duration, etc.)
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_public')->default(false); // Apakah file bisa diakses publik
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
