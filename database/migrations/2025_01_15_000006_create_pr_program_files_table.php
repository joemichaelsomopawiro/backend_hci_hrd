<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * File Program - upload setelah editing
     */
    public function up(): void
    {
        Schema::create('pr_program_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('pr_programs')->onDelete('cascade');
            $table->foreignId('episode_id')->nullable()->constrained('pr_episodes')->onDelete('cascade');
            
            // File Information
            $table->string('file_name'); // Nama file
            $table->string('file_path'); // Path file di storage
            $table->string('file_type'); // video, image, document, dll
            $table->string('mime_type')->nullable(); // MIME type
            $table->bigInteger('file_size')->nullable(); // Ukuran file dalam bytes
            
            // File Category
            $table->enum('category', [
                'raw_footage',        // Raw footage dari produksi
                'edited_video',       // Video yang sudah diedit
                'thumbnail',         // Thumbnail
                'script',            // Script
                'rundown',           // Rundown
                'other'              // Lainnya
            ])->default('other');
            
            // Upload Information
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->text('description')->nullable(); // Deskripsi file
            
            // Status
            $table->enum('status', [
                'active',            // Aktif
                'archived',          // Diarsipkan
                'deleted'            // Dihapus (soft delete)
            ])->default('active');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['program_id', 'category']);
            $table->index(['episode_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pr_program_files');
    }
};
