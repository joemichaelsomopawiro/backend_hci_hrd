<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ManagerController extends Controller
{
    /**
     * Mendapatkan daftar karyawan bawahan
     */
    public function getSubordinates(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee data not found'
            ], 404);
        }

        $subordinates = $user->employee->getSubordinatesByDepartment();
        
        return response()->json([
            'success' => true,
            'data' => $subordinates->load(['user', 'leaveQuotas', 'attendances'])
        ]);
    }

    /**
     * Mendapatkan detail karyawan bawahan
     */
    public function getSubordinateDetail(Request $request, $employeeId): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->canViewEmployee($employeeId)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this employee'
            ], 403);
        }

        $employee = Employee::with([
            'user', 
            'documents', 
            'employmentHistories', 
            'promotionHistories', 
            'trainings', 
            'benefits', 
            'leaveQuotas', 
            'leaveRequests', 
            'attendances'
        ])->find($employeeId);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $employee
        ]);
    }

    /**
     * Mendapatkan leave requests dari bawahan
     */
    public function getSubordinateLeaveRequests(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee data not found'
            ], 404);
        }

        $subordinates = $user->employee->getSubordinatesByDepartment();
        $subordinateIds = $subordinates->pluck('id');
        
        $leaveRequests = LeaveRequest::with(['employee.user'])
            ->whereIn('employee_id', $subordinateIds)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $leaveRequests
        ]);
    }

    /**
     * Approve/Reject leave request
     */
    public function processLeaveRequest(Request $request, $leaveRequestId): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string',
            'rejection_reason' => 'required_if:action,reject|string'
        ]);

        $user = $request->user();
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if (!$leaveRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found'
            ], 404);
        }

        if (!$user->canApproveLeave($leaveRequest->employee_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to process this leave request'
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Leave request already processed'
            ], 400);
        }

        $leaveRequest->update([
            'status' => $request->action === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $user->employee->id,
            'approved_at' => now(),
            'notes' => $request->notes,
            'rejection_reason' => $request->action === 'reject' ? $request->rejection_reason : null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request ' . $request->action . 'd successfully',
            'data' => $leaveRequest->load(['employee.user', 'approver.user'])
        ]);
    }

    /**
     * Mendapatkan daftar jatah cuti dari bawahan langsung manajer dengan status kehadiran.
     */
    public function getSubordinateLeaveQuotas(Request $request): JsonResponse
    {
        $manager = auth()->user();
        $currentYear = 2025; // Tetap gunakan tahun 2025 untuk konsistensi data

        if (!$manager->employee) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan untuk akun manajer ini tidak ditemukan.'
            ], 404);
        }

        // ===================================================================
        // INI ADALAH PERUBAHAN UTAMA
        // Sekarang, kode ini secara spesifik mengambil BAWAHAN LANGSUNG
        // dari manajer yang login, menggunakan relasi 'subordinates()'
        // yang bergantung pada kolom 'manager_id'.
        // ===================================================================
        $subordinates = $manager->employee->subordinates()
            ->with([
                'leaveQuotas' => function ($query) use ($currentYear) {
                    $query->where('year', $currentYear);
                },
                'todaysAttendance',
                'approvedLeaveForToday'
            ])
            ->orderBy('nama_lengkap', 'asc')
            ->get();

        // Pastikan accessor 'current_status' tetap ditambahkan
        $subordinates->each->append('current_status');
        
        return response()->json([
            'success' => true,
            'data' => $subordinates
        ]);
    }
}