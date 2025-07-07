<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmploymentHistory;
use App\Models\PromotionHistory;
use App\Models\Training;
use App\Models\Benefit;
use App\Models\LeaveQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// --- TAMBAHAN ---
use App\Services\RoleHierarchyService;
use Illuminate\Validation\Rule;
// --- AKHIR TAMBAHAN ---

class EmployeeController extends Controller
{
    public function __construct()
    {
        // Middleware removed to disable authentication
    }

    public function index()
    {
        try {
            $employees = Employee::with([
                'user', // <-- tambahkan ini!
                'documents',
                'employmentHistories',
                'promotionHistories',
                'trainings',
                'benefits'
            ])->get();
            return response()->json($employees);
        } catch (\Exception $e) {
            try {
                Log::error('Error in index: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json(['error' => 'Failed to fetch employees: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $employee = Employee::with([
                'user',
                'documents',
                'employmentHistories',
                'promotionHistories',
                'trainings',
                'benefits'
            ])->findOrFail($id);
            return response()->json($employee);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            try {
                Log::error("Employee not found: ID {$id}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Karyawan tidak ditemukan',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            try {
                Log::error('Error in show: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            // --- PERUBAHAN VALIDASI DIMULAI DARI SINI ---
            $allValidRoles = array_merge(
                RoleHierarchyService::getManagerRoles(),
                RoleHierarchyService::getEmployeeRoles()
            );

            $validated = $request->validate([
                'nama_lengkap' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:employees,nama_lengkap',
                    function ($attribute, $value, $fail) {
                        // Cek apakah nama sudah ada di tabel users
                        $existingUser = \App\Models\User::where('name', $value)->first();
                        if ($existingUser) {
                            $fail('Nama tersebut sudah terdaftar sebagai user. Silakan gunakan nama yang berbeda.');
                        }
                    },
                ],
                'nik' => 'required|string|max:16|unique:employees,nik',
                'nip' => 'nullable|string|max:20|unique:employees,nip',
                'tanggal_lahir' => 'required|date',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'alamat' => 'required|string',
                'status_pernikahan' => 'required|in:Belum Menikah,Menikah,Cerai',
                'jabatan_saat_ini' => ['required', 'string', Rule::in($allValidRoles)], // ATURAN YANG DIPERBARUI
                'tanggal_mulai_kerja' => 'required|date',
                'tingkat_pendidikan' => 'required|string|max:50',
                'gaji_pokok' => 'required|numeric|min:0',
                'tunjangan' => 'nullable|numeric|min:0',
                'bonus' => 'nullable|numeric|min:0',
                'nomor_bpjs_kesehatan' => 'nullable|string|max:20',
                'nomor_bpjs_ketenagakerjaan' => 'nullable|string|max:20',
                'npwp' => 'nullable|string|max:20',
                'nomor_kontrak' => 'nullable|string|max:50',
                'tanggal_kontrak_berakhir' => 'nullable|date',
                'documents.*' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
                'employment_histories.*.company_name' => 'nullable|string|max:255',
                'employment_histories.*.position' => 'nullable|string|max:100',
                'employment_histories.*.start_date' => 'nullable|date',
                'employment_histories.*.end_date' => 'nullable|date',
                'promotion_histories.*.position' => 'nullable|string|max:100',
                'promotion_histories.*.promotion_date' => 'nullable|date',
                'trainings.*.training_name' => 'nullable|string|max:255',
                'trainings.*.institution' => 'nullable|string|max:255',
                'trainings.*.completion_date' => 'nullable|date',
                'trainings.*.certificate_number' => 'nullable|string|max:100',
                'benefits.*.benefit_type' => 'nullable|string|max:100',
                'benefits.*.amount' => 'nullable|numeric|min:0',
                'benefits.*.start_date' => 'nullable|date',
            ]);
            // --- AKHIR PERUBAHAN VALIDASI ---

            $employee = Employee::create([
                'nama_lengkap' => $validated['nama_lengkap'],
                'nik' => $validated['nik'],
                'nip' => $validated['nip'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'alamat' => $validated['alamat'],
                'status_pernikahan' => $validated['status_pernikahan'],
                'jabatan_saat_ini' => $validated['jabatan_saat_ini'],
                'tanggal_mulai_kerja' => $validated['tanggal_mulai_kerja'],
                'tingkat_pendidikan' => $validated['tingkat_pendidikan'],
                'gaji_pokok' => $validated['gaji_pokok'],
                'tunjangan' => $validated['tunjangan'] ?? 0,
                'bonus' => $validated['bonus'] ?? 0,
                'nomor_bpjs_kesehatan' => $validated['nomor_bpjs_kesehatan'] ?? null,
                'nomor_bpjs_ketenagakerjaan' => $validated['nomor_bpjs_ketenagakerjaan'] ?? null,
                'npwp' => $validated['npwp'] ?? null,
                'nomor_kontrak' => $validated['nomor_kontrak'] ?? null,
                'tanggal_kontrak_berakhir' => $validated['tanggal_kontrak_berakhir'] ?? null,
            ]);

            // 🔥 LOGIKA BARU: Otomatis cari dan hubungkan dengan user yang sudah ada + sinkronisasi role
            $matchingUser = \App\Models\User::where('name', $validated['nama_lengkap'])
                                            ->whereNull('employee_id')
                                            ->first();
            
            $userLinked = false;
            if ($matchingUser) {
                $matchingUser->update([
                    'employee_id' => $employee->id,
                    'role' => $validated['jabatan_saat_ini'] // Sinkronisasi role
                ]);
                $userLinked = true;
                
                try {
                    Log::info("User '{$matchingUser->name}' (ID: {$matchingUser->id}) berhasil dihubungkan dengan employee '{$employee->nama_lengkap}' (ID: {$employee->id}) dan role disinkronkan ke '{$validated['jabatan_saat_ini']}'.");
                } catch (\Exception $logException) {
                    // Silently ignore logging failure
                }
            }

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

            if (isset($validated['employment_histories'])) {
                foreach ($validated['employment_histories'] as $history) {
                    if (!empty($history['company_name'])) {
                        EmploymentHistory::create([
                            'employee_id' => $employee->id,
                            'company_name' => $history['company_name'],
                            'position' => $history['position'],
                            'start_date' => $history['start_date'],
                            'end_date' => $history['end_date'],
                        ]);
                    }
                }
            }

            if (isset($validated['promotion_histories'])) {
                foreach ($validated['promotion_histories'] as $promotion) {
                    if (!empty($promotion['position'])) {
                        PromotionHistory::create([
                            'employee_id' => $employee->id,
                            'position' => $promotion['position'],
                            'promotion_date' => $promotion['promotion_date'],
                        ]);
                    }
                }
            }

            if (isset($validated['trainings'])) {
                foreach ($validated['trainings'] as $history) {
                    if (!empty($history['training_name'])) {
                        Training::create([
                            'employee_id' => $employee->id,
                            'training_name' => $history['training_name'],
                            'institution' => $history['institution'],
                            'completion_date' => $history['completion_date'],
                            'certificate_number' => $history['certificate_number'],
                        ]);
                    }
                }
            }

            if (isset($validated['benefits'])) {
                foreach ($validated['benefits'] as $benefit) {
                    if (!empty($benefit['benefit_type'])) {
                        Benefit::create([
                            'employee_id' => $employee->id,
                            'benefit_type' => $benefit['benefit_type'],
                            'amount' => $benefit['amount'],
                            'start_date' => $benefit['start_date'],
                        ]);
                    }
                }
            }

            // 🔥 LOGIKA BARU: Otomatis buat default leave quota untuk employee baru
            // Di dalam method store, setelah employee berhasil dibuat, tambahkan:
            $currentYear = date('Y');
            \App\Models\LeaveQuota::create([
                'employee_id' => $employee->id,
                'year' => $currentYear,
                'annual_leave_quota' => 12,
                'annual_leave_used' => 0,
                'sick_leave_quota' => 12,
                'sick_leave_used' => 0,
                'emergency_leave_quota' => 2,
                'emergency_leave_used' => 0,
                'maternity_leave_quota' => $validated['jenis_kelamin'] === 'Perempuan' ? 90 : 0,
                'maternity_leave_used' => 0,
                'paternity_leave_quota' => $validated['jenis_kelamin'] === 'Laki-laki' ? 2 : 0,
                'paternity_leave_used' => 0,
                'marriage_leave_quota' => 2,
                'marriage_leave_used' => 0,
                'bereavement_leave_quota' => 2,
                'bereavement_leave_used' => 0,
            ]);

            try {
                Log::info("Default leave quota berhasil dibuat untuk employee '{$employee->nama_lengkap}' (ID: {$employee->id}) tahun {$currentYear}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }

            DB::commit();

            // Response dengan informasi tambahan tentang user linking dan leave quota
            $responseData = [
                'message' => 'Data pegawai berhasil disimpan',
                'employee' => $employee->load([
                    'documents',
                    'employmentHistories',
                    'promotionHistories',
                    'trainings',
                    'benefits',
                    'user' // Load relationship user jika ada
                ]),
                'user_linked' => $userLinked,
                'leave_quota_created' => true,
                'default_leave_quota_year' => $currentYear
            ];
            
            if ($userLinked) {
                $responseData['linked_user'] = $matchingUser;
                $responseData['message_detail'] = "Data karyawan berhasil dibuat, otomatis terhubung dengan akun user '{$matchingUser->name}', dan default jatah cuti tahun {$currentYear} telah dibuat";
            } else {
                $responseData['message_detail'] = "Data karyawan berhasil dibuat dan default jatah cuti tahun {$currentYear} telah dibuat. Belum ada akun user yang cocok";
            }

            return response()->json($responseData, 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            try {
                Log::error('Validation error in store: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in store: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan saat menyimpan data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($id);
            $oldNamaLengkap = $employee->nama_lengkap; // Simpan nama lama untuk perbandingan

            // --- PERUBAHAN VALIDASI DIMULAI DARI SINI ---
            $allValidRoles = array_merge(
                RoleHierarchyService::getManagerRoles(),
                RoleHierarchyService::getEmployeeRoles()
            );

            $validated = $request->validate([
                'nama_lengkap' => 'required|string|max:255',
                'nik' => 'required|string|max:16|unique:employees,nik,' . $employee->id,
                'nip' => 'nullable|string|max:20|unique:employees,nip,' . $employee->id,
                'tanggal_lahir' => 'required|date',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'alamat' => 'required|string',
                'status_pernikahan' => 'required|in:Belum Menikah,Menikah,Cerai',
                'jabatan_saat_ini' => ['required', 'string', Rule::in($allValidRoles)], // ATURAN YANG DIPERBARUI
                'tanggal_mulai_kerja' => 'required|date',
                'tingkat_pendidikan' => 'required|string|max:50',
                'gaji_pokok' => 'required|numeric|min:0',
                'tunjangan' => 'nullable|numeric|min:0',
                'bonus' => 'nullable|numeric|min:0',
                'nomor_bpjs_kesehatan' => 'nullable|string|max:20',
                'nomor_bpjs_ketenagakerjaan' => 'nullable|string|max:20',
                'npwp' => 'nullable|string|max:20',
                'nomor_kontrak' => 'nullable|string|max:50',
                'tanggal_kontrak_berakhir' => 'nullable|date',
                'documents.*' => 'nullable|file|mimes:pdf,jpg,png|max:2048',
                'employment_histories.*.company_name' => 'nullable|string|max:255',
                'employment_histories.*.position' => 'nullable|string|max:100',
                'employment_histories.*.start_date' => 'nullable|date',
                'employment_histories.*.end_date' => 'nullable|date',
                'promotion_histories.*.position' => 'nullable|string|max:100',
                'promotion_histories.*.promotion_date' => 'nullable|date',
                'trainings.*.training_name' => 'nullable|string|max:255',
                'trainings.*.institution' => 'nullable|string|max:255',
                'trainings.*.completion_date' => 'nullable|date',
                'trainings.*.certificate_number' => 'nullable|string|max:100',
                'benefits.*.benefit_type' => 'nullable|string|max:100',
                'benefits.*.amount' => 'nullable|numeric|min:0',
                'benefits.*.start_date' => 'nullable|date',
            ]);
            // --- AKHIR PERUBAHAN VALIDASI ---

            $employee->update([
                'nama_lengkap' => $validated['nama_lengkap'],
                'nik' => $validated['nik'],
                'nip' => $validated['nip'] ?? null,
                'tanggal_lahir' => $validated['tanggal_lahir'],
                'jenis_kelamin' => $validated['jenis_kelamin'],
                'alamat' => $validated['alamat'],
                'status_pernikahan' => $validated['status_pernikahan'],
                'jabatan_saat_ini' => $validated['jabatan_saat_ini'],
                'tanggal_mulai_kerja' => $validated['tanggal_mulai_kerja'],
                'tingkat_pendidikan' => $validated['tingkat_pendidikan'],
                'gaji_pokok' => $validated['gaji_pokok'],
                'tunjangan' => $validated['tunjangan'] ?? 0,
                'bonus' => $validated['bonus'] ?? 0,
                'nomor_bpjs_kesehatan' => $validated['nomor_bpjs_kesehatan'] ?? null,
                'nomor_bpjs_ketenagakerjaan' => $validated['nomor_bpjs_ketenagakerjaan'] ?? null,
                'npwp' => $validated['npwp'] ?? null,
                'nomor_kontrak' => $validated['nomor_kontrak'] ?? null,
                'tanggal_kontrak_berakhir' => $validated['tanggal_kontrak_berakhir'] ?? null,
            ]);

            if ($request->hasFile('documents')) {
                foreach ($employee->documents as $doc) {
                    if (Storage::disk('public')->exists($doc->file_path)) {
                        Storage::disk('public')->delete($doc->file_path);
                    }
                    $doc->delete();
                }
                foreach ($request->file('documents') as $file) {
                    $path = $file->store('documents', 'public');
                    EmployeeDocument::create([
                        'employee_id' => $employee->id,
                        'document_type' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);
                }
            }

            if (isset($validated['employment_histories'])) {
                $employee->employmentHistories()->delete();
                foreach ($validated['employment_histories'] as $history) {
                    if (!empty($history['company_name'])) {
                        EmploymentHistory::create([
                            'employee_id' => $employee->id,
                            'company_name' => $history['company_name'],
                            'position' => $history['position'],
                            'start_date' => $history['start_date'],
                            'end_date' => $history['end_date'],
                        ]);
                    }
                }
            }

            if (isset($validated['promotion_histories'])) {
                $employee->promotionHistories()->delete();
                foreach ($validated['promotion_histories'] as $promotion) {
                    if (!empty($promotion['position'])) {
                        PromotionHistory::create([
                            'employee_id' => $employee->id,
                            'position' => $promotion['position'],
                            'promotion_date' => $promotion['promotion_date'],
                        ]);
                    }
                }
            }

            if (isset($validated['trainings'])) {
                $employee->trainings()->delete();
                foreach ($validated['trainings'] as $training) {
                    if (!empty($training['training_name'])) {
                        Training::create([
                            'employee_id' => $employee->id,
                            'training_name' => $training['training_name'],
                            'institution' => $training['institution'],
                            'completion_date' => $training['completion_date'],
                            'certificate_number' => $training['certificate_number'],
                        ]);
                    }
                }
            }

            if (isset($validated['benefits'])) {
                $employee->benefits()->delete();
                foreach ($validated['benefits'] as $benefit) {
                    if (!empty($benefit['benefit_type'])) {
                        Benefit::create([
                            'employee_id' => $employee->id,
                            'benefit_type' => $benefit['benefit_type'],
                            'amount' => $benefit['amount'],
                            'start_date' => $benefit['start_date'],
                        ]);
                    }
                }
            }

            // 🔥 LOGIKA BARU: Sinkronisasi nama ketika nama_lengkap berubah
            // 🔥 LOGIKA SINKRONISASI NAMA - DIPERBAIKI
            $userLinked = false;
            $linkedUser = null;
            
            // Selalu periksa dan sinkronkan nama setiap kali update
            if ($oldNamaLengkap !== $validated['nama_lengkap']) {
                // Nama berubah - perlu sinkronisasi
                
                // Jika employee sudah punya user, update nama dan role user-nya
                if ($employee->user) {
                    $employee->user->update([
                        'name' => $validated['nama_lengkap'],
                        'role' => $validated['jabatan_saat_ini'] // Sinkronisasi role
                    ]);
                    $userLinked = true;
                    $linkedUser = $employee->user;
                    
                    try {
                        Log::info("Updated user name from '{$oldNamaLengkap}' to '{$validated['nama_lengkap']}' for employee ID: {$employee->id}");
                    } catch (\Exception $logException) {
                        // Silently ignore logging failure
                    }
                } else {
                    // Employee belum punya user, cari user yang namanya sama dengan nama baru
                    $matchingUser = \App\Models\User::where('name', $validated['nama_lengkap'])
                                                    ->whereNull('employee_id')
                                                    ->first();
                    
                    if ($matchingUser) {
                        $matchingUser->update([
                            'employee_id' => $employee->id,
                            'role' => $validated['jabatan_saat_ini'] // Sinkronisasi role
                        ]);
                        $userLinked = true;
                        $linkedUser = $matchingUser;
                        
                        try {
                            Log::info("User '{$matchingUser->name}' (ID: {$matchingUser->id}) berhasil dihubungkan dengan employee '{$employee->nama_lengkap}' (ID: {$employee->id}) saat update nama");
                        } catch (\Exception $logException) {
                            // Silently ignore logging failure
                        }
                    }
                }
                
                // TAMBAHAN: Putuskan hubungan user lama jika ada yang namanya sama dengan nama lama
                $oldUser = \App\Models\User::where('name', $oldNamaLengkap)
                                            ->where('employee_id', $employee->id)
                                            ->first();
                if ($oldUser) {
                    $oldUser->update(['employee_id' => null]);
                    try {
                        Log::info("Disconnected old user '{$oldUser->name}' (ID: {$oldUser->id}) from employee ID: {$employee->id}");
                    } catch (\Exception $logException) {
                        // Silently ignore logging failure
                    }
                }
            } else {
                // Nama tidak berubah, tapi tetap periksa apakah perlu sinkronisasi role
                if ($employee->user) {
                    // Employee sudah punya user, update role-nya
                    $employee->user->update(['role' => $validated['jabatan_saat_ini']]);
                    $userLinked = true;
                    $linkedUser = $employee->user;
                    
                    try {
                        Log::info("Role user '{$employee->user->name}' (ID: {$employee->user->id}) disinkronkan ke '{$validated['jabatan_saat_ini']}' untuk employee ID: {$employee->id}");
                    } catch (\Exception $logException) {
                        // Silently ignore logging failure
                    }
                } else {
                    // Employee belum punya user, cari user yang namanya sama
                    $matchingUser = \App\Models\User::where('name', $validated['nama_lengkap'])
                                                    ->whereNull('employee_id')
                                                    ->first();
                    
                    if ($matchingUser) {
                        $matchingUser->update([
                            'employee_id' => $employee->id,
                            'role' => $validated['jabatan_saat_ini'] // Sinkronisasi role
                        ]);
                        $userLinked = true;
                        $linkedUser = $matchingUser;
                        
                        try {
                            Log::info("User '{$matchingUser->name}' (ID: {$matchingUser->id}) berhasil dihubungkan dengan employee '{$employee->nama_lengkap}' (ID: {$employee->id}) saat update tanpa perubahan nama");
                        } catch (\Exception $logException) {
                            // Silently ignore logging failure
                        }
                    }
                }
            }

            DB::commit();

            // Response dengan informasi tambahan tentang user linking
            $responseData = [
                'message' => 'Data pegawai berhasil diperbarui',
                'employee' => $employee->load([
                    'documents',
                    'employmentHistories', 
                    'promotionHistories',
                    'trainings',
                    'benefits',
                    'user' // Load relationship user jika ada
                ]),
                'user_linked' => $userLinked
            ];
            
            if ($userLinked && $linkedUser) {
                $responseData['linked_user'] = $linkedUser;
                if ($oldNamaLengkap !== $validated['nama_lengkap']) {
                    $responseData['message_detail'] = "Data karyawan berhasil diperbarui dan nama user telah disinkronkan";
                }
            }

            return response()->json($responseData);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            try {
                Log::error('Validation error in update: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in update: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            try {
                Log::info("Attempting to delete employee with ID: {$id}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            $employee = Employee::findOrFail($id);

            foreach ($employee->documents as $doc) {
                try {
                    Log::info("Deleting document: {$doc->file_path}");
                } catch (\Exception $logException) {
                    // Silently ignore logging failure
                }
                if (Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                } else {
                    try {
                        Log::warning("Document file not found: {$doc->file_path}");
                    } catch (\Exception $logException) {
                        // Silently ignore logging failure
                    }
                }
                $doc->delete();
            }

            try {
                Log::info("Deleting related records for employee ID: {$id}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            $employee->employmentHistories()->delete();
            $employee->promotionHistories()->delete();
            $employee->trainings()->delete();
            $employee->benefits()->delete();

            try {
                Log::info("Deleting employee record with ID: {$id}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            $employee->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data pegawai dan semua data terkait berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            try {
                Log::error("Employee not found: ID {$id}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Karyawan tidak ditemukan',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            try {
                Log::error("Database error in destroy: {$e->getMessage()}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Gagal menghapus karyawan karena masalah database',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error("General error in destroy: {$e->getMessage()}");
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteDocument($employeeId, $documentId)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $document = EmployeeDocument::where('employee_id', $employeeId)
                ->findOrFail($documentId);

            if (Storage::disk('public')->exists($document->file_path)) {
                Storage::disk('public')->delete($document->file_path);
            } else {
                try {
                    Log::warning("Document file not found: {$document->file_path}");
                } catch (\Exception $logException) {
                    // Silently ignore logging failure
                }
            }
            $document->delete();

            DB::commit();

            return response()->json([
                'message' => 'Dokumen berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in deleteDocument: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteEmploymentHistory($employeeId, $historyId)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $history = EmploymentHistory::where('employee_id', $employeeId)
                ->findOrFail($historyId);

            $history->delete();

            DB::commit();

            return response()->json([
                'message' => 'Riwayat pekerjaan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in deleteEmploymentHistory: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deletePromotionHistory($employeeId, $promotionId)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $promotion = PromotionHistory::where('employee_id', $employeeId)
                ->findOrFail($promotionId);

            $promotion->delete();

            DB::commit();

            return response()->json([
                'message' => 'Riwayat promosi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in deletePromotionHistory: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTraining($employeeId, $trainingId)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $training = Training::where('employee_id', $employeeId)
                ->findOrFail($trainingId);

            $training->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data pelatihan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in deleteTraining: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteBenefit($employeeId, $benefitId)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($employeeId);
            $benefit = Benefit::where('employee_id', $employeeId)
                ->findOrFail($benefitId);

            $benefit->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data benefit berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            try {
                Log::error('Error in deleteBenefit: ' . $e->getMessage());
            } catch (\Exception $logException) {
                // Silently ignore logging failure
            }
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}