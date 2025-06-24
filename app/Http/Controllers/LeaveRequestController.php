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
    /**
     * Display a listing of the resource.
     * Method ini telah diperbarui untuk menangani hak akses berdasarkan role.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $query = LeaveRequest::with(['employee.user', 'approvedBy.user']);

        // Filter berdasarkan role yang sedang login
        if (RoleHierarchyService::isEmployee($user->role)) {
            // Jika role adalah employee, hanya tampilkan permohonan miliknya sendiri
            $query->where('employee_id', $user->employee_id);

        } elseif (RoleHierarchyService::isManager($user->role)) {
            // Jika role adalah manager (Program, Distribution, HR), tampilkan permohonan dari bawahannya
            $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role);
            $query->whereHas('employee.user', function ($q) use ($subordinateRoles) {
                $q->whereIn('role', $subordinateRoles);
            });
        }
        // Jika tidak ada kondisi di atas (misal: role tidak terdefinisi), tidak akan menampilkan apa-apa

        // Filter tambahan dari request frontend
        if ($request->filled('status')) {
            $query->where('overall_status', $request->status);
        }

        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }


    /**
     * Store a newly created resource in storage.
     * Method ini telah diperbaiki untuk menghilangkan error relasi dan memperbaiki kalkulasi durasi.
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user || !$user->role) {
            return response()->json(['success' => false, 'message' => 'User not authenticated or role not set'], 401);
        }
        
        // Hanya Employee roles yang bisa mengajukan cuti
        if (!RoleHierarchyService::isEmployee($user->role)) {
            return response()->json(['success' => false, 'message' => 'Hanya role karyawan yang dapat mengajukan cuti'], 403);
        }
        
        if (!$user->employee_id) {
            return response()->json(['success' => false, 'message' => 'User belum terhubung dengan data employee'], 400);
        }

        $request->validate([
            'leave_type' => 'required|in:annual,sick,emergency,maternity,paternity,marriage,bereavement',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:1000',
        ]);

        // DIPERBARUI: Hitung total hari kerja (tidak termasuk Sabtu & Minggu)
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $totalDays = $startDate->diffInDaysFiltered(function(Carbon $date) {
            return !$date->isWeekend();
        }, $endDate) + 1;


        // Cek Quota
        if (in_array($request->leave_type, ['annual', 'sick', 'emergency', 'maternity', 'paternity', 'marriage', 'bereavement'])) {
            $year = $startDate->year;
            $quota = LeaveQuota::where('employee_id', $user->employee_id)
                                 ->where('year', $year)
                                 ->first();
            
            if (!$quota) {
                return response()->json(['success' => false, 'message' => 'Jatah cuti untuk tahun ' . $year . ' belum diatur'], 400);
            }
            
            $quotaField = $request->leave_type . '_leave_quota';
            $usedField = $request->leave_type . '_leave_used';
            
            if (($quota->$usedField + $totalDays) > $quota->$quotaField) {
                return response()->json(['success' => false, 'message' => 'Jatah cuti tidak mencukupi. Sisa: ' . ($quota->$quotaField - $quota->$usedField) . ' hari'], 400);
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
            'overall_status' => 'pending',
        ]);
        
        // DIPERBAIKI: Mengganti relasi yang salah dengan relasi yang benar ('approvedBy')
        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil diajukan',
            'data' => $leaveRequest->load(['employee', 'approvedBy'])
        ], 201);
    }

    /**
     * Approve a leave request.
     * Method ini disederhanakan dan menggunakan RoleHierarchyService untuk otorisasi.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $leaveRequest = LeaveRequest::findOrFail($id);

        if ($leaveRequest->overall_status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Permohonan cuti sudah diproses'], 400);
        }

        $employeeRole = $leaveRequest->employee->user->role;
        if (!RoleHierarchyService::canApproveLeave($user->role, $employeeRole)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki wewenang untuk menyetujui permohonan ini'], 403);
        }

        $leaveRequest->update([
            'overall_status' => 'approved',
            'approved_by' => $user->employee_id,
            'approved_at' => now(),
            'notes' => $request->notes,
        ]);

        $leaveRequest->updateLeaveQuota();

        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil disetujui',
            'data' => $leaveRequest->load(['employee.user', 'approvedBy.user'])
        ]);
    }

    /**
     * Reject a leave request.
     * Method ini disederhanakan dan menggunakan RoleHierarchyService untuk otorisasi.
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $user = auth()->user();
        $leaveRequest = LeaveRequest::findOrFail($id);

        $request->validate(['rejection_reason' => 'required|string|max:1000']);
        
        if ($leaveRequest->overall_status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Permohonan cuti sudah diproses'], 400);
        }

        $employeeRole = $leaveRequest->employee->user->role;
        if (!RoleHierarchyService::canApproveLeave($user->role, $employeeRole)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki wewenang untuk menolak permohonan ini'], 403);
        }

        $leaveRequest->update([
            'overall_status' => 'rejected',
            'approved_by' => $user->employee_id, // Tetap catat siapa yang memproses
            'rejection_reason' => $request->rejection_reason,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permohonan cuti berhasil ditolak',
            'data' => $leaveRequest->load(['employee.user', 'approvedBy.user'])
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