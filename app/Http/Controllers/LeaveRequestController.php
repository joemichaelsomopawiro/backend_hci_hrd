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
        $user = auth()->user();
        
        // Pengecekan jika user tidak ada atau tidak memiliki role
        if (!$user || !$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or role not set'
            ], 401);
        }
        
        $query = LeaveRequest::with(['employee', 'approver']);
        
        // Role-based filtering dengan pengecekan string langsung
        if ($user->role === 'Employee') {
            // Employee hanya bisa melihat cuti mereka sendiri
            $query->where('employee_id', $user->employee_id);
        } elseif ($user->role === 'Manager') {
            // Manager bisa melihat semua cuti yang perlu di-approve dan yang sudah di-approve olehnya
            if ($request->has('for_approval')) {
                $query->where('status', 'pending');
            } else {
                $query->where(function($q) use ($user) {
                    $q->where('approved_by', $user->employee_id)
                      ->orWhere('status', 'pending');
                });
            }
        } elseif ($user->role === 'HR') {
            // HR bisa melihat semua cuti yang sudah di-approve
            if ($request->has('approved_only')) {
                $query->where('status', 'approved');
            }
            // Jika tidak ada filter, HR bisa melihat semua
        }
        
        // Filter tambahan
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
        $user = auth()->user();
        
        // Pengecekan user dan role
        if (!$user || !$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or role not set'
            ], 401);
        }
        
        // Hanya Employee yang bisa mengajukan cuti
        if ($user->role !== 'Employee') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya employee yang dapat mengajukan cuti'
            ], 403);
        }
        
        // Pastikan user memiliki employee_id
        if (!$user->employee_id) {
            return response()->json([
                'success' => false,
                'message' => 'User belum terhubung dengan data employee'
            ], 400);
        }

        $request->validate([
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
            $quota = LeaveQuota::where('employee_id', $user->employee_id)
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
            'employee_id' => $user->employee_id,
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
        $user = auth()->user();
        
        // Pengecekan user dan role
        if (!$user || !$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or role not set'
            ], 401);
        }
        
        // Hanya Manager dan HR yang bisa approve
        if (!in_array($user->role, ['Manager', 'HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui cuti'
            ], 403);
        }

        $request->validate([
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
            'approved_by' => $user->employee_id,
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
        $user = auth()->user();
        
        // Pengecekan user dan role
        if (!$user || !$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or role not set'
            ], 401);
        }
        
        // Hanya Manager dan HR yang bisa reject
        if (!in_array($user->role, ['Manager', 'HR'])) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak cuti'
            ], 403);
        }

        $request->validate([
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
            'approved_by' => $user->employee_id,
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti ditolak',
            'data' => $leaveRequest->load(['employee', 'approver'])
        ]);
    }

    // Endpoint khusus untuk HR melihat semua cuti yang sudah di-approve
    public function getApprovedLeaves(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Pengecekan user dan role
        if (!$user || $user->role !== 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
            ], 403);
        }

        $query = LeaveRequest::with(['employee', 'approver'])
                           ->where('status', 'approved');
        
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }
        
        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }
        
        $approvedLeaves = $query->orderBy('approved_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $approvedLeaves,
            'summary' => [
                'total_approved' => $approvedLeaves->count(),
                'total_days' => $approvedLeaves->sum('total_days')
            ]
        ]);
    }
}