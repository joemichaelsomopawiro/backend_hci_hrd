<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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

        // Verify OTP
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

        // Cek apakah nomor sudah diverifikasi OTP (opsional - bisa ditambahkan validasi tambahan)
        // Untuk saat ini, kita asumsikan frontend sudah memverifikasi OTP sebelumnya
        // Di dalam method register, sebelum create user
$recentOtp = \App\Models\Otp::where('phone', $request->phone)
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
        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'phone_verified_at' => Carbon::now(),
        ]);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer'
            ]
        ]);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string', // bisa email atau phone
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Cek apakah login menggunakan email atau phone
        $loginField = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        
        $user = User::where($loginField, $request->login)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email/Phone atau password salah'
            ], 401);
        }

        // Create token
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

        // Verify OTP
        $otpResult = $this->otpService->verifyOtp($request->phone, $request->otp_code, 'forgot_password');
        
        if (!$otpResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $otpResult['message']
            ], 422);
        }

        // Update password
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
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
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

        // Cek apakah ada OTP yang masih aktif (belum expired)
        $existingOtp = \App\Models\Otp::where('phone', $request->phone)
            ->where('type', $request->type)
            ->where('expires_at', '>', Carbon::now())
            ->where('is_used', false)
            ->first();

        if ($existingOtp) {
            // Jika masih ada OTP aktif, beri tahu user untuk menunggu
            $remainingTime = Carbon::now()->diffInSeconds($existingOtp->expires_at);
            return response()->json([
                'success' => false,
                'message' => "Masih ada kode OTP aktif. Silakan tunggu {$remainingTime} detik atau gunakan kode yang sudah dikirim.",
                'remaining_time' => $remainingTime
            ], 429); // Too Many Requests
        }

        // Validasi tambahan berdasarkan type
        if ($request->type === 'register') {
            // Untuk register, pastikan nomor belum terdaftar
            $existingUser = User::where('phone', $request->phone)->first();
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor handphone sudah terdaftar'
                ], 422);
            }
        } elseif ($request->type === 'forgot_password') {
            // Untuk forgot password, pastikan nomor sudah terdaftar
            $existingUser = User::where('phone', $request->phone)->first();
            if (!$existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor handphone tidak terdaftar'
                ], 422);
            }
        }

        // Generate OTP baru
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
}