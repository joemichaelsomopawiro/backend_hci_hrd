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
        Schema::create('equipment_loan_vocal_recording', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_loan_id')->constrained()->onDelete('cascade');
            $table->foreignId('sound_engineer_recording_id')
                  ->constrained('sound_engineer_recordings', 'id', 'elvr_ser_id_fk')
                  ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_loan_vocal_recording');
    }
};
