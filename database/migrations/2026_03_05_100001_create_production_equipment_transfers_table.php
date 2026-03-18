<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat transfer peminjaman: dari episode A ke episode B (tanpa return).
     */
    public function up(): void
    {
        if (Schema::hasTable('production_equipment_transfers')) {
            // Table already exists (created manually or by previous migration run), skip creation
            return;
        }

        Schema::create('production_equipment_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_equipment_id')->constrained('production_equipment')->onDelete('cascade');
            $table->unsignedBigInteger('from_episode_id')->nullable();
            $table->unsignedBigInteger('to_episode_id');
            $table->unsignedBigInteger('transferred_by')->nullable();
            $table->timestamp('transferred_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('production_equipment_id');
            $table->index(['from_episode_id', 'to_episode_id'], 'pe_transfers_episodes_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_equipment_transfers');
    }
};
