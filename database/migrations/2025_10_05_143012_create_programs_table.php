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
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled'])->default('draft');
            $table->enum('type', ['weekly', 'monthly', 'quarterly', 'special'])->default('weekly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->time('air_time')->nullable(); // Waktu tayang
            $table->integer('duration_minutes')->default(30); // Durasi dalam menit
            $table->string('broadcast_channel')->nullable(); // Channel penyiaran
            $table->text('rundown')->nullable(); // Rundown program
            $table->json('requirements')->nullable(); // Kebutuhan khusus
            $table->foreignId('manager_id')->constrained('users')->onDelete('cascade'); // Manager Program
            $table->foreignId('producer_id')->nullable()->constrained('users')->onDelete('set null'); // Producer
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
