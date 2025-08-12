<?php 

namespace App\Http\Controllers; 

use App\Models\LeaveRequest; 
use App\Models\LeaveQuota; 
use App\Services\RoleHierarchyService; 
use App\Services\LeaveAttendanceIntegrationService; 
use Illuminate\Http\Request; 
use Illuminate\Http\JsonResponse; 
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
// use Barryvdh\DomPDF\Facade\Pdf; // Aktifkan setelah paket diinstall

class LeaveRequestController extends Controller 
{ 
    /** * Display a listing of the resource. 
     * Method ini telah diperbarui untuk menangani hak akses berdasarkan role. 
     * Logika disederhanakan untuk memisahkan otorisasi dan filtering.
     * DIPERBARUI: Mendukung role kustom berdasarkan department
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
            // DIPERBARUI: Manager lain (Program/Distribution) hanya bisa melihat bawahannya
            // Termasuk role kustom dengan department yang sama
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

        // Tambahkan leave_dates pada setiap data cuti
        $transformed = $requests->map(function($leave) {
            $start = \Carbon\Carbon::parse($leave->start_date);
            $end = \Carbon\Carbon::parse($leave->end_date);
            $dates = [];
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dates[] = $date->toDateString();
            }
            $data = $leave->toArray();
            $data['leave_dates'] = $dates;
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $transformed
        ]); 
    }

    /** * Store a newly created resource in storage. 
     * Method ini telah diperbaiki untuk menghilangkan error relasi dan memperbaiki kalkulasi durasi. 
     * DIPERBARUI: Mendukung role kustom dengan access_level employee
     */ 
    public function store(Request $request): JsonResponse 
    { 
        $user = auth()->user(); 

        if (!$user || !$user->role) { 
            return response()->json(['success' => false, 'message' => 'User not authenticated or role not set'], 401); 
        } 
        
        // DIPERBARUI: Validasi role untuk mendukung custom roles
        $canSubmit = $this->canUserSubmitLeave($user, $request);
        
        if (!$canSubmit) {
            return response()->json(['success' => false, 'message' => 'Hanya role karyawan yang dapat mengajukan cuti'], 403); 
        } 
        
        if (!$user->employee_id) { 
            return response()->json(['success' => false, 'message' => 'User belum terhubung dengan data employee'], 400); 
        } 

        $request->validate([ 
            'leave_type' => 'required|in:annual,sick,emergency,maternity,paternity,marriage,bereavement', 
            'start_date' => 'required|date', 
            'end_date' => 'required|date|after_or_equal:start_date', 
            'reason' => 'required|string|max:1000', 
            'notes' => 'nullable|string',
            'leave_location' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'emergency_contact' => 'nullable|string|max:50',
            'employee_signature' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]); 
        
        // Validasi khusus untuk tanggal masa lalu (hanya untuk non-emergency leave)
        $startDate = Carbon::parse($request->start_date);
        $today = Carbon::today();
        
        if ($request->leave_type !== 'emergency' && $startDate->lt($today)) {
            return response()->json([
                'success' => false, 
                'message' => 'Tanggal mulai cuti tidak boleh di masa lalu kecuali untuk cuti darurat'
            ], 400);
        }
        
        // Peringatan untuk cuti di hari yang sama (bukan error, hanya info)
        if ($startDate->eq($today)) {
            // Log atau notifikasi bahwa ini adalah same-day leave request
            Log::info('Same-day leave request submitted', [
                'employee_id' => $user->employee_id,
                'leave_type' => $request->leave_type,
                'start_date' => $request->start_date
            ]);
        } 

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

        // Simpan field opsional hanya jika kolom tersedia
        $optionalUpdates = [];
        if (Schema::hasColumn('leave_requests', 'leave_location')) {
            $optionalUpdates['leave_location'] = $request->leave_location;
        }
        if (Schema::hasColumn('leave_requests', 'contact_phone')) {
            $optionalUpdates['contact_phone'] = $request->contact_phone;
        }
        // Jika frontend mengirim emergency_contact, gunakan sebagai contact_phone jika kolom ada
        if (Schema::hasColumn('leave_requests', 'contact_phone') && $request->filled('emergency_contact')) {
            $optionalUpdates['contact_phone'] = $request->emergency_contact;
        }

        if (!empty($optionalUpdates)) {
            $leaveRequest->update($optionalUpdates);
        }

        // Simpan tanda tangan pegawai bila kolom tersedia dan file dikirim
        if ($request->hasFile('employee_signature') && Schema::hasColumn('leave_requests', 'employee_signature_path')) {
            $path = $request->file('employee_signature')->store("signatures/leave/{$leaveRequest->id}", 'public');
            $leaveRequest->update(['employee_signature_path' => $path]);
        }
        
        return response()->json([ 
            'success' => true, 
            'message' => 'Permohonan cuti berhasil diajukan', 
            'data' => $leaveRequest->load(['employee', 'approvedBy']) 
        ], 201); 
    }

    /**
     * DIPERBARUI: Method untuk mengecek apakah user bisa mengajukan cuti
     * Mendukung role kustom dengan access_level employee
     */
    private function canUserSubmitLeave($user, $request): bool
    {
        $userRole = $user->role;
        
        // Cek access_level dari request (jika ada)
        $accessLevel = $request->input('access_level');
        if ($accessLevel === 'employee') {
            return true;
        }
        
        // Cek apakah role adalah custom role dengan access_level employee
        if (RoleHierarchyService::isCustomRole($userRole)) {
            $customRoleAccessLevel = RoleHierarchyService::getCustomRoleAccessLevel($userRole);
            if ($customRoleAccessLevel === 'employee') {
                return true;
            }
        }
        
        // Cek role standar
        if (RoleHierarchyService::isEmployee($userRole)) {
            return true;
        }
        
        // Role yang tidak boleh mengajukan cuti
        $excludedRoles = [
            'VP President', 
            'President Director', 
            'Program Manager', 
            'Distribution Manager', 
            'HR Manager', 
            'HR'
        ];
        
        return !in_array($userRole, $excludedRoles);
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

        // Validasi fleksibel: hanya validasi field yang memang dikirim
        $rules = [
            'notes' => 'nullable|string',
        ];
        if ($request->hasFile('approver_signature')) {
            $rules['approver_signature'] = 'file|mimes:png,jpg,jpeg,webp|max:5120';
        } elseif ($request->filled('approver_signature_base64')) {
            $rules['approver_signature_base64'] = 'string';
        }
        $request->validate($rules);
        Log::info('Approve request debug', [
            'leave_request_id' => $leaveRequest->id,
            'has_file' => $request->hasFile('approver_signature'),
            'has_base64' => $request->filled('approver_signature_base64'),
            'content_type' => $request->header('Content-Type'),
        ]);

        $updates = [
            'overall_status' => 'approved',
            'approved_by' => $user->employee_id,
            'approved_at' => now(),
            'notes' => $request->notes,
        ];

        // Simpan tanda tangan atasan dengan dukungan beberapa field dan base64
        $storedPath = $this->storeApproverSignatureIfProvided($request, $leaveRequest->id);
        if ($storedPath) {
            $updates['approver_signature_path'] = $storedPath;
            Log::info('Approver signature uploaded', ['leave_request_id' => $leaveRequest->id, 'path' => $storedPath, 'approver_employee_id' => $user->employee_id]);
        }

        $leaveRequest->update($updates);

        $leaveRequest->updateLeaveQuota(); 

        // Sinkronisasi status cuti ke attendance
        $leaveService = new LeaveAttendanceIntegrationService();
        $leaveService->handleLeaveApproval($leaveRequest);

        return response()->json([ 
            'success' => true, 
            'message' => 'Permohonan cuti berhasil disetujui dan status attendance telah diupdate', 
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

        // Reset status attendance jika ada
        $leaveService = new LeaveAttendanceIntegrationService();
        $leaveService->handleLeaveRejection($leaveRequest);

        return response()->json([ 
            'success' => true, 
            'message' => 'Permohonan cuti berhasil ditolak dan status attendance telah direset', 
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

    /**
     * Generate dan download surat cuti (PDF)
     */
    public function downloadLetter($id)
    {
        $leave = LeaveRequest::with(['employee.user', 'approvedBy.user'])->findOrFail($id);

        if ($leave->overall_status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Surat hanya tersedia untuk permohonan yang disetujui'], 400);
        }

        // Siapkan data sederhana untuk template
        $employee = $leave->employee;

        // Siapkan data URI untuk tanda tangan agar aman dipakai di PDF/HTML
        $employeeSignaturePath = $leave->employee_signature_path;
        $approverSignaturePath = $leave->approver_signature_path;

        $guessMime = function (string $path): string {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            return match ($ext) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/png',
            };
        };

        $toDataUri = function (?string $path) use ($guessMime): ?string {
            if (!$path) { return null; }
            // Normal public disk path
            if (Storage::disk('public')->exists($path)) {
                $mime = $guessMime($path);
                return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($path));
            }
            // Path dimulai dengan storage/
            if (str_starts_with($path, 'storage/')) {
                $relative = substr($path, strlen('storage/'));
                if (Storage::disk('public')->exists($relative)) {
                    $mime = $guessMime($relative);
                    return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($relative));
                }
            }
            // Absolute file path
            if (file_exists($path)) {
                $mime = $guessMime($path);
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
            // URL http(s)
            if (preg_match('#^https?://#i', $path)) {
                try {
                    $content = @file_get_contents($path);
                    if ($content !== false) {
                        $mime = $guessMime($path);
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            return null;
        };

        $employeeSignatureDataUri = $toDataUri($employeeSignaturePath);
        $approverSignatureDataUri = $toDataUri($approverSignaturePath);

        // Fallback: pakai signature dari profil atasan jika ada
        if (!$approverSignatureDataUri) {
            $profileSig = $leave->approvedBy->user->signature_path ?? $leave->approvedBy->signature_path ?? null;
            $approverSignatureDataUri = $toDataUri($profileSig);
            Log::info('Approver signature fallback used', [
                'leave_request_id' => $leave->id,
                'fallback_source' => $profileSig ? 'profile_signature_path' : 'none',
                'has_data_uri' => (bool) $approverSignatureDataUri,
            ]);
        }

        // Format rentang tanggal seperti contoh (satu tahun di akhir bila memungkinkan)
        $start = Carbon::parse($leave->start_date)->locale('id');
        $end = Carbon::parse($leave->end_date)->locale('id');
        if ($start->year === $end->year) {
            if ($start->month === $end->month) {
                $dateRange = $start->translatedFormat('j F') . ' - ' . $end->translatedFormat('j F Y');
            } else {
                $dateRange = $start->translatedFormat('j F') . ' - ' . $end->translatedFormat('j F Y');
            }
        } else {
            $dateRange = $start->translatedFormat('j F Y') . ' - ' . $end->translatedFormat('j F Y');
        }

        // Coba ambil logo perusahaan dari public/images atau favicon
        $logoCandidates = [
            public_path('images/image.png'),
            public_path('images/hope_logo.png'),
            public_path('images/hope-logo.png'),
            public_path('images/logo_hope_channel.png'),
            public_path('favicon.ico'),
        ];
        $companyLogoDataUri = null;
        foreach ($logoCandidates as $logoPath) {
            if ($logoPath && file_exists($logoPath)) {
                $mime = function_exists('mime_content_type') ? mime_content_type($logoPath) : 'image/png';
                $companyLogoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($logoPath));
                break;
            }
        }

        $approver = $leave->approvedBy;
        $approverName = $approver?->nama_lengkap ?? $approver?->name ?? 'Atasan';
        $approverPosition = $approver?->jabatan_saat_ini ?? $approver?->user?->role ?? 'Manager Program';

        $employeePosition = $employee->jabatan_saat_ini ?? $employee->jabatan ?? $employee->user?->role ?? '-';

        $data = [
            'employee_name' => $employee->nama_lengkap ?? $employee->name ?? 'Nama Pegawai',
            'employee_position' => $employeePosition,
            'date_range_text' => $dateRange,
            'total_days' => $leave->total_days,
            'leave_type' => $leave->leave_type,
            'letter_date' => now()->locale('id')->translatedFormat('j F Y'),
            'city' => 'Jakarta',
            'year' => $start->year,
            'employee_signature_path' => $employeeSignaturePath ? (storage_path('app/public/' . $employeeSignaturePath)) : null,
            'approver_signature_path' => $approverSignaturePath ? (storage_path('app/public/' . $approverSignaturePath)) : null,
            'employee_signature_data_uri' => $employeeSignatureDataUri,
            'approver_signature_data_uri' => $approverSignatureDataUri,
            'leave_location' => $leave->leave_location,
            'contact_phone' => $leave->contact_phone,
            'emergency_contact' => $leave->emergency_contact ?? $leave->contact_phone,
            'approver_name' => $approverName,
            'approver_position' => $approverPosition,
            'company_logo_data_uri' => $companyLogoDataUri,
            'company_name' => 'HOPE CHANNEL INDONESIA',
            'company_address_lines' => [
                '2nd Floor Gedung Pertemuan Advent',
                'Jl. M. T. Haryono Kav. 4-5 Block A - Jakarta 12810',
                '🕿 62.21.8379 7879 / 8379 7883 ● 🖂: info@hopetv.or.id  ● www.hopechannel.id',
            ],
        ];

        // Jika paket dompdf telah terpasang, hasilkan PDF
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdfs.leave_letter_simple', $data)->setPaper('A4');
            return $pdf->download('surat_cuti_' . $leave->id . '.pdf');
        }

        // Fallback: render HTML jika PDF belum tersedia
        return response()->view('pdfs.leave_letter_simple', $data, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * Show detail leave request (untuk debug/verifikasi tanda tangan)
     */
    public function show($id): JsonResponse
    {
        $leave = LeaveRequest::with(['employee.user', 'approvedBy.user'])->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $leave,
            'debug' => [
                'has_employee_signature' => (bool) $leave->employee_signature_path,
                'has_approver_signature' => (bool) $leave->approver_signature_path,
                'employee_signature_path' => $leave->employee_signature_path,
                'approver_signature_path' => $leave->approver_signature_path,
            ]
        ]);
    }

    /**
     * Upload/ubah tanda tangan atasan terpisah dari proses approve
     */
    public function uploadApproverSignature(Request $request, $id): JsonResponse
    {
        $leaveRequest = LeaveRequest::findOrFail($id);

        // Terima file dari beberapa nama field atau base64
        $path = $this->storeApproverSignatureIfProvided($request, $leaveRequest->id, true);
        if (!$path) {
            return response()->json([
                'success' => false,
                'message' => 'File tanda tangan tidak ditemukan pada permintaan.'
            ], 422);
        }
        $leaveRequest->approver_signature_path = $path;
        $leaveRequest->save();

        return response()->json([
            'success' => true,
            'message' => 'Tanda tangan atasan berhasil diunggah',
            'data' => $leaveRequest,
        ]);
    }

    /**
     * Simpan tanda tangan atasan dari request (mendukung field: approver_signature|signature|file atau base64: approver_signature_base64)
     * Mengembalikan relative path di disk public atau null jika tidak ada.
     */
    private function storeApproverSignatureIfProvided(Request $request, int $leaveRequestId, bool $validate = false): ?string
    {
        $fileKeys = ['approver_signature', 'signature', 'file'];
        foreach ($fileKeys as $key) {
            if ($request->hasFile($key)) {
                if ($validate) {
                    $request->validate([
                        $key => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
                    ]);
                }
                return $request->file($key)->store("signatures/leave/{$leaveRequestId}", 'public');
            }
        }

        // Dukungan base64
        $base64 = $request->input('approver_signature_base64');
        if (is_string($base64) && str_contains($base64, 'base64,')) {
            [$meta, $data] = explode('base64,', $base64, 2);
            $binary = base64_decode($data, true);
            if ($binary !== false) {
                $ext = str_contains($meta, 'image/png') ? 'png' : (str_contains($meta, 'image/webp') ? 'webp' : 'jpg');
                $relativePath = "signatures/leave/{$leaveRequestId}/approver_signature_" . time() . ".{$ext}";
                Storage::disk('public')->put($relativePath, $binary);
                return $relativePath;
            }
        }

        return null;
    }
}