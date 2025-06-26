<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('attendance_machine_id')->nullable()->constrained()->onDelete('set null');
            $table->string('machine_log_id')->nullable(); // ID dari mesin untuk referensi
            $table->enum('source', ['manual', 'machine', 'web'])->default('manual');
            $table->timestamp('machine_timestamp')->nullable(); // Timestamp asli dari mesin
            
            $table->index(['attendance_machine_id', 'machine_log_id']);
            $table->index(['source', 'date']);
        });
    }

    public function down()
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['attendance_machine_id']);
            $table->dropColumn([
                'attendance_machine_id', 
                'machine_log_id', 
                'source', 
                'machine_timestamp'
            ]);
        });
    }
};