<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateEmployeesTable extends Migration
{
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 255);
            $table->string('nik', 16)->unique();
            $table->string('nip', 20)->nullable()->unique();
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->text('alamat');
            $table->enum('status_pernikahan', ['Belum Menikah', 'Menikah', 'Cerai']);
            $table->string('jabatan_saat_ini', 100);
            $table->date('tanggal_mulai_kerja');
            $table->string('tingkat_pendidikan', 50);
            $table->decimal('gaji_pokok', 15, 2);
            $table->decimal('tunjangan', 15, 2)->nullable()->default(0);
            $table->decimal('bonus', 15, 2)->nullable()->default(0);
            $table->string('nomor_bpjs_kesehatan', 20)->nullable();
            $table->string('nomor_bpjs_ketenagakerjaan', 20)->nullable();
            $table->string('npwp', 20)->nullable();
            $table->string('nomor_kontrak', 50)->nullable();
            $table->date('tanggal_kontrak_berakhir')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('employees');
    }

};
