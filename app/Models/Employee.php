<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_lengkap',
        'nik',
        'nip',
        'tanggal_lahir',
        'jenis_kelamin',
        'alamat',
        'status_pernikahan',
        'jabatan_saat_ini',
        'tanggal_mulai_kerja',
        'tingkat_pendidikan',
        'gaji',
        'nomor_bpjs',
        'npwp',
    ];

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class);
    }
}