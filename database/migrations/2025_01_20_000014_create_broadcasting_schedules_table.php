<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Broadcasting Schedules table - Broadcasting management
     */
    public function up(): void
    {
        Schema::create('broadcasting_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained('episodes')->onDelete('cascade');
            $table->enum('platform', [
                'youtube',          // YouTube
                'website',          // Website
                'tv',               // TV
                'instagram',        // Instagram
                'facebook',         // Facebook
                'tiktok'            // TikTok
            ]);
            $table->datetime('schedule_date'); // Tanggal jadwal
            $table->enum('status', [
                'pending',          // Menunggu
                'scheduled',        // Terjadwal
                'uploading',        // Sedang upload
                'uploaded',          // Sudah upload
                'published',         // Sudah publish
                'failed'             // Gagal
            ])->default('pending');
            
            // Content Information
            $table->string('title')->nullable(); // Judul content
            $table->text('description')->nullable(); // Deskripsi content
            $table->json('tags')->nullable(); // Tags untuk SEO
            $table->string('url')->nullable(); // URL content
            $table->string('thumbnail_path')->nullable(); // Path thumbnail
            
            // Upload Information
            $table->string('file_path')->nullable(); // Path file yang diupload
            $table->string('file_name')->nullable(); // Nama file
            $table->bigInteger('file_size')->nullable(); // Ukuran file
            $table->string('mime_type')->nullable(); // MIME type
            
            // Workflow Information
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('uploaded_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->text('upload_notes')->nullable(); // Catatan upload
            $table->text('error_message')->nullable(); // Pesan error jika gagal
            
            $table->timestamps();
            
            // Indexes
            $table->index('episode_id');
            $table->index('platform');
            $table->index('status');
            $table->index('schedule_date');
            $table->index('created_by');
            $table->index('uploaded_by');
            $table->index(['episode_id', 'platform']);
            $table->index(['platform', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('broadcasting_schedules');
    }
};














