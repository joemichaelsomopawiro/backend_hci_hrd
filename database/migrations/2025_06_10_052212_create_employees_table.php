<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('nik')->unique();
            $table->string('nip')->unique()->nullable();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->text('alamat');
            $table->enum('status_pernikahan', ['Belum Menikah', 'Menikah', 'Cerai']);
            $table->string('jabatan_saat_ini');
            $table->date('tanggal_mulai_kerja');
            $table->string('tingkat_pendidikan');
            $table->decimal('gaji', 15, 2);
            $table->string('nomor_bpjs')->nullable();
            $table->string('npwp')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};