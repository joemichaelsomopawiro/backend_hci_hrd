<?php
/* Migration for Quality Score in Broadcasting Works */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $blueprint) {
            $blueprint->tinyInteger('quality_score')->nullable()->after('status');
            $blueprint->unsignedBigInteger('rated_by')->nullable()->after('quality_score');
            $blueprint->timestamp('rated_at')->nullable()->after('rated_by');

            $blueprint->foreign('rated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasting_works', function (Blueprint $blueprint) {
            $blueprint->dropForeign(['rated_by']);
            $blueprint->dropColumn(['quality_score', 'rated_by', 'rated_at']);
        });
    }
};
