<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    /**
     * Menampilkan daftar semua pegawai beserta dokumennya.
     */
    public function index()
    {
        $employees = Employee::with('documents')->get();
        return response()->json($employees);
    }

    /**
     * Menyimpan data pegawai baru dan dokumennya.
     */
    public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'nama_lengkap' => 'required|string|max:255',
            'nik' => 'required|string|max:16|unique:employees,nik',
            'nip' => 'nullable|string|max:20|unique:employees,nip',
            'tanggal_lahir' => 'required|date',
            'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
            'alamat' => 'required|string',
            'status_pernikahan' => 'required|in:Belum Menikah,Menikah,Cerai',
            'jabatan_saat_ini' => 'required|string|max:100',
            'tanggal_mulai_kerja' => 'required|date',
            'tingkat_pendidikan' => 'required|string|max:50',
            'gaji' => 'required|numeric|min:0',
            'nomor_bpjs' => 'nullable|string|max:20',
            'npwp' => 'nullable|string|max:20',
            'documents.*' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
        ]);

        $employee = Employee::create($validated);

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('documents', 'public');
                EmployeeDocument::create([
                    'employee_id' => $employee->id,
                    'document_type' => $file->getClientOriginalName(),
                    'file_path' => $path,
                ]);
            }
        }

        return response()->json([
            'message' => 'Data pegawai berhasil disimpan',
            'employee' => $employee->load('documents'),
        ], 201);
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'message' => 'Validasi gagal',
            'errors' => $e->errors(),
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage(),
        ], 500);
    }
}
}