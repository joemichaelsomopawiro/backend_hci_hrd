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

        $quota = LeaveQuota::create($request->all());
        
        return response()->json([
            'success' => true,
            'message' => 'Jatah cuti berhasil ditambahkan',
            'data' => $quota->load('employee')
        ], 201);
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