<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // Judul lagu
            $table->string('artist')->nullable(); // Artis asli lagu
            $table->string('genre')->nullable(); // Genre lagu
            $table->text('lyrics')->nullable(); // Lirik lagu
            $table->string('duration')->nullable(); // Durasi lagu (format mm:ss)
            $table->string('key_signature')->nullable(); // Kunci lagu (C, D, E, etc.)
            $table->integer('bpm')->nullable(); // Beats per minute
            $table->text('notes')->nullable(); // Catatan tambahan
            $table->enum('status', ['available', 'in_use', 'archived'])->default('available');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            
            // Indexes
            $table->index('title');
            $table->index('artist');
            $table->index('genre');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('songs');
    }
};


