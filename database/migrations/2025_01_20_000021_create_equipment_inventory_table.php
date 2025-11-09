<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Equipment Inventory - Untuk Art & Set Properti
     */
    public function up(): void
    {
        Schema::create('equipment_inventory', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nama alat
            $table->string('category'); // Kategori alat (audio, video, lighting, props, etc.)
            $table->string('brand')->nullable(); // Merek
            $table->string('model')->nullable(); // Model
            $table->string('serial_number')->nullable(); // Serial number
            $table->text('description')->nullable(); // Deskripsi alat
            $table->enum('status', [
                'available',     // Tersedia
                'in_use',        // Sedang digunakan
                'maintenance',   // Sedang maintenance
                'broken',        // Rusak
                'retired'        // Pensiun
            ])->default('available');
            $table->string('location')->nullable(); // Lokasi penyimpanan
            $table->decimal('purchase_price', 15, 2)->nullable(); // Harga beli
            $table->date('purchase_date')->nullable(); // Tanggal beli
            $table->date('last_maintenance')->nullable(); // Terakhir maintenance
            $table->date('next_maintenance')->nullable(); // Jadwal maintenance berikutnya
            $table->text('maintenance_notes')->nullable(); // Catatan maintenance
            $table->json('specifications')->nullable(); // Spesifikasi teknis
            $table->string('image_path')->nullable(); // Foto alat
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index('category');
            $table->index('status');
            $table->index('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_inventory');
    }
};













