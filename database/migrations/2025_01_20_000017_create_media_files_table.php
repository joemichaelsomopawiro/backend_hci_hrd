<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Media Files table - File management system
     */
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('file_type', [
                'audio',            // File audio
                'video',            // File video
                'image',            // File gambar
                'document',         // File dokumen
                'thumbnail',        // Thumbnail
                'bts',              // BTS content
                'highlight',        // Highlight content
                'advertisement'     // Iklan
            ]);
            $table->string('file_path'); // Path file di storage
            $table->string('file_name'); // Nama file asli
            $table->string('file_extension'); // Ekstensi file
            $table->bigInteger('file_size'); // Ukuran file dalam bytes
            $table->string('mime_type'); // MIME type file
            $table->string('storage_disk')->default('public'); // Disk storage
            $table->enum('status', [
                'uploading',        // Sedang upload
                'uploaded',         // Upload selesai
                'processing',       // Sedang proses
                'processed',        // Proses selesai
                'failed'            // Upload gagal
            ])->default('uploading');
            
            // File Information
            $table->text('file_description')->nullable(); // Deskripsi file
            $table->json('metadata')->nullable(); // Metadata file
            $table->string('thumbnail_path')->nullable(); // Path thumbnail (untuk video)
            $table->integer('duration')->nullable(); // Durasi (untuk audio/video)
            $table->json('dimensions')->nullable(); // Dimensi (untuk gambar/video)
            
            // Workflow Information
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable(); // Pesan error jika gagal
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('file_type');
            $table->index('status');
            $table->index('uploaded_by');
            $table->index('uploaded_at');
            $table->index(['episode_id', 'file_type']);
            $table->index(['episode_id', 'status']);
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














