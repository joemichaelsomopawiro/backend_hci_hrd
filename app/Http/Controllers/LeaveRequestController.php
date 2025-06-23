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
        
        if (!$user || !$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated or role not set'
            ], 401);
        }
        
        $query = LeaveRequest::with(['employee.user', 'managerApprovedBy.user', 'hrApprovedBy.user']);
        
        // Role-based filtering
        if (RoleHierarchyService::isEmployee($user->role)) {
            // Employee hanya bisa melihat cuti mereka sendiri
            $query->where('employee_id', $user->employee_id);
        } elseif ($user->role === 'HR') {
            // HR bisa melihat SEMUA data cuti dari semua employee
            // Tidak ada filter tambahan - HR dapat akses penuh
        } elseif (RoleHierarchyService::isManager($user->role)) {
            // Manager bisa melihat cuti dari subordinates mereka
            $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
            
            $query->where(function($q) use ($user, $subordinateRoles) {
                // Cuti yang sudah di-approve oleh manager ini
                if ($user->role === 'HR') {
                    $q->where('hr_approved_by', $user->employee_id);
                } else {
                    $q->where('manager_approved_by', $user->employee_id);
                }
                // Atau cuti dari subordinates yang masih pending
                $q->orWhere(function($subQ) use ($subordinateRoles) {
                    $subQ->where('overall_status', 'pending')
                         ->whereHas('employee.user', function($userQ) use ($subordinateRoles) {
                             $userQ->whereIn('role', $subordinateRoles);
                         });
                });
            });
        }
        
        // Filter tambahan
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        if ($request->has('overall_status')) {
            $query->where('overall_status', $request->overall_status);
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

        // Cek quota
        if (in_array($request->leave_type, ['annual', 'sick', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement'])) {
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
                    'message' => 'Jatah cuti tidak mencukupi. Sisa: ' . ($quota->$quotaField - $quota->$usedField) . ' hari'
                ], 400);
            }
        }

        // Di method store, ubah status awal
        $leaveRequest = LeaveRequest::create([
            'employee_id' => $user->employee_id,
            'leave_type' => $request->leave_type,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $totalDays,
            'reason' => $request->reason,
            'notes' => $request->notes,
            'overall_status' => 'pending', // Ubah dari 'pending_manager' ke 'pending'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil diajukan',
            'data' => $leaveRequest->load(['employee', 'managerApprovedBy', 'hrApprovedBy'])
        ], 201);
    }

    public function approve(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);
    
        $leaveRequest = LeaveRequest::findOrFail($id);
        
        // Cek status saat ini - hanya pending yang bisa diapprove
        if ($leaveRequest->overall_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan cuti sudah diproses'
            ], 400);
        }
        
        // Cek apakah user adalah manager yang berwenang
        if (!RoleHierarchyService::isManager($user->role)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui cuti'
            ], 403);
        }
        
        // Cek apakah user bisa approve leave request ini berdasarkan hierarchy
        $employee = $leaveRequest->employee;
        if (!$employee || !$employee->user) {
            return response()->json([
                'success' => false,
                'message' => 'Data employee tidak ditemukan'
            ], 400);
        }
        
        $employeeRole = $employee->user->role;
        if (!RoleHierarchyService::canApproveLeave($user->role, $employeeRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menyetujui cuti karyawan ini'
            ], 403);
        }
    
        // Update quota saat approve
        if (in_array($leaveRequest->leave_type, ['annual', 'sick', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement'])) {
            $year = Carbon::parse($leaveRequest->start_date)->year;
            $quota = LeaveQuota::where('employee_id', $leaveRequest->employee_id)
                          ->where('year', $year)
                          ->first();
            
            if ($quota) {
                $usedField = $leaveRequest->leave_type . '_leave_used';
                $quota->increment($usedField, $leaveRequest->total_days);
            }
        }
    
        // Update dengan struktur sederhana
        $leaveRequest->update([
            'overall_status' => 'approved',
            'approved_by' => $user->employee_id,
            'approved_at' => now(),
            'notes' => $request->notes,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil disetujui',
            'data' => $leaveRequest->load(['employee', 'approvedBy'])
        ]);
    }

    public function reject(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $leaveRequest = LeaveRequest::findOrFail($id);
        
        if ($leaveRequest->overall_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Permohonan cuti sudah diproses'
            ], 400);
        }
        
        if (!RoleHierarchyService::isManager($user->role)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak cuti'
            ], 403);
        }
        
        $employee = $leaveRequest->employee;
        $employeeRole = $employee->user->role;
        if (!RoleHierarchyService::canApproveLeave($user->role, $employeeRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menolak cuti karyawan ini'
            ], 403);
        }

        $rejectionData = [
            'overall_status' => 'rejected',
        ];
        
        if ($user->role === 'HR') {
            $rejectionData['hr_status'] = 'rejected';
            $rejectionData['hr_rejection_reason'] = $request->rejection_reason;
        } else {
            $rejectionData['manager_status'] = 'rejected';
            $rejectionData['manager_rejection_reason'] = $request->rejection_reason;
        }
        
        $leaveRequest->update($rejectionData);
        
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti ditolak',
            'data' => $leaveRequest->load(['employee', 'managerApprovedBy', 'hrApprovedBy'])
        ]);
    }

    // Endpoint khusus untuk HR melihat semua cuti yang sudah di-approve
    public function getApprovedLeaves(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
            ], 403);
        }

        $query = LeaveRequest::with(['employee.user', 'managerApprovedBy.user', 'hrApprovedBy.user'])
                           ->where('overall_status', 'approved');
        
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }
        
        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }
        
        $requests = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    // Endpoint khusus untuk HR Dashboard - melihat SEMUA data cuti
    public function getAllLeavesForHR(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->role !== 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya HR yang dapat melihat data ini'
            ], 403);
        }

        $query = LeaveRequest::with(['employee.user', 'managerApprovedBy.user', 'hrApprovedBy.user']);
        
        // Filter berdasarkan status jika diminta
        if ($request->has('overall_status')) {
            $query->where('overall_status', $request->overall_status);
        }
        
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }
        
        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }
        
        $requests = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    // Endpoint untuk Manager Dashboard
    public function getManagerDashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || !RoleHierarchyService::isManager($user->role) || $user->role === 'HR') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya manager yang dapat melihat data ini'
            ], 403);
        }

        $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
        
        $query = LeaveRequest::with(['employee.user', 'managerApprovedBy.user', 'hrApprovedBy.user'])
                           ->whereHas('employee.user', function($q) use ($subordinateRoles) {
                               $q->whereIn('role', $subordinateRoles);
                           });
        
        // Filter berdasarkan status jika diminta
        if ($request->has('overall_status')) {
            $query->where('overall_status', $request->overall_status);
        }
        
        if ($request->has('year')) {
            $query->whereYear('start_date', $request->year);
        }
        
        if ($request->has('month')) {
            $query->whereMonth('start_date', $request->month);
        }
        
        $requests = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }
}