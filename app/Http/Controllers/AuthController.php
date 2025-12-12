<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Otp;
use App\Models\Employee;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    private $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    public function sendRegisterOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->otpService->generateOtp($request->phone, 'register');

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Kode OTP berhasil dikirim ke nomor handphone Anda',
                'otp_id' => $result['otp_id']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim kode OTP. Silakan coba lagi.'
        ], 500);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25',
            'otp_code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $otpResult = $this->otpService->verifyOtp($request->phone, $request->otp_code, 'register');

        if (!$otpResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $otpResult['message']
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode OTP berhasil diverifikasi',
            'data' => [
                'phone' => $request->phone,
                'verified' => true
            ]
        ]);
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25|unique:users,phone',
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:users,name', // Nama tidak boleh sama dengan user lain
                function ($attribute, $value, $fail) {
                    // PERUBAHAN: Cek apakah nama ADA di tabel employees dan belum terhubung ke user
                    $existingEmployee = \App\Models\Employee::where('nama_lengkap', $value)->first();
                    if (!$existingEmployee) {
                        $fail('Nama tersebut belum terdaftar sebagai karyawan. Silakan hubungi HR untuk mendaftarkan data karyawan terlebih dahulu.');
                        return;
                    }
                    
                    // Cek apakah employee sudah terhubung dengan user lain
                    $linkedUser = \App\Models\User::where('employee_id', $existingEmployee->id)->first();
                    if ($linkedUser) {
                        $fail('Karyawan dengan nama tersebut sudah memiliki akun user.');
                    }
                },
            ],
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $recentOtp = Otp::where('phone', $request->phone)
            ->where('type', 'register')
            ->where('is_used', true)
            ->where('created_at', '>=', Carbon::now()->subMinutes(10))
            ->first();

        if (!$recentOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan verifikasi OTP terlebih dahulu'
            ], 422);
        }

        // Cari employee berdasarkan nama
        $employee = \App\Models\Employee::where('nama_lengkap', $request->name)->first();
        
        // Buat user dan hubungkan dengan employee
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'phone_verified_at' => Carbon::now(),
            'employee_id' => $employee->id,
            'role' => $employee->jabatan_saat_ini, // Set role sesuai jabatan
        ]);
    
        // AUTO-CREATE JATAH CUTI DEFAULT
        $currentYear = date('Y');
        $existingQuota = \App\Models\LeaveQuota::where('employee_id', $employee->id)
                                          ->where('year', $currentYear)
                                          ->first();
        
        if (!$existingQuota) {
            \App\Models\LeaveQuota::create([
                'employee_id' => $employee->id,
                'year' => $currentYear,
                'annual_leave_quota' => 12, // Default 12 hari cuti tahunan
                'annual_leave_used' => 0,
                'sick_leave_quota' => 3, // Default 3 hari cuti sakit
                'sick_leave_used' => 0,
                'emergency_leave_quota' => 1, // Default 1 hari cuti darurat
                'emergency_leave_used' => 0,
                'maternity_leave_quota' => $employee->jenis_kelamin === 'Perempuan' ? 80 : 0, // 80 hari untuk perempuan
                'maternity_leave_used' => 0,
                'paternity_leave_quota' => $employee->jenis_kelamin === 'Laki-laki' ? 3 : 0, // 3 hari untuk laki-laki
                'paternity_leave_used' => 0,
                'marriage_leave_quota' => 3, // Default 3 hari cuti menikah
                'marriage_leave_used' => 0,
                'bereavement_leave_quota' => 3, // Default 3 hari cuti duka
                'bereavement_leave_used' => 0,
            ]);
        }
    
        // 🔥 AUTO-SYNC: Sinkronisasi otomatis untuk user yang baru register
        $syncResult = \App\Services\EmployeeSyncService::autoSyncUserRegistration($request->name);

        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil dan jatah cuti telah dibuat',
            'data' => [
                'user' => $user->load('employee'),
                'token' => $token,
                'token_type' => 'Bearer',
                'sync_result' => $syncResult
            ]
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        $user = User::where($loginField, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/Phone atau password salah'
            ], 401);
        }

        // Create access token dengan expiration (1 hour)
        $token = $user->createToken('auth_token', ['*'], now()->addHour())->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    // Jangan expose password, token, dll
                ],
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 3600 // 1 hour in seconds
            ]
        ]);
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25|exists:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->otpService->generateOtp($request->phone, 'forgot_password');

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Kode OTP untuk reset password berhasil dikirim',
                'otp_id' => $result['otp_id']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim kode OTP. Silakan coba lagi.'
        ], 500);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25|exists:users,phone',
            'otp_code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $otpResult = $this->otpService->verifyOtp($request->phone, $request->otp_code, 'forgot_password');
        
        if (!$otpResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $otpResult['message']
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil direset'
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    public function me(Request $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna tidak terautentikasi'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function uploadProfilePicture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }
    
        try {
            $user = Auth::user();
    
            // Debug: Log user details
            \Illuminate\Support\Facades\Log::info('User object', [
                'user' => $user,
                'class' => is_object($user) ? get_class($user) : 'null',
                'is_user_model' => $user instanceof \App\Models\User
            ]);
    
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak terautentikasi'
                ], 401);
            }
    
            if (!$user instanceof \App\Models\User) {
                return response()->json([
                    'success' => false,
                    'message' => 'User object is not an instance of User model'
                ], 500);
            }
    
            // Delete old photo if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }
    
            // Upload new photo
            $file = $request->file('profile_picture');
            $filename = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile_pictures', $filename, 'public');
    
            // Update user
            $user->update(['profile_picture' => $path]);
    
            return response()->json([
                'success' => true,
                'message' => 'Foto profil berhasil diunggah',
                'data' => [
                    'profile_picture_url' => $user->profile_picture_url
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Upload profile picture error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunggah foto profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Refresh Token - Generate new token untuk user yang sudah authenticated
     * POST /api/auth/refresh
     */
    public function refresh(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak terautentikasi'
                ], 401);
            }

            // Delete current token
            $request->user()->currentAccessToken()->delete();

            // Create new token dengan expiration (1 hour)
            $token = $user->createToken('auth_token', ['*'], now()->addHour())->plainTextToken;

            // Log audit
            \Illuminate\Support\Facades\Log::channel('audit')->info('Token refreshed', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token berhasil di-refresh',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 3600, // 1 hour in seconds
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        // Jangan expose password, token, dll
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Token refresh failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal refresh token'
                // Jangan expose error details ke frontend
            ], 500);
        }
    }

    public function deleteProfilePicture(Request $request)
    {
        try {
            $user = Auth::user();
    
            // Debug: Log user details
            \Illuminate\Support\Facades\Log::info('User object', [
                'user' => $user,
                'class' => is_object($user) ? get_class($user) : 'null',
                'is_user_model' => $user instanceof \App\Models\User
            ]);
    
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengguna tidak terautentikasi'
                ], 401);
            }
    
            if (!$user instanceof \App\Models\User) {
                return response()->json([
                    'success' => false,
                    'message' => 'User object is not an instance of User model'
                ], 500);
            }
    
            if (!$user->profile_picture) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada foto profil untuk dihapus'
                ], 404);
            }
    
            // Delete file from storage
            if (Storage::disk('public')->delete($user->profile_picture)) {
                // Update user
                $user->update(['profile_picture' => null]);
    
                return response()->json([
                    'success' => true,
                    'message' => 'Foto profil berhasil dihapus'
                ]);
            }
    
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto profil dari penyimpanan'
            ], 500);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Delete profile picture error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus foto profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|min:10|max:25',
            'type' => 'required|string|in:register,forgot_password'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $existingOtp = Otp::where('phone', $request->phone)
            ->where('type', $request->type)
            ->where('expires_at', '>', Carbon::now())
            ->where('is_used', false)
            ->first();

        if ($existingOtp) {
            $remainingTime = Carbon::now()->diffInSeconds($existingOtp->expires_at);
            return response()->json([
                'success' => false,
                'message' => "Masih ada kode OTP aktif. Silakan tunggu {$remainingTime} detik atau gunakan kode yang sudah dikirim.",
                'remaining_time' => $remainingTime
            ], 429);
        }

        if ($request->type === 'register') {
            $existingUser = User::where('phone', $request->phone)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor handphone sudah terdaftar'
                ], 422);
            }
        } elseif ($request->type === 'forgot_password') {
            $existingUser = User::where('phone', $request->phone)->first();
            if (!$existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor handphone tidak terdaftar'
                ], 422);
            }
        }

        $result = $this->otpService->generateOtp($request->phone, $request->type);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Kode OTP berhasil dikirim ulang ke nomor handphone Anda',
                'otp_id' => $result['otp_id']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Gagal mengirim ulang kode OTP. Silakan coba lagi.'
        ], 500);
    }
    
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('employee');
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'employee_data' => $user->employee
            ]
        ]);
    }

    /**
     * Check employee status for logged in user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkEmployeeStatus(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                    'code' => 'USER_NOT_FOUND'
                ], 401);
            }
            
            // Cek apakah user masih ada di tabel employee
            $employee = Employee::where('id', $user->employee_id)->first();
            
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maaf, Anda sudah tidak terdaftar sebagai karyawan Hope Channel Indonesia',
                    'code' => 'EMPLOYEE_NOT_FOUND'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'employee_id' => $employee->id,
                    'status' => 'active',
                    'name' => $employee->nama_lengkap,
                    'jabatan' => $employee->jabatan_saat_ini,
                    'nik' => $employee->nik,
                    'nip' => $employee->nip,
                    'tanggal_mulai_kerja' => $employee->tanggal_mulai_kerja,
                    'manager_id' => $employee->manager_id
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengecek status employee',
                'code' => 'API_ERROR',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}