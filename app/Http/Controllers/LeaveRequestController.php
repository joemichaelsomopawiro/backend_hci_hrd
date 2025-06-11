<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveQuota;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::with(['employee', 'approver']);
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }
        
        $requests = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type' => 'required|in:annual,sick,emergency,maternity,paternity',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        // Hitung total hari
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Cek quota jika bukan cuti sakit
        if (in_array($request->leave_type, ['annual', 'emergency'])) {
            $year = $startDate->year;
            $quota = LeaveQuota::where('employee_id', $request->employee_id)
                              ->where('year', $year)
                              ->first();
            
            if (!$quota) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jatah cuti untuk tahun ' . $year . ' belum diatur'
                ], 400);
            }
            
            $quotaField = $request->leave_type . '_leave_quota';
            $usedField = $request->leave_type . '_leave_used';
            
            if (($quota->$usedField + $totalDays) > $quota->$quotaField) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jatah cuti tidak mencukupi'
                ], 400);
            }
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $request->employee_id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'notes' => $request->notes,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil diajukan',
            'data' => $leaveRequest->load(['employee', 'approver'])
        ], 201);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $request->validate([
            'approved_by' => 'required|exists:employees,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);
        
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan cuti sudah diproses'
            ], 400);
        }

        // Update quota jika disetujui
        if (in_array($leaveRequest->leave_type, ['annual', 'emergency'])) {
            $year = Carbon::parse($leaveRequest->start_date)->year;
            $quota = LeaveQuota::where('employee_id', $leaveRequest->employee_id)
                              ->where('year', $year)
                              ->first();
            
            if ($quota) {
                $usedField = $leaveRequest->leave_type . '_leave_used';
                $quota->increment($usedField, $leaveRequest->total_days);
            }
        }

        $leaveRequest->update([
            'status' => 'approved',
            'approved_by' => $request->approved_by,
            'approved_at' => now(),
            'notes' => $request->notes,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti disetujui',
            'data' => $leaveRequest->load(['employee', 'approver'])
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $request->validate([
            'approved_by' => 'required|exists:employees,id',
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);
        
        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan cuti sudah diproses'
            ], 400);
        }

        $leaveRequest->update([
            'status' => 'rejected',
            'approved_by' => $request->approved_by,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti ditolak',
            'data' => $leaveRequest->load(['employee', 'approver'])
        ]);
    }
}