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
        Schema::create('program_files', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_name');
            $table->string('file_path');
            $table->enum('file_type', ['image', 'video', 'document', 'audio', 'other']);
            $table->bigInteger('file_size');
            $table->string('mime_type');
            $table->enum('category', [
                'script', 'bts_video', 'bts_photo', 'thumbnail', 'production_video', 
                'production_photo', 'edited_video', 'final_video', 'audio', 'other'
            ]);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('uploaded_by');
            $table->string('fileable_type'); // Program, Episode, Schedule
            $table->unsignedBigInteger('fileable_id');
            $table->enum('status', ['uploading', 'uploaded', 'processing', 'ready', 'error', 'deleted'])->default('uploading');
            $table->json('metadata')->nullable(); // Additional file metadata
            $table->timestamps();

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['fileable_type', 'fileable_id']);
            $table->index('category');
            $table->index('file_type');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('program_files');
    }
};

