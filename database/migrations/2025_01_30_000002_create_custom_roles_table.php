<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('custom_roles', function (Blueprint $table) {
            $table->id();
            $table->string('role_name')->unique();
            $table->text('description')->nullable();
            $table->enum('access_level', ['employee', 'manager', 'hr_readonly', 'hr_full'])->default('employee');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['is_active', 'access_level']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('custom_roles');
    }
};