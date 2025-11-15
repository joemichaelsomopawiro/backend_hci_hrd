<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Tambah field untuk bukti social media posting (screenshot/proof file)
     */
    public function up(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table) {
            $table->string('proof_file_path')->nullable()->after('post_url'); // Path file bukti (screenshot)
            $table->string('proof_file_name')->nullable()->after('proof_file_path'); // Nama file bukti
            $table->enum('proof_type', [
                'screenshot',      // Screenshot postingan
                'link_proof',      // Bukti berupa link
                'file_proof'       // Bukti berupa file lainnya
            ])->nullable()->after('proof_file_name');
            $table->text('proof_notes')->nullable()->after('proof_type'); // Catatan bukti
            $table->timestamp('proof_submitted_at')->nullable()->after('proof_notes'); // Waktu submit bukti
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_media_posts', function (Blueprint $table) {
            $table->dropColumn([
                'proof_file_path',
                'proof_file_name',
                'proof_type',
                'proof_notes',
                'proof_submitted_at'
            ]);
        });
    }
};


















