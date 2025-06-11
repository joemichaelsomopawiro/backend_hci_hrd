<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmploymentHistory;
use App\Models\PromotionHistory;
use App\Models\Training;
use App\Models\Benefit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
                'documents',
                'employmentHistories',
                'promotionHistories',
                'trainings',
                'benefits'
            ])->get();
            return response()->json($employees);
        } catch (\Exception $e) {
            \Log::error('Error in index: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch employees: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

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

            $employee = Employee::create([
                'nama_lengkap' => $validated['nama_lengkap'],
                'nik' => $validated['nik'],
                'nip' => $validated['nip'],
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
                'nomor_bpjs_kesehatan' => $validated['nomor_bpjs_kesehatan'],
                'nomor_bpjs_ketenagakerjaan' => $validated['nomor_bpjs_ketenagakerjaan'],
                'npwp' => $validated['npwp'],
                'nomor_kontrak' => $validated['nomor_kontrak'],
                'tanggal_kontrak_berakhir' => $validated['tanggal_kontrak_berakhir'],
            ]);

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

            DB::commit();

            return response()->json([
                'message' => 'Data pegawai berhasil disimpan',
                'employee' => $employee->load([
                    'documents',
                    'employmentHistories',
                    'promotionHistories',
                    'trainings',
                    'benefits'
                ]),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $employee = Employee::with([
                'documents',
                'employmentHistories',
                'promotionHistories',
                'trainings',
                'benefits'
            ])->findOrFail($id);
            return response()->json($employee);
        } catch (\Exception $e) {
            \Log::error('Error in show: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch employee: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $employee = Employee::findOrFail($id);

            $validated = $request->validate([
                'nama_lengkap' => 'required|string|max:255',
                'nik' => 'required|string|max:16|unique:employees,nik,' . $employee->id,
                'nip' => 'nullable|string|max:20|unique:employees,nip,' . $employee->id,
                'tanggal_lahir' => 'required|date',
                'jenis_kelamin' => 'required|in:Laki-laki,Perempuan',
                'alamat' => 'required|string',
                'status_pernikahan' => 'required|in:Belum Menikah,Menikah,Cerai',
                'jabatan_saat_ini' => 'required|string|max:100',
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

            $employee->update([
                'nama_lengkap' => $validated['nama_lengkap'],
                'nik' => $validated['nik'],
                'nip' => $validated['nip'],
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
                'nomor_bpjs_kesehatan' => $validated['nomor_bpjs_kesehatan'],
                'nomor_bpjs_ketenagakerjaan' => $validated['nomor_bpjs_ketenagakerjaan'],
                'npwp' => $validated['npwp'],
                'nomor_kontrak' => $validated['nomor_kontrak'],
                'tanggal_kontrak_berakhir' => $validated['tanggal_kontrak_berakhir'],
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

            DB::commit();

            return response()->json([
                'message' => 'Data pegawai berhasil diperbarui',
                'employee' => $employee->load([
                    'documents',
                    'employmentHistories',
                    'promotionHistories',
                    'trainings',
                    'benefits'
                ]),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
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

            \Log::info("Attempting to delete employee with ID: {$id}");
            $employee = Employee::findOrFail($id);

            foreach ($employee->documents as $doc) {
                \Log::info("Deleting document: {$doc->file_path}");
                if (Storage::disk('public')->exists($doc->file_path)) {
                    Storage::disk('public')->delete($doc->file_path);
                } else {
                    \Log::warning("Document file not found: {$doc->file_path}");
                }
                $doc->delete();
            }

            \Log::info("Deleting related records for employee ID: {$id}");
            $employee->employmentHistories()->delete();
            $employee->promotionHistories()->delete();
            $employee->trainings()->delete();
            $employee->benefits()->delete();

            \Log::info("Deleting employee record with ID: {$id}");
            $employee->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data pegawai dan semua data terkait berhasil dihapus'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            \Log::error("Employee not found: ID {$id}");
            return response()->json([
                'message' => 'Karyawan tidak ditemukan',
                'error' => $e->getMessage(),
            ], 404);
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            \Log::error("Database error in destroy: {$e->getMessage()}");
            return response()->json([
                'message' => 'Gagal menghapus karyawan karena masalah database',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("General error in destroy: {$e->getMessage()}");
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
                \Log::warning("Document file not found: {$document->file_path}");
            }
            $document->delete();

            DB::commit();

            return response()->json([
                'message' => 'Dokumen berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error in deleteDocument: ' . $e->getMessage());
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
            \Log::error('Error in deleteEmploymentHistory: ' . $e->getMessage());
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
            \Log::error('Error in deletePromotionHistory: ' . $e->getMessage());
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
            \Log::error('Error in deleteTraining: ' . $e->getMessage());
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
            \Log::error('Error in deleteBenefit: ' . $e->getMessage());
            return response()->json([
                'message' => 'Terjadi kesalahan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}