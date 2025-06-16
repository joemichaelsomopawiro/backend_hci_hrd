<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('otp_code');
            $table->enum('type', ['register', 'forgot_password']);
            $table->timestamp('expires_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            
            $table->index(['phone', 'type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('otps');
    }
};