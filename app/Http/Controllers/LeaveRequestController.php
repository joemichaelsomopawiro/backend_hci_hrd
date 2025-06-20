<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\LeaveQuota;
use App\Services\RoleHierarchyService;
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
        
        $query = LeaveRequest::with(['employee.user', 'approver.user']);
        
        // Role-based filtering menggunakan RoleHierarchyService
        if (RoleHierarchyService::isEmployee($user->role)) {
            // Employee roles hanya bisa melihat cuti mereka sendiri
            $query->where('employee_id', $user->employee_id);
        } elseif ($user->role === 'HR') {
            // HR bisa melihat SEMUA data cuti dari semua employee
            // Tidak ada filter tambahan - HR dapat akses penuh
        } elseif (RoleHierarchyService::isManager($user->role)) {
            // Manager lain bisa melihat cuti dari subordinates mereka
            $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
            
            if ($request->has('for_approval')) {
                $query->where('status', 'pending')
                      ->whereHas('employee.user', function($q) use ($subordinateRoles) {
                          $q->whereIn('role', $subordinateRoles);
                      });
            } else {
                $query->where(function($q) use ($user, $subordinateRoles) {
                    $q->where('approved_by', $user->employee_id)
                      ->orWhere(function($subQ) use ($subordinateRoles) {
                          $subQ->where('status', 'pending')
                               ->whereHas('employee.user', function($userQ) use ($subordinateRoles) {
                                   $userQ->whereIn('role', $subordinateRoles);
                               });
                      });
                });
            }
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
        
        // Hanya Employee roles yang bisa mengajukan cuti
        if (!RoleHierarchyService::isEmployee($user->role)) {
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
            'leave_type' => 'required|in:annual,sick,emergency,maternity,paternity,marriage,bereavement',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        // Hitung total hari
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Cek quota untuk semua jenis cuti kecuali sick (unlimited)
        if (in_array($request->leave_type, ['annual', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement'])) {
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
        
        // Cek apakah user adalah manager
        if (!RoleHierarchyService::isManager($user->role)) {
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
        
        // Cek apakah user bisa approve leave request ini - gunakan Employee model
        if (!$user->employee || !$user->employee->canApproveLeaveFor($leaveRequest->employee_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui cuti karyawan ini'
            ], 403);
        }

        // Update quota jika disetujui (untuk semua jenis cuti kecuali sick)
        if (in_array($leaveRequest->leave_type, ['annual', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement'])) {
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
        
        // Hanya Manager yang bisa reject menggunakan RoleHierarchyService
        if (!RoleHierarchyService::isManager($user->role)) {
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
        
        // Cek apakah user bisa reject leave request ini
        if (!$user->employee || !$user->employee->canApproveLeaveFor($leaveRequest->employee_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak cuti karyawan ini'
            ], 403);
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
        
        // Pengecekan user dan role menggunakan RoleHierarchyService
        if (!$user || $user->role !== 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
            ], 403);
        }

        $query = LeaveRequest::with(['employee.user', 'approver.user'])
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

    /**
     * HR Dashboard - Melihat semua data cuti dengan summary lengkap
     * Endpoint khusus untuk HR melihat overview semua data cuti
     */
    public function getAllLeavesForHR(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        // Hanya HR yang bisa mengakses endpoint ini
        if (!$user || $user->role !== 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
            ], 403);
        }

        $query = LeaveRequest::with(['employee.user', 'approver.user']);
        
        // Filter berdasarkan parameter
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }
        
        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('department')) {
            $query->whereHas('employee.user', function($q) use ($request) {
                $q->where('role', $request->department);
            });
        }
        
        $allLeaves = $query->orderBy('created_at', 'desc')->get();
        
        // Summary data untuk HR dashboard
        $summary = [
            'total_requests' => $allLeaves->count(),
            'pending_requests' => $allLeaves->where('status', 'pending')->count(),
            'approved_requests' => $allLeaves->where('status', 'approved')->count(),
            'rejected_requests' => $allLeaves->where('status', 'rejected')->count(),
            'total_days_requested' => $allLeaves->sum('total_days'),
            'total_days_approved' => $allLeaves->where('status', 'approved')->sum('total_days'),
            'by_leave_type' => [
                'annual' => $allLeaves->where('leave_type', 'annual')->count(),
                'sick' => $allLeaves->where('leave_type', 'sick')->count(),
                'emergency' => $allLeaves->where('leave_type', 'emergency')->count(),
                'maternity' => $allLeaves->where('leave_type', 'maternity')->count(),
                'paternity' => $allLeaves->where('leave_type', 'paternity')->count(),
                'marriage' => $allLeaves->where('leave_type', 'marriage')->count(),
                'bereavement' => $allLeaves->where('leave_type', 'bereavement')->count(),
            ],
            'by_department' => $allLeaves->groupBy('employee.user.role')->map(function($group) {
                return $group->count();
            }),
            'recent_requests' => $allLeaves->take(10)->values()
        ];
        
        return response()->json([
            'success' => true,
            'data' => $allLeaves,
            'summary' => $summary,
            'message' => 'Data semua cuti berhasil diambil untuk HR dashboard'
        ]);
    }
}