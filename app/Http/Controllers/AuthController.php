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
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|unique:users,phone',
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
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/',
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
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|unique:users,phone',
            'name' => 'required|string|max:255',
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

        // AUTO-LINKING: Cari employee dengan nama yang sama
        $employee = Employee::where('nama_lengkap', $request->name)->first();
        $employee_id = $employee ? $employee->id : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'phone_verified_at' => Carbon::now(),
            'employee_id' => $employee_id, // Tambahkan ini!
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'linked_to_employee' => $employee_id ? true : false // Info tambahan
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

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function sendForgotPasswordOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|exists:users,phone',
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
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/|exists:users,phone',
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
            'phone' => 'required|string|regex:/^[0-9+\-\s]+$/',
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
}