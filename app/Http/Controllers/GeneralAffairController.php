<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MorningReflection;
use App\Models\Leave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

     class GeneralAffairController extends Controller
     {
         // Get all employees for dropdown (Bagian A)
         public function getEmployees()
         {
             $employees = Employee::select('id', 'full_name')->get();
             return response()->json(['data' => $employees], 200);
         }

         // Store morning reflection attendance manually (Bagian A)
    public function storeMorningReflection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'status' => 'required|in:Hadir,Absen,Terlambat',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            
            // Use firstOrCreate to handle race conditions atomically
            $morningReflection = MorningReflection::firstOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'date' => $request->date
                ],
                [
                    'status' => $request->status,
                    'join_time' => null
                ]
            );
            
            // Check if record was just created or already existed
            if (!$morningReflection->wasRecentlyCreated) {
                DB::rollBack();
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini pada tanggal ini sudah ada.']
                ], 422);
            }
            
            DB::commit();
            
            Log::info('Manual attendance recorded', [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'status' => $request->status
            ]);
            
            return response()->json([
                'data' => $morningReflection,
                'message' => 'Absensi berhasil disimpan'
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error storing manual attendance', [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'errors' => ['system' => 'Terjadi kesalahan sistem. Silakan coba lagi.']
            ], 500);
        }
    }

         // Record Zoom join for morning worship (Bagian A - Zoom Integration)
    public function recordZoomJoin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'zoom_link' => 'nullable|url', // Opsional, untuk mencatat link Zoom
            'skip_time_validation' => 'nullable|boolean', // Parameter untuk testing
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Use single timestamp for consistency
        $now = Carbon::now();
        $date = $now->toDateString();
        $dayOfWeek = $now->dayOfWeek; // 1 = Senin, 3 = Rabu, 5 = Jumat
        
        // Cek hari (Senin, Rabu, Jumat) - bisa dilewati untuk testing
        $skipTimeValidation = $request->input('skip_time_validation', false);
        
        if (!$skipTimeValidation && !in_array($dayOfWeek, [1, 3, 5])) {
            return response()->json([
                'errors' => ['day' => 'Worship pagi hanya diadakan pada Senin, Rabu, dan Jumat.']
            ], 422);
        }

        // Validasi waktu: hanya boleh join antara 07:10 - 07:35 (bisa dilewati untuk testing)
        // Tambahan: bypass otomatis jika environment adalah local/testing
        $isTestingEnvironment = config('app.env') === 'local' || config('app.env') === 'testing';
        
        if (!$skipTimeValidation && !$isTestingEnvironment) {
            $startTime = Carbon::today()->setTime(7, 10); // 07:10
            $endTime = Carbon::today()->setTime(7, 35);   // 07:35 - Closed
            
            if ($now->lt($startTime) || $now->gt($endTime)) {
                return response()->json([
                    'errors' => ['time' => 'Absensi Zoom hanya dapat dilakukan antara pukul 07:10 - 07:35.']
                ], 422);
            }
        }

        try {
            DB::beginTransaction();
            
            // Tentukan status berdasarkan waktu klik
            // 07:10-07:30 = Hadir, 07:31-07:35 = Terlambat, >07:35 = Closed
            $cutoffTime = Carbon::today()->setTime(7, 30); // 07:30
            $status = $now->lte($cutoffTime) ? 'Hadir' : 'Terlambat';
            
            // Use firstOrCreate to handle race conditions atomically
            $morningReflection = MorningReflection::firstOrCreate(
                [
                    'employee_id' => $request->employee_id,
                    'date' => $date
                ],
                [
                    'status' => $status,
                    'join_time' => $now
                ]
            );
            
            // Check if record was just created or already existed
            if (!$morningReflection->wasRecentlyCreated) {
                DB::rollBack();
                
                Log::warning('Duplicate Zoom attendance attempt', [
                    'employee_id' => $request->employee_id,
                    'date' => $date,
                    'existing_status' => $morningReflection->status,
                    'existing_join_time' => $morningReflection->join_time
                ]);
                
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini hari ini sudah ada.'],
                    'existing_data' => [
                        'status' => $morningReflection->status,
                        'join_time' => $morningReflection->join_time,
                        'date' => $morningReflection->date
                    ]
                ], 422);
            }
            
            DB::commit();
            
            Log::info('Zoom attendance recorded successfully', [
                'employee_id' => $request->employee_id,
                'date' => $date,
                'status' => $status,
                'join_time' => $now->toDateTimeString()
            ]);
            
            // Kembalikan data absensi dan link Zoom
            return response()->json([
                'data' => $morningReflection,
                'message' => 'Absensi Zoom berhasil dicatat',
                'zoom_link' => $request->zoom_link ?? 'https://zoom.us/j/meeting'
            ], 201);
            
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Error recording Zoom attendance', [
                'employee_id' => $request->employee_id,
                'date' => $date,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Check if it's a duplicate key error (unique constraint violation)
            if (str_contains($e->getMessage(), 'unique_employee_date_attendance') || 
                str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'errors' => ['date' => 'Absensi untuk pegawai ini hari ini sudah ada.']
                ], 422);
            }
            
            return response()->json([
                'errors' => ['system' => 'Terjadi kesalahan sistem. Silakan coba lagi.']
            ], 500);
        }
    }

         // Get all morning reflections for dashboard (Bagian C)
         public function getMorningReflections()
         {
             $reflections = MorningReflection::with('employee')->get();
             return response()->json(['data' => $reflections], 200);
         }

         // Get all leaves for dashboard (Bagian C)
         public function getLeaves()
         {
             $leaves = Leave::with('employee')->get();
             return response()->json(['data' => $leaves], 200);
         }
     }