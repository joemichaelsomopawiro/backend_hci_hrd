<?php

namespace App\Services;

use App\Models\Otp;
use Carbon\Carbon;
use Illuminate\Support\Str;

class OtpService
{
    private $fontteService;

    public function __construct(FontteService $fontteService)
    {
        $this->fontteService = $fontteService;
    }

    public function generateOtp($phone, $type)
    {
        // Hapus OTP lama yang belum digunakan
        Otp::where('phone', $phone)
            ->where('type', $type)
            ->where('is_used', false)
            ->delete();

        // Generate OTP 6 digit
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Simpan OTP ke database
        $otp = Otp::create([
            'phone' => $phone,
            'otp_code' => $otpCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(5),
        ]);

        // Kirim OTP via Fonnte
        $sent = $this->fontteService->sendOtp($phone, $otpCode);

        return [
            'success' => $sent,
            'otp_id' => $otp->id,
        ];
    }

    public function verifyOtp($phone, $otpCode, $type)
    {
        $otp = Otp::where('phone', $phone)
            ->where('otp_code', $otpCode)
            ->where('type', $type)
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return ['success' => false, 'message' => 'Kode OTP tidak valid'];
        }

        if ($otp->isExpired()) {
            return ['success' => false, 'message' => 'Kode OTP sudah kadaluarsa'];
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        return ['success' => true, 'message' => 'OTP berhasil diverifikasi'];
    }
}