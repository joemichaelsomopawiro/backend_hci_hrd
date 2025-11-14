<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if table already exists
        if (!Schema::hasTable('attendance_machines')) {
            Schema::create('attendance_machines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('Nama mesin absensi');
            $table->string('ip_address', 15)->unique()->comment('IP Address mesin');
            $table->integer('port')->default(80)->comment('Port untuk SOAP Web Service');
            $table->string('comm_key', 10)->default('0')->comment('Communication Key');
            $table->string('device_id', 50)->nullable()->comment('Device ID mesin');
            $table->string('serial_number', 50)->nullable()->comment('Serial number mesin');
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamp('last_sync_at')->nullable()->comment('Waktu sync terakhir');
            $table->json('settings')->nullable()->comment('Pengaturan tambahan mesin');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'last_sync_at']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('attendance_machines');
    }
}; 