<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('pr_program_concepts', 'read_by')) {
            Schema::table('pr_program_concepts', function (Blueprint $table) {
                $table->foreignId('read_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
                $table->timestamp('read_at')->nullable()->after('read_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('pr_program_concepts', function (Blueprint $table) {
            $table->dropForeign(['read_by']);
            $table->dropColumn(['read_by', 'read_at']);
        });
    }
};
