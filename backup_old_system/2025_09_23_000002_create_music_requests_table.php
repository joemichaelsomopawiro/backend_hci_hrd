<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('music_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('song_id'); // ID lagu yang dipilih
            $table->unsignedBigInteger('music_arranger_id'); // ID Music Arranger yang mengajukan
            $table->unsignedBigInteger('proposed_singer_id')->nullable(); // ID penyanyi yang diusulkan (opsional)
            $table->unsignedBigInteger('producer_id')->nullable(); // ID Producer yang menangani
            $table->unsignedBigInteger('approved_singer_id')->nullable(); // ID penyanyi yang disetujui producer
            
            // Status workflow
            $table->enum('status', [
                'submitted', // Diajukan oleh Music Arranger
                'reviewed', // Sedang direview oleh Producer
                'approved', // Disetujui oleh Producer
                'rejected', // Ditolak oleh Producer
                'in_progress', // Sedang dikerjakan
                'completed' // Selesai
            ])->default('submitted');
            
            // Detail request
            $table->text('arrangement_notes')->nullable(); // Catatan dari Music Arranger
            $table->text('producer_notes')->nullable(); // Catatan dari Producer
            $table->date('requested_date')->nullable(); // Tanggal diminta selesai
            $table->date('approved_date')->nullable(); // Tanggal disetujui
            $table->date('completed_date')->nullable(); // Tanggal selesai
            
            // Workflow tracking
            $table->timestamp('submitted_at')->nullable(); // Kapan diajukan
            $table->timestamp('reviewed_at')->nullable(); // Kapan direview
            $table->timestamp('approved_at')->nullable(); // Kapan disetujui
            $table->timestamp('rejected_at')->nullable(); // Kapan ditolak
            $table->timestamp('completed_at')->nullable(); // Kapan selesai
            
            $table->timestamps();

            // Foreign keys
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
            $table->foreign('music_arranger_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('proposed_singer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('producer_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('approved_singer_id')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('status');
            $table->index('music_arranger_id');
            $table->index('producer_id');
            $table->index(['status', 'music_arranger_id']);
            $table->index(['status', 'producer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('music_requests');
    }
};


