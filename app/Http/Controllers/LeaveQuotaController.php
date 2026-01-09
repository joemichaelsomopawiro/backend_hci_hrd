<?php

namespace App\Http\Controllers;

use App\Models\LeaveQuota;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LeaveQuotaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LeaveQuota::with('employee');
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        
        $quotas = $query->get();
        
        return response()->json([
            'success' => true,
            'data' => $quotas
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'year' => 'required|integer|min:2020|max:2030',
            'annual_leave_quota' => 'required|integer|min:0',
            'sick_leave_quota' => 'required|integer|min:0',
            'emergency_leave_quota' => 'required|integer|min:0',
            'maternity_leave_quota' => 'integer|min:0',
            'paternity_leave_quota' => 'integer|min:0',
            'marriage_leave_quota' => 'integer|min:0',
            'bereavement_leave_quota' => 'integer|min:0',
        ]);

        // PEMBATASAN: Cek apakah employee sudah memiliki jatah cuti untuk tahun tersebut
        $existingQuota = LeaveQuota::where('employee_id', $request->employee_id)
                                  ->where('year', $request->year)
                                  ->first();
        
        if ($existingQuota) {
            return response()->json([
                'success' => false,
                'message' => 'Karyawan sudah memiliki jatah cuti untuk tahun tersebut. Gunakan fitur edit untuk mengubah data.'
            ], 422);
        }

        $quota = LeaveQuota::create($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti berhasil ditambahkan',
            'data' => $quota->load('employee')
        ], 201);
    }

    // Tambahkan method untuk mendapatkan employee yang belum punya jatah cuti
    public function getEmployeesWithoutQuota(Request $request): JsonResponse
    {
        $year = $request->get('year', date('Y'));
        
        $employeesWithoutQuota = \App\Models\Employee::whereDoesntHave('leaveQuotas', function($query) use ($year) {
            $query->where('year', $year);
        })->select('id', 'nama_lengkap', 'jabatan_saat_ini')
          ->get();
        
        return response()->json([
            'success' => true,
            'message' => 'Daftar karyawan yang belum memiliki jatah cuti',
            'data' => $employeesWithoutQuota
        ]);
    }

    public function show($id): JsonResponse
    {
        $quota = LeaveQuota::with('employee')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $quota
        ]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $quota = LeaveQuota::findOrFail($id);
        
        $request->validate([
            'annual_leave_quota' => 'integer|min:0',
            'sick_leave_quota' => 'integer|min:0',
            'emergency_leave_quota' => 'integer|min:0',
            'maternity_leave_quota' => 'integer|min:0',
            'paternity_leave_quota' => 'integer|min:0',
            'marriage_leave_quota' => 'integer|min:0',
            'bereavement_leave_quota' => 'integer|min:0',
            'annual_leave_used' => 'integer|min:0',
            'sick_leave_used' => 'integer|min:0',
            'emergency_leave_used' => 'integer|min:0',
            'maternity_leave_used' => 'integer|min:0',
            'paternity_leave_used' => 'integer|min:0',
            'marriage_leave_used' => 'integer|min:0',
            'bereavement_leave_used' => 'integer|min:0',
        ]);

        $quota->update($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti berhasil diperbarui',
            'data' => $quota->load('employee')
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $quota = LeaveQuota::findOrFail($id);
        $quota->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti berhasil dihapus'
        ]);
    }

    // Endpoint khusus untuk HR - Bulk update jatah cuti
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'updates' => 'required|array',
            'updates.*.employee_id' => 'required|exists:employees,id',
            'updates.*.year' => 'required|integer|min:2020|max:2030',
            'updates.*.annual_leave_quota' => 'integer|min:0',
            'updates.*.sick_leave_quota' => 'integer|min:0',
            'updates.*.emergency_leave_quota' => 'integer|min:0',
            'updates.*.maternity_leave_quota' => 'integer|min:0',
            'updates.*.paternity_leave_quota' => 'integer|min:0',
            'updates.*.marriage_leave_quota' => 'integer|min:0',
            'updates.*.bereavement_leave_quota' => 'integer|min:0',
        ]);

        $updatedQuotas = [];
        
        foreach ($request->updates as $update) {
            $quota = LeaveQuota::updateOrCreate(
                [
                    'employee_id' => $update['employee_id'],
                    'year' => $update['year']
                ],
                $update
            );
            $updatedQuotas[] = $quota->load('employee');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti berhasil diperbarui secara bulk',
            'data' => $updatedQuotas
        ]);
    }

    // Endpoint untuk reset jatah cuti tahunan
    public function resetAnnualQuotas(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'default_quotas' => 'required|array',
            'default_quotas.annual_leave_quota' => 'required|integer|min:0',
            'default_quotas.sick_leave_quota' => 'required|integer|min:0',
            'default_quotas.emergency_leave_quota' => 'required|integer|min:0',
            'default_quotas.maternity_leave_quota' => 'integer|min:0',
            'default_quotas.paternity_leave_quota' => 'integer|min:0',
            'default_quotas.marriage_leave_quota' => 'integer|min:0',
            'default_quotas.bereavement_leave_quota' => 'integer|min:0',
        ]);

        $employees = Employee::all();
        $createdQuotas = [];
        
        foreach ($employees as $employee) {
            $quotaData = array_merge(
                $request->default_quotas,
                [
                    'employee_id' => $employee->id,
                    'year' => $request->year,
                    'annual_leave_used' => 0,
                    'sick_leave_used' => 0,
                    'emergency_leave_used' => 0,
                    'maternity_leave_used' => 0,
                    'paternity_leave_used' => 0,
                    'marriage_leave_used' => 0,
                    'bereavement_leave_used' => 0,
                ]
            );
            
            $quota = LeaveQuota::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'year' => $request->year
                ],
                $quotaData
            );
            
            $createdQuotas[] = $quota->load('employee');
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti tahunan berhasil direset untuk semua karyawan',
            'data' => $createdQuotas
        ]);
    }

    // Endpoint untuk reset jatah cuti tahunan manual
    public function resetAnnual(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'confirm' => 'required|boolean|accepted'
        ]);

        if (!$request->confirm) {
            return response()->json([
                'success' => false,
                'message' => 'Konfirmasi diperlukan untuk melakukan reset jatah cuti tahunan'
            ], 422);
        }

        try {
            // Jalankan command reset
            \Artisan::call('leave:reset-annual', [
                'year' => $request->year
            ]);

            $output = \Artisan::output();

            return response()->json([
                'success' => true,
                'message' => 'Reset jatah cuti tahunan berhasil dilakukan',
                'data' => [
                    'year' => $request->year,
                    'command_output' => $output
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan reset jatah cuti tahunan: ' . $e->getMessage()
            ], 500);
        }
    }

    // Endpoint untuk mendapatkan jatah cuti user yang sedang login
    public function getMyCurrentQuotas(Request $request): JsonResponse
    {
        // 1. Ambil user yang terotentikasi
        $user = auth()->user();
        
        // 2. Lakukan pengecekan paling penting di awal
        if (!$user || !$user->employee_id) {
            // Jika tidak ada user atau user tidak punya employee_id, langsung hentikan
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau user tidak terhubung dengan data karyawan.'
            ], 401); // 401 Unauthorized lebih tepat
        }
        
        // 3. Tentukan tahun, gunakan tahun berjalan
        $year = date('Y');
        
        // 4. Jalankan query ke database
        $quota = LeaveQuota::where('employee_id', $user->employee_id)
                          ->where('year', $year)
                          ->first();
        
        // 5. Jika query tidak menemukan data, kembalikan response yang jelas
        if (!$quota) {
            return response()->json([
                'success' => false,
                'message' => 'Data jatah cuti untuk Anda di tahun ' . $year . ' belum diatur oleh HR.'
            ], 404); // 404 Not Found
        }
        
        // 6. Jika semua berhasil, kembalikan data yang ditemukan
        return response()->json([
            'success' => true,
            'data' => $quota
        ]);
    }

    // Endpoint untuk mendapatkan ringkasan penggunaan cuti
    public function getUsageSummary(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'integer|min:2020|max:2030',
            'employee_id' => 'exists:employees,id'
        ]);

        $query = LeaveQuota::with('employee');
        
        if ($request->has('year')) {
            $query->where('year', $request->year);
        }
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        $quotas = $query->get();
        
        $summary = [
            'total_employees' => $quotas->count(),
            'leave_types_summary' => [
                'annual' => [
                    'total_quota' => $quotas->sum('annual_leave_quota'),
                    'total_used' => $quotas->sum('annual_leave_used'),
                    'total_remaining' => $quotas->sum('annual_leave_quota') - $quotas->sum('annual_leave_used')
                ],
                'sick' => [
                    'total_quota' => $quotas->sum('sick_leave_quota'),
                    'total_used' => $quotas->sum('sick_leave_used'),
                    'total_remaining' => $quotas->sum('sick_leave_quota') - $quotas->sum('sick_leave_used')
                ],
                'emergency' => [
                    'total_quota' => $quotas->sum('emergency_leave_quota'),
                    'total_used' => $quotas->sum('emergency_leave_used'),
                    'total_remaining' => $quotas->sum('emergency_leave_quota') - $quotas->sum('emergency_leave_used')
                ],
                'maternity' => [
                    'total_quota' => $quotas->sum('maternity_leave_quota'),
                    'total_used' => $quotas->sum('maternity_leave_used'),
                    'total_remaining' => $quotas->sum('maternity_leave_quota') - $quotas->sum('maternity_leave_used')
                ],
                'paternity' => [
                    'total_quota' => $quotas->sum('paternity_leave_quota'),
                    'total_used' => $quotas->sum('paternity_leave_used'),
                    'total_remaining' => $quotas->sum('paternity_leave_quota') - $quotas->sum('paternity_leave_used')
                ],
                'marriage' => [
                    'total_quota' => $quotas->sum('marriage_leave_quota'),
                    'total_used' => $quotas->sum('marriage_leave_used'),
                    'total_remaining' => $quotas->sum('marriage_leave_quota') - $quotas->sum('marriage_leave_used')
                ],
                'bereavement' => [
                    'total_quota' => $quotas->sum('bereavement_leave_quota'),
                    'total_used' => $quotas->sum('bereavement_leave_used'),
                    'total_remaining' => $quotas->sum('bereavement_leave_quota') - $quotas->sum('bereavement_leave_used')
                ]
            ]
        ];
        
        return response()->json([
            'success' => true,
            'data' => $quotas,
            'summary' => $summary
        ]);
    }
}