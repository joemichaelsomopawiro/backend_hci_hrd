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
    /** * Display a listing of the resource. 
     * Method ini telah diperbarui untuk menangani hak akses berdasarkan role. 
     * Logika disederhanakan untuk memisahkan otorisasi dan filtering.
     */ 
    public function index(Request $request): JsonResponse 
    { 
        $user = auth()->user(); 
        $query = LeaveRequest::with(['employee.user', 'approvedBy.user']); 

        // ========== BAGIAN 1: OTORISASI (Siapa boleh lihat apa) ========== 
        if (RoleHierarchyService::isHrManager($user->role)) { 
            // HR hanya dapat melihat permohonan dari bawahannya langsung (Finance, General Affairs, Office Assistant)
            // Tidak bisa melihat permohonan dari Program Manager atau Distribution Manager
            $hrSubordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role); 
            if (!empty($hrSubordinateRoles)) { 
                $query->whereHas('employee.user', function ($q) use ($hrSubordinateRoles) { 
                    $q->whereIn('role', $hrSubordinateRoles); 
                }); 
            } else { 
                // Jika HR tidak punya bawahan, kembalikan data kosong. 
                return response()->json(['success' => true, 'data' => []]); 
            } 
        } elseif (RoleHierarchyService::isOtherManager($user->role)) { 
            // Manager lain (Program/Distribution) hanya bisa melihat bawahannya. 
            $subordinateRoles = RoleHierarchyService::getSubordinateRoles($user->role); 
            if (!empty($subordinateRoles)) { 
                $query->whereHas('employee.user', function ($q) use ($subordinateRoles) { 
                    $q->whereIn('role', $subordinateRoles); 
                }); 
            } else { 
                // Jika manager tidak punya bawahan, kembalikan data kosong. 
                return response()->json(['success' => true, 'data' => []]); 
            } 
        } else { 
            // Karyawan biasa hanya bisa melihat permohonannya sendiri. 
            $query->where('employee_id', $user->employee_id); 
        } 

        // ========== BAGIAN 2: FILTERING (Berdasarkan input dari frontend) ========== 
        $statusFilter = $request->input('status'); 

        // Ini untuk mengatasi komponen yang mungkin masih mengirim `for_approval=true` 
        if ($request->input('for_approval') === 'true' && !$request->filled('status')) { 
            $statusFilter = 'pending'; 
        } 
         
        if ($statusFilter) { 
            $query->where('overall_status', $statusFilter); 
        } 

        if ($request->filled('leave_type')) { 
            $query->where('leave_type', $request->leave_type); 
        } 

        // ========== BAGIAN 3: EKSEKUSI QUERY ========== 
        $requests = $query->orderBy('created_at', 'desc')->get(); 

        return response()->json([ 
            'success' => true, 
            'data' => $requests 
        ]); 
    }

    /** * Store a newly created resource in storage. 
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
        
        // Hitung hari kerja dengan mengecualikan Sabtu dan Minggu
        $totalDays = 0;
        $currentDate = $startDate->copy();
        
        while ($currentDate->lte($endDate)) {
            // Hanya hitung jika bukan weekend (Sabtu = 6, Minggu = 0)
            if (!$currentDate->isWeekend()) {
                $totalDays++;
            }
            $currentDate->addDay();
        }


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
            'notes' => $request->notes, // `notes` bisa datang dari form, misal 'serah terima pekerjaan'
            'overall_status' => 'pending', 
        ]); 
        
        return response()->json([ 
            'success' => true, 
            'message' => 'Permohonan cuti berhasil diajukan', 
            'data' => $leaveRequest->load(['employee', 'approvedBy']) 
        ], 201); 
    } 

    /** * Approve a leave request. 
     * Method ini disederhanakan dan menggunakan RoleHierarchyService untuk otorisasi. 
     */ 
    public function approve(Request $request, $id): JsonResponse 
    { 
        $user = auth()->user(); 
        $leaveRequest = LeaveRequest::findOrFail($id); 

        // Check if request is expired
        $leaveRequest->checkAndExpire();
        
        if (!$leaveRequest->canBeProcessed()) { 
            $statusMessage = $leaveRequest->isExpired() ? 'Permohonan cuti sudah expired karena melewati tanggal mulai cuti' : 'Permohonan cuti sudah diproses';
            return response()->json(['success' => false, 'message' => $statusMessage], 400); 
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

    /** * Reject a leave request. 
     * Method ini disederhanakan dan menggunakan RoleHierarchyService untuk otorisasi. 
     */ 
    public function reject(Request $request, $id): JsonResponse 
    { 
        $user = auth()->user(); 
        $leaveRequest = LeaveRequest::findOrFail($id); 

        $request->validate(['rejection_reason' => 'required|string|max:1000']); 
        
        // Check if request is expired
        $leaveRequest->checkAndExpire();
        
        if (!$leaveRequest->canBeProcessed()) { 
            $statusMessage = $leaveRequest->isExpired() ? 'Permohonan cuti sudah expired karena melewati tanggal mulai cuti' : 'Permohonan cuti sudah diproses';
            return response()->json(['success' => false, 'message' => $statusMessage], 400); 
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

    public function destroy($id): JsonResponse 
    { 
        $user = auth()->user(); 
        $leaveRequest = LeaveRequest::findOrFail($id); 

        // Otorisasi: Pastikan yang menghapus adalah pemilik request 
        if ($user->employee_id !== $leaveRequest->employee_id) { 
            return response()->json([ 
                'success' => false, 
                'message' => 'Anda tidak memiliki wewenang untuk membatalkan permohonan ini.' 
            ], 403); // 403 Forbidden 
        } 

        // Check if request is expired
        $leaveRequest->checkAndExpire();
        
        // Validasi Status: Hanya permohonan 'pending' yang bisa dibatalkan 
        if (!$leaveRequest->canBeProcessed()) { 
            $statusMessage = $leaveRequest->isExpired() ? 'Permohonan ini sudah expired dan tidak dapat dibatalkan.' : 'Permohonan ini sudah diproses dan tidak dapat dibatalkan.';
            return response()->json([ 
                'success' => false, 
                'message' => $statusMessage 
            ], 400); // 400 Bad Request 
        } 

        // Hapus permohonan cuti 
        $leaveRequest->delete(); 

        return response()->json([ 
            'success' => true, 
            'message' => 'Permohonan cuti berhasil dibatalkan.' 
        ]); 
    } 
}