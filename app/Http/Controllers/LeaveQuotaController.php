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
}