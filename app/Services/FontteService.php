<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FontteService
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $this->apiUrl = 'https://api.fonnte.com/send';
        $this->token = env('FONTTE_TOKEN');
    }

    public function sendOtp($phone, $otpCode)
    {
        try {
            // Format nomor telepon untuk Fontte (hapus karakter non-digit kecuali +)
            $formattedPhone = $this->formatPhoneNumber($phone);
            
            $message = "Kode OTP Anda: {$otpCode}. Berlaku selama 5 menit. Jangan bagikan kode ini kepada siapapun.";
            
            Log::info('Sending OTP via Fontte', [
                'original_phone' => $phone,
                'formatted_phone' => $formattedPhone,
                'token_exists' => !empty($this->token)
            ]);
            
            // Temporarily disable SSL verification for local development (REMOVE IN PRODUCTION)
            $response = Http::withOptions([
                'verify' => false, // Bypasses SSL certificate verification to resolve cURL error 60
            ])->withHeaders([
                'Authorization' => $this->token,
            ])->post($this->apiUrl, [
                'target' => $formattedPhone,
                'message' => $message,
            ]);

            Log::info('Fontte API Response', [
                'status' => $response->status(),
                'body' => $response->body(),
                'headers' => $response->headers()
            ]);

            if ($response->successful()) {
                Log::info('OTP sent successfully', ['phone' => $formattedPhone]);
                return true;
            }

            Log::error('Failed to send OTP', [
                'phone' => $formattedPhone,
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('Exception sending OTP', [
                'phone' => $phone,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    private function formatPhoneNumber($phone)
    {
        // Hapus semua karakter kecuali angka dan +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Jika dimulai dengan 08, ganti dengan +628
        if (substr($phone, 0, 2) === '08') {
            $phone = '+628' . substr($phone, 2);
        }
        // Jika dimulai dengan 8 (tanpa 0), tambahkan +62
        elseif (substr($phone, 0, 1) === '8' && substr($phone, 0, 2) !== '62') {
            $phone = '+62' . $phone;
        }
        // Jika dimulai dengan 62, tambahkan +
        elseif (substr($phone, 0, 2) === '62') {
            $phone = '+' . $phone;
        }
        
        return $phone;
    }
}