<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add social_media_proof JSON field to promotion_works table
     * Untuk menyimpan link screenshot bukti sharing ke social media
     */
    public function up(): void
    {
        Schema::table('promotion_works', function (Blueprint $table) {
            // JSON field untuk bukti sharing social media
            // Format: {
            //   "facebook_post": "https://drive.google.com/proof-fb.png",
            //   "instagram_story": "https://drive.google.com/proof-ig.png",
            //   "facebook_reels": "https://drive.google.com/proof-fb-reels.png",
            //   "whatsapp_group": "https://drive.google.com/proof-wa.png"
            // }
            $table->json('social_media_proof')->nullable()->after('social_media_links');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotion_works', function (Blueprint $table) {
            $table->dropColumn('social_media_proof');
        });
    }
};
