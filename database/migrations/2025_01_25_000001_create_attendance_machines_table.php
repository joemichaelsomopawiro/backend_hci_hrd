<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('attendance_machines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ip_address');
            $table->integer('port')->default(4370);
            $table->string('comm_key')->nullable();
            $table->string('soap_port')->default('80');
            $table->string('device_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->enum('status', ['active', 'inactive', 'maintenance'])->default('active');
            $table->timestamp('last_sync_at')->nullable();
            $table->json('settings')->nullable(); // Additional machine settings
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->unique('ip_address');
            $table->index(['status', 'last_sync_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_machines');
    }
};